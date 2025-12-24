<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CbcBinding;
use App\Models\ChemistryBinder;
use App\Models\HormoneBinding;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BindingController extends Controller
{
    /**
     * Get all bindings for a specific type
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type'); // 'cbc', 'chemistry', or 'hormone'
        
        switch ($type) {
            case 'cbc':
                $bindings = CbcBinding::all();
                break;
            case 'chemistry':
                $bindings = ChemistryBinder::all();
                break;
            case 'hormone':
                $bindings = HormoneBinding::all();
                break;
            default:
                return response()->json(['error' => 'Invalid binding type'], 400);
        }
        
        return response()->json(['data' => $bindings]);
    }

    /**
     * Store a new binding
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:cbc,chemistry,hormone',
            'child_id_array' => 'required|string',
            'name_in_table' => 'required|string',
        ]);

        $data = [
            'child_id_array' => $validated['child_id_array'],
        ];

        switch ($validated['type']) {
            case 'cbc':
                $data['name_in_sysmex_table'] = $validated['name_in_table'];
                $binding = CbcBinding::create($data);
                break;
            case 'chemistry':
                $data['name_in_mindray_table'] = $validated['name_in_table'];
                $binding = ChemistryBinder::create($data);
                break;
            case 'hormone':
                $data['name_in_hormone_table'] = $validated['name_in_table'];
                $binding = HormoneBinding::create($data);
                break;
        }

        return response()->json(['data' => $binding, 'message' => 'Binding created successfully'], 201);
    }

    /**
     * Update a binding
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:cbc,chemistry,hormone',
            'child_id_array' => 'required|string',
            'name_in_table' => 'required|string',
        ]);

        switch ($validated['type']) {
            case 'cbc':
                $binding = CbcBinding::findOrFail($id);
                $binding->update([
                    'child_id_array' => $validated['child_id_array'],
                    'name_in_sysmex_table' => $validated['name_in_table'],
                ]);
                break;
            case 'chemistry':
                $binding = ChemistryBinder::findOrFail($id);
                $binding->update([
                    'child_id_array' => $validated['child_id_array'],
                    'name_in_mindray_table' => $validated['name_in_table'],
                ]);
                break;
            case 'hormone':
                $binding = HormoneBinding::findOrFail($id);
                $binding->update([
                    'child_id_array' => $validated['child_id_array'],
                    'name_in_hormone_table' => $validated['name_in_table'],
                ]);
                break;
        }

        return response()->json(['data' => $binding, 'message' => 'Binding updated successfully']);
    }

    /**
     * Delete a binding
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $type = $request->query('type');
        
        if (!$type || !in_array($type, ['cbc', 'chemistry', 'hormone'])) {
            return response()->json(['error' => 'Invalid binding type'], 400);
        }

        switch ($type) {
            case 'cbc':
                $binding = CbcBinding::findOrFail($id);
                break;
            case 'chemistry':
                $binding = ChemistryBinder::findOrFail($id);
                break;
            case 'hormone':
                $binding = HormoneBinding::findOrFail($id);
                break;
        }

        $binding->delete();

        return response()->json(['message' => 'Binding deleted successfully']);
    }

    /**
     * Get table columns for a specific binding type
     */
    public function getTableColumns(Request $request): JsonResponse
    {
        $type = $request->query('type'); // 'cbc', 'chemistry', or 'hormone'
        
        $tableName = match($type) {
            'cbc' => 'sysmex',
            'chemistry' => 'mindray2',
            'hormone' => 'hormone',
            default => null,
        };

        if (!$tableName) {
            return response()->json(['error' => 'Invalid binding type'], 400);
        }

        try {
            $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");
            $columnNames = array_map(function($column) {
                return $column->Field;
            }, $columns);

            // Filter out common non-data columns if needed
            $filteredColumns = array_filter($columnNames, function($name) {
                return !in_array(strtolower($name), ['id', 'created_at', 'updated_at', 'doctorvisit_id']);
            });

            return response()->json(['data' => array_values($filteredColumns)]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch columns: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get table data for a specific table type
     */
    public function getTableData(Request $request): JsonResponse
    {
        $type = $request->query('type'); // 'sysmex', 'hormone', or 'mindray2'
        $search = $request->query('search', '');
        $limit = $request->query('limit', 100);
        $offset = $request->query('offset', 0);

        try {
            $tableName = match($type) {
                'sysmex' => 'sysmex',
                'hormone' => 'hormone',
                'mindray2' => 'mindray2',
                default => null,
            };

            if (!$tableName) {
                return response()->json(['error' => 'Invalid table type'], 400);
            }

            $query = DB::table($tableName);

            // Apply search if provided (search only by doctorvisit_id)
            if ($search) {
                $query->where('doctorvisit_id', 'like', "%{$search}%");
            }

            $total = $query->count();
            $data = $query->limit($limit)->offset($offset)->get();

            return response()->json([
                'data' => $data,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a record from a specific table
     */
    public function deleteTableRecord(Request $request, int $id): JsonResponse
    {
        $type = $request->query('type'); // 'sysmex', 'hormone', or 'mindray2'

        try {
            $tableName = match($type) {
                'sysmex' => 'sysmex',
                'hormone' => 'hormone',
                'mindray2' => 'mindray2',
                default => null,
            };

            if (!$tableName) {
                return response()->json(['error' => 'Invalid table type'], 400);
            }

            $deleted = DB::table($tableName)->where('id', $id)->delete();
            
            if ($deleted === 0) {
                return response()->json(['error' => 'Record not found'], 404);
            }

            return response()->json(['message' => 'Record deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete record: ' . $e->getMessage()], 500);
        }
    }
}

