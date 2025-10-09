<?php

use App\Http\Controllers\Api\CompanyReportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\LabResultController;
use App\Http\Controllers\WebHookController;
use App\Models\DoctorShift;
use App\Models\Hl7Message;
use App\Models\Patient;
use App\Models\Shift;
use App\Services\HL7\Devices\SysmexCbcInserter;
use App\Services\HL7\Devices\ZybioHandler;
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
// http://127.0.0.1/jawda-medical/public/reports/clinic-shift-summary/pdf?shift=2
// http://192.168.100.12/jawda-medical/public/api/reports/clinic-shift-summary/pdf?shift=201
Route::get('/api/reports/clinic-shift-summary/pdf', [ReportController::class, 'allclinicsReportNew']);
Route::get('/reports/clinic-shift-summary/pdf', [ReportController::class, 'allclinicsReportNew']);
Route::get('/reports/clinic-report-old/pdf', [ReportController::class, 'clinicReport_old']);
// reports/doctor-shifts/10/financial-summary/pdf
Route::get('/reports/doctor-shifts/{doctor}/financial-summary/pdf', [ReportController::class, 'clinicReport']);
   Route::get('/reports/company/{company}/service-contracts/pdf', [ReportController::class, 'generateCompanyServiceContractPdf']);
    Route::get('/reports/company/{company}/test-contracts/pdf', [ReportController::class, 'generateCompanyMainTestContractPdf']);
    Route::get('/reports/monthly-service-deposits-income/pdf', [ReportController::class, 'exportMonthlyServiceDepositsIncomePdf']);

Route::get('/reports/doctor-shifts/pdf', [ReportController::class, 'doctorShiftsReportPdf']);
Route::get('/', function () {
    $shift = Shift::find(168);
    return $shift->shiftClinicServiceCosts();
});
Route::get('/reports/doctor-shifts/pdf', [ReportController::class, 'doctorShiftsReportPdf']);
Route::get('/reports/doctor-reclaims/pdf', [ReportController::class, 'generateDoctorReclaimsPdf']);
Route::get('/reports/lab-general/pdf', [ReportController::class, 'generateLabGeneralReportPdf']);
///api/visits/10664/lab-report/pdf
Route::get('/visits/{visit}/lab-report/pdf', [ReportController::class, 'result']);
// Route::get('/visits/{doctorvisit}/lab-report/pdf', [ReportController::class, 'generateLabVisitReportPdf']);
Route::get('/visits/{id}/lab-report-old/pdf', [ReportController::class, 'result']);

Route::get('/reports/doctor-shifts/test',function(){
    $doctorShifts = DoctorShift::whereRaw('Date(created_at) between ? and ?', ['2025-06-24','2025-06-24'])->get();
    return $doctorShifts;
});
Route::get('/reports/companies/pdf', [CompanyReportController::class, 'exportAllCompaniesPdf']);
Route::get('/webhook', [WebHookController::class, 'webhook']);
Route::post('/webhook', [WebHookController::class, 'webhook']);

// Excel reclaim route for web access
Route::get('/excel/reclaim', [\App\Http\Controllers\Api\ExcelController::class, 'reclaim']);

//phpinfo
Route::get('/phpinfo', function () {
    phpinfo();
});


Route::get('/hl7', function () {
    //get hl7 message from hl7_messages table
    $hl7Message = Hl7Message::find(21);

    $correctedMessage = ZybioHandler::correctHl7MessageFormat($hl7Message->raw_message);

    $hl7Message = new Aranyasen\Hl7\Message($correctedMessage);
    $msh = $hl7Message->getSegmentByIndex(0);
    return $msh->getField(49);
    return $correctedMessage;
    
    // return $hl7Message;
    // return $hl7Message;
    // $removed_white_space = preg_replace('/\s+/', '', $hl7Message);
    // var_dump($hl7Message->raw_message);
    //parse hl7 message using aranyasen/hl7
    // return $removed_white_space;
    $hl7Message = new  Aranyasen\HL7\Message($hl7Message);
    dd($hl7Message);
    $msh = $hl7Message->getSegmentByIndex(0);
    // return $msh->getField(33); patient id for akon
    // return $msh->getFields();
    // return $msh->getField(25); //patient id for bc6800

    // return $hl7Message->getSegmentsByName('MSH');

    //get msh segment
    // $msh = $hl7Message->getSegments();
    //get msh fields
    // $mshFields = $msh->getFields();
    //get msh fields
    // return $hl7Message;

    
});