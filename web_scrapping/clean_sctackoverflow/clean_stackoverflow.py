import pandas as pd

df = pd.read_csv("C:/Program Files/MariaDB 11.7/data/survey_results_public.csv", low_memory=False)
keep = [
  "MainBranch","Age","Employment","RemoteWork","EdLevel",
  "YearsCode","YearsCodePro","DevType","Country","Currency",
  "CompTotal","LanguageHaveWorkedWith","LanguageWantToWorkWith",
  "LanguageAdmired","AISent","AIBen","JobSat","Industry"
]
df = df[keep]
df["Year"] = 2024
df.to_csv("C:/Program Files/MariaDB 11.7/data/survey_results_clean.csv", index=False)
