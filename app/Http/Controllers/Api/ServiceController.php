<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ServiceController extends Controller
{
    public function indexList()
    {
        $services = Service::select('id', 'name')
            ->where('activate', true)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $services]);
    }

    public function index(Request $request)
    {
        $query = Service::with('serviceGroup')->withoutTrashed(); // Eager load by default

        // Filter by search term (service name)
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%'.$request->search.'%');
        }

        // Filter by service group ID
        if ($request->filled('service_group_id')) {
            $query->where('service_group_id', $request->service_group_id);
        }

        $services = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 15));

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
            'has_cost' => 'sometimes|boolean',
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
            'has_cost' => 'sometimes|boolean',
        ]);
        $service->update($validatedData);

        return new ServiceResource($service->load('serviceGroup'));
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return response()->json(null, 204);
    }

    public function trashed()
    {
        $services = Service::onlyTrashed()
            ->with('serviceGroup')
            ->orderByDesc('deleted_at')
            ->get();

        return ServiceResource::collection($services);
    }

    public function restore(string $id)
    {
        $service = Service::withTrashed()->findOrFail($id);

        if (! $service->trashed()) {
            return response()->json(['message' => 'Service is not deleted.'], 400);
        }

        $service->restore();

        return response()->json([
            'message' => 'Service restored successfully.',
            'data' => new ServiceResource($service->load('serviceGroup')),
        ]);
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

    /**
     * Export all services with their prices to an Excel file that can be
     * edited and re-imported via importPricesExcel().
     */
    public function exportPricesExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'service_group_id' => 'nullable',
        ]);

        $query = Service::with('serviceGroup:id,name')->withoutTrashed()->orderBy('id');
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%'.$request->search.'%');
        }
        if ($request->filled('service_group_id') && $request->service_group_id !== 'all') {
            $query->where('service_group_id', $request->service_group_id);
        }
        $services = $query->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Services Prices');

        $headers = ['ID', 'اسم الخدمة', 'المجموعة', 'السعر'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNumber = 2;
        foreach ($services as $service) {
            $sheet->setCellValue('A'.$rowNumber, $service->id);
            $sheet->setCellValue('B'.$rowNumber, $service->name);
            $sheet->setCellValue('C'.$rowNumber, $service->serviceGroup?->name ?? '');
            $sheet->setCellValue('D'.$rowNumber, (float) ($service->price ?? 0));
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

        $fileName = 'services-prices-'.date('Y-m-d').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'svc_prices_');
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
     * price of each service matched by the ID column.
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

                $affected = Service::whereKey((int) $id)->update(['price' => (float) $price]);
                if ($affected === 0) {
                    $errors[] = 'السطر '.($index + 1).": لا توجد خدمة بالمعرّف {$id}.";

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
            'message' => "تم تحديث أسعار {$updatedCount} خدمة.".(count($errors) > 0 ? ' مع بعض التحذيرات.' : ''),
            'updated_count' => $updatedCount,
            'errors' => $errors,
        ]);
    }
}
