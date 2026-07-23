<?php

namespace Tests\Feature;

use App\Models\DoctorVisit;
use App\Models\MedicalAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicalAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uploads_an_attachment_for_a_visit(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $visit = DoctorVisit::factory()->create();
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/doctor-visits/{$visit->id}/attachments", [
            'file' => $file,
            'category' => 'lab_result',
            'title' => 'Blood Test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Blood Test')
            ->assertJsonPath('data.category', 'lab_result');

        $this->assertDatabaseHas('medical_attachments', [
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'title' => 'Blood Test',
        ]);

        $attachment = MedicalAttachment::first();
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    public function test_it_rejects_a_disallowed_mime_type(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $visit = DoctorVisit::factory()->create();
        $file = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');

        $response = $this->postJson("/api/doctor-visits/{$visit->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_it_deletes_an_attachment_and_its_stored_file(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $visit = DoctorVisit::factory()->create();
        $file = UploadedFile::fake()->create('report.pdf', 50, 'application/pdf');
        $file->storeAs('public/medical-attachments', 'test-file.pdf');

        $attachment = MedicalAttachment::create([
            'patient_id' => $visit->patient_id,
            'doctor_visit_id' => $visit->id,
            'category' => 'other',
            'original_filename' => 'report.pdf',
            'file_path' => 'medical-attachments/test-file.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 50,
        ]);

        $response = $this->deleteJson("/api/attachments/{$attachment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('medical_attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing('medical-attachments/test-file.pdf');
    }
}
