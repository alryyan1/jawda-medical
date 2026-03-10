<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;

class FirebaseDebugController extends Controller
{
    /**
     * Simple page to check Firebase configuration and connection.
     */
    public function index()
    {
        $issues = FirebaseService::validateConfig();
        $ok = FirebaseService::testConnection();
        $projectId = FirebaseService::getProjectId();
        $collections = null;
        $collectionsError = null;
        if ($ok && $projectId) {
            $collections = FirebaseService::listRootCollectionIds();
            if ($collections === null) {
                $collectionsError = 'Could not list collections (check Firestore permissions).';
            }
        }

        return response()->view('firebase-check', [
            'ok' => $ok,
            'issues' => $issues,
            'projectId' => $projectId,
            'collections' => $collections ?? [],
            'collectionsError' => $collectionsError,
        ]);
    }
}

