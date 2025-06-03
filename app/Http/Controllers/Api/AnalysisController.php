<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorShift;
use App\Models\DoctorVisit;
use App\Models\RequestedService;
use App\Models\Cost;
use App\Models\Service;
use App\Models\Doctor;
use App\Models\LabRequest;
use App\Models\RequestedServiceDeposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalysisController extends Controller
{
    public function __construct()
    {
        // Add permission: e.g., 'view analysis_dashboard'
        // $this->middleware('can:view_financial_analysis');
    }

    /**
     * Fetch all analysis data for a given period.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAnalysisData(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();
        $numberOfDays = $startDate->diffInDays($endDate) + 1;

        // 1. Total Income (from RequestedServiceDeposits and LabRequest payments)
        $totalServiceIncome = RequestedServiceDeposit::whereBetween('created_at', [$startDate, $endDate])->sum('amount');
        $totalLabIncome = LabRequest::whereBetween('created_at', [$startDate, $endDate])->where('is_paid', true)->sum('amount_paid');
        $totalIncome = (float)$totalServiceIncome + (float)$totalLabIncome;

        // 2. Total Number of Unique Doctors Present (worked at least one shift)
        $doctorsPresentCount = DoctorShift::whereBetween('start_time', [$startDate, $endDate])
            ->orWhere(function($query) use ($startDate, $endDate) { // Shifts that started before but ended within or after period
                $query->where('start_time', '<', $startDate)
                      ->where(function($q2) use ($startDate, $endDate){
                          $q2->whereNull('end_time') // Still open
                             ->orWhereBetween('end_time', [$startDate, $endDate]);
                      });
            })
            ->distinct('doctor_id')
            ->count('doctor_id');

        // 3. Average Daily Income
        $averageDailyIncome = $numberOfDays > 0 ? $totalIncome / $numberOfDays : 0;

        // 4. Average Daily Patient Frequency (number of visits per day)
        $totalVisitsCount = DoctorVisit::whereBetween('visit_date', [$startDate, $endDate])->count();
        $averagePatientFrequency = $numberOfDays > 0 ? $totalVisitsCount / $numberOfDays : 0;
        
        // 5. Total Costs
        $totalCosts = Cost::whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw('amount + amount_bankak'));


        // 6. Top 10 Services Requested (by count of requests)
        $topServices = RequestedService::with('service:id,name')
            ->select('service_id', DB::raw('SUM(count) as request_count')) // Sum count for each service
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('service_id')
            ->orderByDesc('request_count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'service_name' => $item->service?->name ?? 'Unknown Service',
                    'request_count' => (int) $item->request_count,
                ];
            });

        // 7. Doctor with Top Patient Frequency (most visits)
        $topDoctorByVisits = DoctorVisit::with('doctor:id,name')
            ->select('doctor_id', DB::raw('COUNT(id) as visit_count'))
            ->whereNotNull('doctor_id') // Ensure doctor is assigned
            ->whereBetween('visit_date', [$startDate, $endDate])
            ->groupBy('doctor_id')
            ->orderByDesc('visit_count')
            ->first();


        return response()->json([
            'data' => [
                'period' => [
                    'from' => $startDate->toDateString(),
                    'to' => $endDate->toDateString(),
                    'number_of_days' => $numberOfDays,
                ],
                'total_income' => round($totalIncome, 2),
                'doctors_present_count' => $doctorsPresentCount,
                'average_daily_income' => round($averageDailyIncome, 2),
                'average_patient_frequency' => round($averagePatientFrequency, 2),
                'total_costs' => round((float)$totalCosts, 2),
                'top_services' => $topServices,
                'most_frequent_doctor' => $topDoctorByVisits ? [
                    'doctor_name' => $topDoctorByVisits->doctor?->name ?? 'Unknown Doctor',
                    'visit_count' => (int) $topDoctorByVisits->visit_count,
                ] : null,
            ]
        ]);
    }
}