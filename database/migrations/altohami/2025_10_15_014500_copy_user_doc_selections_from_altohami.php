<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Copy user_doc_selections from altohamil connection into local DB
        $source = DB::connection('altohamil')->table('user_doc_selections');

        $total = $source->count();
        $barSize = 1000;

        $source->orderBy('user_id')->orderBy('doc_id')->chunk($barSize, function ($rows) {
            $inserts = [];
            foreach ($rows as $row) {
                $inserts[] = [
                    'user_id' => (int)($row->user_id ?? 0),
                    'doc_id' => (int)($row->doc_id ?? 0),
                    'active' => (int)($row->active ?? 0),
                    'fav_service' => isset($row->fav_service) ? (int)$row->fav_service : null,
                ];
            }

            if (!empty($inserts)) {
                // Use insertOrIgnore to respect composite PK and avoid duplicates
                DB::table('user_doc_selections')->insertOrIgnore($inserts);
            }
        });
    }

    public function down(): void
    {
        // No-op: We won't delete copied data on rollback to avoid data loss
    }
};






