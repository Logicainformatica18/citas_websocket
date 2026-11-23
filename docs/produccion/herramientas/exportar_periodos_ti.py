"""
Genera el mismo reporte de ofertas por periodo, pero sin los roles que no son TI:

    ofertas_<año>10_TI.xlsx   del 1 de enero al 30 de junio
    ofertas_<año>20_TI.xlsx   del 1 de julio al 31 de diciembre

Se quitan los roles que no son de TI según su TÍTULO (columna title), aunque
la oferta pida tecnologías: un "Gerente comercial" que pide Python no cuenta. Las ofertas completas de cada
periodo las sigue generando exportar_periodos.py; este script es aparte.

Uso:
    python exportar_periodos_ti.py          # usa el año 2026
    python exportar_periodos_ti.py 2025     # cualquier otro año

Requisitos:
    pip install pandas sqlalchemy pymysql openpyxl python-dotenv

La conexión se lee del archivo .env que está en la misma carpeta que este script.
"""
import os
import re
import sys
import unicodedata
from pathlib import Path

import pandas as pd
from dotenv import load_dotenv
from openpyxl import load_workbook
from openpyxl.cell.cell import ILLEGAL_CHARACTERS_RE
from openpyxl.styles import Font
from sqlalchemy import create_engine, text
from sqlalchemy.engine import URL

# =====================================================================
# CRITERIO DE PERFILES TI  (edita estas listas para ajustarlo)
# =====================================================================
# Los títulos se comparan en minúsculas y sin tildes, así que escribe los
# patrones igual: "informatica", no "Informática". Cada elemento es una
# expresión regular; \b marca el borde de una palabra.

# ¿Cómo se decide qué se quita?
#   SOLO_EXCLUIR = True  -> se quitan SOLO los títulos que coinciden con
#                           PATRONES_EXCLUIR. Todo lo demás se queda.
#   SOLO_EXCLUIR = False -> además se quitan los títulos que no tienen ninguna
#                           palabra de PATRONES_TI (criterio más estricto).
SOLO_EXCLUIR = True

# 1) Roles que NO son TI. Si el título contiene alguno de estos, la oferta se
#    quita aunque pida tecnologías (ej.: "Gerente comercial", "Sales Engineer").
PATRONES_EXCLUIR = [
    # ventas y comercial
    r"comercial", r"commercial", r"\bsales?\b", r"pre-?sales", r"\bventas?\b",
    r"vendedor", r"preventa", r"account executive", r"account manager",
    r"ejecutivo de cuentas?", r"key account", r"business development",
    r"desarrollo de negocios?",
    # marketing
    r"marketing", r"mercadeo", r"community manager",
    # recursos humanos
    r"recruit", r"reclutador", r"talent acquisition", r"recursos humanos",
    r"human resources",
    # contabilidad y atención al cliente
    r"contador", r"contab", r"accountant", r"accounting", r"customer service",
    r"atencion al cliente", r"call center",
    # digitación (contiene "data"/"datos" pero no es TI)
    r"data entry", r"captura de datos", r"digitador",
    # finanzas, legal y administración
    r"\bfinanzas\b", r"tesorer", r"auditor contable", r"abogad", r"lawyer",
    r"attorney", r"paralegal", r"legal counsel", r"asistente administrativ",
    r"administrative assistant", r"receptionist", r"recepcionista",
    r"secretari",
    # salud
    r"nurse", r"enfermer", r"clinical", r"clinico", r"medic(o|a)\b",
    r"physician", r"pharmac", r"farmac", r"dentist", r"odontolog",
    r"therapist", r"terapeuta", r"caregiver", r"cuidador",
    # educación
    r"teacher", r"docente", r"profesor", r"tutor\b", r"infant", r"toddler",
    r"preschool", r"maestr(o|a)\b",
    # transporte, logística y operarios
    r"(truck|delivery|bus|van|cdl) driver", r"chofer", r"\bconductor\b",
    r"warehouse", r"almacen", r"bodeg", r"forklift", r"montacarg",
    r"operario", r"machine operator", r"technician mechanic", r"soldador",
    r"welder", r"plumber", r"fontaner", r"carpinter",
    # comercio, hostelería y servicios
    r"cashier", r"cajer", r"retail", r"store manager", r"\bchef\b",
    r"\bcook\b", r"cociner", r"mesero", r"waiter", r"barista", r"housekeep",
    r"limpieza", r"cleaner", r"security guard", r"guardia", r"vigilante",
    # otras ingenierías
    r"mechanical", r"mecanic", r"\bcivil\b", r"structural", r"estructural",
    r"electrical engineer", r"ingeniero electric", r"electricista",
    r"construction", r"construccion", r"powertrain", r"automotive engineer",
    r"chemical engineer", r"ingeniero quimic", r"process engineer",
    r"ingeniero de procesos", r"manufactur", r"produccion",
]

# 2) Palabras de TI. Solo se usan si SOLO_EXCLUIR = False: entonces una oferta
#    que no se excluyó se queda únicamente si su título tiene alguna de estas.
PATRONES_TI = [
    # desarrollo
    r"developer", r"\bdev\b", r"desarrollador", r"desarrollo (de )?software",
    r"desarrollo web", r"programad", r"programmer", r"software",
    r"desenvolvedor", r"developpeur", r"entwickler", r"sviluppat",
    r"full ?stack", r"front ?end", r"back ?end", r"\bweb\b",
    r"\bjava\b", r"python", r"javascript", r"typescript", r"\breact",
    r"angular", r"\bnode", r"\.net\b", r"\bphp\b", r"\bruby\b", r"golang",
    r"c\+\+", r"c#", r"kotlin", r"\bswift\b", r"\bios\b", r"android",
    r"\bmobile\b", r"embedded", r"firmware", r"linux", r"unix", r"kernel",
    # datos e inteligencia artificial
    r"\bdata\b", r"\bdatos\b", r"\bdados\b", r"donnees", r"big data",
    r"machine learning", r"\bml\b", r"\bai\b", r"\bia\b",
    r"inteligencia artificial", r"artificial intelligence", r"deep learning",
    r"\bnlp\b", r"computer vision", r"\bllm", r"\bbi\b",
    r"business intelligence", r"power ?bi", r"analytics", r"\betl\b",
    r"\bdba\b", r"database", r"bases? de datos", r"\bsql\b", r"\bspark\b",
    r"databricks", r"snowflake", r"hadoop",
    # infraestructura, nube y redes
    r"devops", r"devsecops", r"\bsre\b", r"site reliability", r"\bcloud\b",
    r"\baws\b", r"azure", r"\bgcp\b", r"sysadmin", r"systems? admin",
    r"administrador de (sistemas|redes)", r"\bsistemas\b", r"network",
    r"\bredes\b",
    # seguridad
    r"ciberseguridad", r"cyber", r"seguridad (informatica|de la informacion)",
    r"infosec", r"pentest", r"\bsoc\b",
    # calidad y soporte
    r"\bqa\b", r"tester", r"software test", r"test automation",
    r"automatizacion de pruebas", r"help ?desk", r"service desk",
    r"soporte (tecnico|ti|it)", r"it support", r"desktop support",
    # diseño, gestión y arquitectura
    r"\bux\b", r"\bui\b", r"product owner", r"scrum", r"agile coach",
    r"(software|solutions?|cloud|enterprise|data|it) architect",
    r"arquitecto de (software|soluciones|datos|nube)", r"tech lead",
    r"\bcto\b", r"\bcio\b",
    # informática en general
    r"informati", r"computer", r"computacion", r"\bsap\b", r"salesforce",
    r"information technology", r"tecnologias? de (la )?informacion",
    r"(gerente|jefe|director|head) (de |of )?tecnologia",
    r"head of technology",
]

# 3) Siglas que solo cuentan escritas en MAYÚSCULAS (para no confundir el
#    inglés "it" con "IT"). Se buscan en el título original.
PATRON_SIGLAS_TI = r"\b(IT|TI|TIC|TICs)\b"

RE_EXCLUIR = re.compile("|".join(PATRONES_EXCLUIR))
RE_TI = re.compile("|".join(PATRONES_TI))
RE_SIGLAS = re.compile(PATRON_SIGLAS_TI)

# =====================================================================
# CONEXIÓN
# =====================================================================
ENV_PATH = Path(__file__).resolve().with_name(".env")
if not ENV_PATH.is_file():
    sys.exit(f"No se encontró el archivo .env en: {ENV_PATH}\n"
             "Copia tu .env a esa carpeta (revisa que no se llame .env.txt).")
load_dotenv(ENV_PATH)
if not os.getenv("DB_DATABASE"):
    sys.exit(f"El archivo {ENV_PATH} no tiene la variable DB_DATABASE.")

# URL.create escapa la contraseña, así que funciona aunque tenga @, : o /
DB_URL = URL.create(
    "mysql+pymysql",
    username=os.getenv("DB_USERNAME", "root"),
    password=os.getenv("DB_PASSWORD", ""),
    host=os.getenv("DB_HOST", "localhost"),
    port=int(os.getenv("DB_PORT", "3306")),
    database=os.getenv("DB_DATABASE"),
    query={"charset": "utf8mb4"},
)
ANIO_POR_DEFECTO = 2026

LIMITE_CELDA = 32_767     # máximo de caracteres que admite una celda de Excel
LIMITE_FILAS = 1_048_575  # máximo de filas de datos (la fila 1 es el encabezado)

COLUMNAS = """
    id, title, company, country, region, region_normalized, state_code,
    city, city_ascii, location, zip_code, modality, job_type, remote_type,
    workload, experience_level, seniority, education_level, education_field,
    certifications, requirements, skills, description, benefits,
    latitude, longitude, salary_min, salary_max, currency, compensation_type,
    source, search_query, external_id, url, application_url, application_type,
    published_at, expiry, created_at, updated_at
"""

# Rango semiabierto: >= inicio y < fin. Así entran también las ofertas
# publicadas el 30 de junio o el 31 de diciembre a cualquier hora.
SQL_PERIODO = text(f"""
    SELECT {COLUMNAS}
    FROM job_offers
    WHERE published_at >= :inicio
      AND published_at <  :fin
    ORDER BY published_at DESC
""")


# =====================================================================
# FUNCIONES
# =====================================================================
def periodos(anio: int) -> dict:
    """Devuelve {codigo: (inicio, fin)} para los dos semestres del año."""
    return {
        f"{anio}10": (f"{anio}-01-01", f"{anio}-07-01"),
        f"{anio}20": (f"{anio}-07-01", f"{anio + 1}-01-01"),
    }


def normalizar(texto: str) -> str:
    """Minúsculas y sin tildes: 'Analista Informático' -> 'analista informatico'."""
    sin_tildes = unicodedata.normalize("NFKD", texto)
    return "".join(c for c in sin_tildes if not unicodedata.combining(c)).lower()


def clasificar(titulo) -> str:
    """Devuelve 'excluido', 'ti' o 'sin_palabra_ti' según el título del puesto."""
    if not isinstance(titulo, str) or not titulo.strip():
        return "sin_palabra_ti"
    t = normalizar(titulo)
    if RE_EXCLUIR.search(t):
        return "excluido"
    if RE_TI.search(t) or RE_SIGLAS.search(titulo):
        return "ti"
    return "sin_palabra_ti"


def limpiar_texto(valor):
    """Quita caracteres de control que Excel no acepta y recorta al límite de celda."""
    if isinstance(valor, str):
        return ILLEGAL_CHARACTERS_RE.sub("", valor)[:LIMITE_CELDA]
    return valor


def exportar(engine, codigo: str, inicio: str, fin: str) -> None:
    print(f"[{codigo}] Consultando ofertas del {inicio} al {fin} (sin incluir)...")
    df = pd.read_sql(SQL_PERIODO, engine, params={"inicio": inicio, "fin": fin})
    if df.empty:
        print(f"[{codigo}] No hay ofertas en este periodo; no se crea archivo.\n")
        return

    total = len(df)
    clase = df["title"].map(clasificar)
    n_excluidos = int((clase == "excluido").sum())
    n_sin_palabra = int((clase == "sin_palabra_ti").sum())
    if SOLO_EXCLUIR:
        df = df[clase != "excluido"].copy()       # se quitan solo los roles no TI
    else:
        df = df[clase == "ti"].copy()             # se quedan solo los títulos TI
    if df.empty:
        print(f"[{codigo}] Ninguna oferta del periodo es de TI; no se crea archivo.\n")
        return

    recortadas = 0
    for col in df.select_dtypes(include="object").columns:
        largos = df[col].map(lambda v: len(v) if isinstance(v, str) else 0)
        recortadas += int((largos > LIMITE_CELDA).sum())
        df[col] = df[col].map(limpiar_texto)

    archivo = f"ofertas_{codigo}_TI.xlsx"
    with pd.ExcelWriter(archivo, engine="openpyxl") as writer:
        df.to_excel(writer, index=False, sheet_name=codigo)
        hoja = writer.sheets[codigo]
        hoja.freeze_panes = "A2"                 # encabezado siempre visible
        hoja.auto_filter.ref = hoja.dimensions   # filtros en cada columna
        for celda in hoja[1]:
            celda.font = Font(name="Arial", bold=True)

    wb = load_workbook(archivo, read_only=True)
    filas_excel = wb[codigo].max_row - 1
    wb.close()

    n_ti = len(df)
    print(f"[{codigo}] Ofertas del periodo:              {total:,}")
    print(f"[{codigo}] Quitadas por ser roles no TI:     {n_excluidos:,}")
    if SOLO_EXCLUIR:
        print(f"[{codigo}] Sin palabra de TI (se quedaron): {n_sin_palabra:,}")
    else:
        print(f"[{codigo}] Quitadas por no tener palabra TI: {n_sin_palabra:,}")
    print(f"[{codigo}] Quedan en el reporte:             {n_ti:,} "
          f"({n_ti / total * 100:.1f}%)")
    print(f"[{codigo}] Filas en {archivo}: {filas_excel:,}  "
          f"({'coinciden' if filas_excel == n_ti else 'NO COINCIDEN'})")
    if recortadas:
        print(f"[{codigo}] Aviso: {recortadas} celdas superaban {LIMITE_CELDA:,} "
              "caracteres y se recortaron.")
    print()


def main() -> None:
    anio = int(sys.argv[1]) if len(sys.argv) > 1 else ANIO_POR_DEFECTO
    engine = create_engine(DB_URL)
    for codigo, (inicio, fin) in periodos(anio).items():
        exportar(engine, codigo, inicio, fin)


if __name__ == "__main__":
    main()