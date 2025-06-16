<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;
use App\Http\Resources\HolidayResource;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;


class HolidayController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:manage holidays');
    }

    public function index(Request $request)
    {
        $query = Holiday::query();
        if ($request->filled('year')) {
            $query->whereYear('holiday_date', $request->year);
        }
        if ($request->filled('month')) {
            $query->whereMonth('holiday_date', $request->month);
        }
        $holidays = $query->orderBy('holiday_date')->paginate($request->get('per_page', 15));
        return HolidayResource::collection($holidays);
    }
    
    public function indexList(Request $request) // For calendar display
    {
        $query = Holiday::query();
        // Potentially filter by year/month for calendar view performance
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('holiday_date', [
                Carbon::parse($request->start_date)->toDateString(),
                Carbon::parse($request->end_date)->toDateString()
            ]);
        }
        return HolidayResource::collection($query->orderBy('holiday_date')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => [
                'required', 'date_format:Y-m-d',
                Rule::unique('holidays')->where(function ($query) use ($request) {
                    return $query->where('name', $request->name); // Or just unique by date
                })
            ],
            'is_recurring' => 'sometimes|boolean',
            'description' => 'nullable|string',
        ]);
        $holiday = Holiday::create($validated);
        return new HolidayResource($holiday);
    }

    public function show(Holiday $holiday)
    {
        return new HolidayResource($holiday);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'holiday_date' => [
                'sometimes', 'required', 'date_format:Y-m-d',
                Rule::unique('holidays')->ignore($holiday->id)->where(function ($query) use ($request) {
                    return $query->where('name', $request->name);
                })
            ],
            'is_recurring' => 'sometimes|boolean',
            'description' => 'nullable|string',
        ]);
        $holiday->update($validated);
        return new HolidayResource($holiday);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return response()->json(null, 204);
    }
}