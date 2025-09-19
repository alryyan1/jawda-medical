<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display queue status information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Queue Status Information');
        $this->line('========================');

        // Count pending jobs
        $pendingJobs = DB::table('jobs')->count();
        $this->line("Pending Jobs: {$pendingJobs}");

        // Count failed jobs
        $failedJobs = DB::table('failed_jobs')->count();
        $this->line("Failed Jobs: {$failedJobs}");

        // Show recent jobs
        if ($pendingJobs > 0) {
            $this->line('');
            $this->info('Recent Pending Jobs:');
            $recentJobs = DB::table('jobs')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'queue', 'payload', 'created_at']);

            foreach ($recentJobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobName = $payload['displayName'] ?? 'Unknown Job';
                $this->line("- {$jobName} (Queue: {$job->queue}, Created: {$job->created_at})");
            }
        }

        // Show recent failed jobs
        if ($failedJobs > 0) {
            $this->line('');
            $this->error('Recent Failed Jobs:');
            $recentFailedJobs = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(3)
                ->get(['id', 'queue', 'payload', 'exception', 'failed_at']);

            foreach ($recentFailedJobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobName = $payload['displayName'] ?? 'Unknown Job';
                $this->line("- {$jobName} (Queue: {$job->queue}, Failed: {$job->failed_at})");
            }
        }

        $this->line('');
        $this->info('Commands:');
        $this->line('- Start worker: php artisan queue:work');
        $this->line('- Retry failed jobs: php artisan queue:retry all');
        $this->line('- Clear failed jobs: php artisan queue:flush');
    }
}