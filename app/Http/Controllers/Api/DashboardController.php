<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Cost;
use App\Models\DoctorShift;
use App\Models\DoctorVisit;
use App\Models\LabRequest;
use App\Models\RequestedService;
use App\Models\RequestedServiceDeposit;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    public function getSummary(Request $request)
    {
        // Permission check: e.g., can('view dashboard_summary')
        // if (!Auth::user()->can('view dashboard_summary')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'date' => 'nullable|date_format:Y-m-d', // For daily summary if no shift
        ]);

        $shiftId = $request->input('shift_id');
        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();

        $targetShift = null;
        if ($shiftId) {
            $targetShift = Shift::find($shiftId);
        } elseif (!$shiftId && !$request->filled('date')) { // No shift, no date, get current open shift
            $targetShift = Shift::open()->latest('created_at')->first();
        }


        // --- Patients Today/In Shift ---
        // Counts distinct patients who had a DoctorVisit during the target period
        $patientsTodayQuery = DoctorVisit::query();
        if ($targetShift) {
            $patientsTodayQuery->where('shift_id', $targetShift->id);
        } else { // Date based
            $patientsTodayQuery->whereDate('visit_date', $date);
        }
        $patientsTodayCount = $patientsTodayQuery->distinct('patient_id')->count('patient_id');


        // --- Doctors on Shift ---
        // Counts distinct doctors who have an active DoctorShift record for the target period
        $doctorsOnShiftQuery = DoctorShift::query();
        if ($targetShift) {
            // Active during any part of the targetShift's duration
            // This assumes DoctorShift has start_time and optionally end_time
            // And general Shift also has start/end (created_at / closed_at)
            $doctorsOnShiftQuery->where('shift_id', $targetShift->id) // Doctor shifts linked to this general shift
                ->where('status', true); // Or more complex overlap logic
        } else { // Date based - doctors active at any point today
            $doctorsOnShiftQuery->where(function ($q) use ($date) {
                $q->whereDate('start_time', $date)
                    ->orWhere(function ($q2) use ($date) { // Started before today but not ended or ended today
                        $q2->where('start_time', '<', $date->copy()->startOfDay())
                            ->where(function ($q3) use ($date) {
                                $q3->whereNull('end_time')
                                    ->orWhereDate('end_time', $date);
                            });
                    });
            })->where('status', true); // And are currently active
        }
        $doctorsOnShiftCount = $doctorsOnShiftQuery->distinct('doctor_id')->count('doctor_id');


        // --- Revenue Today/In Shift ---
        $totalRevenue = 0;
        $serviceRevenue = 0;
        $labRevenue = 0;

        // Service Revenue
        $serviceRevenueQuery = RequestedService::query();
        
        if ($targetShift) {
            $serviceRevenueQuery->whereHas('doctorVisit', function($query) use ($targetShift) {
                $query->where('shift_id', $targetShift->id);
            });
        } else {
            $serviceRevenueQuery->whereDate('requested_services.created_at', $date);
        }

        $serviceRevenue = $serviceRevenueQuery
            ->join('doctorvisits', 'requested_services.doctorvisits_id', '=', 'doctorvisits.id')
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->selectRaw('SUM(
                (requested_services.price * requested_services.count) 
                - requested_services.discount 
                - CASE 
                    WHEN patients.company_id IS NOT NULL 
                    THEN requested_services.endurance 
                    ELSE 0 
                END
            ) as total_revenue')
            ->value('total_revenue') ?? 0;

        // Lab Revenue
        $labRevenueQuery = LabRequest::query();
        
        if ($targetShift) {
            $labRevenueQuery->whereHas('doctorVisit', function($query) use ($targetShift) {
                $query->where('shift_id', $targetShift->id);
            });
        } else {
            $labRevenueQuery->whereDate('labrequests.created_at', $date);
        }

        $labRevenue = $labRevenueQuery
            ->join('doctorvisits', 'labrequests.doctor_visit_id', '=', 'doctorvisits.id')
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->selectRaw('SUM(
                labrequests.price 
                - (labrequests.price * labrequests.discount_per / 100)
                - CASE 
                    WHEN patients.company_id IS NOT NULL 
                    THEN labrequests.endurance 
                    ELSE 0 
                END
            ) as total_revenue')
            ->value('total_revenue') ?? 0;
            
        $totalRevenue = (float) $serviceRevenue + (float) $labRevenue;


        // --- Appointments Today ---
        // Counts appointments scheduled for the target date
        $appointmentsQuery = Appointment::query(); // Assuming Appointment model
        if ($targetShift) {
            // If appointments are linked to general shifts or fall within shift times
            // This logic is complex and depends on your Appointment <-> Shift relation
            // For simplicity, let's use visit_date of the shift
            if ($targetShift->created_at) {
                $appointmentsQuery->whereDate('appointment_date', Carbon::parse($targetShift->created_at));
            }
        } else {
            $appointmentsQuery->whereDate('appointment_date', $date);
        }
        // $appointmentsQuery->whereNotIn('status', ['cancelled', 'no_show']); // Count only relevant appointments
        $appointmentsTodayCount = $appointmentsQuery->count();


        return response()->json([
            'data' => [
                'patientsToday' => $patientsTodayCount,
                'doctorsOnShift' => $doctorsOnShiftCount,
                'revenueToday' => round($totalRevenue, 2),
                'appointmentsToday' => $appointmentsTodayCount,
            ]
        ]);
    }

    public function getFinancialSummary(Request $request)
    {
        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'date' => 'nullable|date_format:Y-m-d',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
        ]);

        $shiftId = $request->input('shift_id');
        $date = $request->filled('date') ? Carbon::parse($request->date) : null;
        $fromDate = $request->filled('from_date') ? Carbon::parse($request->input('from_date')) : null;
        $toDate = $request->filled('to_date') ? Carbon::parse($request->input('to_date')) : null;

        $targetShift = null;
        if ($shiftId) {
            $targetShift = Shift::find($shiftId);
        } elseif (!$shiftId && !$request->filled('date') && !$request->filled('from_date') && !$request->filled('to_date')) {
            $targetShift = Shift::open()->latest('created_at')->first();
        }
        
        // If from_date or to_date is provided, ensure both are set (default to today if only one is provided)
        if ($request->filled('from_date') || $request->filled('to_date')) {
            $fromDate = $fromDate ?? Carbon::today();
            $toDate = $toDate ?? Carbon::today();
            // Ensure targetShift is null when using date range
            $targetShift = null;
        }

        // Lab Revenue (cash and bank)
        $labRevenueBaseQuery = LabRequest::query()
            ->join('doctorvisits', 'labrequests.doctor_visit_id', '=', 'doctorvisits.id')
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id');
            
        if ($targetShift) {
            $labRevenueBaseQuery->where('doctorvisits.shift_id', $targetShift->id);
        } elseif ($date) {
            $labRevenueBaseQuery->whereDate('labrequests.created_at', $date);
        } elseif ($fromDate && $toDate) {
            $labRevenueBaseQuery->whereBetween('labrequests.created_at', [
                $fromDate->copy()->startOfDay(),
                $toDate->copy()->endOfDay()
            ]);
        }

        $labRevenueCash = (float) (clone $labRevenueBaseQuery)
            ->where('labrequests.is_bankak', 0)
            ->selectRaw('SUM(
                labrequests.price 
                - (labrequests.price * labrequests.discount_per / 100)
                - CASE 
                    WHEN patients.company_id IS NOT NULL 
                    THEN labrequests.endurance 
                    ELSE 0 
                END
            ) as total_revenue')
            ->value('total_revenue') ?? 0;

        $labRevenueBank = (float) (clone $labRevenueBaseQuery)
            ->where('labrequests.is_bankak', 1)
            ->selectRaw('SUM(
                labrequests.price 
                - (labrequests.price * labrequests.discount_per / 100)
                - CASE 
                    WHEN patients.company_id IS NOT NULL 
                    THEN labrequests.endurance 
                    ELSE 0 
                END
            ) as total_revenue')
            ->value('total_revenue') ?? 0;

        // Services Revenue from deposits (cash and bank)
        $depositsQuery = RequestedServiceDeposit::query();
        if ($targetShift) {
            $depositsQuery->where('shift_id', $targetShift->id);
        } elseif ($date) {
            $depositsQuery->whereDate('created_at', $date);
        } elseif ($fromDate && $toDate) {
            $depositsQuery->whereBetween('created_at', [
                $fromDate->copy()->startOfDay(),
                $toDate->copy()->endOfDay()
            ]);
        }

        $servicesRevenueCash = (float) (clone $depositsQuery)
            ->where('is_bank', false)
            ->sum('amount') ?? 0;

        $servicesRevenueBank = (float) (clone $depositsQuery)
            ->where('is_bank', true)
            ->sum('amount') ?? 0;

        // Costs (cash and bank)
        $costsQuery = Cost::query();
        if ($targetShift) {
            $costsQuery->where('shift_id', $targetShift->id);
        } elseif ($date) {
            $costsQuery->whereDate('created_at', $date);
        } elseif ($fromDate && $toDate) {
            $costsQuery->whereBetween('created_at', [
                $fromDate->copy()->startOfDay(),
                $toDate->copy()->endOfDay()
            ]);
        }

        $costsCash = (float) (clone $costsQuery)->sum('amount') ?? 0;
        $costsBank = (float) (clone $costsQuery)->sum('amount_bankak') ?? 0;

        // Calculate totals
        $totalLabRevenue = $labRevenueCash + $labRevenueBank;
        $totalServicesRevenue = $servicesRevenueCash + $servicesRevenueBank;
        $totalCosts = $costsCash + $costsBank;
        $net = ($totalLabRevenue + $totalServicesRevenue) - $totalCosts;

        // Patients Count
        $patientsCountQuery = DoctorVisit::query();
        if ($targetShift) {
            $patientsCountQuery->where('shift_id', $targetShift->id);
        } elseif ($date) {
            $patientsCountQuery->whereDate('visit_date', $date);
        } elseif ($fromDate && $toDate) {
            $patientsCountQuery->whereBetween('visit_date', [
                $fromDate->copy()->startOfDay(),
                $toDate->copy()->endOfDay()
            ]);
        }
        $patientsCount = $patientsCountQuery->distinct('patient_id')->count('patient_id');

        return response()->json([
            'data' => [
                'lab_revenue' => [
                    'cash' => round($labRevenueCash, 2),
                    'bank' => round($labRevenueBank, 2),
                    'total' => round($totalLabRevenue, 2),
                ],
                'services_revenue' => [
                    'cash' => round($servicesRevenueCash, 2),
                    'bank' => round($servicesRevenueBank, 2),
                    'total' => round($totalServicesRevenue, 2),
                ],
                'costs' => [
                    'cash' => round($costsCash, 2),
                    'bank' => round($costsBank, 2),
                    'total' => round($totalCosts, 2),
                ],
                'net' => round($net, 2),
                'patients_count' => $patientsCount,
            ]
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
