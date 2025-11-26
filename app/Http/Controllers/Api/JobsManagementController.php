<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class JobsManagementController extends Controller
{
    /**
     * Get list of failed jobs
     */
    public function getFailedJobs(Request $request): JsonResponse
    {
        try {
            $perPage = (int) ($request->get('per_page', 50));
            $page = (int) ($request->get('page', 1));

            $failedJobs = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $jobs = $failedJobs->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $jobName = $payload['displayName'] ?? 'Unknown Job';
                
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'job_name' => $jobName,
                    'connection' => $job->connection,
                    'exception' => $job->exception,
                    'failed_at' => $job->failed_at,
                    'payload' => $payload,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $jobs,
                'meta' => [
                    'current_page' => $failedJobs->currentPage(),
                    'last_page' => $failedJobs->lastPage(),
                    'per_page' => $failedJobs->perPage(),
                    'total' => $failedJobs->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching failed jobs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch failed jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of pending jobs
     */
    public function getPendingJobs(Request $request): JsonResponse
    {
        try {
            $perPage = (int) ($request->get('per_page', 50));
            $page = (int) ($request->get('page', 1));
            $queue = $request->get('queue');

            $query = DB::table('jobs')
                ->orderBy('created_at', 'desc');

            if ($queue) {
                $query->where('queue', $queue);
            }

            $pendingJobs = $query->paginate($perPage, ['*'], 'page', $page);

            $jobs = $pendingJobs->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $jobName = $payload['displayName'] ?? 'Unknown Job';
                
                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'job_name' => $jobName,
                    'attempts' => $job->attempts,
                    'reserved_at' => $job->reserved_at,
                    'available_at' => $job->available_at,
                    'created_at' => $job->created_at,
                    'payload' => $payload,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $jobs,
                'meta' => [
                    'current_page' => $pendingJobs->currentPage(),
                    'last_page' => $pendingJobs->lastPage(),
                    'per_page' => $pendingJobs->perPage(),
                    'total' => $pendingJobs->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching pending jobs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get jobs statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $pendingCount = DB::table('jobs')->count();
            $failedCount = DB::table('failed_jobs')->count();
            
            // Get counts by queue
            $pendingByQueue = DB::table('jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->get()
                ->pluck('count', 'queue');

            $failedByQueue = DB::table('failed_jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->get()
                ->pluck('count', 'queue');

            return response()->json([
                'success' => true,
                'data' => [
                    'pending_total' => $pendingCount,
                    'failed_total' => $failedCount,
                    'pending_by_queue' => $pendingByQueue,
                    'failed_by_queue' => $failedByQueue,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching jobs statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry a failed job
     */
    public function retryJob(Request $request, $id): JsonResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => $id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Job retried successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Error retrying job {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry all failed jobs
     */
    public function retryAllJobs(): JsonResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => 'all']);
            
            return response()->json([
                'success' => true,
                'message' => 'All failed jobs retried successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrying all jobs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry all jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a failed job
     */
    public function deleteFailedJob($id): JsonResponse
    {
        try {
            $deleted = DB::table('failed_jobs')->where('id', $id)->delete();
            
            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Failed job deleted successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed job not found',
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error("Error deleting failed job {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete all failed jobs
     */
    public function deleteAllFailedJobs(): JsonResponse
    {
        try {
            $count = DB::table('failed_jobs')->count();
            DB::table('failed_jobs')->truncate();
            
            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} failed jobs",
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting all failed jobs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete all jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete failed jobs by queue name
     */
    public function deleteFailedJobsByQueue(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'queue' => 'required|string',
            ]);

            $count = DB::table('failed_jobs')
                ->where('queue', $validated['queue'])
                ->count();

            DB::table('failed_jobs')
                ->where('queue', $validated['queue'])
                ->delete();
            
            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} failed jobs from queue '{$validated['queue']}'",
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting failed jobs by queue: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete jobs by queue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete multiple failed jobs by IDs
     */
    public function deleteFailedJobsByIds(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|integer',
            ]);

            $count = DB::table('failed_jobs')
                ->whereIn('id', $validated['ids'])
                ->count();

            DB::table('failed_jobs')
                ->whereIn('id', $validated['ids'])
                ->delete();
            
            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} failed jobs",
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting failed jobs by IDs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available queues
     */
    public function getQueues(): JsonResponse
    {
        try {
            $queues = DB::table('jobs')
                ->select('queue')
                ->distinct()
                ->pluck('queue')
                ->merge(
                    DB::table('failed_jobs')
                        ->select('queue')
                        ->distinct()
                        ->pluck('queue')
                )
                ->unique()
                ->values();

            return response()->json([
                'success' => true,
                'data' => $queues,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching queues: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch queues',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a pending job by ID
     */
    public function deletePendingJob($id): JsonResponse
    {
        try {
            $deleted = DB::table('jobs')->where('id', $id)->delete();
            
            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pending job deleted successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Pending job not found',
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error("Error deleting pending job {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete all pending jobs
     */
    public function deleteAllPendingJobs(): JsonResponse
    {
        try {
            $count = DB::table('jobs')->count();
            DB::table('jobs')->truncate();
            
            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} pending jobs",
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting all pending jobs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete all jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete pending jobs by queue name
     */
    public function deletePendingJobsByQueue(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'queue' => 'required|string',
            ]);

            $count = DB::table('jobs')
                ->where('queue', $validated['queue'])
                ->count();

            DB::table('jobs')
                ->where('queue', $validated['queue'])
                ->delete();
            
            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} pending jobs from queue '{$validated['queue']}'",
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting pending jobs by queue: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete jobs by queue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete multiple pending jobs by IDs
     */
    public function deletePendingJobsByIds(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|integer',
            ]);

            $count = DB::table('jobs')
                ->whereIn('id', $validated['ids'])
                ->count();

            DB::table('jobs')
                ->whereIn('id', $validated['ids'])
                ->delete();
            
            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} pending jobs",
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting pending jobs by IDs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

