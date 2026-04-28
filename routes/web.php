<?php

use App\Http\Controllers\Api\CompanyReportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\WebHookController;
use App\Http\Controllers\InsuranceReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// PDF Report Routes
Route::get('/api/reports/clinic-shift-summary/pdf', [ReportController::class, 'allclinicsReportNew']);
Route::get('/reports/clinic-shift-summary/pdf', [ReportController::class, 'allclinicsReportNew']);
Route::get('/reports/clinic-report-old/pdf', [ReportController::class, 'clinicReport_old']);
Route::get('/reports/doctor-shifts/{doctor}/financial-summary/pdf', [ReportController::class, 'clinicReport']);
Route::get('/reports/company/{company}/service-contracts/pdf', [ReportController::class, 'generateCompanyServiceContractPdf']);
Route::get('/reports/company/{company}/test-contracts/pdf', [ReportController::class, 'generateCompanyMainTestContractPdf']);
Route::get('/reports/monthly-service-deposits-income/pdf', [ReportController::class, 'exportMonthlyServiceDepositsIncomePdf']);
Route::get('/reports/doctor-shifts/pdf', [ReportController::class, 'doctorShiftsReportPdf']);
Route::get('/reports/insurance/pdf', [InsuranceReportController::class, 'insuranceReport']);
Route::get('/reports/doctor-reclaims/pdf', [ReportController::class, 'generateDoctorReclaimsPdf']);
Route::get('/reports/lab-general/pdf', [ReportController::class, 'generateLabGeneralReportPdf']);
Route::get('/reports/lab-shift/pdf', [ReportController::class, 'labShiftReportPdf']);
Route::get('/reports/shift-patients-discount/pdf', [ReportController::class, 'generateShiftPatientsDiscountPdfWeb']);
Route::get('/reports/shift-refunds/pdf', [ReportController::class, 'generateShiftRefundsPdfWeb']);
Route::get('/reports/cash-reconciliation/pdf', [ReportController::class, 'generateCashReconciliationPdfWeb']);
Route::get('/reports/companies/pdf', [CompanyReportController::class, 'exportAllCompaniesPdf']);
Route::get('/visits/{visit}/lab-report/pdf', [ReportController::class, 'result']);

// Excel
Route::get('/excel/reclaim', [\App\Http\Controllers\Api\ExcelController::class, 'reclaim']);

// Webhooks
Route::get('/webhook', [WebHookController::class, 'webhook']);
Route::post('/webhook', [WebHookController::class, 'webhook']);

// WhatsApp / Ultramsg
Route::get('/ultramsg/send-document-from-firebase', [\App\Http\Controllers\UltramsgController::class, 'sendDocumentFromFirebase']);

// Firebase debug
Route::get('/firebase-check', [\App\Http\Controllers\FirebaseDebugController::class, 'index']);