<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\FinanceAccountController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ServiceGroupController;
use App\Http\Controllers\Api\SpecialistController;
use App\Http\Controllers\Api\UserController;
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

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('doctors-list', [DoctorController::class, 'indexList']);
    Route::get('companies-list', [CompanyController::class, 'indexList']);
    Route::get('specialists-list', [SpecialistController::class, 'indexList']);
    Route::get('finance-accounts-list', [FinanceAccountController::class, 'indexList']);
    Route::apiResource('specialists', SpecialistController::class)->middleware('auth:sanctum');

    // Full CRUD for Patients, Doctors, Companies (as you build them out)
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('doctors', DoctorController::class); // This will have the main index, store, show, etc. for doctors
    Route::apiResource('companies', CompanyController::class);
    Route::get('service-groups-list', [ServiceGroupController::class, 'indexList']);
    Route::post('service-groups', [ServiceGroupController::class, 'store']); // For quick add
    // If you need full CRUD for Service Groups outside of just quick-add:
    // Route::apiResource('service-groups', ServiceGroupController::class);
    Route::get('roles-list', [UserController::class, 'getRolesList']); // Route for fetching roles list
    Route::apiResource('users', UserController::class);
    Route::apiResource('services', ServiceController::class);
    // ... your other protected API routes
});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
    // For Dropdowns / Lists

});
