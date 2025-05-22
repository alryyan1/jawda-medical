<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClinicWorkspaceController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyServiceController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\DoctorShiftController;
use App\Http\Controllers\Api\DoctorVisitController;
use App\Http\Controllers\Api\FinanceAccountController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RequestedServiceDepositController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceGroupController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SpecialistController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VisitServiceController;
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

    // Settings
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::post('/settings', [SettingsController::class, 'update']);


    Route::get('/reports/service-statistics', [ReportController::class, 'serviceStatistics']);

});
