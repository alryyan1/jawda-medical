<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class QueueWorkerController extends Controller
{
    private $pidFile;
    private $logFile;

    public function __construct()
    {
        $this->pidFile = storage_path('app/queue-worker.pid');
        $this->logFile = storage_path('logs/queue-worker.log');
    }

    /**
     * Get queue worker status
     */
    public function status(): JsonResponse
    {
        try {
            $isRunning = $this->isWorkerRunning();
            $pid = $this->getWorkerPid();
            
            // If no PID file but process is running, try to get the actual PID
            if ($isRunning && !$pid) {
                $pid = $this->getRunningQueueWorkerPid();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_running' => $isRunning,
                    'pid' => $pid,
                    'status' => $isRunning ? 'running' : 'stopped'
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
            if ($this->isWorkerRunning()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Queue worker is already running',
                    'data' => [
                        'is_running' => true,
                        'status' => 'running'
                    ]
                ]);
            }

            // Build the command
            $command = "php artisan queue:work --queue=notifications --sleep=1 --tries=3 --timeout=120";
            
            // Start the process in background using a more reliable method
            if (PHP_OS_FAMILY === 'Windows') {
                // Use batch file to start the process in background
                $batchFile = base_path('start-queue-worker.bat');
                
                // Start the batch file in background
                $command = "start /B \"\" \"$batchFile\"";
                shell_exec($command);
                
                // Wait a moment for the process to start
                sleep(3);
                
                // Try to find the PID of the running queue worker
                $pid = $this->getRunningQueueWorkerPid();
                if ($pid) {
                    $this->saveWorkerPid($pid);
                } else {
                    // If we can't find the PID, but the process might be running, 
                    // let's check if any queue worker is running
                    if ($this->isQueueWorkerProcessRunning()) {
                        // Process is running but we can't get PID, that's okay
                        Log::info("Queue worker is running but PID could not be determined");
                        $pid = null; // We'll handle this case
                    } else {
                        throw new \Exception('Failed to start queue worker - process not found');
                    }
                }
            } else {
                // On Linux/Unix, use nohup
                $fullCommand = "nohup php artisan queue:work --queue=notifications --sleep=1 --tries=3 --timeout=120 > " . storage_path('logs/queue-worker.log') . " 2>&1 &";
                shell_exec($fullCommand);
                
                // Wait a moment for the process to start
                sleep(3);
                
                // Try to find the PID of the running queue worker
                $pid = $this->getRunningQueueWorkerPid();
                if ($pid) {
                    $this->saveWorkerPid($pid);
                } else {
                    // If we can't find the PID, but the process might be running, 
                    // let's check if any queue worker is running
                    if ($this->isQueueWorkerProcessRunning()) {
                        // Process is running but we can't get PID, that's okay
                        Log::info("Queue worker is running but PID could not be determined");
                        $pid = null; // We'll handle this case
                    } else {
                        throw new \Exception('Failed to start queue worker - process not found');
                    }
                }
            }
            
            Log::info("Queue worker started with PID: {$pid}");
            
            return response()->json([
                'success' => true,
                'message' => 'Queue worker started successfully',
                'data' => [
                    'is_running' => true,
                    'pid' => $pid,
                    'status' => 'running'
                ]
            ]);
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
     * Stop queue worker
     */
    public function stop(): JsonResponse
    {
        try {
            if (!$this->isWorkerRunning()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Queue worker is not running',
                    'data' => [
                        'is_running' => false,
                        'status' => 'stopped'
                    ]
                ]);
            }

            $pid = $this->getWorkerPid();
            if ($pid) {
                // Kill the process
                if (PHP_OS_FAMILY === 'Windows') {
                    $killProcess = new Process(['taskkill', '/PID', (string)$pid, '/F']);
                    $killProcess->run();
                } else {
                    $killProcess = new Process(['kill', (string)$pid]);
                    $killProcess->run();
                }
                
                // Remove PID file
                if (File::exists($this->pidFile)) {
                    File::delete($this->pidFile);
                }
                
                Log::info("Queue worker stopped (PID: {$pid})");
            }

            return response()->json([
                'success' => true,
                'message' => 'Queue worker stopped successfully',
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
        if ($this->isWorkerRunning()) {
            return $this->stop();
        } else {
            return $this->start();
        }
    }

    /**
     * Check if queue worker is running
     */
    private function isWorkerRunning(): bool
    {
        // First check if we have a PID file and the process is running
        $pid = $this->getWorkerPid();
        if ($pid && $this->isProcessRunning($pid)) {
            return true;
        }

        // If no PID file or process not found, check for any running queue worker processes
        return $this->isQueueWorkerProcessRunning();
    }

    /**
     * Check if a specific process ID is running
     */
    private function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $result = new Process(['tasklist', '/FI', "PID eq {$pid}"]);
            $result->run();
            return str_contains($result->getOutput(), (string)$pid);
        } else {
            $result = new Process(['ps', '-p', (string)$pid]);
            $result->run();
            return $result->isSuccessful();
        }
    }

    /**
     * Check if any queue worker process is running
     */
    private function isQueueWorkerProcessRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, use wmic to check for queue worker processes
            $result = new Process(['wmic', 'process', 'where', 'name="php.exe"', 'get', 'processid,commandline']);
            $result->run();
            $output = $result->getOutput();
            
            // Clean the output and check for queue:work
            $cleanOutput = preg_replace('/[^\x20-\x7E]/', '', $output);
            return str_contains($cleanOutput, 'queue:work');
        } else {
            // On Linux/Unix, use ps to find queue worker processes
            $result = new Process(['ps', 'aux']);
            $result->run();
            $output = $result->getOutput();
            
            return str_contains($output, 'queue:work');
        }
    }

    /**
     * Get worker PID from file
     */
    private function getWorkerPid(): ?int
    {
        if (!File::exists($this->pidFile)) {
            return null;
        }

        $pid = (int) File::get($this->pidFile);
        return $pid > 0 ? $pid : null;
    }

    /**
     * Save worker PID to file
     */
    private function saveWorkerPid(int $pid): void
    {
        File::put($this->pidFile, (string)$pid);
    }

    /**
     * Get PID of running queue worker process
     */
    private function getRunningQueueWorkerPid(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, use wmic to get process info
            $result = new Process(['wmic', 'process', 'where', 'name="php.exe"', 'get', 'processid,commandline']);
            $result->run();
            $output = $result->getOutput();
            
            // Clean the output and parse
            $cleanOutput = preg_replace('/[^\x20-\x7E]/', '', $output);
            $lines = explode("\n", $cleanOutput);
            
            foreach ($lines as $line) {
                if (str_contains($line, 'queue:work')) {
                    // Extract PID from the line
                    preg_match('/(\d+)/', $line, $matches);
                    if (isset($matches[1]) && is_numeric($matches[1])) {
                        return (int)$matches[1];
                    }
                }
            }
        } else {
            // On Linux/Unix, use ps to get process info
            $result = new Process(['ps', 'aux']);
            $result->run();
            $output = $result->getOutput();
            
            // Parse the output to find queue worker process
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (str_contains($line, 'queue:work')) {
                    $parts = preg_split('/\s+/', $line);
                    if (count($parts) >= 2 && is_numeric($parts[1])) {
                        return (int)$parts[1];
                    }
                }
            }
        }
        
        return null;
    }
}
