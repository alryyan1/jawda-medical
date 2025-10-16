<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class SyncCompanyEnduranceFromAltohami extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'altohami:sync-company-endurance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync companies lab_endurance and service_endurance from altohami.insurance.cover_perc';

    public function handle(): int
    {
        $this->info('Starting sync from altohami.insurance...');

        // Build a direct connection config (do not rely on .env as requested)
        $altohamiConfig = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'altohami',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];

        // Register a temporary connection and purge previous one if exists
        Config::set('database.connections.altohami_temp', $altohamiConfig);
        DB::purge('altohami_temp');

        try {
            $insurance = DB::connection('altohami_temp')
                ->table('insurance')
                ->select(['insu_id', 'cover_perc'])
                ->get();

            if ($insurance->isEmpty()) {
                $this->warn('No rows found in altohami.insurance');
                return self::SUCCESS;
            }

            $updated = 0;
            DB::beginTransaction();
            foreach ($insurance as $row) {
                $companyId = (int)($row->insu_id);
                $cover = is_null($row->cover_perc) ? null : (float)$row->cover_perc;

                // If cover is null, skip updating to avoid overwriting with null
                if ($cover === null) {
                    continue;
                }

                $affected = DB::table('companies')
                    ->where('id', $companyId)
                    ->update([
                        'lab_endurance' => $cover,
                        'service_endurance' => $cover,
                        'updated_at' => now(),
                    ]);

                $updated += $affected;
            }
            DB::commit();

            $this->info("Updated {$updated} company rows.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}


