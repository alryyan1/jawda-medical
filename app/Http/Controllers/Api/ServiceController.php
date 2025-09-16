<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\ServiceCollection; // If you create one for pagination
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with('serviceGroup'); // Eager load by default
    
        // Filter by search term (service name)
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }
    
        // Filter by service group ID
        if ($request->filled('service_group_id')) {
            $query->where('service_group_id', $request->service_group_id);
        }
    
        $services = $query->orderBy('id','desc')->paginate($request->get('per_page', 15));
        return ServiceResource::collection($services);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'service_group_id' => 'required|exists:service_groups,id',
            'price' => 'required|numeric|min:0',
            'activate' => 'required|boolean',
            'variable' => 'required|boolean', // As per schema, no default, so required
        ]);
        $service = Service::create($validatedData);
        return new ServiceResource($service->load('serviceGroup'));
    }

    public function show(Service $service)
    {
        return new ServiceResource($service->load('serviceGroup'));
    }

    public function update(Request $request, Service $service)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'service_group_id' => 'sometimes|required|exists:service_groups,id',
            'price' => 'sometimes|required|numeric|min:0',
            'activate' => 'sometimes|required|boolean',
            'variable' => 'sometimes|required|boolean',
        ]);
        $service->update($validatedData);
        return new ServiceResource($service->load('serviceGroup'));
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return response()->json(null, 204);
    }

      /**
     * Preview or execute a batch update on service prices based on dynamic conditions.
     */
    public function batchUpdatePrices(Request $request)
    {
        // if (!Auth::user()->can('batch_update_service_prices')) { /* ... */ }

        $validated = $request->validate([
            'update_mode'       => ['required', Rule::in(['increase', 'decrease'])],
            'update_type'       => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'update_value'      => 'required|numeric|min:0',
            'conditions'        => 'present|array',
            'conditions.*.field' => ['required', Rule::in(['service_group_id', 'price', 'name'])], // Add more valid fields here
            'conditions.*.operator' => ['required', Rule::in(['=', '!=', '<', '>', '<=', '>=', 'LIKE'])],
            'conditions.*.value' => 'required',
            'is_preview'        => 'sometimes|boolean', // If true, just return the count
        ]);

        $query = Service::query();

        // --- Dynamically apply conditions ---
        foreach ($validated['conditions'] as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];

            // Sanitize and validate operator/field combos to prevent SQL injection-like issues
            if (!in_array($field, ['service_group_id', 'price', 'name'])) {
                continue; // Skip invalid fields
            }
            if ($operator === 'LIKE' && $field === 'name') {
                 $query->where($field, 'LIKE', '%' . $value . '%');
            } elseif (in_array($operator, ['=', '!=', '<', '>', '<=', '>='])) {
                 $query->where($field, $operator, $value);
            }
        }
        
        // If it's just a preview, return the count of affected services
        if ($request->boolean('is_preview')) {
            $count = $query->count();
            return response()->json(['message' => "This update will affect {$count} services.", 'affected_count' => $count]);
        }

        // --- Execute the update ---
        $updateValue = (float)$validated['update_value'];
        $updateExpression = '';

        if ($validated['update_type'] === 'percentage') {
            if ($validated['update_mode'] === 'increase') {
                $updateExpression = "price * (1 + (? / 100))";
            } else { // decrease
                $updateExpression = "price * (1 - (? / 100))";
            }
        } else { // fixed_amount
            if ($validated['update_mode'] === 'increase') {
                $updateExpression = "price + ?";
            } else { // decrease
                $updateExpression = "price - ?";
            }
        }

        try {
            // Get the services to update
            $servicesToUpdate = $query->get();
            $affectedRows = 0;

            foreach ($servicesToUpdate as $service) {
                $newPrice = $service->price;
                
                if ($validated['update_type'] === 'percentage') {
                    if ($validated['update_mode'] === 'increase') {
                        $newPrice = $service->price * (1 + ($updateValue / 100));
                    } else { // decrease
                        $newPrice = $service->price * (1 - ($updateValue / 100));
                    }
                } else { // fixed_amount
                    if ($validated['update_mode'] === 'increase') {
                        $newPrice = $service->price + $updateValue;
                    } else { // decrease
                        $newPrice = $service->price - $updateValue;
                    }
                }

            // Ensure price doesn't go below zero
                $newPrice = max(0, $newPrice);
                
                $service->update(['price' => $newPrice]);
                $affectedRows++;
            }

        } catch (\Exception $e) {
            Log::error('Batch service price update failed: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred during the update process.', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => "Successfully updated the price for {$affectedRows} services."]);
    }

    /**
     * Activate all services (set activate = true)
     */
    public function activateAll(Request $request)
    {
        // Optionally add authorization here
        $affected = Service::where('activate', false)->update(['activate' => true]);
        return response()->json([
            'message' => 'تم تفعيل جميع الخدمات بنجاح',
            'affected_count' => $affected,
        ]);
    }
}