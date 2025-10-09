import pandas as pd
import numpy as np
import re

# === 1️⃣ Rutas ===
INPUT_PATH = r"C:\Program Files\MariaDB 12.0\data\survey_results_public.csv"
OUTPUT_PATH = r"C:\Program Files\MariaDB 12.0\data\stackoverflow_2025_clean.csv"

print("📂 Cargando CSV original...")
df = pd.read_csv(INPUT_PATH, low_memory=False)

# === 2️⃣ Seleccionar solo las columnas relevantes ===
columns_map = {
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
    "LanguageAdmired": "language_admired",
    "DatabaseHaveWorkedWith": "database_have_worked_with",
    "WebframeHaveWorkedWith": "webframe_have_worked_with",
    "PlatformHaveWorkedWith": "platform_have_worked_with",
    "OrgSize": "org_size",
    "Industry": "industry",
    "JobSat": "job_satisfaction",
    "AISelect": "ai_select",
    "AISent": "ai_sentiment",
    "AIAcc": "ai_acceptance",
    "AIComplex": "ai_complexity",
    "AIFrustration": "ai_frustration",
    "AIExplain": "ai_explain",
    "AIAgents": "ai_agents",
    "AIAgentImpactSomewhat agree": "ai_agent_impact",
    "AIAgentChallengesStrongly agree": "ai_agent_challenges",
}

existing_cols = [c for c in columns_map.keys() if c in df.columns]
df = df[existing_cols].rename(columns=columns_map)

# === 3️⃣ Limpieza general ===
def clean_text(x):
    if isinstance(x, str):
        x = re.sub(r"\s+", " ", x.strip())
        if x in ["NA", "N/A", "None", "NaN", "nan", ""]:
            return None
    return x

df = df.applymap(clean_text)

# === 4️⃣ Limpieza y normalización de Remote Work ===
def normalize_remote_work(value):
    if pd.isna(value):
        return None
    v = value.strip()
    if v.startswith("Hybrid") or "choice" in v.lower():
        return "Hybrid"
    elif v.startswith("Remote"):
        return "Remote"
    elif v.startswith("In-person"):
        return "In-person"
    else:
        return None

df["remote_work"] = df["remote_work"].apply(normalize_remote_work)

# === 5️⃣ Limpiar 'comp_total' ===
def clean_salary(val):
    if pd.isna(val):
        return None
    try:
        val = str(val).replace(",", "").replace("$", "").strip()
        if re.match(r"^\d+(\.\d+)?$", val):
            return float(val)
        return None
    except:
        return None

df["comp_total"] = df["comp_total"].apply(clean_salary)

# === 6️⃣ Agregar columnas faltantes ===
optional_columns = [
    "iso2",
    "language_admired",
    "ai_acceptance",
    "ai_complexity",
    "ai_frustration",
    "ai_explain",
    "ai_agents",
    "ai_agent_impact",
    "ai_agent_challenges",
]
for col in optional_columns:
    if col not in df.columns:
        df[col] = None

df["year"] = 2025

# === 7️⃣ Reordenar columnas ===
columns_order = [
    "main_branch", "age", "country", "iso2", "employment", "remote_work",
    "ed_level", "learn_code", "years_code", "years_code_pro", "dev_type",
    "currency", "comp_total", "language_have_worked_with", "language_want_work_with",
    "language_admired", "database_have_worked_with", "webframe_have_worked_with",
    "platform_have_worked_with", "ai_select", "ai_sentiment", "ai_acceptance",
    "ai_complexity", "ai_frustration", "ai_explain", "ai_agents", "ai_agent_impact",
    "ai_agent_challenges", "org_size", "industry", "job_satisfaction", "year"
]
for col in columns_order:
    if col not in df.columns:
        df[col] = None
df = df[columns_order]

# === 8️⃣ Guardar CSV limpio ===
df.to_csv(OUTPUT_PATH, index=False, encoding="utf-8")

print(f"✅ Archivo limpio generado: {OUTPUT_PATH}")
print(f"💾 Total de filas procesadas: {len(df):,}")
print("\n📊 Distribución de Remote Work normalizado:")
print(df["remote_work"].value_counts(dropna=False))
