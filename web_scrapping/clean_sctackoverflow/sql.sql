LOAD DATA INFILE 'C:/Program Files/MariaDB 12.0/data/survey_results_2023.csv'
INTO TABLE stackoverflow_surveys
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"' 
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(
  MainBranch,
  Age,
  Country,
  Employment,
  RemoteWork,
  EdLevel,
  YearsCode,
  YearsCodePro,
  DevType,
  Currency,
  @CompTotal,
  LanguageHaveWorkedWith,
  LanguageWantToWorkWith,
  LanguageAdmired,
  @AISent,
  @AIBen,
  JobSat,
  Industry
)
SET
  -- ✅ Mapeo de nombres CamelCase → snake_case
  main_branch = NULLIF(MainBranch, ''),
  age = NULLIF(Age, ''),
  country = NULLIF(Country, ''),
  employment = NULLIF(Employment, ''),
  remote_work = NULLIF(RemoteWork, ''),
  ed_level = NULLIF(EdLevel, ''),
  years_code = NULLIF(YearsCode, ''),
  years_code_pro = NULLIF(YearsCodePro, ''),
  dev_type = NULLIF(DevType, ''),
  currency = NULLIF(Currency, ''),
  language_have_worked_with = NULLIF(LanguageHaveWorkedWith, ''),
  language_want_work_with = NULLIF(LanguageWantToWorkWith, ''),
  language_admired = NULLIF(LanguageAdmired, ''),
  job_satisfaction = NULLIF(JobSat, ''),
  industry = NULLIF(Industry, ''),

  -- 💰 Limpieza numérica del salario
  comp_total = CASE
    WHEN @CompTotal IN ('NA','N/A','NaN','','NULL','None','Missing') THEN NULL
    WHEN @CompTotal NOT REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN NULL
    WHEN CAST(@CompTotal AS DECIMAL(18,2)) > 100000000 THEN NULL
    ELSE CAST(@CompTotal AS DECIMAL(18,2))
  END,

  -- 🤖 Opinión sobre IA
  ai_sentiment = NULLIF(@AISent, ''),
  ai_benefit = NULLIF(@AIBen, ''),

  -- 📅 Año fijo (porque el CSV 2023 no lo incluye explícitamente)
  year = 2023;
