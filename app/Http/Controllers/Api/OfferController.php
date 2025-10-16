<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\MainTest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $offers = Offer::with('mainTests')->get();
        
        return response()->json([
            'status' => true,
            'data' => $offers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'main_test_ids' => 'array',
            'main_test_ids.*' => 'exists:main_tests,id'
        ]);

        DB::beginTransaction();
        try {
            $offer = Offer::create([
                'name' => $request->name,
                'price' => $request->price,
            ]);

            if ($request->has('main_test_ids')) {
                $ids = array_values(array_unique($request->main_test_ids));
                $count = max(1, count($ids));
                $total = (int)round($request->price);
                $base = intdiv($total, $count);
                $remainder = $total % $count;

                $attachData = [];
                foreach ($ids as $index => $id) {
                    $portion = $base + ($index < $remainder ? 1 : 0); // spread remainder
                    $attachData[$id] = ['price' => $portion];
                }
                $offer->mainTests()->attach($attachData);
            }

            $offer->load('mainTests');

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Offer created successfully',
                'data' => $offer
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create offer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $offer = Offer::with('mainTests')->find($id);

        if (!$offer) {
            return response()->json([
                'status' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $offer
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'main_test_ids' => 'array',
            'main_test_ids.*' => 'exists:main_tests,id',
            'offered_tests' => 'array',
            'offered_tests.*.main_test_id' => 'required_with:offered_tests|exists:main_tests,id',
            'offered_tests.*.price' => 'required_with:offered_tests|integer|min:0'
        ]);

        $offer = Offer::find($id);

        if (!$offer) {
            return response()->json([
                'status' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $offer->update($request->only(['name', 'price']));

            if ($request->has('offered_tests') && is_array($request->offered_tests)) {
                // Explicit per-test prices provided from client
                $syncData = [];
                foreach ($request->offered_tests as $item) {
                    $syncData[$item['main_test_id']] = ['price' => (int)$item['price']];
                }
                $offer->mainTests()->sync($syncData);
            } elseif ($request->has('main_test_ids') || $request->has('price')) {
                // Recompute equal distribution if tests or total price changed
                $ids = $request->has('main_test_ids') ? array_values(array_unique($request->main_test_ids)) : $offer->mainTests()->pluck('main_tests.id')->all();
                $count = max(1, count($ids));
                $total = (int)round($request->input('price', $offer->price));
                $base = intdiv($total, $count);
                $remainder = $total % $count;

                $syncData = [];
                foreach ($ids as $index => $id) {
                    $portion = $base + ($index < $remainder ? 1 : 0);
                    $syncData[$id] = ['price' => $portion];
                }
                $offer->mainTests()->sync($syncData);
            }

            $offer->load('mainTests');

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Offer updated successfully',
                'data' => $offer
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'message' => 'Failed to update offer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $offer = Offer::find($id);

        if (!$offer) {
            return response()->json([
                'status' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        try {
            $offer->delete();

            return response()->json([
                'status' => true,
                'message' => 'Offer deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete offer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all main tests for selection
     */
    public function getMainTests(): JsonResponse
    {
        $mainTests = MainTest::select('id', 'main_test_name')->get();

        return response()->json([
            'status' => true,
            'data' => $mainTests
        ]);
    }
}
