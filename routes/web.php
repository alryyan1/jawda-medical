<?php

use App\Http\Controllers\Api\ReportController;
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

Route::get('/reports/doctor-shifts/pdf', [ReportController::class, 'doctorShiftsReportPdf']);
Route::get('/', function () {
    return view('welcome');
});
