<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Operation;
use App\Models\OperationFinanceItem;
use App\Http\Resources\OperationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OperationController extends Controller
{
    /**
     * Display a listing of operations
     */
    /**
     * Display a listing of operations
     */
    public function index(Request $request)
    {
        $query = Operation::with(['admission.patient', 'user', 'financeItems.operationItem', 'costs.operationItem']);

        // Filter for templates (settings page)
        if ($request->has('is_template') && $request->is_template) {
            $query->whereNull('admission_id');
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('operation_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('operation_date', '<=', $request->date_to);
        }

        // Filter by admission
        if ($request->has('admission_id') && $request->admission_id) {
            $query->where('admission_id', $request->admission_id);
        }

        $operations = $query->orderBy('operation_date', 'desc')
            ->orderBy('operation_time', 'desc')
            ->paginate($request->get('per_page', 15));

        return OperationResource::collection($operations);
    }

    /**
     * Store a newly created operation
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'admission_id' => 'nullable|exists:admissions,id',
            'operation_date' => 'required|date',
            'operation_time' => 'nullable|date_format:H:i:s',
            'operation_type' => 'required|string|max:255',
            'description' => 'nullable|string',
            'surgeon_fee' => 'required|numeric|min:0',
            'cash_paid' => 'nullable|numeric|min:0',
            'bank_paid' => 'nullable|numeric|min:0',
            'bank_receipt_image' => 'nullable|image|max:2048',
            'notes' => 'nullable|string',
            'manual_items' => 'nullable|array',

            // Costs Validation
            'costs' => 'nullable|array',
            'costs.*.operation_item_id' => 'required|exists:operation_items,id',
            'costs.*.perc' => 'nullable|numeric|min:0|max:100',
            'costs.*.fixed' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validatedData, $request) {
            // Handle file upload
            if ($request->hasFile('bank_receipt_image')) {
                $path = $request->file('bank_receipt_image')->store('operation_receipts', 'public');
                $validatedData['bank_receipt_image'] = $path;
            }

            // Create operation
            $validatedData['user_id'] = Auth::id();
            $validatedData['status'] = 'pending';

            $operation = Operation::create($validatedData);

            // Sync Costs (Config)
            if (isset($validatedData['costs'])) {
                foreach ($validatedData['costs'] as $cost) {
                    $operation->costs()->create([
                        'operation_item_id' => $cost['operation_item_id'],
                        'perc' => $cost['perc'] ?? null,
                        'fixed' => $cost['fixed'] ?? null,
                    ]);
                }
            }

            // Calculate automatic items (Logic can be updated to use costs config if needed later)
            $operation->calculateAutoItems();

            // Add manual items if provided
            if (isset($validatedData['manual_items'])) {
                foreach ($validatedData['manual_items'] as $item) {
                    $operation->financeItems()->create([
                        'item_type' => $item['item_type'],
                        'category' => $item['category'],
                        'description' => $item['description'] ?? null,
                        'amount' => $item['amount'],
                        'is_auto_calculated' => false,
                    ]);
                }

                // Recalculate totals after adding manual items
                $operation->updateTotals();
            }

            return new OperationResource($operation->load(['admission.patient', 'user', 'financeItems.operationItem', 'costs.operationItem']));
        });
    }

    /**
     * Display the specified operation
     */
    public function show(Operation $operation)
    {
        return new OperationResource($operation->load(['admission.patient', 'user', 'financeItems.operationItem', 'costs.operationItem']));
    }

    /**
     * Update the specified operation
     */
    public function update(Request $request, Operation $operation)
    {
        $validatedData = $request->validate([
            'operation_date' => 'sometimes|date',
            'operation_time' => 'nullable|date_format:H:i:s',
            'operation_type' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'surgeon_fee' => 'sometimes|numeric|min:0',
            'cash_paid' => 'nullable|numeric|min:0',
            'bank_paid' => 'nullable|numeric|min:0',
            'bank_receipt_image' => 'nullable|image|max:2048',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:pending,completed,cancelled',
            'manual_items' => 'nullable|array',

            // Costs Validation
            'costs' => 'nullable|array',
            'costs.*.operation_item_id' => 'required|exists:operation_items,id',
            'costs.*.perc' => 'nullable|numeric|min:0|max:100',
            'costs.*.fixed' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validatedData, $request, $operation) {
            // Handle file upload
            if ($request->hasFile('bank_receipt_image')) {
                // Delete old file
                if ($operation->bank_receipt_image) {
                    Storage::disk('public')->delete($operation->bank_receipt_image);
                }
                $path = $request->file('bank_receipt_image')->store('operation_receipts', 'public');
                $validatedData['bank_receipt_image'] = $path;
            }

            $operation->update($validatedData);

            // Update Costs if provided
            if (isset($validatedData['costs'])) {
                // Determine if we should sync (delete all and add) or update.
                // Sync is safer for config lists.
                $operation->costs()->delete();
                foreach ($validatedData['costs'] as $cost) {
                    $operation->costs()->create([
                        'operation_item_id' => $cost['operation_item_id'],
                        'perc' => $cost['perc'] ?? null,
                        'fixed' => $cost['fixed'] ?? null,
                    ]);
                }
            }

            // If surgeon fee changed, recalculate auto items
            // But skip if 'skip_auto_calculations' is true
            if (isset($validatedData['surgeon_fee']) && !$request->boolean('skip_auto_calculations')) {
                $operation->calculateAutoItems();
            }

            // Update manual items if provided
            if (isset($validatedData['manual_items'])) {
                // If skipping auto calculations, we might want to treat ALL provided items as the source of truth
                if ($request->boolean('skip_auto_calculations')) {
                    // If we took full control, maybe we should delete ALL items before adding new ones?
                    $operation->financeItems()->delete();
                } else {
                    $operation->financeItems()->where('is_auto_calculated', false)->delete();
                }

                // Add new manual items
                foreach ($validatedData['manual_items'] as $item) {
                    $operation->financeItems()->create([
                        'operation_item_id' => $item['operation_item_id'] ?? null,
                        'description' => $item['description'] ?? null,
                        'amount' => $item['amount'],
                        'is_auto_calculated' => false,
                    ]);
                }

                // Recalculate totals
                $operation->updateTotals();
            }

            return new OperationResource($operation->fresh()->load(['admission.patient', 'user', 'financeItems.operationItem', 'costs.operationItem']));
        });
    }

    /**
     * Remove the specified operation
     */
    public function destroy(Operation $operation)
    {
        // Delete receipt image if exists
        if ($operation->bank_receipt_image) {
            Storage::disk('public')->delete($operation->bank_receipt_image);
        }

        $operation->delete();

        return response()->json(['message' => 'تم حذف العملية بنجاح'], 200);
    }

    /**
     * Get financial report
     */
    public function getFinancialReport(Request $request)
    {
        $query = Operation::with('financeItems');

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('operation_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('operation_date', '<=', $request->date_to);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $operations = $query->get();

        $totalStaff = $operations->sum('total_staff');
        $totalCenter = $operations->sum('total_center');
        $totalAmount = $operations->sum('total_amount');
        $totalCashPaid = $operations->sum('cash_paid');
        $totalBankPaid = $operations->sum('bank_paid');
        $totalBalance = $totalAmount - ($totalCashPaid + $totalBankPaid);

        return response()->json([
            'operations_count' => $operations->count(),
            'total_staff' => $totalStaff,
            'total_center' => $totalCenter,
            'total_amount' => $totalAmount,
            'total_cash_paid' => $totalCashPaid,
            'total_bank_paid' => $totalBankPaid,
            'total_balance' => $totalBalance,
            'operations' => OperationResource::collection($operations),
        ]);
    }

    /**
     * Get list of operation items (catalogue)
     */
    public function getItems()
    {
        $items = \App\Models\OperationItem::where('is_active', true)->get();
        return response()->json($items);
    }

    /**
     * Generate Financial Report PDF
     */
    public function printReport(Operation $operation)
    {
        $operation->load(['admission.patient', 'financeItems']);

        $report = new \App\Services\Pdf\OperationFinancialReport($operation);
        $pdfContent = $report->generate();

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="operation-report-' . $operation->id . '.pdf"');
    }
}
