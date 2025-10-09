LOAD DATA INFILE 'C:/Program Files/MariaDB 11.7/data/survey_results_clean.csv'
INTO TABLE stackoverflow_surveys
FIELDS TERMINATED BY ',' ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(
  main_branch,
  age,
  employment,
  remote_work,
  ed_level,
  years_code,
  years_code_pro,
  dev_type,
  country,
  currency,
  @comp_total,
  language_have_worked_with,
  language_want_work_with,
  language_admired,
  ai_sentiment,
  ai_benefit,
  job_satisfaction,
  industry,
  @year
)
SET
  comp_total = CASE
    WHEN @comp_total IN ('NA','N/A','NaN','','NULL','None','Missing') THEN NULL
    WHEN @comp_total NOT REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN NULL  -- ❌ ignora texto o notación científica
    WHEN CAST(@comp_total AS DECIMAL(18,2)) > 100000000 THEN NULL -- ❌ ignora outliers > 100M
    ELSE CAST(@comp_total AS DECIMAL(18,2))
  END,
  year = CASE
    WHEN @year IN ('NA','N/A','NaN','','NULL','None','Missing') THEN NULL
    ELSE @year
  END;
