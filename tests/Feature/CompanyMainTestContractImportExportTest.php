<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\MainTest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CompanyMainTestContractImportExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeMainTest(string $name): MainTest
    {
        $container = Container::firstOrCreate(['container_name' => 'Tube']);

        return MainTest::create([
            'main_test_name' => $name,
            'container_id' => $container->id,
            'price' => 100,
            'available' => true,
        ]);
    }

    private function makeCompanyWithContract(MainTest $test, array $pivot = []): Company
    {
        $company = Company::create([
            'name' => 'Acme Insurance',
            'lab_endurance' => 0,
            'service_endurance' => 0,
            'status' => true,
        ]);
        $company->contractedMainTests()->attach($test->id, array_merge([
            'status' => true,
            'price' => 100,
            'approve' => true,
            'endurance_static' => 0,
            'endurance_percentage' => 0,
            'use_static' => false,
        ], $pivot));

        return $company;
    }

    private function buildFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['ID', 'اسم الفحص', 'السعر', 'نسبة التحمل %', 'التحمل الثابت'], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'cmt_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'contracts.xlsx', null, null, true);
    }

    public function test_export_returns_an_excel_file(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $test = $this->makeMainTest('CBC');
        $company = $this->makeCompanyWithContract($test);

        $response = $this->get("/api/companies/{$company->id}/contracted-main-tests/export-excel");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_import_updates_pivot_price_and_endurance(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $test = $this->makeMainTest('CBC');
        $company = $this->makeCompanyWithContract($test);

        $file = $this->buildFile([[$test->id, 'CBC', 175.5, 30, 5]]);

        $response = $this->post("/api/companies/{$company->id}/contracted-main-tests/import-excel", ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('updated_count', 1);
        $pivot = $company->contractedMainTests()->where('main_tests.id', $test->id)->first()->pivot;
        $this->assertEquals(175.5, $pivot->price);
        $this->assertEquals(30, $pivot->endurance_percentage);
        $this->assertEquals(5, $pivot->endurance_static);
    }

    public function test_import_skips_tests_not_contracted_by_the_company(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $contracted = $this->makeMainTest('CBC');
        $other = $this->makeMainTest('Lipid');
        $company = $this->makeCompanyWithContract($contracted);

        $file = $this->buildFile([
            [$contracted->id, 'CBC', 150, '', ''],
            [$other->id, 'Lipid', 300, '', ''],
        ]);

        $response = $this->post("/api/companies/{$company->id}/contracted-main-tests/import-excel", ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('updated_count', 1);
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_import_rejects_non_spreadsheet_upload(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $test = $this->makeMainTest('CBC');
        $company = $this->makeCompanyWithContract($test);

        $response = $this->post("/api/companies/{$company->id}/contracted-main-tests/import-excel", [
            'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(422);
    }
}
