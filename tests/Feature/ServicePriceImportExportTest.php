<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ServicePriceImportExportTest extends TestCase
{
    use RefreshDatabase;

    private function buildPricesFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['ID', 'اسم الخدمة', 'المجموعة', 'السعر'], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'svc_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'prices.xlsx', null, null, true);
    }

    public function test_export_returns_an_excel_file(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Service::factory()->create();

        $response = $this->get('/api/services/export-prices-excel');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_import_updates_prices_by_id(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $a = Service::factory()->create(['price' => 100]);
        $b = Service::factory()->create(['price' => 200]);

        $file = $this->buildPricesFile([
            [$a->id, $a->name, 'G', 150.5],
            [$b->id, $b->name, 'G', 250],
        ]);

        $response = $this->post('/api/services/import-prices-excel', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('updated_count', 2);
        $this->assertEquals(150.5, $a->fresh()->price);
        $this->assertEquals(250, $b->fresh()->price);
    }

    public function test_import_reports_rows_with_unknown_ids(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $a = Service::factory()->create(['price' => 100]);

        $file = $this->buildPricesFile([
            [$a->id, $a->name, 'G', 150],
            [999999, 'Ghost', 'G', 300],
        ]);

        $response = $this->post('/api/services/import-prices-excel', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('updated_count', 1);
        $this->assertNotEmpty($response->json('errors'));
        $this->assertEquals(150, $a->fresh()->price);
    }

    public function test_import_rejects_non_spreadsheet_upload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/services/import-prices-excel', [
            'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(422);
    }
}
