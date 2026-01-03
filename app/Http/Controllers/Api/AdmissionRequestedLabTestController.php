<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionRequestedLabTest;
use App\Models\MainTest;
use App\Models\Company;
use App\Models\AdmissionTransaction;
use App\Http\Resources\AdmissionRequestedLabTestResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdmissionRequestedLabTestController extends Controller
{
    /**
     * Get all requested lab tests for an admission.
     */
    public function index(Admission $admission)
    {
        $requested = $admission->requestedLabTests()
            ->with([
                'mainTest',
                'requestingUser:id,name',
                'performingDoctor:id,name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return AdmissionRequestedLabTestResource::collection($requested);
    }

    /**
     * Add lab tests to an admission.
     */
    public function store(Request $request, Admission $admission)
    {
        // Check if admission is active
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن إضافة فحوصات لإقامة غير نشطة.'], 403);
        }

        $validated = $request->validate([
            'main_test_ids' => 'required|array',
            'main_test_ids.*' => 'required|integer|exists:main_tests,id',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
        ]);

        $patient = $admission->patient;
        $company = $patient->company_id ? Company::find($patient->company_id) : null;

        $createdItems = [];
        DB::beginTransaction();
        try {
            foreach ($validated['main_test_ids'] as $mainTestId) {
                $mainTest = MainTest::find($mainTestId);
                if (!$mainTest) {
                    Log::warning("Main Test ID {$mainTestId} not found during admission lab test request.");
                    continue;
                }

                if (!$mainTest->available) {
                    return response()->json(['message' => "الفحص {$mainTest->main_test_name} غير متاح."], 403);
                }
                
                if ($company == null) {
                    if ($mainTest->price == 0 || $mainTest->price == null) {
                        return response()->json(['message' => 'لا يمكنك إضافة فحص بسعر 0.'], 403);
                    }
                }

                // Initialize default values
                $price = (float) $mainTest->price;
                $contractApproval = false;

                if ($company) {
                    $contract = $company->contractedMainTests()
                        ->where('main_tests.id', $mainTestId)
                        ->first();
                    
                    if ($contract && $contract->pivot) {
                        $contractPivot = $contract->pivot;
                        if ($contractPivot->price == 0) {
                            return response()->json(['message' => 'لا يمكنك إضافة فحص غير مسعر في العقد.'], 403);
                        }
                        
                        $price = (float) $contractPivot->price;
                        $contractApproval = (bool) $contractPivot->approve;
                    }
                }

                $doctorId = $validated['doctor_id'] ?? $admission->doctor_id;

                $requestedLabTest = AdmissionRequestedLabTest::create([
                    'admission_id' => $admission->id,
                    'main_test_id' => $mainTestId,
                    'user_id' => Auth::id(),
                    'doctor_id' => $doctorId,
                    'price' => $price,
                    'discount' => 0,
                    'discount_per' => 0,
                    'approval' => $contractApproval,
                    'done' => false,
                ]);

                // Create debit transaction for the lab test
                $netPayable = $requestedLabTest->net_payable_by_patient;
                if ($netPayable > 0) {
                    AdmissionTransaction::create([
                        'admission_id' => $admission->id,
                        'type' => 'debit',
                        'amount' => $netPayable,
                        'description' => $mainTest->main_test_name,
                        'reference_type' => 'lab_test',
                        'reference_id' => $requestedLabTest->id,
                        'is_bank' => false,
                        'user_id' => Auth::id(),
                    ]);
                }

                $createdItems[] = new AdmissionRequestedLabTestResource(
                    $requestedLabTest->load(['mainTest', 'requestingUser', 'performingDoctor'])
                );
            }

            DB::commit();
            return response()->json([
                'message' => 'تم إضافة الفحوصات بنجاح.',
                'lab_tests' => $createdItems
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to add lab tests to admission {$admission->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل إضافة الفحوصات.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a requested lab test.
     */
    public function update(Request $request, AdmissionRequestedLabTest $requestedLabTest)
    {
        // Check if admission is active
        if ($requestedLabTest->admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن تعديل فحوصات لإقامة غير نشطة.'], 403);
        }

        $validated = $request->validate([
            'price' => 'sometimes|required|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
            'discount_per' => 'sometimes|integer|min:0|max:100',
            'doctor_id' => 'sometimes|nullable|integer|exists:doctors,id',
        ]);

        DB::beginTransaction();
        try {
            $oldNetPayable = $requestedLabTest->net_payable_by_patient;
            
            $requestedLabTest->update($validated);

            $newNetPayable = $requestedLabTest->net_payable_by_patient;
            $difference = $newNetPayable - $oldNetPayable;

            // Update or create transaction
            $transaction = AdmissionTransaction::where('admission_id', $requestedLabTest->admission_id)
                ->where('reference_type', 'lab_test')
                ->where('reference_id', $requestedLabTest->id)
                ->first();

            if ($transaction) {
                if ($newNetPayable > 0) {
                    $transaction->update(['amount' => $newNetPayable]);
                } else {
                    $transaction->delete();
                }
            } elseif ($newNetPayable > 0) {
                AdmissionTransaction::create([
                    'admission_id' => $requestedLabTest->admission_id,
                    'type' => 'debit',
                    'amount' => $newNetPayable,
                    'description' => $requestedLabTest->mainTest->main_test_name ?? 'فحص مختبر',
                    'reference_type' => 'lab_test',
                    'reference_id' => $requestedLabTest->id,
                    'is_bank' => false,
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();
            return new AdmissionRequestedLabTestResource(
                $requestedLabTest->load(['mainTest', 'requestingUser', 'performingDoctor'])
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update admission requested lab test {$requestedLabTest->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تحديث الفحص.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove a requested lab test.
     */
    public function destroy(Admission $admission, AdmissionRequestedLabTest $requestedLabTest)
    {
        // Check if admission is active
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن حذف فحوصات لإقامة غير نشطة.'], 403);
        }

        // Verify lab test belongs to admission
        if ($requestedLabTest->admission_id !== $admission->id) {
            return response()->json(['message' => 'الفحص لا ينتمي لهذه الإقامة.'], 404);
        }

        DB::beginTransaction();
        try {
            // Delete related transaction
            AdmissionTransaction::where('admission_id', $admission->id)
                ->where('reference_type', 'lab_test')
                ->where('reference_id', $requestedLabTest->id)
                ->delete();

            $requestedLabTest->delete();

            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete admission requested lab test {$requestedLabTest->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل حذف الفحص.', 'error' => $e->getMessage()], 500);
        }
    }
}

