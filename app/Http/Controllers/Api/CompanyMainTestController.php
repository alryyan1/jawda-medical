<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyMainTestResource;
use App\Http\Resources\MainTestStrippedResource;
// The pivot model
use App\Models\Company;
use App\Models\MainTest;
use Illuminate\Http\Request; // For available tests
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CompanyMainTestController extends Controller
{
    public function __construct()
    {
        // Permissions: e.g., 'view company_contracts', 'manage company_contracts'
    }

    // List contracted tests for a specific company
    public function index(Request $request, Company $company)
    {
        // $this->authorize('view', $company); // Or specific contract view permission
        $query = $company->contractedMainTests()->with('container')->orderBy('id', 'asc');

        if ($request->filled('search')) {
            $query->where('main_test_name', 'LIKE', '%'.$request->search.'%');
        }

        $contractedTests = $query->paginate($request->get('per_page', 20));

        return CompanyMainTestResource::collection($contractedTests);
    }

    // List main tests NOT yet contracted by this company
    public function availableMainTests(Company $company)
    {
        // $this->authorize('update', $company); // Or manage_contracts permission
        $contractedTestIds = $company->contractedMainTests()->pluck('main_tests.id');
        $availableTests = MainTest::where('available', true) // Only active main tests
            ->whereNotIn('id', $contractedTestIds)
            ->orderBy('main_test_name')
            ->get(['id', 'main_test_name', 'price']); // Include default price

        return MainTestStrippedResource::collection($availableTests);
    }

    // Add a new test contract to a company
    public function store(Request $request, Company $company)
    {
        // $this->authorize('update', $company);
        $validatedData = $request->validate([
            'main_test_id' => [
                'required', 'integer', 'exists:main_tests,id',
                Rule::unique('company_main_test')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'status' => 'required|boolean',
            'price' => 'required|numeric|min:0',
            'approve' => 'required|boolean',
            'endurance_static' => 'required|integer|min:0',
            'endurance_percentage' => 'required|numeric|min:0|max:100',
            'use_static' => 'required|boolean',
        ]);

        $company->contractedMainTests()->attach($validatedData['main_test_id'], [
            'status' => $validatedData['status'],
            'price' => $validatedData['price'],
            'approve' => $validatedData['approve'],
            'endurance_static' => $validatedData['endurance_static'],
            'endurance_percentage' => $validatedData['endurance_percentage'],
            'use_static' => $validatedData['use_static'],
        ]);

        // Fetch the newly attached MainTest with its pivot data
        $newlyContractedTest = $company->contractedMainTests()
            ->where('main_tests.id', $validatedData['main_test_id'])
            ->first();

        return new CompanyMainTestResource($newlyContractedTest);
    }

    // Update an existing test contract for a company
    public function update(Request $request, Company $company, MainTest $mainTest) // Route model binding for MainTest
    {
        // $this->authorize('update', $company);
        if (! $company->contractedMainTests()->where('main_tests.id', $mainTest->id)->exists()) {
            return response()->json(['message' => 'Test contract not found for this company.'], 404);
        }
        $validatedData = $request->validate([
            'status' => 'sometimes|required|boolean',
            'price' => 'sometimes|required|numeric|min:0',
            'approve' => 'sometimes|required|boolean',
            'endurance_static' => 'sometimes|required|integer|min:0',
            'endurance_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'use_static' => 'sometimes|required|boolean',
        ]);

        $company->contractedMainTests()->updateExistingPivot($mainTest->id, $validatedData);

        $updatedContractedTest = $company->contractedMainTests()
            ->where('main_tests.id', $mainTest->id)
            ->first();

        return new CompanyMainTestResource($updatedContractedTest);
    }

    // Remove a test contract from a company
    public function destroy(Company $company, MainTest $mainTest)
    {
        // $this->authorize('update', $company);
        if (! $company->contractedMainTests()->where('main_tests.id', $mainTest->id)->exists()) {
            return response()->json(['message' => 'Test contract not found for this company.'], 404);
        }
        $company->contractedMainTests()->detach($mainTest->id);

        return response()->json(null, 204);
    }

    // Import all active main tests to this company's contract (similar to services)
    public function importAllMainTests(Request $request, Company $company)
    {
        // $this->authorize('update', $company);
        $allActiveMainTests = MainTest::where('available', true)->get();
        // return $allActiveMainTests;
        $existingContractedTestIds = $company->contractedMainTests()->pluck('main_tests.id')->toArray();

        $testsToImport = $allActiveMainTests->reject(fn ($test) => in_array($test->id, $existingContractedTestIds));
        // return ['testsToImport' => $testsToImport,'availableTests' => $allActiveMainTests];
        if ($testsToImport->isEmpty()) {
            return response()->json(['message' => 'جميع الفحوصات النشطة مضافة بالفعل.', 'imported_count' => 0]);
        }

        $defaultContractTerms = [

            'status' => $request->input('default_status', true),
            'approve' => $request->input('default_approve', true),
            'endurance_static' => $request->input('default_endurance_static', 0),
            'endurance_percentage' => $request->input('default_endurance_percentage', $company->lab_endurance ?? 0), // Use company default
            'use_static' => $request->input('default_use_static', false),
        ];

        $attachData = [];
        foreach ($testsToImport as $test) {
            // return $test;
            $attachData[$test->id] = array_merge(
                $defaultContractTerms,
                ['price' => $test->price ?? 0] // Default contract price to test's standard price
            );
        }

        $company->contractedMainTests()->attach($attachData);

        return response()->json([
            'message' => 'تم استيراد '.count($attachData).' فحص بنجاح.',
            'imported_count' => count($attachData),
        ]);
    }

    // Copy main test contracts from one company to another
    public function copyContractsFrom(Request $request, Company $targetCompany, Company $sourceCompany)
    {
        // Authorization: User should be able to manage contracts for the targetCompany
        // if (!Auth::user()->can('manage company_contracts', $targetCompany)) { // Example policy check
        //     return response()->json(['message' => 'Unauthorized to modify target company contracts.'], 403);
        // }
        // if (!Auth::user()->can('view company_contracts', $sourceCompany)) { // Example policy check
        //     return response()->json(['message' => 'Unauthorized to view source company contracts.'], 403);
        // }

        if ($targetCompany->id === $sourceCompany->id) {
            return response()->json(['message' => 'لا يمكن نسخ العقود من نفس الشركة إلى نفسها.'], 422);
        }

        // Crucial Check: Target company must not have existing main test contracts
        // if ($targetCompany->contractedMainTests()->exists()) {
        //     return response()->json(['message' => 'لا يمكن نسخ العقود. الشركة المستهدفة لديها عقود فحوصات موجودة بالفعل.'], 409); // 409 Conflict
        // }

        $sourceContracts = $sourceCompany->contractedMainTests()->get();

        if ($sourceContracts->isEmpty()) {
            return response()->json(['message' => 'الشركة المصدر لا تحتوي على عقود فحوصات لنسخها.', 'copied_count' => 0], 404);
        }

        // Get existing contract IDs in target company
        $existingContractIds = $targetCompany->contractedMainTests()->pluck('main_tests.id')->toArray();

        $attachData = [];
        $updateData = [];
        foreach ($sourceContracts as $sourceContractPivotedTest) {
            // $sourceContractPivotedTest is a MainTest model with ->pivot populated
            $pivotData = $sourceContractPivotedTest->pivot;
            $contractData = [
                'status' => $pivotData->status,
                'price' => $pivotData->price,
                'approve' => $pivotData->approve,
                'endurance_static' => $pivotData->endurance_static,
                'endurance_percentage' => $pivotData->endurance_percentage,
                'use_static' => $pivotData->use_static,
                'updated_at' => now(),
            ];

            if (in_array($sourceContractPivotedTest->id, $existingContractIds)) {
                // Contract exists, prepare for update
                $updateData[$sourceContractPivotedTest->id] = $contractData;
            } else {
                // New contract, prepare for attach
                $contractData['created_at'] = now(); // New timestamps for the new contract
                $attachData[$sourceContractPivotedTest->id] = $contractData;
            }
        }

        DB::beginTransaction();
        try {
            // Update existing contracts
            foreach ($updateData as $testId => $data) {
                $targetCompany->contractedMainTests()->updateExistingPivot($testId, $data);
            }

            // Attach new contracts
            if (! empty($attachData)) {
                $targetCompany->contractedMainTests()->attach($attachData);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to copy main test contracts from company {$sourceCompany->id} to {$targetCompany->id}: ".$e->getMessage());

            return response()->json(['message' => 'فشل نسخ عقود الفحوصات.', 'error' => $e->getMessage()], 500);
        }

        $totalCount = count($attachData) + count($updateData);
        $message = 'تم ';
        if (! empty($updateData)) {
            $message .= 'تحديث '.count($updateData).' عقد فحص موجود ';
        }
        if (! empty($attachData)) {
            if (! empty($updateData)) {
                $message .= 'و';
            }
            $message .= 'إضافة '.count($attachData).' عقد فحص جديد ';
        }
        $message .= "من {$sourceCompany->name} إلى {$targetCompany->name}.";

        return response()->json([
            'message' => $message,
            'copied_count' => $totalCount,
            'updated_count' => count($updateData),
            'added_count' => count($attachData),
        ]);
    }

    /**
     * Export this company's contracted main tests to an Excel file that can be
     * edited and re-imported via importContractsExcel().
     */
    public function exportContractsExcel(Request $request, Company $company): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $query = $company->contractedMainTests()->orderBy('main_tests.id');
        if ($request->filled('search')) {
            $query->where('main_test_name', 'LIKE', '%'.$request->search.'%');
        }
        $contracts = $query->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Company Test Contracts');

        $headers = ['ID', 'اسم الفحص', 'السعر', 'نسبة التحمل %', 'التحمل الثابت'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNumber = 2;
        foreach ($contracts as $test) {
            $sheet->setCellValue('A'.$rowNumber, $test->id);
            $sheet->setCellValue('B'.$rowNumber, $test->main_test_name);
            $sheet->setCellValue('C'.$rowNumber, (float) ($test->pivot->price ?? 0));
            $sheet->setCellValue('D'.$rowNumber, (float) ($test->pivot->endurance_percentage ?? 0));
            $sheet->setCellValue('E'.$rowNumber, (int) ($test->pivot->endurance_static ?? 0));
            $rowNumber++;
        }

        $lastRow = $rowNumber - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle('C2:D'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(45);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);

        $fileName = trim($company->name).'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'cmt_contract_');
        $writer->save($tempFile);
        $spreadsheet->disconnectWorksheets();

        return response()->streamDownload(function () use ($tempFile) {
            if (file_exists($tempFile)) {
                readfile($tempFile);
                unlink($tempFile);
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Import an Excel file (as produced by exportContractsExcel) and update the
     * pivot price / endurance columns of each contracted main test matched by
     * the ID column. Rows for tests not currently contracted are reported and skipped.
     */
    public function importContractsExcel(Request $request, Company $company): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Exception $e) {
            return response()->json(['message' => 'تعذر قراءة الملف. تأكد من أنه ملف Excel صالح.'], 422);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        $contractedIds = $company->contractedMainTests()->pluck('main_tests.id')->all();

        $updatedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue; // header row
                }
                $id = isset($row[0]) ? trim((string) $row[0]) : '';
                $priceRaw = isset($row[2]) ? trim((string) $row[2]) : '';
                $percentageRaw = isset($row[3]) ? trim((string) $row[3]) : '';
                $staticRaw = isset($row[4]) ? trim((string) $row[4]) : '';

                if ($id === '' && $priceRaw === '' && $percentageRaw === '' && $staticRaw === '') {
                    continue; // empty line
                }
                if (! ctype_digit($id)) {
                    $errors[] = 'السطر '.($index + 1).": معرّف غير صالح ({$id}).";

                    continue;
                }
                if (! in_array((int) $id, $contractedIds)) {
                    $errors[] = 'السطر '.($index + 1).": الفحص {$id} غير متعاقد عليه لهذه الشركة.";

                    continue;
                }

                $pivotData = [];

                $price = str_replace(',', '', $priceRaw);
                if ($priceRaw === '' || ! is_numeric($price) || (float) $price < 0) {
                    $errors[] = 'السطر '.($index + 1).": سعر غير صالح ({$priceRaw}).";

                    continue;
                }
                $pivotData['price'] = (float) $price;

                if ($percentageRaw !== '') {
                    $percentage = str_replace(',', '', $percentageRaw);
                    if (! is_numeric($percentage) || (float) $percentage < 0 || (float) $percentage > 100) {
                        $errors[] = 'السطر '.($index + 1).": نسبة تحمل غير صالحة ({$percentageRaw}).";

                        continue;
                    }
                    $pivotData['endurance_percentage'] = (float) $percentage;
                }

                if ($staticRaw !== '') {
                    $static = str_replace(',', '', $staticRaw);
                    if (! is_numeric($static) || (float) $static < 0) {
                        $errors[] = 'السطر '.($index + 1).": تحمل ثابت غير صالح ({$staticRaw}).";

                        continue;
                    }
                    $pivotData['endurance_static'] = (int) $static;
                }

                $company->contractedMainTests()->updateExistingPivot((int) $id, $pivotData);
                $updatedCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'فشل استيراد العقود.', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => "تم تحديث {$updatedCount} عقد فحص.".(count($errors) > 0 ? ' مع بعض التحذيرات.' : ''),
            'updated_count' => $updatedCount,
            'errors' => $errors,
        ]);
    }
}
