@echo off
setlocal
set GITRM=git rm -r -f --ignore-unmatch --quiet

REM ===== DIRECTORIOS COMPLETOS =====
%GITRM% app/Http/Controllers/AI
%GITRM% app/Http/Controllers/Api
%GITRM% app/Http/Controllers/Dashboard
%GITRM% app/Http/Controllers/Ocr
%GITRM% app/Http/Controllers/Trends

REM ===== RESTOS PROYECTO CITAS =====
%GITRM% app/Http/Controllers/AppointmentTypeController.php
%GITRM% app/Http/Controllers/AreaController.php
%GITRM% app/Http/Controllers/ArticleController.php
%GITRM% app/Http/Controllers/BankStatementController.php
%GITRM% app/Http/Controllers/BotController.php
%GITRM% app/Http/Controllers/ChatMessageController.php
%GITRM% app/Http/Controllers/ClientController.php
%GITRM% app/Http/Controllers/CommentController.php
%GITRM% app/Http/Controllers/ExternalStateController.php
%GITRM% app/Http/Controllers/InternalStateController.php
%GITRM% app/Http/Controllers/MotiveController.php
%GITRM% app/Http/Controllers/PaymentsController.php
%GITRM% app/Http/Controllers/PaymentsTableController.php
%GITRM% app/Http/Controllers/ProductController.php
%GITRM% app/Http/Controllers/ProjectController.php
%GITRM% app/Http/Controllers/ReportController.php
%GITRM% app/Http/Controllers/SaleController.php
%GITRM% app/Http/Controllers/SupportController.php
%GITRM% app/Http/Controllers/SupportDetailController.php
%GITRM% app/Http/Controllers/TransactionController.php
%GITRM% app/Http/Controllers/TransferController.php
%GITRM% app/Http/Controllers/TypeController.php
%GITRM% app/Http/Controllers/WaitingDayController.php
%GITRM% app/Http/Controllers/WebSocketTestController.php
%GITRM% app/Http/Controllers/ImageAnalysisController.php
%GITRM% app/Http/Controllers/ImportJobController.php

REM ===== OBSERVATORIO =====
%GITRM% app/Http/Controllers/AIController.php
%GITRM% app/Http/Controllers/BackupController.php
%GITRM% app/Http/Controllers/CareerController.php
%GITRM% app/Http/Controllers/CareerCourseController.php
%GITRM% app/Http/Controllers/CompetencyController.php
%GITRM% app/Http/Controllers/CourseController.php
%GITRM% app/Http/Controllers/DashboardController.php
%GITRM% app/Http/Controllers/EntityTrendController.php
%GITRM% app/Http/Controllers/ImportController.php
%GITRM% app/Http/Controllers/JobOfferController.php
%GITRM% app/Http/Controllers/JobOfferImportController.php
%GITRM% app/Http/Controllers/JobStatsController.php
%GITRM% app/Http/Controllers/LanguageController.php
%GITRM% app/Http/Controllers/MarketEntityController.php
%GITRM% app/Http/Controllers/MethodologyController.php
%GITRM% app/Http/Controllers/PdfDocumentPartController.php
%GITRM% app/Http/Controllers/PdfOcrController.php
%GITRM% app/Http/Controllers/ScrapingController.php
%GITRM% app/Http/Controllers/ScrapingFieldController.php
%GITRM% app/Http/Controllers/ScrapingSourceController.php
%GITRM% app/Http/Controllers/ScrapingWebResultController.php
%GITRM% app/Http/Controllers/SegmentAnalyzerController.php
%GITRM% app/Http/Controllers/SourceStatusController.php
%GITRM% app/Http/Controllers/SQLDashboardController.php
%GITRM% app/Http/Controllers/SyllabusController.php
%GITRM% app/Http/Controllers/TechnologyController.php
%GITRM% app/Http/Controllers/TechPositionController.php
%GITRM% app/Http/Controllers/TopicsIAController.php

echo.
echo Listo. Verifica: dir /b app\Http\Controllers
endlocal