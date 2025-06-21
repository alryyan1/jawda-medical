        
        <?php

use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\LabResultController;
use App\Models\Shift;
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
///api/visits/10664/lab-report/pdf
Route::get('/visits/{visit}/lab-report/pdf', [ReportController::class, 'result']);
// Route::get('/visits/{doctorvisit}/lab-report/pdf', [ReportController::class, 'generateLabVisitReportPdf']);
Route::get('/visits/{id}/lab-report-old/pdf', [ReportController::class, 'result']);
//phpinfo
Route::get('/phpinfo', function () {
    phpinfo();
});


