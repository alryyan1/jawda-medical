<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChildGroupController;
use App\Http\Controllers\Api\ChildTestController;
use App\Http\Controllers\Api\ChildTestOptionController;
use App\Http\Controllers\Api\ClinicWorkspaceController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyMainTestController;
use App\Http\Controllers\Api\CompanyRelationController;
use App\Http\Controllers\Api\CompanyServiceController;
use App\Http\Controllers\Api\ContainerController;
use App\Http\Controllers\Api\CostController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\DoctorShiftController;
use App\Http\Controllers\Api\DoctorVisitController;
use App\Http\Controllers\Api\ExcelController;
use App\Http\Controllers\Api\FinanceAccountController;
use App\Http\Controllers\Api\InsuranceAuditController;
use App\Http\Controllers\Api\LabRequestController;
use App\Http\Controllers\Api\MainTestController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RequestedServiceCostController;
use App\Http\Controllers\Api\RequestedServiceDepositController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceCostController;
use App\Http\Controllers\Api\ServiceGroupController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SpecialistController;
use App\Http\Controllers\Api\SubcompanyController;
use App\Http\Controllers\Api\SubServiceCostController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VisitServiceController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
  /*
    |--------------------------------------------------------------------------
    | User Authentication & Profile Routes
    |--------------------------------------------------------------------------
    */
  Route::get('/user', [AuthController::class, 'user']);
  Route::post('/logout', [AuthController::class, 'logout']);

  /*
    |--------------------------------------------------------------------------
    | User & Role Management Routes
    |--------------------------------------------------------------------------
    */
  Route::get('roles-list', [UserController::class, 'getRolesList']);
  Route::get('permissions-list', [RoleController::class, 'getPermissionsList']);
  Route::apiResource('users', UserController::class);
  Route::apiResource('roles', RoleController::class);
  Route::post('/users/{user}/update-password', [UserController::class, 'updatePassword']);

  /*
    |--------------------------------------------------------------------------
    | Medical Staff Routes (Doctors & Specialists)
    |--------------------------------------------------------------------------
    */
  // Specialists
  Route::get('specialists-list', [SpecialistController::class, 'indexList']);
  Route::apiResource('specialists', SpecialistController::class);

  // Doctors
  Route::get('doctors-list', [DoctorController::class, 'indexList']);
  Route::apiResource('doctors', DoctorController::class);

  // Doctor Schedules
  Route::get('/doctor-schedules', [DoctorScheduleController::class, 'index']);
  Route::get('/doctors/{doctor}/schedule', [DoctorScheduleController::class, 'getDoctorSchedule']);
  Route::post('/doctors/{doctor}/schedule', [DoctorScheduleController::class, 'storeOrUpdateForDoctor']);

  /*
    |--------------------------------------------------------------------------
    | Shift Management Routes
    |--------------------------------------------------------------------------
    */
  // General Shifts
  Route::get('/shifts/current-open', [ShiftController::class, 'getCurrentOpenShift']);
  Route::post('/shifts/open', [ShiftController::class, 'openShift']);
  Route::put('/shifts/{shift}/close', [ShiftController::class, 'closeShift']);
  Route::put('/shifts/{shift}/financials', [ShiftController::class, 'updateFinancials']);
  Route::apiResource('shifts', ShiftController::class)->except(['store', 'update', 'destroy']);

  // Doctor Shifts
  Route::get('/active-doctor-shifts', [DoctorShiftController::class, 'getActiveShifts']);
  Route::get('/doctors-with-shift-status', [DoctorShiftController::class, 'getDoctorsWithShiftStatus']);
  Route::post('/doctor-shifts/start', [DoctorShiftController::class, 'startShift']);
  Route::put('/doctor-shifts/{doctorShift}/end', [DoctorShiftController::class, 'endShift']);
  Route::get('/doctor-shifts/{doctorShift}/financial-summary', [DoctorShiftController::class, 'showFinancialSummary']);
  Route::apiResource('doctor-shifts', DoctorShiftController::class)->except(['store', 'update']);

  /*
    |--------------------------------------------------------------------------
    | Patient & Visit Management Routes
    |--------------------------------------------------------------------------
    */
  Route::get('/patients/search-existing', [PatientController::class, 'searchExisting']);
  Route::post('/patients/{patient}/store-visit-from-history', [PatientController::class, 'storeVisitFromHistory']);
  // Patients
  Route::apiResource('patients', PatientController::class);
  Route::get('/clinic-active-patients', [ClinicWorkspaceController::class, 'getActivePatients']);

  // Doctor Visits
  Route::put('/doctor-visits/{doctorVisit}/status', [DoctorVisitController::class, 'updateStatus']);
  Route::apiResource('doctor-visits', DoctorVisitController::class);

  // Visit Services
  Route::get('/visits/{visit}/available-services', [VisitServiceController::class, 'getAvailableServices']);
  Route::get('/visits/{visit}/requested-services', [VisitServiceController::class, 'getRequestedServices']);
  Route::post('/visits/{visit}/request-services', [VisitServiceController::class, 'addRequestedServices']);
  Route::put('/requested-services/{requestedService}', [VisitServiceController::class, 'updateRequestedService']);
  Route::delete('/visits/{visit}/requested-services/{requestedService}', [VisitServiceController::class, 'removeRequestedService']);
  Route::post('/requested-services/{requestedService}/deposits', [RequestedServiceDepositController::class, 'store']);

  /*
    |--------------------------------------------------------------------------
    | Company & Service Management Routes
    |--------------------------------------------------------------------------
    */
  // Companies
  Route::get('companies-list', [CompanyController::class, 'indexList']);
  Route::apiResource('companies', CompanyController::class);

  // Company Services
  Route::get('companies/{company}/contracted-services', [CompanyServiceController::class, 'index']);
  Route::get('companies/{company}/available-services', [CompanyServiceController::class, 'availableServices']);
  Route::post('companies/{company}/contracted-services', [CompanyServiceController::class, 'store']);
  Route::put('companies/{company}/contracted-services/{service}', [CompanyServiceController::class, 'update']);
  Route::delete('companies/{company}/contracted-services/{service}', [CompanyServiceController::class, 'destroy']);
  Route::post('companies/{company}/contracted-services/import-all', [CompanyServiceController::class, 'importAllServices']);

  // Services & Service Groups
  Route::get('service-groups-list', [ServiceGroupController::class, 'indexList']);
  Route::get('/service-groups-with-services', [ServiceGroupController::class, 'getGroupsWithServices']);
  Route::post('service-groups', [ServiceGroupController::class, 'store']);
  Route::apiResource('services', ServiceController::class);

  /*
    |--------------------------------------------------------------------------
    | Finance & Settings Routes
    |--------------------------------------------------------------------------
    */
  // Finance Accounts
  Route::get('finance-accounts-list', [FinanceAccountController::class, 'indexList']);
  Route::get('/user/current-shift-income-summary', [UserController::class, 'getCurrentUserShiftIncomeSummary']);

  // Settings
  Route::get('/settings', [SettingsController::class, 'show']);
  Route::post('/settings', [SettingsController::class, 'update']);

  Route::get('/reports/doctor-shifts/pdf', [ReportController::class, 'doctorShiftsReportPdf']);
  Route::get('/reports/service-statistics', [ReportController::class, 'serviceStatistics']);

  Route::get('containers-list', [ContainerController::class, 'indexList']);
  Route::post('containers', [ContainerController::class, 'store']); // For quick add dialog
  // Route::apiResource('containers', ContainerController::class); // If full CRUD for containers

  Route::apiResource('main-tests', MainTestController::class);
  Route::get('units-list', [UnitController::class, 'indexList']);
  Route::post('units', [UnitController::class, 'store']);
  Route::get('child-groups-list', [ChildGroupController::class, 'indexList']);
  Route::post('child-groups', [ChildGroupController::class, 'store']);

  // Nested resource for child tests under a main test
  Route::apiResource('main-tests.child-tests', ChildTestController::class)->shallow();
  Route::put('/batch-update-prices', [MainTestController::class, 'batchUpdatePrices']);
  Route::post('/main-tests/batch-delete', [MainTestController::class, 'batchDeleteTests']); // Using POST for body with IDs
  Route::get('/reports/lab-price-list/pdf', [ReportController::class, 'generatePriceListPdf']);

  Route::get('packages-list', [PackageController::class, 'indexList']);
  Route::apiResource('packages', PackageController::class);

  // Routes for managing main test contracts of a specific company
  Route::get('companies/{company}/contracted-main-tests', [CompanyMainTestController::class, 'index'])->name('companies.main_test_contracts.index');
  Route::get('companies/{company}/available-main-tests', [CompanyMainTestController::class, 'availableMainTests'])->name('companies.main_test_contracts.available');
  Route::post('companies/{company}/contracted-main-tests', [CompanyMainTestController::class, 'store'])->name('companies.main_test_contracts.store');
  Route::post('companies/{company}/contracted-main-tests/import-all', [CompanyMainTestController::class, 'importAllMainTests'])->name('companies.main_test_contracts.importAll');
  // Note: For update and destroy, Laravel's route model binding will bind {main_test} to a MainTest instance
  Route::put('companies/{company}/contracted-main-tests/{main_test}', [CompanyMainTestController::class, 'update'])->name('companies.main_test_contracts.update');
  Route::delete('companies/{company}/contracted-main-tests/{main_test}', [CompanyMainTestController::class, 'destroy'])->name('companies.main_test_contracts.destroy');
  // Example for a dedicated route to assign tests if needed, though update handles it
  // Route::post('packages/{package}/assign-tests', [PackageController::class, 'assignTests']);

  Route::get('/reports/company/{company}/service-contracts/pdf', [ReportController::class, 'generateCompanyServiceContractPdf']);
  Route::get('/reports/company/{company}/test-contracts/pdf', [ReportController::class, 'generateCompanyMainTestContractPdf']);

  // Lab Requests
  Route::get('/visits/{visit}/lab-requests', [LabRequestController::class, 'indexForVisit']);
  Route::get('/visits/{visit}/available-lab-tests', [LabRequestController::class, 'availableTestsForVisit']);
  Route::post('/visits/{visit}/lab-requests-batch', [LabRequestController::class, 'storeBatchForVisit']);

  Route::put('/labrequests/{labrequest}', [LabRequestController::class, 'update']);
  Route::delete('/labrequests/{labrequest}', [LabRequestController::class, 'destroy']);
  Route::post('/labrequests/{labrequest}/pay', [LabRequestController::class, 'recordPayment']);
  Route::post('/labrequests/{labrequest}/authorize', [LabRequestController::class, 'authorizeResults']);

  Route::get('/lab/pending-queue', [LabRequestController::class, 'getLabPendingQueue']);

  // This is the endpoint that ResultEntryPanel uses to get all data for one LabRequest
  Route::get('/labrequests/{labrequest}/for-result-entry', [LabRequestController::class, 'getLabRequestForEntry']);

  Route::post('/labrequests/{labrequest}/results', [LabRequestController::class, 'saveResults']);

  // Generic LabRequest CRUD (if needed separately from visit context for some actions)
  Route::apiResource('labrequests', LabRequestController::class)->except(['index', 'store']);
  Route::post('/main-tests/{main_test}/child-tests/batch-update-order', [ChildTestController::class, 'batchUpdateOrder'])->middleware('auth:sanctum');
  Route::apiResource('child-tests.options', ChildTestOptionController::class)->shallow()->except(['show']);
  Route::get('/cost-categories-list', [CostController::class, 'getCostCategories']);
  // api/main-tests/find/123
  Route::get('/main-tests/find/{identifier}', [MainTestController::class, 'findByIdOrCode']);
  Route::delete('/visits/{visit}/lab-requests/clear-pending', [LabRequestController::class, 'clearPendingRequests'])->middleware('auth:sanctum');
  Route::post('/costs', [CostController::class, 'store']); // For the dialog
  // The route api/visits/48/lab-requests/batch-pay could not be foun
  Route::post('/visits/{visit}/lab-requests/batch-pay', [LabRequestController::class, 'batchPayLabRequests'])->middleware('auth:sanctum');
  // Route::apiResource('costs', CostController::class); // For full CRUD page later
  // The `except(['index', 'store'])` means GET /labrequests/{labrequest} (for show) SHOULD be defined by apiResource.
  Route::get('/reports/monthly-lab-income/pdf', [ReportController::class, 'generateMonthlyLabIncomePdf']);
  Route::get('/dashboard/summary', [DashboardController::class, 'getSummary'])->middleware('auth:sanctum');
  Route::get('/shifts/{shift}/financial-summary', [ShiftController::class, 'getFinancialSummary'])->middleware('auth:sanctum');
  Route::get('/subcompanies-list', [SubcompanyController::class, 'indexList'])->middleware('auth:sanctum');
  Route::post('/subcompanies', [SubcompanyController::class, 'store'])->middleware('auth:sanctum');
  // Routes: /company-relations-list and POST /company-relations.
  Route::get('/company-relations-list', [CompanyRelationController::class, 'indexList'])->middleware('auth:sanctum');
  // the route api/companies/1/relations could not be found
  Route::post('/companies/{company}/relations', [CompanyRelationController::class, 'store'])->middleware('auth:sanctum');

  // "The route api/company-relations could not be found."
  Route::apiResource('company-relations', CompanyRelationController::class);
  // he route api/companies/1/subcompanies could not be found."
  // Route::get('/companies/{company}/subcompanies', [SubcompanyController::class, 'indexList'])->middleware('auth:sanctum');
  Route::apiResource('/companies/{company}/subcompanies', SubcompanyController::class);


  Route::get('/reports/doctor-shifts/pdf', [ReportController::class, 'doctorShiftsReportPdf']);

  //api/reports/clinic-report/1/financial-summary/pdf
  Route::get('/reports/doctor-shifts/{doctorShift}/financial-summary/pdf', [ReportController::class, 'clinicReport']);
  // The route api/reports/clinic-shift-summary/pdf could not be found.
  Route::get('/reports/clinic-shift-summary/pdf', [ReportController::class, 'allclinicsReportNew']);
  // ...
  Route::get('/visits/{visit}/thermal-receipt/pdf', [ReportController::class, 'generateThermalServiceReceipt']);
  Route::get('/reports/costs/pdf', [ReportController::class, 'generateCostsReportPdf']);
    // Route for fetching costs for the list page (if not using a full apiResource for costs)
    Route::get('/costs-report-data', [CostController::class, 'index']); // Using CostController@index for data
  // Route::
  
    /*
    |--------------------------------------------------------------------------
    | Service Costing Routes
    |--------------------------------------------------------------------------
    */

    // SubServiceCost (Cost Components/Types)
    Route::get('/sub-service-costs-list', [SubServiceCostController::class, 'indexList']);
    Route::apiResource('sub-service-costs', SubServiceCostController::class);
    // Route::post('/doctors/{doctor}/sub-service-costs', [DoctorSubServiceCostController::class, 'store']); // If managing pivot
    // Route::delete('/doctors/{doctor}/sub-service-costs/{subServiceCost}', [DoctorSubServiceCostController::class, 'destroy']); // If managing pivot


    // ServiceCost (Defines costs for a specific Service)
    Route::apiResource('services.service-costs', ServiceCostController::class)->shallow();
    // This will create routes like:
    // GET    /api/services/{service}/service-costs        (index)
    // POST   /api/services/{service}/service-costs        (store)
    // GET    /api/service-costs/{service_cost}          (show) - shallow
    // PUT    /api/service-costs/{service_cost}          (update) - shallow
    // DELETE /api/service-costs/{service_cost}          (destroy) - shallow


    // RequestedServiceCost (Actual cost breakdown for a requested service)
    // These are often created programmatically, direct CRUD might be less common.
    // Example: Route to view cost breakdown for a specific requested service
    // Route::apiResource('requested-service-costs', RequestedServiceCostController::class); // If full CRUD needed
    
    // For RequestedServiceCost entries linked to a specific RequestedService
    Route::get('/requested-services/{requested_service}/cost-breakdown', [RequestedServiceCostController::class, 'indexForRequestedService']);
    Route::post('/requested-services/{requested_service}/costs', [RequestedServiceCostController::class, 'storeOrUpdateBatch']); // For creating/updating multiple costs for a RequestedService
    
    // If you need individual CRUD for RequestedServiceCost items directly by their own ID
    // Route::apiResource('requested-service-costs', RequestedServiceCostController::class)->only(['show', 'update', 'destroy']);
    // OR if you want to allow creating one by one via its own resource controller, less common if always tied to requested service
    Route::post('/requested-service-costs', [RequestedServiceCostController::class, 'storeSingle']);
    Route::put('/requested-service-costs/{requested_service_cost}', [RequestedServiceCostController::class, 'updateSingle']);
    Route::delete('/requested-service-costs/{requested_service_cost}', [RequestedServiceCostController::class, 'destroySingle']);
    
    // Deposits for a specific Requested Service
    Route::get('/requested-services/{requested_service}/deposits', [RequestedServiceDepositController::class, 'indexForRequestedService']); // NEW or ensure exists
    // POST to '/requested-services/{requested_service}/deposits' is already handled by RequestedServiceDepositController@store
    
    // If you want direct CRUD on the deposit records themselves by their own ID:
    // Route::apiResource('requested-service-deposits', RequestedServiceDepositController::class)->except(['store']); 
    // Or more specific routes for update/delete:
    Route::put('/requested-service-deposits/{requestedServiceDeposit}', [RequestedServiceDepositController::class, 'update']); // NEW
    Route::delete('/requested-service-deposits/{requestedServiceDeposit}', [RequestedServiceDepositController::class, 'destroy']); // NEW
    Route::post('companies/{targetCompany}/copy-contracts-from/{sourceCompany}', [CompanyServiceController::class, 'copyContractsFrom']); // NEW ROUTE
     /*
    |--------------------------------------------------------------------------
    | Insurance Auditing Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/insurance-audit/patients', [InsuranceAuditController::class, 'listAuditableVisits']);
    Route::get('/insurance-audit/visits/{doctorVisit}/audit-record', [InsuranceAuditController::class, 'getOrCreateAuditRecordForVisit']);
    Route::put('/insurance-audit/records/{auditedPatientRecord}', [InsuranceAuditController::class, 'updateAuditedPatientInfo']);
    Route::post('/insurance-audit/records/{auditedPatientRecord}/copy-services', [InsuranceAuditController::class, 'copyServicesToAudit']);
    Route::post('/insurance-audit/audited-services', [InsuranceAuditController::class, 'storeAuditedService']);
    Route::put('/insurance-audit/audited-services/{auditedRequestedService}', [InsuranceAuditController::class, 'updateAuditedService']);
    Route::delete('/insurance-audit/audited-services/{auditedRequestedService}', [InsuranceAuditController::class, 'deleteAuditedService']);
    Route::post('/insurance-audit/records/{auditedPatientRecord}/verify', [InsuranceAuditController::class, 'verifyAuditRecord']);

    // PDF/Excel Export Routes
    Route::get('/insurance-audit/export/pdf', [InsuranceAuditController::class, 'exportPdf']);
    // http://127.0.0.1/jawda-medical/public/api/insurance-audit/export/excel?company_id=1&date_from=2025-05-01&date_to=2025-05-31&service_group_ids[]=9&service_group_ids[]=1&service_group_ids[]=2&service_group_ids[]=3&service_group_ids[]=4&service_group_ids[]=7&service_group_ids[]=5
    Route::get('/insurance-audit/export/excel', [ExcelController::class, 'exportInsuranceClaim']);
    Route::get('/reports/monthly-service-deposits-income', [ReportController::class, 'monthlyServiceDepositsIncome']);
     /*
    |--------------------------------------------------------------------------
    | Patient Specific Actions (ensure these are within the auth:sanctum group)
    |--------------------------------------------------------------------------
    */
    // Existing search and store-from-history routes for patients
    Route::get('/patients/search-existing', [PatientController::class, 'searchExisting']);
    Route::post('/patients/{patient}/store-visit-from-history', [PatientController::class, 'storeVisitFromHistory']);
    
    // NEW: Route for patient visit history
    Route::get('/patients/{patient}/visit-history', [PatientController::class, 'visitHistory'])->name('patients.visitHistory');
    
    Route::apiResource('patients', PatientController::class); // This should already be there

    /*
    |--------------------------------------------------------------------------
    | Doctor Visit Specific Actions (ensure these are within the auth:sanctum group)
    |--------------------------------------------------------------------------
    */
    // Existing status update and apiResource for doctor visits
    Route::put('/doctor-visits/{doctorVisit}/status', [DoctorVisitController::class, 'updateStatus']);
    Route::apiResource('doctor-visits', DoctorVisitController::class);

    // NEW: Route for reassigning a doctor visit to a different shift
    Route::post('/doctor-visits/{doctorVisit}/reassign-shift', [DoctorVisitController::class, 'reassignToShift'])->name('doctorVisits.reassignShift');
    
    // NEW: Route for creating a new visit for a patient by copying their data to a new shift
    Route::post('/patients/{sourcePatient}/copy-to-new-visit', [DoctorVisitController::class, 'createCopiedVisitForNewShift'])->name('patients.copyToNewVisit');
    
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Communication Routes
    |--------------------------------------------------------------------------
    */
    Route::post('/whatsapp/send-text', [WhatsAppController::class, 'sendText']);
    Route::post('/whatsapp/send-media', [WhatsAppController::class, 'sendMedia']);
    // Route::get('/whatsapp/templates', [WhatsAppController::class, 'getMessageTemplates']); // 
    Route::get('/reports/monthly-service-deposits-income/pdf', [ReportController::class, 'exportMonthlyServiceDepositsIncomePdf']);
    Route::get('/reports/monthly-service-deposits-income/excel', [ReportController::class, 'exportMonthlyServiceDepositsIncomeExcel']);
});
