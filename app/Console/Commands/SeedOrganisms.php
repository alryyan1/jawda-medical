<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\SuggestedOrganismSeeder;

class SeedOrganisms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organisms:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the suggested organisms database with common organisms and their antibiotic profiles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Seeding suggested organisms...');
        
        $seeder = new SuggestedOrganismSeeder();
        $seeder->run();
        
        $this->info('Suggested organisms seeded successfully!');
        
        return Command::SUCCESS;
    }
}