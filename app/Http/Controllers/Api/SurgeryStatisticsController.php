<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequestedSurgery;
use App\Models\RequestedSurgeryTransaction;
use App\Models\RequestedSurgeryFinance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SurgeryStatisticsController extends Controller
{
    public function getStatistics(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Apply global date filters if provided
        $dateFilter = function ($query) use ($startDate, $endDate) {
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }
        };

        // 1. Total Surgeries Count (RequestedSurgeries)
        $totalSurgeriesQuery = RequestedSurgery::query();
        $dateFilter($totalSurgeriesQuery);
        $totalSurgeries = $totalSurgeriesQuery->count();

        // 2. Total Revenue & Total Collected (Transactions)
        $transactionsQuery = RequestedSurgeryTransaction::query();
        $dateFilter($transactionsQuery);

        $totals = $transactionsQuery->select(
            DB::raw("CAST(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) AS UNSIGNED) as total_revenue"),
            DB::raw("CAST(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) AS UNSIGNED) as total_collected")
        )->first();

        $totalRevenue = (float)($totals->total_revenue ?? 0);
        $totalCollected = (float)($totals->total_collected ?? 0);
        $outstandingBalance = $totalRevenue - $totalCollected;

        // 3. Top Surgeries (Bar Chart)
        $topSurgeriesQuery = RequestedSurgery::with('surgery')
            ->select('surgery_id', DB::raw('count(*) as count'))
            ->groupBy('surgery_id')
            ->orderByDesc('count')
            ->limit(5);
        $dateFilter($topSurgeriesQuery);

        $topSurgeriesData = $topSurgeriesQuery->get()->map(function ($rs) {
            return [
                'name' => $rs->surgery ? $rs->surgery->name : 'Unknown',
                'count' => $rs->count
            ];
        });

        // 4. Status Breakdown (Pie Chart)
        $statusBreakdownQuery = RequestedSurgery::select('status', DB::raw('count(*) as count'))
            ->groupBy('status');
        $dateFilter($statusBreakdownQuery);
        $statusBreakdown = $statusBreakdownQuery->get()->pluck('count', 'status')->toArray();

        // Ensure all statuses have a default of 0
        $statusData = [
            'pending' => $statusBreakdown['pending'] ?? 0,
            'approved' => $statusBreakdown['approved'] ?? 0,
            'rejected' => $statusBreakdown['rejected'] ?? 0,
        ];

        // 5. Monthly Trend (Line Chart) -> Last 6 Months or within range
        $trendQuery = RequestedSurgery::select('created_at');

        if (!$startDate) {
            $trendQuery->whereDate('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth());
        } else {
            $dateFilter($trendQuery);
        }

        $trendRecords = $trendQuery->get();
        // Group and count using collection methods
        $monthlyTrend = $trendRecords->groupBy(function ($date) {
            return Carbon::parse($date->created_at)->format('Y-m');
        })->map(function ($group, $month) {
            return [
                'month' => $month,
                'count' => $group->count()
            ];
        })->values()->sortBy('month')->values();

        // 6. Doctor Performance
        $doctorPerfQuery = RequestedSurgery::with('doctor')
            ->whereNotNull('doctor_id')
            ->select('doctor_id', DB::raw('count(*) as surgery_count'))
            ->groupBy('doctor_id');
        $dateFilter($doctorPerfQuery);

        $doctorPerformance = $doctorPerfQuery->get()->map(function ($rs) use ($startDate, $endDate) {
            // Calculate generated revenue for this doctor based on the transactions linked to their surgeries
            $revenueQuery = RequestedSurgeryTransaction::whereHas('requestedSurgery', function ($q) use ($rs) {
                $q->where('doctor_id', $rs->doctor_id);
            })->where('type', 'debit');

            if ($startDate) $revenueQuery->whereDate('created_at', '>=', $startDate);
            if ($endDate) $revenueQuery->whereDate('created_at', '<=', $endDate);

            $generatedRevenue = (float)$revenueQuery->sum('amount');

            return [
                'doctor_id' => $rs->doctor_id,
                'doctor_name' => $rs->doctor ? $rs->doctor->name : 'Unknown',
                'surgery_count' => $rs->surgery_count,
                'generated_revenue' => $generatedRevenue
            ];
        })->sortByDesc('generated_revenue')->values();


        return response()->json([
            'summary' => [
                'total_surgeries' => $totalSurgeries,
                'total_revenue' => $totalRevenue,
                'total_collected' => $totalCollected,
                'outstanding_balance' => $outstandingBalance,
            ],
            'top_surgeries' => $topSurgeriesData,
            'status_breakdown' => $statusData,
            'monthly_trend' => $monthlyTrend,
            'doctor_performance' => $doctorPerformance,
        ]);
    }

    public function getDailyReport(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $daysInMonth = $startDate->daysInMonth;
        $reportData = [];

        // Pre-fetch data for the entire month to avoid N+1 queries in the loop
        $surgeriesCount = RequestedSurgery::whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->get()
            ->pluck('count', 'date');

        $transactions = RequestedSurgeryTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN type = "debit" THEN amount ELSE 0 END) as total_income'),
                DB::raw('SUM(CASE WHEN type = "credit" AND payment_method = "cash" THEN amount ELSE 0 END) as total_cash'),
                DB::raw('SUM(CASE WHEN type = "credit" AND payment_method = "bankak" THEN amount ELSE 0 END) as total_bank')
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $financeCharges = RequestedSurgeryFinance::whereBetween('created_at', [$startDate, $endDate])
            ->with('financeCharge')
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->created_at)->format('Y-m-d');
            });

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = Carbon::createFromDate($year, $month, $i)->format('Y-m-d');

            $staffCharges = 0;
            $centerCharges = 0;

            if (isset($financeCharges[$date])) {
                foreach ($financeCharges[$date] as $charge) {
                    if ($charge->financeCharge) {
                        if ($charge->financeCharge->beneficiary === 'staff') {
                            $staffCharges += (float)$charge->amount;
                        } elseif ($charge->financeCharge->beneficiary === 'center') {
                            $centerCharges += (float)$charge->amount;
                        }
                    }
                }
            }

            $reportData[] = [
                'date' => $date,
                'surgeries_count' => $surgeriesCount[$date] ?? 0,
                'total_income' => (float)($transactions[$date]->total_income ?? 0),
                'staff_charges' => $staffCharges,
                'center_charges' => $centerCharges,
                'total_cash' => (float)($transactions[$date]->total_cash ?? 0),
                'total_bank' => (float)($transactions[$date]->total_bank ?? 0),
            ];
        }

        return response()->json([
            'year' => $year,
            'month' => $month,
            'data' => $reportData
        ]);
    }
}
