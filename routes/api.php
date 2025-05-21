<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClinicWorkspaceController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyServiceController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorShiftController;
use App\Http\Controllers\Api\DoctorVisitController;
use App\Http\Controllers\Api\FinanceAccountController;
use App\Http\Controllers\Api\PatientController;
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

// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Authenticated user info and logout
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // User & Role management
    Route::get('roles-list', [UserController::class, 'getRolesList']);
    Route::apiResource('users', UserController::class);
    Route::get('permissions-list', [RoleController::class, 'getPermissionsList']);
    Route::apiResource('roles', RoleController::class);

    // Specialist management
    Route::get('specialists-list', [SpecialistController::class, 'indexList']);
    Route::apiResource('specialists', SpecialistController::class);

    // Doctor management
    Route::get('doctors-list', [DoctorController::class, 'indexList']);
    Route::apiResource('doctors', DoctorController::class);

    // Doctor Shift management
    Route::get('/active-doctor-shifts', [DoctorShiftController::class, 'getActiveShifts']);
    Route::post('/doctor-shifts/start', [DoctorShiftController::class, 'startShift']);
    Route::put('/doctor-shifts/{doctorShift}/end', [DoctorShiftController::class, 'endShift']);
    Route::apiResource('doctor-shifts', DoctorShiftController::class)->except(['store', 'update']);

    // Patient management
    Route::apiResource('patients', PatientController::class);

    // Company management
    Route::get('companies-list', [CompanyController::class, 'indexList']);
    Route::apiResource('companies', CompanyController::class);

    // Company Service Contracts
    Route::get('companies/{company}/contracted-services', [CompanyServiceController::class, 'index'])->name('companies.contracts.index');
    Route::get('companies/{company}/available-services', [CompanyServiceController::class, 'availableServices'])->name('companies.contracts.available');
    Route::post('companies/{company}/contracted-services', [CompanyServiceController::class, 'store'])->name('companies.contracts.store');
    Route::put('companies/{company}/contracted-services/{service}', [CompanyServiceController::class, 'update'])->name('companies.contracts.update');
    Route::delete('companies/{company}/contracted-services/{service}', [CompanyServiceController::class, 'destroy'])->name('companies.contracts.destroy');
    Route::get('/clinic-active-patients', [ClinicWorkspaceController::class, 'getActivePatients'])->middleware('auth:sanctum');

    // Finance Account management
    Route::get('finance-accounts-list', [FinanceAccountController::class, 'indexList']);

    // Service Group management
    Route::get('service-groups-list', [ServiceGroupController::class, 'indexList']);
    Route::post('service-groups', [ServiceGroupController::class, 'store']);
    // Route::apiResource('service-groups', ServiceGroupController::class); // Uncomment for full CRUD

    // Service management
    Route::apiResource('services', ServiceController::class);

    // Visit Services
    Route::get('/visits/{visit}/available-services', [VisitServiceController::class, 'getAvailableServices']);
    Route::post('/visits/{visit}/request-services', [VisitServiceController::class, 'addRequestedServices']);
    Route::get('/visits/{visit}/requested-services', [VisitServiceController::class, 'getRequestedServices']);
    Route::delete('/visits/{visit}/requested-services/{requestedService}', [VisitServiceController::class, 'removeRequestedService']);


    Route::put('/doctor-visits/{doctorVisit}/status', [DoctorVisitController::class, 'updateStatus']);
    Route::apiResource('doctor-visits', DoctorVisitController::class);

    // Routes for services related to a visit are already in VisitServiceController:
    // Route::get('/visits/{visit}/available-services', [VisitServiceController::class, 'getAvailableServices']);
    // Route::post('/visits/{visit}/request-services', [VisitServiceController::class, 'addRequestedServices']);
    // Route::get('/visits/{visit}/requested-services', [VisitServiceController::class, 'getRequestedServices']);
    // Route::delete('/visits/{visit}/requested-services/{requestedService}', [VisitServiceController::class, 'removeRequestedService']);
    Route::get('/shifts/current-open', [ShiftController::class, 'getCurrentOpenShift']);
    Route::post('/shifts/open', [ShiftController::class, 'openShift']);
    Route::put('/shifts/{shift}/close', [ShiftController::class, 'closeShift']);
    Route::put('/shifts/{shift}/financials', [ShiftController::class, 'updateFinancials']);
    Route::get('/doctors-with-shift-status', [DoctorShiftController::class, 'getDoctorsWithShiftStatus'])->middleware('auth:sanctum');

    // Standard resource routes if you need general listing/viewing of shifts
    Route::apiResource('shifts', ShiftController::class)->except(['store', 'update', 'destroy']);
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::post('/settings', [SettingsController::class, 'update']); // Use POST for updates if sending FormData with files

    // ... your other protected API routes
});
Route::get('/service-groups-with-services', [ServiceGroupController::class, 'getGroupsWithServices'])->middleware('auth:sanctum');
Route::post('/requested-services/{requestedService}/deposits', [RequestedServiceDepositController::class, 'store'])->middleware('auth:sanctum');
Route::put('/requested-services/{requestedService}', [VisitServiceController::class, 'updateRequestedService']);
// For Dropdowns / Lists (duplicate user info route for convenience)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();
    $user->load('roles.permissions');
    return $user;
});
