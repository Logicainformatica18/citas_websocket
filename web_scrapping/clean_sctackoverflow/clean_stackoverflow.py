import pandas as pd
import numpy as np
import os

# === 1️⃣ Ruta del CSV original ===
SOURCE = r"C:\Program Files\MariaDB 12.0\data\survey_results_public.csv"
OUTPUT = r"C:\Program Files\MariaDB 12.0\data\stackoverflow_2023_clean.csv"

print(f"📂 Leyendo archivo: {SOURCE}")

if not os.path.exists(SOURCE):
    raise FileNotFoundError(f"❌ No se encontró el archivo en {SOURCE}")

df = pd.read_csv(SOURCE, low_memory=False)
print(f"✅ Archivo cargado con {len(df):,} filas y {len(df.columns)} columnas")

# === 2️⃣ Seleccionar columnas relevantes ===
rename_map = {
    "MainBranch": "main_branch",
    "Age": "age",
    "Country": "country",
    "Employment": "employment",
    "RemoteWork": "remote_work",
    "EdLevel": "ed_level",
    "LearnCode": "learn_code",
    "YearsCode": "years_code",
    "YearsCodePro": "years_code_pro",
    "DevType": "dev_type",
    "Currency": "currency",
    "CompTotal": "comp_total",
    "LanguageHaveWorkedWith": "language_have_worked_with",
    "LanguageWantToWorkWith": "language_want_work_with",
    "DatabaseHaveWorkedWith": "database_have_worked_with",
    "WebframeHaveWorkedWith": "webframe_have_worked_with",
    "PlatformHaveWorkedWith": "platform_have_worked_with",
    "AISelect": "ai_select",
    "AISent": "ai_sentiment",
    "AIBen": "ai_benefit",
    "OrgSize": "org_size",
    "Industry": "industry",
}

cols = [c for c in rename_map.keys() if c in df.columns]
missing = [c for c in rename_map.keys() if c not in df.columns]
if missing:
    print(f"⚠️ Columnas ausentes en el CSV original: {missing}")

df = df[cols].rename(columns=rename_map)

# === 3️⃣ Limpieza general ===
df = df.applymap(lambda x: x.strip() if isinstance(x, str) else x)
df = df.replace({np.nan: None, "NA": None, "N/A": None, "": None})

# === 4️⃣ Limpiar columna comp_total ===
def clean_salary(val):
    if val is None or (isinstance(val, float) and np.isnan(val)):
        return None
    if isinstance(val, (int, float)):
        return val if 0 <= val <= 1e15 else None
    if isinstance(val, str):
        val = val.replace(",", "").replace("$", "").replace("€", "").strip()
        try:
            num = float(val)
            if 0 <= num <= 1e15:
                return num
        except ValueError:
            pass
    return None

df["comp_total"] = df["comp_total"].apply(clean_salary)

# === 5️⃣ Campos extra ===
df["iso2"] = None
df["language_admired"] = None
df["job_satisfaction"] = None
df["year"] = 2023

# === 6️⃣ Orden columnas ===
columns_order = [
    "main_branch", "age", "country", "iso2", "employment", "remote_work",
    "ed_level", "learn_code", "years_code", "years_code_pro", "dev_type",
    "currency", "comp_total", "language_have_worked_with", "language_want_work_with",
    "language_admired", "database_have_worked_with", "webframe_have_worked_with",
    "platform_have_worked_with", "ai_select", "ai_sentiment", "ai_benefit",
    "org_size", "industry", "job_satisfaction", "year"
]
df = df.reindex(columns=columns_order)

# === 7️⃣ Limitar tamaño texto ===
for col in df.select_dtypes(include=["object"]).columns:
    df[col] = df[col].apply(lambda x: x[:60000] if isinstance(x, str) else x)

# === 8️⃣ Exportar CSV limpio ===
df.to_csv(OUTPUT, index=False, encoding="utf-8", na_rep="")
print(f"✅ Archivo limpio generado: {OUTPUT}")
print(f"👉 Filas totales: {len(df):,}")
