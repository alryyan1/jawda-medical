<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Server-to-server bridge to the jawda-inpatient admission system's public
 * API. Used to look up an admission's companion/emergency contact when a
 * dose is recorded as given.
 */
class JawdaInpatientClient
{
    private readonly string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.jawda_inpatient.base_url'), '/');
    }

    /**
     * @return array{name: ?string, relationship: ?string, phone: ?string}
     */
    public function getCompanionContact(int $admissionId): array
    {
        $response = Http::acceptJson()
            ->timeout(5)
            ->get("{$this->baseUrl}/public/admissions/{$admissionId}/companion-contact")
            ->throw();

        return $response->json();
    }
}
