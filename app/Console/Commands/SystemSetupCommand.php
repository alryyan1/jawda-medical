<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class SystemSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:setup 
                            {--fresh : Wipe the database before running migrations}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run system setup migrations and seeders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (App::environment('production') && ! $this->option('force')) {
            $this->error('You are in production! Use --force to run this command.');
            return 1;
        }

        $this->info('Starting system setup...');

        $systemSetupPath = 'database/migrations/system_setup';
        $migrationsPath = 'database/migrations';

        // Run Migrations
        if ($this->option('fresh')) {
            if ($this->confirm('This will wipe all data in the database. Are you sure?', true)) {
                $this->info('Wiping database and running fresh migrations...');
                $this->call('migrate:fresh', [
                    '--path' => $systemSetupPath,
                    '--force' => true,
                ]);
                $this->info('Running root migrations...');
                $this->call('migrate', [
                    '--path' => $migrationsPath,
                    '--force' => true,
                ]);
            } else {
                $this->info('Operation cancelled.');
                return 0;
            }
        } else {
            $this->info('Running system setup migrations...');
            $this->call('migrate', [
                '--path' => $systemSetupPath,
                '--force' => true,
            ]);
            $this->info('Running database migrations...');
            $this->call('migrate', [
                '--path' => $migrationsPath,
                '--force' => true,
            ]);
        }

        // Run Seeders
        $this->info('Running seeders...');
        try {
            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\SystemSetupSeeder',
                '--force' => true,
            ]);
            $this->info('Seeding completed.');
        } catch (\Exception $e) {
            $this->error('Seeding failed: ' . $e->getMessage());
            return 1;
        }

        $this->info('System setup completed successfully!');
        return 0;
    }
}
