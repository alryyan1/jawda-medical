<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MainTestResource;
use App\Http\Resources\MainTestStrippedResource;
use App\Models\DoctorVisit;
use App\Models\MainTest;
use App\Services\Pdf\PriceList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MainTestController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:list lab_tests')->only('index');
        // $this->middleware('can:create lab_tests')->only('store');
        // $this->middleware('can:edit lab_tests')->only('update');
        // ... etc.
    }

    // app/Http/Controllers/Api/MainTestController.php
    public function findByIdOrCode(Request $request, $identifier)
    {
        // $this->authorize('list lab_tests'); // Or specific permission
        $mainTest = MainTest::where('id', $identifier)
            // ->orWhere('short_code', $identifier) // If you have short codes
            ->where('available', true)
            ->first();

        if (! $mainTest) {
            return response()->json(['message' => 'لم يتم العثور على فحص بهذا المعرف أو الرمز.'], 404);
        }
        // Optionally, check if this test is already requested for the current visit if visit_id is passed
        if ($request->has('visit_id')) {
            $visit = DoctorVisit::find($request->visit_id);
            if ($visit && $visit->labRequests()->where('main_test_id', $mainTest->id)->exists()) {
                return response()->json(['message' => 'هذا الفحص مطلوب بالفعل لهذه الزيارة.'], 409); // Conflict
            }
        }

        return new MainTestStrippedResource($mainTest);
    }

    public function index(Request $request)
    {
        $query = MainTest::with('container'); // Eager load container

        if ($request->filled('search')) {
            $query->where('main_test_name', 'LIKE', '%'.$request->search.'%');
        }
        if ($request->has('available') && $request->available !== '') {
            $query->where('available', (bool) $request->available);
        }

        // // return ['name' => 'waleed'];
        if ($request->filled('container_id')) {
            $query->where('container_id', $request->container_id);
        }
        // $query = MainTest::with('container', 'package'); // serviceGroup if tests are also services
        // $query->where('available', true); // Only available tests

        if ($request->filled('pack_id') && $request->pack_id !== 'all') { // 'all' could mean no package filter
            $query->where('pack_id', $request->pack_id);
        } elseif ($request->pack_id === 'none') { // Explicitly request tests with no package
            $query->whereNull('pack_id');
        }
        // // Exclude tests already requested for a specific visit if visit_id is provided
        // if ($request->filled('visit_id_to_exclude_requests')) {
        //     $visit = DoctorVisit::find($request->visit_id_to_exclude_requests);
        //     if ($visit) {
        //         $requestedTestIds = $visit->labRequests()->pluck('main_test_id')->toArray();
        //         $query->whereNotIn('id', $requestedTestIds);
        //     }
        // }

        // If not paginating for a selection list (e.g., for a specific package tab)
        if ($request->boolean('no_pagination')) {

            // $query->when('pack_id',function($q)use($request){
            //     $q->where('pack_id','=',$request->get('pack_id'));
            // });
            $mainTests = $query->orderBy('id')->get();

            return MainTestStrippedResource::collection($mainTests);
        }

        $mainTests = $query->orderBy('id')->paginate($request->get('per_page', 50)); // Default 50 for selection

        return MainTestResource::collection($mainTests); // Or MainTestStrippedResource

    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'main_test_name' => 'required|string|max:70|unique:main_tests,main_test_name',
            'pack_id' => 'nullable|integer', // Add exists rule if 'packs' table exists
            'pageBreak' => 'required|boolean',
            'container_id' => 'required|exists:containers,id',
            'price' => 'nullable|numeric|min:0',
            'divided' => 'required|boolean',
            'available' => 'required|boolean',
            'is_special_test' => 'sometimes|boolean',
            'conditions' => 'sometimes|string',
            'timer' => 'sometimes|integer',
            'hide_unit' => 'sometimes|boolean',
            'allow_sorting' => 'sometimes|boolean',
            'default_comment' => 'sometimes|nullable|string',
        ]);
        if ($request->has('is_special_test')) {
            $validatedData['is_special_test'] = (bool) $request->boolean('is_special_test');
        }
        if ($request->has('conditions')) {
            $validatedData['conditions'] = $request->conditions;
        }
        if ($request->has('timer')) {
            $validatedData['timer'] = $request->timer;
        }
        if ($request->has('hide_unit')) {
            $validatedData['hide_unit'] = (bool) $request->boolean('hide_unit');
        }
        if ($request->has('allow_sorting')) {
            $validatedData['allow_sorting'] = (bool) $request->boolean('allow_sorting');
        }
        $mainTest = MainTest::create($validatedData);

        return new MainTestResource($mainTest->load('container'));
    }

    public function show(MainTest $mainTest)
    {
        return new MainTestResource($mainTest->load('container'));
    }

    public function update(Request $request, MainTest $mainTest)
    {
        $validatedData = $request->validate([
            'main_test_name' => ['sometimes', 'required', 'string'],
            'pack_id' => 'nullable|integer',
            'pageBreak' => 'sometimes|required|boolean',
            'container_id' => 'sometimes|required|exists:containers,id',
            'price' => 'nullable|numeric|min:0',
            'divided' => 'sometimes|required|boolean',
            'available' => 'sometimes|required|boolean',
            'is_special_test' => 'sometimes|boolean',
            'conditions' => 'sometimes|string',
            'timer' => 'sometimes|integer',
            'hide_unit' => 'sometimes|boolean',
            'allow_sorting' => 'sometimes|boolean',
            'default_comment' => 'sometimes|nullable|string',
        ]);
        if ($request->has('is_special_test')) {
            $validatedData['is_special_test'] = (bool) $request->boolean('is_special_test');
        }
        if ($request->has('conditions')) {
            $validatedData['conditions'] = $request->conditions;
        }
        if ($request->has('timer')) {
            $validatedData['timer'] = $request->timer;
        }
        if ($request->has('hide_unit')) {
            $validatedData['hide_unit'] = (bool) $request->boolean('hide_unit');
        }
        if ($request->has('allow_sorting')) {
            $validatedData['allow_sorting'] = (bool) $request->boolean('allow_sorting');
        }
        $mainTest->update($validatedData);

        return new MainTestResource($mainTest->load('container'));
    }

    public function destroy(MainTest $mainTest)
    {
        // Add checks: e.g., cannot delete if linked to lab_requests or child_tests
        if ($mainTest->labRequests()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف هذا الفحص لارتباطه بطلبات أو فحوصات فرعية.'], 403);
        }
        $mainTest->delete();

        return response()->json(null, 204);
    }

    // app/Http/Controllers/Api/MainTestController.php
    public function batchUpdatePrices(Request $request)
    {
        // $this->authorize('edit lab_tests'); // Or a specific 'update lab_test_prices'
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|integer|exists:main_tests,id',
            'updates.*.price' => 'required|numeric|min:0',
        ]);

        // return $validated;

        DB::beginTransaction();
        try {
            foreach ($validated['updates'] as $update) {
                MainTest::where('id', $update['id'])->update(['price' => $update['price']]);
            }
            DB::commit();

            return response()->json(['message' => 'تم تحديث أسعار الفحوصات بنجاح.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'فشل تحديث الأسعار.', 'error' => $e->getMessage()], 500);
        }
    }

    // app/Http/Controllers/Api/MainTestController.php
    public function batchDeleteTests(Request $request)
    {
        // $this->authorize('delete lab_tests');
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:main_tests,id',
        ]);

        $deletedCount = 0;
        $errors = [];
        DB::beginTransaction();
        try {
            foreach ($validated['ids'] as $id) {
                $mainTest = MainTest::find($id);
                if ($mainTest) {
                    // Dependency check (example)
                    if ($mainTest->labRequests()->exists()) {
                        $errors[] = "لا يمكن حذف الفحص '{$mainTest->main_test_name}' لارتباطه ببيانات أخرى.";

                        continue;
                    }
                    if ($mainTest->delete()) {
                        // Delete child tests if delete was successful
                        $mainTest->childTests()->delete();
                    }
                    $deletedCount++;
                }
            }
            DB::commit();
            $message = "تم حذف {$deletedCount} فحص بنجاح.";
            if (! empty($errors)) {
                $message .= ' الأخطاء: '.implode(', ', $errors);
            }

            return response()->json(['message' => $message, 'deleted_count' => $deletedCount, 'errors' => $errors]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'فشل حذف الفحوصات.', 'error' => $e->getMessage()], 500);
        }
    }

    public function generatePriceListPdf(Request $request)
    {
        try {
            // Get all main tests with their containers
            $mainTests = MainTest::with('container')
                ->where('available', true)
                ->orderBy('id')
                ->get();

            // Transform data for PDF
            $testsData = $mainTests->map(function ($test) {
                return [
                    'id' => $test->id,
                    'main_test_name' => $test->main_test_name,
                    'container_name' => $test->container ? $test->container->container_name : null,
                    'price' => $test->price ?? 0,
                ];
            })->toArray();

            // Get hospital name from config or use default
            $hospitalName = config('app.name', 'مستشفى جوادة');

            // Generate PDF
            $pdf = new PriceList($testsData, $hospitalName);
            $pdfContent = $pdf->generate();

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="price-list-'.date('Y-m-d').'.pdf"');

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطأ في إنشاء قائمة الأسعار',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export all main tests with their prices to an Excel file that can be
     * edited and re-imported via importPricesExcel().
     */
    public function exportPricesExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $query = MainTest::with('container:id,container_name')->orderBy('id');
        if ($request->filled('search')) {
            $query->where('main_test_name', 'LIKE', '%'.$request->search.'%');
        }
        $mainTests = $query->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Main Tests Prices');

        $headers = ['ID', 'اسم التحليل', 'الوعاء', 'السعر'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNumber = 2;
        foreach ($mainTests as $test) {
            $sheet->setCellValue('A'.$rowNumber, $test->id);
            $sheet->setCellValue('B'.$rowNumber, $test->main_test_name);
            $sheet->setCellValue('C'.$rowNumber, $test->container?->container_name ?? '');
            $sheet->setCellValue('D'.$rowNumber, (float) ($test->price ?? 0));
            $rowNumber++;
        }

        $lastRow = $rowNumber - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle('D2:D'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(45);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(15);

        $fileName = 'main-tests-prices-'.date('Y-m-d').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'mt_prices_');
        $writer->save($tempFile);
        $spreadsheet->disconnectWorksheets();

        return response()->streamDownload(function () use ($tempFile) {
            if (file_exists($tempFile)) {
                readfile($tempFile);
                unlink($tempFile);
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Import an Excel file (as produced by exportPricesExcel) and update the
     * price of each main test matched by the ID column.
     */
    public function importPricesExcel(Request $request): \Illuminate\Http\JsonResponse
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

        $updatedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue; // header row
                }
                $id = isset($row[0]) ? trim((string) $row[0]) : '';
                $priceRaw = isset($row[3]) ? trim((string) $row[3]) : '';

                if ($id === '' && $priceRaw === '') {
                    continue; // empty line
                }
                if (! ctype_digit($id)) {
                    $errors[] = 'السطر '.($index + 1).": معرّف غير صالح ({$id}).";

                    continue;
                }
                $price = str_replace(',', '', $priceRaw);
                if ($priceRaw === '' || ! is_numeric($price) || (float) $price < 0) {
                    $errors[] = 'السطر '.($index + 1).": سعر غير صالح ({$priceRaw}).";

                    continue;
                }

                $affected = MainTest::where('id', (int) $id)->update(['price' => (float) $price]);
                if ($affected === 0) {
                    $errors[] = 'السطر '.($index + 1).": لا يوجد تحليل بالمعرّف {$id}.";

                    continue;
                }
                $updatedCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'فشل استيراد الأسعار.', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => "تم تحديث أسعار {$updatedCount} تحليل.".(count($errors) > 0 ? ' مع بعض التحذيرات.' : ''),
            'updated_count' => $updatedCount,
            'errors' => $errors,
        ]);
    }
}
