<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class QueueWorkerController extends Controller
{
    private $pidFile;

    public function __construct()
    {
        $this->pidFile = storage_path('app/queue-worker.pid');
    }

    /**
     * Get queue worker status - fast check using wmic/pgrep
     */
    public function status(): JsonResponse
    {
        try {
            // Find ALL running processes
            $pids = $this->findAllQueueWorkers();
            
            if (!empty($pids)) {
                // Save first PID
                $this->saveWorkerPid($pids[0]);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_running' => true,
                        'pid' => $pids[0],
                        'status' => 'running',
                        'worker_count' => count($pids)
                    ]
                ]);
            }
            
            // Clean up stale PID file
            if (File::exists($this->pidFile)) {
                File::delete($this->pidFile);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_running' => false,
                    'pid' => null,
                    'status' => 'stopped',
                    'worker_count' => 0
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking queue worker status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking worker status',
                'data' => [
                    'is_running' => false,
                    'status' => 'stopped'
                ]
            ]);
        }
    }

    /**
     * Start queue worker
     */
    public function start(): JsonResponse
    {
        try {
            // Check if already running
            $existingPids = $this->findAllQueueWorkers();
            if (!empty($existingPids)) {
                $this->saveWorkerPid($existingPids[0]);
                return response()->json([
                    'success' => true,
                    'message' => 'Queue worker is already running',
                    'data' => [
                        'is_running' => true,
                        'pid' => $existingPids[0],
                        'status' => 'running',
                        'worker_count' => count($existingPids)
                    ]
                ]);
            }

            // Clean up old PID file
            if (File::exists($this->pidFile)) {
                File::delete($this->pidFile);
            }

            $pid = null;
            $artisanPath = base_path('artisan');
            $logPath = storage_path('logs/queue-worker.log');
            $queues = 'resultsUpload,ServicePaymentCancel,notifications,whatsapp,default,sms';
            
            if (PHP_OS_FAMILY === 'Windows') {
                // Use popen - fastest method, doesn't block
                $command = 'start /B php "' . $artisanPath . '" queue:work --queue=' . $queues . ' --sleep=1 --tries=3 --timeout=300 > "' . $logPath . '" 2>&1';
                pclose(popen($command, 'r'));
                
                // Quick check - 0.5 seconds
                usleep(500000);
                $pids = $this->findAllQueueWorkers();
                $pid = !empty($pids) ? $pids[0] : null;
            } else {
                // On Linux/Unix, use nohup
                $command = "nohup php {$artisanPath} queue:work --queue={$queues} --sleep=1 --tries=3 --timeout=300 > {$logPath} 2>&1 & echo $!";
                $output = shell_exec($command);
                $pid = (int)trim($output);
            }
            
            if ($pid && $pid > 0) {
                $this->saveWorkerPid($pid);
                Log::info("Queue worker started with PID: {$pid}");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Queue worker started successfully',
                    'data' => [
                        'is_running' => true,
                        'pid' => $pid,
                        'status' => 'running',
                        'worker_count' => 1
                    ]
                ]);
            }
            
            // Could not start
            return response()->json([
                'success' => false,
                'message' => 'Failed to start queue worker',
                'data' => [
                    'is_running' => false,
                    'status' => 'stopped'
                ]
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Error starting queue worker: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start queue worker: ' . $e->getMessage(),
                'data' => [
                    'is_running' => false,
                    'status' => 'stopped'
                ]
            ], 500);
        }
    }

    /**
     * Stop queue worker - kills ALL queue workers
     */
    public function stop(): JsonResponse
    {
        try {
            // Find ALL running processes
            $pids = $this->findAllQueueWorkers();
            
            if (empty($pids)) {
                // Clean up PID file
                if (File::exists($this->pidFile)) {
                    File::delete($this->pidFile);
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Queue worker is not running',
                    'data' => [
                        'is_running' => false,
                        'status' => 'stopped'
                    ]
                ]);
            }

            // Kill ALL processes
            $killedCount = 0;
            foreach ($pids as $pid) {
                $this->killProcess($pid, true);
                $killedCount++;
            }
            
            // Clean up PID file
            if (File::exists($this->pidFile)) {
                File::delete($this->pidFile);
            }
            
            // Quick verify - 0.3 seconds
            usleep(300000);
            $stillRunning = $this->findAllQueueWorkers();
            
            if (!empty($stillRunning)) {
                // Try again with remaining processes
                foreach ($stillRunning as $pid) {
                    $this->killProcess($pid, true);
                }
                usleep(200000);
                $stillRunning = $this->findAllQueueWorkers();
            }
            
            if (!empty($stillRunning)) {
                return response()->json([
                    'success' => false,
                    'message' => "Failed to stop some queue workers. PIDs still running: " . implode(', ', $stillRunning),
                    'data' => [
                        'is_running' => true,
                        'pid' => $stillRunning[0],
                        'status' => 'running'
                    ]
                ], 500);
            }

            Log::info("Queue worker(s) stopped. Killed {$killedCount} process(es)");
            
            return response()->json([
                'success' => true,
                'message' => "Stopped {$killedCount} queue worker(s)",
                'data' => [
                    'is_running' => false,
                    'status' => 'stopped'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error stopping queue worker: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop queue worker: ' . $e->getMessage(),
                'data' => [
                    'is_running' => false,
                    'status' => 'stopped'
                ]
            ], 500);
        }
    }

    /**
     * Toggle queue worker (start if stopped, stop if running)
     */
    public function toggle(): JsonResponse
    {
        $pid = $this->findRunningQueueWorker();
        if ($pid) {
            return $this->stop();
        } else {
            return $this->start();
        }
    }
    
    /**
     * Find running queue worker process - returns first valid PID or from PID file
     */
    private function findRunningQueueWorker(): ?int
    {
        // First check if we have a saved PID that's still running
        $savedPid = $this->getWorkerPid();
        if ($savedPid && $this->isProcessRunning($savedPid)) {
            return $savedPid;
        }
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Use wmic to find the process - get ALL PIDs
            $output = shell_exec('wmic process where "commandline like \'%queue:work%\' and name=\'php.exe\'" get processid /format:value 2>nul');
            if ($output && preg_match_all('/ProcessId=(\d+)/i', $output, $matches)) {
                foreach ($matches[1] as $pidStr) {
                    $pid = (int)$pidStr;
                    if ($pid > 100) {
                        return $pid;
                    }
                }
            }
            return null;
        } else {
            // On Linux/Unix, use pgrep - get first one
            $output = shell_exec('pgrep -f "queue:work" 2>/dev/null | head -1');
            if ($output) {
                $pid = (int)trim($output);
                return $pid > 0 ? $pid : null;
            }
            return null;
        }
    }
    
    /**
     * Find ALL running queue worker processes
     */
    private function findAllQueueWorkers(): array
    {
        $pids = [];
        
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('wmic process where "commandline like \'%queue:work%\' and name=\'php.exe\'" get processid /format:value 2>nul');
            if ($output && preg_match_all('/ProcessId=(\d+)/i', $output, $matches)) {
                foreach ($matches[1] as $pidStr) {
                    $pid = (int)$pidStr;
                    if ($pid > 100) {
                        $pids[] = $pid;
                    }
                }
            }
        } else {
            $output = shell_exec('pgrep -f "queue:work" 2>/dev/null');
            if ($output) {
                foreach (explode("\n", trim($output)) as $line) {
                    $pid = (int)trim($line);
                    if ($pid > 0) {
                        $pids[] = $pid;
                    }
                }
            }
        }
        
        return $pids;
    }

    /**
     * Get worker PID from file
     */
    private function getWorkerPid(): ?int
    {
        if (!File::exists($this->pidFile)) {
            return null;
        }

        $pid = (int) trim(File::get($this->pidFile));
        return $pid > 0 ? $pid : null;
    }

    /**
     * Save worker PID to file
     */
    private function saveWorkerPid(int $pid): void
    {
        // Ensure the directory exists before writing
        $directory = dirname($this->pidFile);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        File::put($this->pidFile, (string)$pid);
    }

    /**
     * Check if a specific process ID is running
     */
    private function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Use tasklist to check if process exists
            $command = "tasklist /FI \"PID eq {$pid}\" /NH 2>NUL";
            $output = shell_exec($command);
            
            // tasklist returns "INFO: No tasks are running..." if process not found
            // or returns the process info if found
            return $output && !str_contains($output, 'INFO:') && str_contains($output, (string)$pid);
        } else {
            // On Linux/Unix, use kill -0 to check if process exists
            $command = "kill -0 {$pid} 2>/dev/null && echo 'running' || echo 'stopped'";
            $output = trim(shell_exec($command));
            return $output === 'running';
        }
    }

    /**
     * Kill a process by PID
     */
    private function killProcess(int $pid, bool $force = false): bool
    {
        if ($pid <= 0) {
            return false;
        }
        
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Use taskkill
                $forceFlag = $force ? '/F' : '';
                $command = "taskkill /PID {$pid} {$forceFlag} 2>&1";
                $output = shell_exec($command);
                Log::info("Taskkill output for PID {$pid}: " . trim($output));
                
                return str_contains($output, 'SUCCESS') || str_contains($output, 'terminated');
            } else {
                // On Linux/Unix
                $signal = $force ? '-9' : '-15';
                $command = "kill {$signal} {$pid} 2>&1";
                shell_exec($command);
                return true;
            }
        } catch (\Exception $e) {
            Log::warning("Failed to kill process {$pid}: " . $e->getMessage());
            return false;
        }
    }
}
