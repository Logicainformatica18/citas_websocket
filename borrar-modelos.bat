@echo off
REM ============================================================================
REM  borrar-modelos.bat
REM
REM  Borra los 82 modelos que no se usan de app\Models.
REM  CONSERVA UNICAMENTE: User.php
REM
REM  USO (desde la raiz del proyecto):
REM      borrar-modelos.bat
REM
REM  PARA DESHACER TODO:
REM      git checkout -- app/Models
REM ============================================================================

echo.
echo Borrando 82 modelos de app\Models...
echo Se conserva unicamente User.php
echo.

REM ---- Proyecto Citas ----
git rm -f "app/Models/AppointmentType.php"
git rm -f "app/Models/Area.php"
git rm -f "app/Models/Bot.php"
git rm -f "app/Models/ChatMessage.php"
git rm -f "app/Models/ExternalState.php"
git rm -f "app/Models/InternalState.php"
git rm -f "app/Models/Motive.php"
git rm -f "app/Models/MotivoCitaArea.php"
git rm -f "app/Models/Support.php"
git rm -f "app/Models/SupportDetail.php"
git rm -f "app/Models/WaitingDay.php"

REM ---- Comercial / transacciones ----
git rm -f "app/Models/BankStatement.php"
git rm -f "app/Models/Payment.php"
git rm -f "app/Models/Product.php"
git rm -f "app/Models/Project.php"
git rm -f "app/Models/Sale.php"
git rm -f "app/Models/Transaction.php"
git rm -f "app/Models/TransactionBlock.php"
git rm -f "app/Models/TransactionLine.php"
git rm -f "app/Models/Transfer.php"

REM ---- Observatorio · IA y entrenamiento ----
git rm -f "app/Models/AITraining.php"
git rm -f "app/Models/SQLTraining.php"
git rm -f "app/Models/ChatHistory.php"
git rm -f "app/Models/SemanticContext.php"

REM ---- Observatorio · carreras y competencias ----
git rm -f "app/Models/Career.php"
git rm -f "app/Models/CareerCourse.php"
git rm -f "app/Models/Course.php"
git rm -f "app/Models/Competency.php"
git rm -f "app/Models/CompetencyMetric.php"
git rm -f "app/Models/Syllabus.php"

REM ---- Observatorio · certificaciones ----
git rm -f "app/Models/Certification.php"
git rm -f "app/Models/CertificationMetric.php"

REM ---- Observatorio · mercado laboral ----
git rm -f "app/Models/JobOffer.php"
git rm -f "app/Models/MarketEntity.php"
git rm -f "app/Models/MarketEntityMetric.php"
git rm -f "app/Models/TechPosition.php"
git rm -f "app/Models/Language.php"
git rm -f "app/Models/LanguageMetric.php"
git rm -f "app/Models/Methodology.php"
git rm -f "app/Models/MethodologyMetric.php"

REM ---- Observatorio · tecnologias ----
git rm -f "app/Models/Technology.php"
git rm -f "app/Models/TechnologyCategory.php"
git rm -f "app/Models/TechnologyMetric.php"
git rm -f "app/Models/TechnologyTrend.php"
git rm -f "app/Models/TechnologyTrendEnriched.php"
git rm -f "app/Models/TechnologyTrendJob.php"
git rm -f "app/Models/TechnologyTrendTechnology.php"

REM ---- Observatorio · tendencias ----
git rm -f "app/Models/EntityTrend.php"
git rm -f "app/Models/GlobalInsight.php"
git rm -f "app/Models/GlobalTrend.php"
git rm -f "app/Models/TrendMarketSignal.php"
git rm -f "app/Models/TrendTechnology.php"
git rm -f "app/Models/TrendTopic.php"

REM ---- Observatorio · scraping ----
git rm -f "app/Models/ScraperRun.php"
git rm -f "app/Models/Scraping.php"
git rm -f "app/Models/ScrapingField.php"
git rm -f "app/Models/ScrapingSource.php"
git rm -f "app/Models/ScrapingWebResult.php"
git rm -f "app/Models/SourceStatus.php"

REM ---- Observatorio · PDF y OCR ----
git rm -f "app/Models/PdfChunk.php"
git rm -f "app/Models/PdfDocumentPart.php"
git rm -f "app/Models/PdfPage.php"
git rm -f "app/Models/PdfPageGraph.php"
git rm -f "app/Models/PdfPageTable.php"
git rm -f "app/Models/PdfPartSummary.php"
git rm -f "app/Models/ImageAnalysis.php"

REM ---- Observatorio · dashboards y reportes ----
git rm -f "app/Models/Dashboard.php"
git rm -f "app/Models/DashboardSection.php"
git rm -f "app/Models/DashboardWidget.php"
git rm -f "app/Models/RankingWeigh.php"
git rm -f "app/Models/ReportQuery.php"

REM ---- Observatorio · importacion y datos externos ----
git rm -f "app/Models/ImportJob.php"
git rm -f "app/Models/ImportMapping.php"
git rm -f "app/Models/StackOverFlowSurvey.php"
git rm -f "app/Models/WorldbankIndicator.php"
git rm -f "app/Models/Backup.php"

REM ---- Varios ----
git rm -f "app/Models/Article.php"
git rm -f "app/Models/City.php"
git rm -f "app/Models/Comment.php"
git rm -f "app/Models/Prueba.php"

REM ---- COLISIONES CON ENCUESTAS ----
REM  Estos dos nombres existen en tu proyecto Y en Encuestas, con contenido
REM  distinto. Se borran ahora y se reemplazan por los del port.
git rm -f "app/Models/Client.php"
git rm -f "app/Models/Type.php"

echo.
echo ============================================================
echo  Listo.
echo.
echo  Verifica que quede solo User.php:
echo      dir /b app\Models
echo.
echo  Regenera el autoload:
echo      composer dump-autoload
echo.
echo  Para deshacer todo:
echo      git checkout -- app/Models
echo ============================================================
echo.
