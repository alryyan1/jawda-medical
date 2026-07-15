<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Service;
use App\Models\PartyServiceCost;
use Illuminate\Http\Request;
use App\Http\Resources\PartyServiceCostResource;
use App\Http\Resources\ServiceResource;
use Illuminate\Validation\Rule;

class PartyServiceCostController extends Controller
{
    // List all priced services for a specific party
    public function index(Party $party, Request $request)
    {
        $search = $request->get('search');

        $query = $party->services();

        if ($search) {
            $query->where('services.name', 'like', '%' . $search . '%');
        }

        $query->with('serviceGroup');

        if ($request->has('page') && $request->page == 0) {
            $partyServices = $query->get();
        } else {
            $partyServices = $query->paginate(20);
        }

        return PartyServiceCostResource::collection($partyServices);
    }

    // List services NOT yet priced for this party (for adding new prices)
    public function availableServices(Party $party)
    {
        $pricedServiceIds = $party->services()->pluck('services.id');
        $availableServices = Service::whereNotIn('id', $pricedServiceIds)
            ->where('activate', true)
            ->with('serviceGroup')
            ->orderBy('name')
            ->get();

        return ServiceResource::collection($availableServices);
    }

    // Add a price for a service under this party
    public function store(Request $request, Party $party)
    {
        $validatedData = $request->validate([
            'service_id' => [
                'required',
                'exists:services,id',
                Rule::unique('party_service_costs')->where(function ($query) use ($party) {
                    return $query->where('party_id', $party->id);
                }),
            ],
            'price' => 'required|numeric|min:0',
        ]);

        $party->services()->attach($validatedData['service_id'], [
            'price' => $validatedData['price'],
        ]);

        $entry = PartyServiceCost::where('party_id', $party->id)
            ->where('service_id', $validatedData['service_id'])
            ->firstOrFail();

        return new PartyServiceCostResource($entry->load('service.serviceGroup'));
    }

    // Update an existing service price for a party
    public function update(Request $request, Party $party, Service $service)
    {
        if (!$party->services()->where('services.id', $service->id)->exists()) {
            return response()->json(['message' => 'Service price not found for this party.'], 404);
        }

        $validatedData = $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $party->services()->updateExistingPivot($service->id, $validatedData);

        $entry = PartyServiceCost::where('party_id', $party->id)
            ->where('service_id', $service->id)
            ->firstOrFail();

        return new PartyServiceCostResource($entry->load('service.serviceGroup'));
    }

    // Remove a service price from a party
    public function destroy(Party $party, Service $service)
    {
        if (!$party->services()->where('services.id', $service->id)->exists()) {
            return response()->json(['message' => 'Service price not found for this party.'], 404);
        }

        $party->services()->detach($service->id);
        return response()->json(null, 204);
    }

    // Import all active services from a service group into this party's prices
    public function importByServiceGroup(Request $request, Party $party)
    {
        $validated = $request->validate([
            'service_group_id' => 'required|exists:service_groups,id',
            'price_preference' => 'required|string|in:standard_price,zero_price',
        ]);

        $groupServices = Service::where('service_group_id', $validated['service_group_id'])
            ->where('activate', true)
            ->get();

        $existingServiceIds = $party->services()->pluck('services.id')->toArray();

        $servicesToImport = $groupServices->reject(
            fn ($service) => in_array($service->id, $existingServiceIds)
        );

        if ($servicesToImport->isEmpty()) {
            return response()->json([
                'message' => 'جميع خدمات هذه المجموعة مسعّرة بالفعل لهذه الجهة.',
                'imported_count' => 0,
            ]);
        }

        $attachData = [];
        foreach ($servicesToImport as $service) {
            $price = $validated['price_preference'] === 'standard_price'
                ? (float) ($service->price ?? 0)
                : 0.0;
            $attachData[$service->id] = ['price' => $price];
        }

        $party->services()->attach($attachData);

        return response()->json([
            'message' => count($attachData) . ' خدمة تم استيرادها بنجاح.',
            'imported_count' => count($attachData),
        ]);
    }
}
