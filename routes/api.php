<?php

use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AdmissionRequestedServiceController;
use App\Http\Controllers\Api\AdmissionRequestedLabTestController;
use App\Http\Controllers\Api\AdmissionVitalSignController;
use App\Http\Controllers\Api\AdmissionRequestedServiceDepositController;
use App\Http\Controllers\Api\AdmissionDepositController;
use App\Http\Controllers\Api\AdmissionTransactionController;
use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\WebHookController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceReportController;
use App\Http\Controllers\Api\AttendanceSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankakImageController;
use App\Http\Controllers\Api\BedController;
use App\Http\Controllers\Api\CashDenominationController;
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
use App\Http\Controllers\Api\DeviceChildTestNormalRangeController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\DoctorServiceController;
use App\Http\Controllers\Api\DoctorShiftController;
use App\Http\Controllers\Api\DoctorVisitController;
use App\Http\Controllers\Api\ExcelController;
use App\Http\Controllers\Api\FinanceAccountController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\InsuranceAuditController;
use App\Http\Controllers\Api\LabRequestController;
use App\Http\Controllers\Api\MainTestController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PdfSettingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RequestedServiceCostController;
use App\Http\Controllers\Api\RequestedServiceDepositController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SampleCollectionController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceCostController;
use App\Http\Controllers\Api\ServiceGroupController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\ShiftDefinitionController;
use App\Http\Controllers\Api\SpecialistController;
use App\Http\Controllers\Api\SubSpecialistController;
use App\Http\Controllers\Api\SubcompanyController;
use App\Http\Controllers\Api\SubServiceCostController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserDocSelectionController;
use App\Http\Controllers\Api\WardController;
use App\Http\Controllers\Api\VisitServiceController;
use App\Http\Controllers\Api\CompanyReportController;
use App\Http\Controllers\Api\SettingUploadController;
use App\Http\Controllers\Api\HL7MessageController;
use App\Http\Controllers\Api\HL7MessageInsertController;
use App\Http\Controllers\Api\ImageProxyController;
use App\Http\Controllers\Api\BindingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SmsController;

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
    | Doctor Specific Service Configuration Routes
    |--------------------------------------------------------------------------
    */

Route::get('/doctors/{doctor}/configured-services', [DoctorServiceController::class, 'index']);
Route::get('/doctors/{doctor}/available-services-for-config', [DoctorServiceController::class, 'availableServices']);
Route::post('/doctors/{doctor}/configure-service', [DoctorServiceController::class, 'store']);
Route::put('/doctors/{doctor}/configure-service/{service}', [DoctorServiceController::class, 'update']);
Route::delete('/doctors/{doctor}/configure-service/{service}', [DoctorServiceController::class, 'destroy']);
/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| HL7 Client Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::get('/hl7-client/status', [\App\Http\Controllers\Api\HL7ClientController::class, 'status']);
Route::post('/hl7-client/start', [\App\Http\Controllers\Api\HL7ClientController::class, 'start']);
Route::post('/hl7-client/stop', [\App\Http\Controllers\Api\HL7ClientController::class, 'stop']);
Route::post('/hl7-client/toggle', [\App\Http\Controllers\Api\HL7ClientController::class, 'toggle']);

// Public PDF route for Doctors List (opens in new tab)
Route::get('/reports/doctors-list/pdf', [ReportController::class, 'exportDoctorsListToPdf']);

/*
|--------------------------------------------------------------------------
| Queue Worker Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::get('/queue-worker/status', [\App\Http\Controllers\Api\QueueWorkerController::class, 'status']);
Route::post('/queue-worker/start', [\App\Http\Controllers\Api\QueueWorkerController::class, 'start']);
Route::post('/queue-worker/stop', [\App\Http\Controllers\Api\QueueWorkerController::class, 'stop']);
Route::post('/queue-worker/toggle', [\App\Http\Controllers\Api\QueueWorkerController::class, 'toggle']);
Route::get('/lab/pending-queue', [LabRequestController::class, 'getLabPendingQueue']);
Route::apiResource('main-tests', MainTestController::class);
Route::get('/visits/{visit}/lab-requests', [LabRequestController::class, 'indexForVisit']);

Route::middleware('auth:sanctum')->group(function () {
  // SMS
  Route::post('/sms/send', [SmsController::class, 'send']);
  /*
    |--------------------------------------------------------------------------
    | User Authentication & Profile Routes
    |--------------------------------------------------------------------------
    */
  Route::get('/user', [AuthController::class, 'user']);
  Route::get('/get-users', [UserController::class, 'index']);
  Route::post('/logout', [AuthController::class, 'logout']);

  /*
    |--------------------------------------------------------------------------
    | User & Role Management Routes
    |--------------------------------------------------------------------------
    */
  Route::get('roles-list', [UserController::class, 'getRolesList']);
  Route::get('permissions-list', [RoleController::class, 'getPermissionsList']);
  // Specific routes must come before apiResource to avoid route model binding conflicts
  Route::get('/users/with-shift-transactions', [UserController::class, 'getUsersWithShiftTransactions']);
  Route::get('/users/shift-patient-transactions', [UserController::class, 'getUserShiftPatientTransactions']);
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

  // Favorite Doctors Management
  Route::get('/favorite-doctors', [UserDocSelectionController::class, 'index']);
  Route::get('/doctors-with-favorites', [UserDocSelectionController::class, 'getDoctorsWithFavorites']);
  Route::post('/favorite-doctors', [UserDocSelectionController::class, 'store']);
  Route::post('/favorite-doctors/toggle', [UserDocSelectionController::class, 'toggle']);
  Route::delete('/favorite-doctors/{docId}', [UserDocSelectionController::class, 'destroy']);

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
  Route::get('/shifts/current-shift', [ShiftController::class, 'getCurrentShift']);
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

  // New route for searching visits by patient name for autocomplete:
  Route::get('/doctor-visits/search-by-patient', [PatientController::class, 'searchRecentDoctorVisitsByPatientName']);
  Route::get('/patients/recent-lab-activity', [PatientController::class, 'getRecentLabActivityPatients']);

  Route::get('/patients/search-existing', [PatientController::class, 'searchExisting']);
  Route::post('/patients/{doctorVisit}/store-visit-from-history', [PatientController::class, 'storeVisitFromHistory']);
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
  Route::post('companies/activate-all', [CompanyController::class, 'activateAll']);
  Route::put('companies/{company}/firestore-id', [CompanyController::class, 'updateFirestoreId']);

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
  Route::apiResource('services', ServiceController::class);

  /*
    |--------------------------------------------------------------------------
    | Admissions Management Routes
    |--------------------------------------------------------------------------
    */
  // Wards
  Route::get('wards-list', [WardController::class, 'indexList']);
  Route::apiResource('wards', WardController::class);
  Route::get('wards/{ward}/rooms', [WardController::class, 'getRooms']);

  // Rooms
  Route::apiResource('rooms', RoomController::class);
  Route::get('rooms/{room}/beds', [RoomController::class, 'getBeds']);

  // Beds
  // Specific routes must come BEFORE apiResource to avoid route conflicts
  Route::get('beds/available', [BedController::class, 'getAvailable']);
  Route::apiResource('beds', BedController::class);

  // Admissions
  // Specific routes must come BEFORE apiResource to avoid route conflicts
  Route::get('admissions/active', [AdmissionController::class, 'getActive']);
  Route::apiResource('admissions', AdmissionController::class);
  Route::put('admissions/{admission}/discharge', [AdmissionController::class, 'discharge']);
  Route::put('admissions/{admission}/transfer', [AdmissionController::class, 'transfer']);
  Route::get('admissions/{admission}/balance', [AdmissionTransactionController::class, 'balance']);

  // Admission Transactions
  Route::get('admissions/{admission}/transactions', [AdmissionTransactionController::class, 'index']);
  Route::post('admissions/{admission}/transactions', [AdmissionTransactionController::class, 'store']);
  Route::delete('admissions/{admission}/transactions/{transaction}', [AdmissionTransactionController::class, 'destroy']);
  Route::get('admissions/{admission}/ledger', [AdmissionTransactionController::class, 'ledger']);

  // Admission Deposits (deprecated - kept for backward compatibility, will redirect to transactions)
  Route::get('admissions/{admission}/deposits', [AdmissionDepositController::class, 'index']);
  Route::post('admissions/{admission}/deposits', [AdmissionDepositController::class, 'store']);

  // Admission Services
  Route::get('admissions/{admission}/requested-services', [AdmissionRequestedServiceController::class, 'index']);
  Route::post('admissions/{admission}/request-services', [AdmissionRequestedServiceController::class, 'store']);
  Route::put('admission-requested-services/{requestedService}', [AdmissionRequestedServiceController::class, 'update']);
  Route::delete('admissions/{admission}/requested-services/{requestedService}', [AdmissionRequestedServiceController::class, 'destroy']);

  // Admission Service Cost Breakdown
  Route::get('admission-requested-services/{requestedService}/cost-breakdown', [AdmissionRequestedServiceController::class, 'getServiceCosts']);
  Route::post('admission-requested-services/{requestedService}/costs', [AdmissionRequestedServiceController::class, 'addServiceCosts']);

  // Admission Service Deposits
  Route::get('admission-requested-services/{requestedService}/deposits', [AdmissionRequestedServiceDepositController::class, 'indexForRequestedService']);
  Route::post('admission-requested-services/{requestedService}/deposits', [AdmissionRequestedServiceDepositController::class, 'store']);

  // Admission Lab Tests
  Route::get('admissions/{admission}/requested-lab-tests', [AdmissionRequestedLabTestController::class, 'index']);
  Route::post('admissions/{admission}/request-lab-tests', [AdmissionRequestedLabTestController::class, 'store']);
  Route::put('admission-requested-lab-tests/{requestedLabTest}', [AdmissionRequestedLabTestController::class, 'update']);
  Route::delete('admissions/{admission}/requested-lab-tests/{requestedLabTest}', [AdmissionRequestedLabTestController::class, 'destroy']);
  Route::put('admission-requested-service-deposits/{deposit}', [AdmissionRequestedServiceDepositController::class, 'update']);
  Route::delete('admission-requested-service-deposits/{deposit}', [AdmissionRequestedServiceDepositController::class, 'destroy']);

  // Admission Vital Signs
  Route::get('admissions/{admission}/vital-signs', [AdmissionVitalSignController::class, 'index']);
  Route::post('admissions/{admission}/vital-signs', [AdmissionVitalSignController::class, 'store']);
  Route::put('admission-vital-signs/{vitalSign}', [AdmissionVitalSignController::class, 'update']);
  Route::delete('admission-vital-signs/{vitalSign}', [AdmissionVitalSignController::class, 'destroy']);

  // PDF Settings
  Route::get('pdf-settings', [PdfSettingController::class, 'index']);
  Route::put('pdf-settings', [PdfSettingController::class, 'update']);
  Route::post('pdf-settings/upload-logo', [PdfSettingController::class, 'uploadLogo']);
  Route::post('pdf-settings/upload-header', [PdfSettingController::class, 'uploadHeader']);
  Route::delete('pdf-settings/logo', [PdfSettingController::class, 'deleteLogo']);
  Route::delete('pdf-settings/header', [PdfSettingController::class, 'deleteHeader']);

  /*
    |--------------------------------------------------------------------------
    | Finance & Settings Routes
    |--------------------------------------------------------------------------
    */
  // Finance Accounts
  Route::get('finance-accounts-list', [FinanceAccountController::class, 'indexList']);
  Route::get('/user/current-shift-income-summary', [UserController::class, 'getCurrentUserShiftIncomeSummary']);

  Route::post('/settings/upload', [SettingUploadController::class, 'upload']);
  // Settings
  Route::get('/settings', [SettingsController::class, 'show']);
  Route::post('/settings', [SettingsController::class, 'update']);

  // Bindings Management (CBC, Chemistry, Hormone)
  Route::get('/bindings', [BindingController::class, 'index']);
  Route::get('/bindings/table-columns', [BindingController::class, 'getTableColumns']);
  Route::get('/bindings/table-data', [BindingController::class, 'getTableData']);
  Route::post('/bindings', [BindingController::class, 'store']);
  Route::put('/bindings/{id}', [BindingController::class, 'update']);
  Route::delete('/bindings/{id}', [BindingController::class, 'destroy']);
  Route::delete('/bindings/table-record/{id}', [BindingController::class, 'deleteTableRecord']);

  Route::get('/reports/doctor-shifts/pdf', [ReportController::class, 'doctorShiftsReportPdf']);
  Route::get('/reports/doctor-shifts/excel', [ExcelController::class, 'doctorShiftsReportExcel']);
  Route::get('/reports/specialist-shifts/excel', [ExcelController::class, 'specialistShiftsReportExcel']);
  Route::get('/reports/service-statistics', [ReportController::class, 'serviceStatistics']);

  Route::get('containers-list', [ContainerController::class, 'indexList']);
  Route::post('containers', [ContainerController::class, 'store']); // For quick add dialog
  // Route::apiResource('containers', ContainerController::class); // If full CRUD for containers

  Route::apiResource('offers', OfferController::class);
  Route::get('offers-main-tests', [OfferController::class, 'getMainTests']);
  Route::get('units-list', [UnitController::class, 'indexList']);
  Route::post('units', [UnitController::class, 'store']);
  Route::get('child-groups-list', [ChildGroupController::class, 'indexList']);
  Route::post('child-groups', [ChildGroupController::class, 'store']);

  // Nested resource for child tests under a main test
  Route::apiResource('main-tests.child-tests', ChildTestController::class)->shallow();
  // Get all child tests (for autocomplete)
  Route::get('/child-tests', [ChildTestController::class, 'getAll']);
  // JSON params dedicated endpoints for child tests
  Route::get('/child-tests/{child_test}/json-params', [\App\Http\Controllers\Api\ChildTestController::class, 'getJsonParams']);
  Route::put('/child-tests/{child_test}/json-params', [\App\Http\Controllers\Api\ChildTestController::class, 'updateJsonParams']);
  Route::put('/batch-update-prices', [MainTestController::class, 'batchUpdatePrices']);
  Route::post('/main-tests/batch-delete', [MainTestController::class, 'batchDeleteTests']); // Using POST for body with IDs
  Route::get('/reports/lab-price-list/pdf', [ReportController::class, 'generatePriceListPdf']);
  Route::get('/reports/price-list-pdf', [MainTestController::class, 'generatePriceListPdf']);

  Route::get('packages-list', [PackageController::class, 'indexList']);
  Route::apiResource('packages', PackageController::class);

  // Routes for managing main test contracts of a specific company
  Route::get('companies/{company}/contracted-main-tests', [CompanyMainTestController::class, 'index']);
  Route::get('companies/{company}/available-main-tests', [CompanyMainTestController::class, 'availableMainTests']);
  Route::post('companies/{company}/contracted-main-tests', [CompanyMainTestController::class, 'store']);
  Route::post('companies/{company}/contracted-main-tests/import-all', [CompanyMainTestController::class, 'importAllMainTests']);
  Route::post('companies/{targetCompany}/copy-main-test-contracts-from/{sourceCompany}', [CompanyMainTestController::class, 'copyContractsFrom']);
  // Note: For update and destroy, Laravel's route model binding will bind {main_test} to a MainTest instance
  Route::put('companies/{company}/contracted-main-tests/{main_test}', [CompanyMainTestController::class, 'update']);
  Route::delete('companies/{company}/contracted-main-tests/{main_test}', [CompanyMainTestController::class, 'destroy']);
  // Example for a dedicated route to assign tests if needed, though update handles it
  // Route::post('packages/{package}/assign-tests', [PackageController::class, 'assignTests']);

  Route::get('/reports/company/{company}/service-contracts/pdf', [ReportController::class, 'generateCompanyServiceContractPdf']);
  Route::get('/reports/company/{company}/test-contracts/pdf', [ReportController::class, 'generateCompanyMainTestContractPdf']);

  // Lab Requests
  // Removed: container barcode and print-all-samples endpoints
  Route::get('/visits/{visit}/available-lab-tests', [LabRequestController::class, 'availableTestsForVisit']);
  Route::post('/visits/{visit}/lab-requests-batch', [LabRequestController::class, 'storeBatchForVisit']);

  Route::put('/labrequests/{labrequest}', [LabRequestController::class, 'update']);
  Route::delete('/labrequests/{labrequest}', [LabRequestController::class, 'destroy']);
  Route::post('/labrequests/{labrequest}/pay', [LabRequestController::class, 'recordPayment']);
  Route::post('/labrequests/{labrequest}/authorize', [LabRequestController::class, 'authorizeResults']);

  Route::get('/lab/ready-for-print-queue', [LabRequestController::class, 'getLabReadyForPrintQueue']);
  Route::get('/lab/unfinished-results-queue', [LabRequestController::class, 'getLabUnfinishedResultsQueue']);
  Route::get('/lab/queue-item/{visitId}', [LabRequestController::class, 'getSinglePatientLabQueueItem']);

  // This is the endpoint that ResultEntryPanel uses to get all data for one LabRequest
  Route::get('/labrequests/{labrequest}/for-result-entry', [LabRequestController::class, 'getLabRequestForEntry']);

  Route::post('/labrequests/{requestedResult}/results', [LabRequestController::class, 'saveSingleResult']);
  Route::patch('/labrequests/{labrequest}/childtests/{child_test}/result', [LabRequestController::class, 'saveSingleResult']);
  Route::patch('/labrequests/{labrequest}/childtests/{child_test}/normal-range', [LabRequestController::class, 'updateNormalRange']);
  Route::patch('/labrequests/{labrequest}/comment', [LabRequestController::class, 'updateComment']);

  // Comment suggestions endpoints
  Route::get('/lab/comment-suggestions', [LabRequestController::class, 'getCommentSuggestions']);
  Route::post('/lab/comment-suggestions', [LabRequestController::class, 'addCommentSuggestion']);

  // Organism suggestions endpoints
  Route::get('/lab/suggestions', [LabRequestController::class, 'getSuggestions']);
  Route::post('/lab/suggestions', [LabRequestController::class, 'addSuggestion']);

  // Generic LabRequest CRUD (if needed separately from visit context for some actions)
  Route::apiResource('labrequests', LabRequestController::class)->except(['index', 'store']);
  Route::post('/main-tests/{main_test}/child-tests/batch-update-order', [ChildTestController::class, 'batchUpdateOrder'])->middleware('auth:sanctum');
  Route::apiResource('child-tests.options', ChildTestOptionController::class)->shallow()->except(['show']);
  Route::get('/cost-categories-list', [CostController::class, 'getCostCategories']);
  // api/main-tests/find/123
  Route::get('/main-tests/find/{identifier}', [MainTestController::class, 'findByIdOrCode']);
  Route::delete('/visits/{visit}/lab-requests/clear-pending', [LabRequestController::class, 'clearPendingRequests'])->middleware('auth:sanctum');
  Route::post('/labrequests/{labrequest}/unpay', [LabRequestController::class, 'unpay'])->middleware('auth:sanctum');
  Route::post('/costs', [CostController::class, 'store']); // For the dialog
  Route::delete('/costs/{cost}', [CostController::class, 'destroy']); // For deleting costs
  // The route api/visits/48/lab-requests/batch-pay could not be found
  Route::post('/visits/{visit}/lab-requests/batch-pay', [LabRequestController::class, 'batchPayLabRequests'])->middleware('auth:sanctum');
  // Route::apiResource('costs', CostController::class); // For full CRUD page later
  // The `except(['index', 'store'])` means GET /labrequests/{labrequest} (for show) SHOULD be defined by apiResource.
  Route::get('/reports/monthly-lab-income/pdf', [ReportController::class, 'generateMonthlyLabIncomePdf']);
  Route::get('/dashboard/summary', [DashboardController::class, 'getSummary'])->middleware('auth:sanctum');
  Route::get('/dashboard/financial-summary', [DashboardController::class, 'getFinancialSummary'])->middleware('auth:sanctum');
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

  // "The route api/reports/monthly-lab-income could not be found."
  Route::get('/reports/monthly-lab-income', [ReportController::class, 'monthlyLabIncome']);
  //api/reports/clinic-report/1/financial-summary/pdf
  Route::get('/reports/doctor-shifts/{doctorShift}/financial-summary/pdf', [ReportController::class, 'clinicReport']);
  // The route api/reports/clinic-shift-summary/pdf could not be found.
  Route::get('/reports/clinic-shift-summary/pdf', [ReportController::class, 'allclinicsReportNew']);
  // ...
  Route::get('/visits/{visit}/lab-thermal-receipt/pdf', [LabRequestController::class, 'generateLabThermalReceiptPdf']);
  Route::get('/reports/costs/pdf', [ReportController::class, 'generateCostsReportPdf']);
  Route::get('/reports/costs/excel', [ExcelController::class, 'exportCostsReportToExcel']);
  // Route for fetching costs for the list page (if not using a full apiResource for costs)
  Route::get('/costs-report-data', [CostController::class, 'index']); // Using CostController@index for data
  Route::get('/reports/costs-by-day', [CostController::class, 'costsByDay']); // Daily costs report
  Route::get('/reports/costs-by-day/pdf', [CostController::class, 'costsByDayPdf']); // Daily costs PDF
  Route::get('/reports/costs-by-day/excel', [CostController::class, 'costsByDayExcel']); // Daily costs Excel
  // Route::
  Route::get('/reports/lab-test-statistics', [ReportController::class, 'labTestStatistics']);
  Route::get('/reports/test-result-statistics', [ReportController::class, 'testResultStatistics']);
  Route::get('/reports/lab-general', [ReportController::class, 'labGeneral']);
  Route::get('/reports/lab-general/pdf', [ReportController::class, 'generateLabGeneralReportPdf']);

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
  Route::get('/requested-services/{requested_service}/deposits', [RequestedServiceDepositController::class, 'indexForRequestedService']); // list deposits for one service
  // POST to '/requested-services/{requested_service}/deposits' is already handled by RequestedServiceDepositController@store

  // Direct CRUD on the deposit records themselves by their own ID
  Route::put('/requested-service-deposits/{requestedServiceDeposit}', [RequestedServiceDepositController::class, 'update']);
  Route::delete('/requested-service-deposits/{requestedServiceDeposit}', [RequestedServiceDepositController::class, 'destroy']);

  // Listing deleted/voided deposits
  Route::get('/requested-service-deposit-deletions', [\App\Http\Controllers\Api\RequestedServiceDepositDeletionController::class, 'index']);

  // Jobs Management Routes
  Route::prefix('jobs-management')->group(function () {
    Route::get('/failed', [\App\Http\Controllers\Api\JobsManagementController::class, 'getFailedJobs']);
    Route::get('/pending', [\App\Http\Controllers\Api\JobsManagementController::class, 'getPendingJobs']);
    Route::get('/statistics', [\App\Http\Controllers\Api\JobsManagementController::class, 'getStatistics']);
    Route::get('/queues', [\App\Http\Controllers\Api\JobsManagementController::class, 'getQueues']);
    Route::post('/retry/{id}', [\App\Http\Controllers\Api\JobsManagementController::class, 'retryJob']);
    Route::post('/retry-all', [\App\Http\Controllers\Api\JobsManagementController::class, 'retryAllJobs']);
    // Failed jobs deletion
    Route::delete('/failed/{id}', [\App\Http\Controllers\Api\JobsManagementController::class, 'deleteFailedJob']);
    Route::delete('/failed', [\App\Http\Controllers\Api\JobsManagementController::class, 'deleteAllFailedJobs']);
    Route::post('/failed/delete-by-queue', [\App\Http\Controllers\Api\JobsManagementController::class, 'deleteFailedJobsByQueue']);
    Route::post('/failed/delete-by-ids', [\App\Http\Controllers\Api\JobsManagementController::class, 'deleteFailedJobsByIds']);
    // Pending jobs deletion
    Route::delete('/pending/{id}', [\App\Http\Controllers\Api\JobsManagementController::class, 'deletePendingJob']);
    Route::delete('/pending', [\App\Http\Controllers\Api\JobsManagementController::class, 'deleteAllPendingJobs']);
    Route::post('/pending/delete-by-queue', [\App\Http\Controllers\Api\JobsManagementController::class, 'deletePendingJobsByQueue']);
    Route::post('/pending/delete-by-ids', [\App\Http\Controllers\Api\JobsManagementController::class, 'deletePendingJobsByIds']);
  });

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
  Route::get('/excel/reclaim', [ExcelController::class, 'reclaim']);
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
  Route::get('/patients/{patient}/visit-history', [PatientController::class, 'visitHistory']);

  // NEW: Route for patient lab history by phone number
  Route::get('/patients/{patient}/lab-history', [PatientController::class, 'getLabHistory']);

  // NEW: Route for creating clinic visit from history
  Route::post('/doctor-visits/{doctorVisit}/create-clinic-visit-from-history', [PatientController::class, 'createClinicVisitFromHistory']);

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
  Route::post('/doctor-visits/{doctorVisit}/reassign-shift', [DoctorVisitController::class, 'reassignToShift']);

  // NEW: Route for creating a new visit for a patient by copying their data to a new shift
  Route::post('/patients/{sourcePatient}/copy-to-new-visit', [DoctorVisitController::class, 'createCopiedVisitForNewShift']);
  Route::post('/labrequests/{labrequest}/set-default-results', [LabRequestController::class, 'setDefaultResults']);
  Route::post('/labrequests/{labrequest}/populate-cbc-from-sysmex', [LabRequestController::class, 'populateCbcResultsFromSysmex']);
  Route::post('/labrequests/{labrequest}/add-organism', [LabRequestController::class, 'addOrganism']);
  Route::get('/labrequests/{labrequest}/organisms', [LabRequestController::class, 'getOrganisms']);
  Route::patch('/requested-organisms/{organism}', [LabRequestController::class, 'updateOrganism']);
  Route::delete('/requested-organisms/{organism}', [LabRequestController::class, 'deleteOrganism']);
  Route::patch('/patients/{patient}/toggle-result-lock', [PatientController::class, 'toggleResultLock']);
  Route::patch('/patients/{patient}/authenticate-results', [PatientController::class, 'authenticateResults']);
  Route::get('/patients/{patient}/result-url', [PatientController::class, 'getResultUrl']);
  Route::post('/patients/{patient}/upload-to-firebase', [PatientController::class, 'uploadToFirebase']);
  Route::patch('/patients/{patient}/toggle-authentication', [PatientController::class, 'toggleAuthentication']);

  /*
    |--------------------------------------------------------------------------
    | WhatsApp Communication Routes
    |--------------------------------------------------------------------------
    */
  Route::get('/reports/monthly-service-deposits-income/pdf', [ReportController::class, 'exportMonthlyServiceDepositsIncomePdf']);
  Route::get('/reports/monthly-service-deposits-income/excel', [ExcelController::class, 'exportMonthlyServiceDepositsIncomeExcel']);
  Route::put('/doctor-shifts/{doctorShift}/update-proofing-flags', [DoctorShiftController::class, 'updateProofingFlags']);

  Route::get('/analysis/summary', [AnalysisController::class, 'getAnalysisData']);

  Route::get('/reports/doctor-reclaims/pdf', [ReportController::class, 'generateDoctorReclaimsPdf']);
  // "The route api/reports/service-cost-breakdown could not be found."
  Route::get('/reports/service-cost-breakdown', [ReportController::class, 'serviceCostBreakdownReport']);
  // {message: "The route api/reports/service-cost-breakdown/pdf could not be found.",…}
  Route::get('/reports/service-cost-breakdown/pdf', [ReportController::class, 'exportServiceCostBreakdownPdf']);
  Route::get('/reports/doctor-statistics', [ReportController::class, 'doctorStatisticsReport']);
  Route::get('/reports/doctor-statistics/pdf', [ReportController::class, 'exportDoctorStatisticsPdf']);
  Route::get('/reports/company-performance', [ReportController::class, 'companyPerformanceReport']);
  Route::get('/reports/company-performance/pdf', [ReportController::class, 'exportCompanyPerformancePdf']);
  Route::get('/reports/doctor-company-entitlement', [ReportController::class, 'doctorCompanyEntitlementReport']);
  Route::get('/reports/doctor-company-entitlement/pdf', [ReportController::class, 'exportDoctorCompanyEntitlementPdf']);
  // Companies PDF
  Route::get('/reports/companies/pdf', [CompanyReportController::class, 'exportAllCompaniesPdf']);
  Route::get('/reports/yearly-income-comparison', [ReportController::class, 'yearlyIncomeComparisonByMonth']);
  Route::get('/reports/yearly-patient-frequency', [ReportController::class, 'yearlyPatientFrequencyByMonth']);
  // Route::get('/reports/yearly-patient-frequency/pdf', [ReportController::class, 'exportYearlyPatientFrequencyPdf']); // For future PDF
  /*
    |--------------------------------------------------------------------------
    | ATTENDANCE MODULE - CONFIGURATION ROUTES
    |--------------------------------------------------------------------------
    */

  // 1. Global Attendance Settings
  // Fetches the single global attendance settings record
  Route::get('/attendance-settings', [AttendanceSettingController::class, 'show']);
  // Updates the single global attendance settings record
  Route::put('/attendance-settings', [AttendanceSettingController::class, 'update']);

  // 2. Shift Definitions (e.g., Morning, Evening Shift timings)
  // Provides a simplified list, often for dropdowns (e.g., only active shifts)
  Route::get('/shifts-definitions/list', [ShiftDefinitionController::class, 'indexList']);
  // Standard CRUD for shift definitions


  // 3. Holiday Management
  // Provides a simplified list, often for calendar highlighting or dropdowns
  Route::get('/holidays/list', [HolidayController::class, 'indexList']);
  // Standard CRUD for holidays


  // 4. User-Specific Attendance Settings
  // (Integrated into existing UserController or a dedicated UserAttendanceSettingController)

  // Endpoint to update a user's supervisor status and their default shift assignments
  Route::put('/users/{user}/attendance-settings', [UserController::class, 'updateAttendanceSettings']);
  // Endpoint to get a user's currently assigned default shifts
  Route::get('/users/{user}/default-shifts', [UserController::class, 'getUserDefaultShifts']);


  /*
    |--------------------------------------------------------------------------
    | ATTENDANCE MODULE - RECORDING & VIEWING (from previous steps, for context)
    |--------------------------------------------------------------------------
    */
  Route::get('/attendances/monthly-sheet', [AttendanceController::class, 'getMonthlySheet']);
  Route::post('/attendances/record', [AttendanceController::class, 'recordOrUpdateAttendance']);
  Route::delete('/attendances/{attendance}', [AttendanceController::class, 'destroyAttendance']);


  /*
    |--------------------------------------------------------------------------
    | ATTENDANCE MODULE - REPORTING (from previous steps, for context)
    |--------------------------------------------------------------------------
    */
  Route::get('/attendance/reports/monthly-employee-summary', [AttendanceReportController::class, 'monthlyEmployeeSummary']);
  Route::get('/attendance/reports/daily-detail', [AttendanceReportController::class, 'dailyAttendanceDetail']);
  Route::get('/attendance/reports/payroll', [AttendanceReportController::class, 'payrollAttendanceReport']);
  Route::prefix('attendance/reports')->group(function () {
    Route::get('/monthly-summary', [ReportController::class, 'getMonthlyAttendanceSummary']);
    Route::get('/monthly-summary/pdf', [ReportController::class, 'generateMonthlyAttendancePdf']);
  });
  /*
    |--------------------------------------------------------------------------
    | Attendance Configuration Routes
    |--------------------------------------------------------------------------
    */
  Route::prefix('attendance-config')->group(function () {
    // Attendance Global Settings
    Route::get('/settings', [AttendanceSettingController::class, 'show']);
    Route::post('/settings', [AttendanceSettingController::class, 'storeOrUpdate']); // Use POST for create or update

    // Shift Definitions
    Route::get('/shift-definitions/list', [ShiftDefinitionController::class, 'indexList']); // For dropdowns
    Route::apiResource('/shift-definitions', ShiftDefinitionController::class);

    // Holidays
    Route::apiResource('/holidays', HolidayController::class);

    // User Default Shift Assignments (assuming these are part of UserController)
    Route::get('/users/{user}/default-shifts', [UserController::class, 'getUserDefaultShifts']);
    Route::put('/users/{user}/default-shifts', [UserController::class, 'updateUserDefaultShifts']);
  });

  /*
  |--------------------------------------------------------------------------
  | Attendance Recording & Reporting Routes (To be created)
  |--------------------------------------------------------------------------
  */
  Route::prefix('attendance')->group(function () {
    Route::get('/daily-sheet', [AttendanceController::class, 'getDailySheet']);
    Route::post('/record', [AttendanceController::class, 'recordAttendance']);
    Route::put('/record/{attendance}', [AttendanceController::class, 'updateAttendanceStatus']); // For changing status later
    // Add more routes for reports later
    // Route::get('/reports/monthly', [AttendanceController::class, 'getMonthlyReport']);
  });
  Route::post('/visits/{visit}/send-whatsapp-report', [ReportController::class, 'sendVisitReportViaWhatsApp']);
  Route::get('/search/patient-visits', [PatientController::class, 'searchPatientVisitsForAutocomplete']);
  Route::post('/patients/{doctorVisit}/create-lab-visit', [PatientController::class, 'createLabVisitForExistingPatient']);


  Route::post('/patients/store-from-lab', [PatientController::class, 'storeFromLab']);
  Route::post('/patients/save-from-online-lab', [PatientController::class, 'saveFromOnlineLab']);

  Route::get('/visits/{doctorvisit}/lab-barcode/pdf', [ReportController::class, 'printBarcodeWithViewer']);
  // Devices
  Route::get('/devices-list', [DeviceController::class, 'indexList']);
  Route::post('/devices', [DeviceController::class, 'store']); // If you add device creation dialog
  // NEW route for lab reception queue
  Route::get('/lab/reception-queue', [LabRequestController::class, 'getNewlyRegisteredLabPendingQueue']);
  // Device Specific Normal Ranges for Child Tests
  Route::get('/child-tests/{child_test}/devices/{device}/normal-range', [DeviceChildTestNormalRangeController::class, 'getNormalRange']);
  Route::post('/child-tests/{child_test}/devices/{device}/normal-range', [DeviceChildTestNormalRangeController::class, 'storeOrUpdateNormalRange']);
  Route::get('/visits/{visit}/lab-thermal-receipt/pdf', [LabRequestController::class, 'generateLabThermalReceiptPdf']);
  Route::get('/visits/{visit}/lab-invoice/pdf', [ReportController::class, 'generateLabInvoicePdf']);
  Route::get('/visits/{visit}/lab-sample-labels/pdf', [ReportController::class, 'generateLabSampleLabelPdf']);
  Route::post('/visits/{doctorvisit}/print-barcode', [PatientController::class, 'printBarcode']); // For Zebra printer barcode printing
  Route::get('/visits/{doctorvisit}/lab-report/pdf', [ReportController::class, 'result']); // For "View Report Preview"
  Route::post('/visits/{doctorvisit}/lab-report/mark-printed', [ReportController::class, 'markReportPrinted']); // Mark report as printed
  Route::get('service-groups-list', [ServiceGroupController::class, 'indexList']); // For dropdowns
  Route::apiResource('service-groups', ServiceGroupController::class);
  Route::prefix('sample-collection')->group(function () {
    Route::get('/queue', [SampleCollectionController::class, 'getQueue']);
    Route::patch('/labrequests/{labrequest}/mark-collected', [SampleCollectionController::class, 'markSampleCollected']);
    Route::post('/visits/{visit}/mark-all-collected', [SampleCollectionController::class, 'markAllSamplesCollectedForVisit']);
    Route::patch('/labrequests/{labrequest}/generate-sample-id', [SampleCollectionController::class, 'generateSampleIdForRequest']);
    Route::post('/visits/{visit}/mark-patient-collected', [SampleCollectionController::class, 'markPatientSampleCollectedForVisit']);
  });
  Route::get('specialists-list', [SpecialistController::class, 'indexList']);
  Route::apiResource('specialists', SpecialistController::class);

  // Sub Specialists routes
  Route::get('specialists/{specialist}/sub-specialists', [SubSpecialistController::class, 'index']);
  Route::post('specialists/{specialist}/sub-specialists', [SubSpecialistController::class, 'store']);
  Route::put('specialists/{specialist}/sub-specialists/{subSpecialist}', [SubSpecialistController::class, 'update']);
  Route::delete('specialists/{specialist}/sub-specialists/{subSpecialist}', [SubSpecialistController::class, 'destroy']);
  Route::get('/reports/services-list/excel', [ExcelController::class, 'exportServicesListToExcel']);
  // NEW route for the services with cost details export
  Route::get('/reports/services-with-costs/excel', [ExcelController::class, 'exportServicesWithCostsToExcel']);
  Route::post('/services/batch-update-prices', [ServiceController::class, 'batchUpdatePrices']);
  // NEW route for the PDF services list export
  Route::get('/reports/services-list/pdf', [ReportController::class, 'exportServicesListToPdf']);
  Route::post('/services/activate-all', [ServiceController::class, 'activateAll']);
  Route::get('/user/current-shift-lab-income-summary', [UserController::class, 'getCurrentUserLabIncomeSummary']);
  Route::get('/cash-denominations', [CashDenominationController::class, 'getDenominationsForShift']);
  Route::post('/cash-denominations', [CashDenominationController::class, 'saveDenominationCounts']);

  /*
    |--------------------------------------------------------------------------
    | HL7 Messages Routes
    |--------------------------------------------------------------------------
    */
  Route::get('/hl7-messages', [HL7MessageController::class, 'index']);
  Route::get('/hl7-messages/{id}', [HL7MessageController::class, 'show']);
  Route::delete('/hl7-messages/{id}', [HL7MessageController::class, 'destroy']);
  Route::get('/hl7-messages/recent', [HL7MessageController::class, 'recent']);
  Route::get('/hl7-messages/devices', [HL7MessageController::class, 'devices']);
  Route::get('/hl7-messages/statistics', [HL7MessageController::class, 'statistics']);

  // HL7 Message Insert Routes
  Route::post('/hl7-messages/insert', [HL7MessageInsertController::class, 'store']);
  Route::post('/hl7-messages/insert-batch', [HL7MessageInsertController::class, 'storeBatch']);


  /*
    |--------------------------------------------------------------------------
    | Ultramsg WhatsApp API Routes
    |--------------------------------------------------------------------------
    */
  Route::prefix('ultramsg')->group(function () {
    Route::post('/send-text', [\App\Http\Controllers\UltramsgController::class, 'sendTextMessage']);
    Route::post('/send-document', [\App\Http\Controllers\UltramsgController::class, 'sendDocument']);
    Route::post('/send-document-file', [\App\Http\Controllers\UltramsgController::class, 'sendDocumentFromFile']);
    Route::post('/send-document-url', [\App\Http\Controllers\UltramsgController::class, 'sendDocumentFromUrl']);
    Route::get('/instance-status', [\App\Http\Controllers\UltramsgController::class, 'getInstanceStatus']);
    Route::get('/configured', [\App\Http\Controllers\UltramsgController::class, 'isConfigured']);
  });

  /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API Routes
    |--------------------------------------------------------------------------
    */
  Route::prefix('whatsapp-cloud')->group(function () {
    Route::post('/send-text', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'sendTextMessage']);
    Route::post('/send-template', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'sendTemplateMessage']);
    Route::post('/send-document', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'sendDocument']);
    Route::post('/send-image', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'sendImage']);
    Route::post('/send-audio', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'sendAudio']);
    Route::post('/send-video', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'sendVideo']);
    Route::post('/send-location', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'sendLocation']);
    Route::get('/phone-numbers', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'getPhoneNumbers']);
    Route::get('/templates', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'getTemplates']);
    Route::get('/configured', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'isConfigured']);
  });
});

// Ultramsg routes with custom credentials (no auth required since credentials are in request)
Route::post('/ultramsg/send-text-message-with-credentials', [\App\Http\Controllers\UltramsgController::class, 'sendTextMessageWithCredentials']);
Route::post('/ultramsg/send-document-with-credentials', [\App\Http\Controllers\UltramsgController::class, 'sendDocumentWithCredentials']);

// Image proxy for cross-origin images -> base64
Route::get('/image-proxy/base64', [ImageProxyController::class, 'fetchBase64']);

// Add missing routes for LabRequestsColumn functionality
Route::patch('/labrequests/{labrequest}/discount', [LabRequestController::class, 'updateDiscount'])->middleware('auth:sanctum');
Route::post('/doctor-visits/{visit}/pay-all-lab-requests', [LabRequestController::class, 'payAllLabRequests'])->middleware('auth:sanctum');
Route::post('/lab-requests/{labrequest}/cancel-payment', [LabRequestController::class, 'cancelPayment'])->middleware('auth:sanctum');
Route::patch('/labrequests/{labrequest}/toggle-bankak', [LabRequestController::class, 'toggleBankak'])->middleware('auth:sanctum');
Route::patch('/doctor-visits/{visit}/update-all-lab-requests-bankak', [LabRequestController::class, 'updateAllLabRequestsBankak'])->middleware('auth:sanctum');
Route::get('/lab-requests/visit/{visit}/thermal-receipt-pdf', [LabRequestController::class, 'generateLabThermalReceiptPdf']);
Route::get('/visits/{visit}/thermal-receipt/pdf', [ReportController::class, 'generateThermalServiceReceipt']);
Route::get('/visits/{visit}/requested-services/{requestedService}/thermal-receipt/pdf', [ReportController::class, 'generateSingleServiceThermalReceipt']);
Route::post('/reports/cash-reconciliation/pdf', [ReportController::class, 'generateCashReconciliationPdf'])->middleware('auth:sanctum');

// Firestore update endpoints
Route::post('/firestore/update-document', [App\Http\Controllers\Api\FirestoreController::class, 'updateFirestoreDocument'])->middleware('auth:sanctum');
Route::post('/firestore/update-patient-pdf', [App\Http\Controllers\Api\FirestoreController::class, 'updatePatientPdf']);

// Bankak Images endpoints
Route::middleware('auth:sanctum')->group(function () {
  Route::get('/bankak-images', [BankakImageController::class, 'index']);
  Route::get('/bankak-images/dates', [BankakImageController::class, 'getAvailableDates']);
  Route::get('/bankak-images/stats', [BankakImageController::class, 'getStats']);
});

//send from firebase storage using visit_id and settings.storage_name
Route::post('/ultramsg/send-document-from-firebase', [\App\Http\Controllers\UltramsgController::class, 'sendDocumentFromFirebase']);

// WhatsApp Cloud API Webhook endpoints (no CSRF protection needed)
Route::get('/whatsapp-cloud/webhook', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'verifyWebhook']);
Route::post('/whatsapp-cloud/webhook', [\App\Http\Controllers\Api\WhatsAppCloudApiController::class, 'webhook']);

// Webhook endpoints (no CSRF protection needed)
Route::get('/webhook', [WebHookController::class, 'webhook']);
Route::post('/webhook', [WebHookController::class, 'webhook']);
Route::post('populatePatientChemistryData/{doctorvisit}', [PatientController::class, 'populatePatientChemistryData']);
Route::post('populatePatientHormoneData/{doctorvisit}', [PatientController::class, 'populatePatientHormoneData']);


Route::post('/sendWhatsappDirectPdfReport', [PatientController::class, 'sendWhatsappDirectPdfReport']);
Route::post('/sendWhatsappDirectWithoutUpload', [PatientController::class, 'sendWhatsappDirectWithoutUpload']);
