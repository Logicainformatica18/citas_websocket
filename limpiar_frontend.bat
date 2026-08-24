@echo off
setlocal
set GITRM=git rm -r -f --ignore-unmatch --quiet

REM ===== RESTOS DEL PROYECTO DE CITAS (sin rutas) =====
%GITRM% resources/js/pages/appointment-types
%GITRM% resources/js/pages/areas
%GITRM% resources/js/pages/articles
%GITRM% resources/js/pages/bank_statements
%GITRM% resources/js/pages/bot
%GITRM% resources/js/pages/clients
%GITRM% resources/js/pages/external-states
%GITRM% resources/js/pages/internal-states
%GITRM% resources/js/pages/motives
%GITRM% resources/js/pages/payment
%GITRM% resources/js/pages/products
%GITRM% resources/js/pages/projects
%GITRM% resources/js/pages/transfers
%GITRM% resources/js/pages/types
%GITRM% resources/js/pages/waiting-days
%GITRM% resources/js/pages/Imports

REM ===== IA =====
%GITRM% resources/js/pages/AITraining
%GITRM% resources/js/pages/Chat_IA
%GITRM% resources/js/pages/topicsIA

REM ===== DASHBOARDS / INDICADORES =====
%GITRM% resources/js/pages/Dashboard
%GITRM% resources/js/pages/dashboards
%GITRM% resources/js/pages/DashboardCompanies
%GITRM% resources/js/pages/DashboardJobDemandGeo
%GITRM% resources/js/pages/DashboardSeniority
%GITRM% resources/js/pages/dashboardLovable

REM ===== SCRAPING =====
%GITRM% resources/js/pages/Scraping
%GITRM% resources/js/pages/Scrapings
%GITRM% resources/js/pages/ScrapingFields
%GITRM% resources/js/pages/ScrapingSources
%GITRM% resources/js/pages/Backups
%GITRM% resources/js/pages/sources

REM ===== DOMINIO OBSERVATORIO =====
%GITRM% resources/js/pages/careers
%GITRM% resources/js/pages/courses
%GITRM% resources/js/pages/competencies
%GITRM% resources/js/pages/technologies
%GITRM% resources/js/pages/languages
%GITRM% resources/js/pages/methodologies
%GITRM% resources/js/pages/market-entities
%GITRM% resources/js/pages/entity-trends
%GITRM% resources/js/pages/techpositions
%GITRM% resources/js/pages/job_offers
%GITRM% resources/js/pages/syllabus

echo.
echo Listo. Revisa con: git status
endlocal