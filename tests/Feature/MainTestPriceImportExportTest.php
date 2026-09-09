<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\MainTest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MainTestPriceImportExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeMainTest(string $name, float $price): MainTest
    {
        $container = Container::firstOrCreate(['container_name' => 'Tube']);

        return MainTest::create([
            'main_test_name' => $name,
            'container_id' => $container->id,
            'price' => $price,
            'available' => true,
        ]);
    }

    private function buildPricesFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['ID', 'اسم التحليل', 'الوعاء', 'السعر'], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'test_prices_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'prices.xlsx', null, null, true);
    }

    public function test_export_returns_an_excel_file(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->makeMainTest('CBC', 100);

        $response = $this->get('/api/main-tests/export-prices-excel');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_import_updates_prices_by_id(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $cbc = $this->makeMainTest('CBC', 100);
        $lipid = $this->makeMainTest('Lipid', 200);

        $file = $this->buildPricesFile([
            [$cbc->id, 'CBC', 'Tube', 150.5],
            [$lipid->id, 'Lipid', 'Tube', 250],
        ]);

        $response = $this->post('/api/main-tests/import-prices-excel', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('updated_count', 2);
        $this->assertEquals(150.5, $cbc->fresh()->price);
        $this->assertEquals(250, $lipid->fresh()->price);
    }

    public function test_import_reports_rows_with_unknown_ids(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $cbc = $this->makeMainTest('CBC', 100);

        $file = $this->buildPricesFile([
            [$cbc->id, 'CBC', 'Tube', 150],
            [999999, 'Ghost', 'Tube', 300],
        ]);

        $response = $this->post('/api/main-tests/import-prices-excel', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('updated_count', 1);
        $this->assertNotEmpty($response->json('errors'));
        $this->assertEquals(150, $cbc->fresh()->price);
    }

    public function test_import_rejects_non_spreadsheet_upload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/main-tests/import-prices-excel', [
            'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(422);
    }
}
