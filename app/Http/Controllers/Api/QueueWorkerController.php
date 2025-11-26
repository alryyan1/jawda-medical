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
     * Get queue worker status
     */
    public function status(): JsonResponse
    {
        try {
            $pid = $this->getWorkerPid();
            $isRunning = $pid && $this->isProcessRunning($pid);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_running' => $isRunning,
                    'pid' => $isRunning ? $pid : null,
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
            $existingPid = $this->getWorkerPid();
            if ($existingPid && $this->isProcessRunning($existingPid)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Queue worker is already running',
                    'data' => [
                        'is_running' => true,
                        'pid' => $existingPid,
                        'status' => 'running'
                    ]
                ]);
            }

            // Clean up old PID file if process is not running
            if (File::exists($this->pidFile)) {
                File::delete($this->pidFile);
            }

            $pid = null;
            
            if (PHP_OS_FAMILY === 'Windows') {
                // On Windows, start the queue worker in background
                $artisanPath = base_path('artisan');
                $logPath = storage_path('logs/queue-worker.log');
                $queues = 'resultsUpload,ServicePaymentCancel,notifications,whatsapp,default';
                
                // Create a VBS script to run the command hidden and get PID
                $vbsFile = storage_path('app/start-queue-worker.vbs');
                $vbsContent = 'Set objShell = CreateObject("WScript.Shell")' . "\r\n";
                $vbsContent .= 'Set objExec = objShell.Exec("php ""' . str_replace('\\', '\\\\', $artisanPath) . '"" queue:work --queue=' . $queues . ' --sleep=1 --tries=3 --timeout=300")' . "\r\n";
                $vbsContent .= 'WScript.Echo objExec.ProcessID' . "\r\n";
                file_put_contents($vbsFile, $vbsContent);
                
                // Run the VBS script and get PID
                $output = shell_exec('cscript //nologo "' . $vbsFile . '" 2>&1');
                $output = trim($output);
                Log::info("VBS start output: " . $output);
                
                if ($output && is_numeric($output)) {
                    $pid = (int)$output;
                }
                
                // If VBS method failed, try popen method
                if (!$pid) {
                    // Use popen to start process in background
                    $command = 'start /B php "' . $artisanPath . '" queue:work --queue=' . $queues . ' --sleep=1 --tries=3 --timeout=300 > "' . $logPath . '" 2>&1';
                    pclose(popen($command, 'r'));
                    
                    Log::info("Started queue worker using popen, waiting to find PID...");
                    
                    // Wait and find the PHP process running queue:work
                    sleep(3);
                    
                    // Use wmic to find the process
                    $findCmd = 'wmic process where "commandline like \'%queue:work%\'" get processid /format:value 2>nul';
                    $output = shell_exec($findCmd);
                    Log::info("WMIC find output: " . $output);
                    
                    if (preg_match('/ProcessId=(\d+)/i', $output, $matches)) {
                        $pid = (int)$matches[1];
                    }
                }
            } else {
                // On Linux/Unix, use nohup
                // Include all queues: resultsUpload, ServicePaymentCancel, notifications, whatsapp, default
                $logPath = storage_path('logs/queue-worker.log');
                $command = "nohup php " . base_path('artisan') . " queue:work --queue=resultsUpload,ServicePaymentCancel,notifications,whatsapp,default --sleep=1 --tries=3 --timeout=300 > $logPath 2>&1 & echo $!";
                $output = shell_exec($command);
                $pid = (int)trim($output);
            }
            
            if ($pid && $pid > 0) {
                // Wait a moment and verify the process is running
                sleep(2);
                
                if ($this->isProcessRunning($pid)) {
                    $this->saveWorkerPid($pid);
                    Log::info("Queue worker started successfully with PID: {$pid}");
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Queue worker started successfully',
                        'data' => [
                            'is_running' => true,
                            'pid' => $pid,
                            'status' => 'running'
                        ]
                    ]);
                }
            }
            
            // Could not start or verify
            return response()->json([
                'success' => false,
                'message' => 'Failed to start queue worker - process could not be verified',
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
     * Stop queue worker
     */
    public function stop(): JsonResponse
    {
        try {
            $pid = $this->getWorkerPid();
            
            if (!$pid) {
                // No PID file, assume stopped
                return response()->json([
                    'success' => true,
                    'message' => 'Queue worker is not running',
                    'data' => [
                        'is_running' => false,
                        'status' => 'stopped'
                    ]
                ]);
            }
            
            if (!$this->isProcessRunning($pid)) {
                // Process not running, clean up PID file
                File::delete($this->pidFile);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Queue worker is not running',
                    'data' => [
                        'is_running' => false,
                        'status' => 'stopped'
                    ]
                ]);
            }

            // Kill the process
            $killed = $this->killProcess($pid);
            
            // Clean up PID file
            if (File::exists($this->pidFile)) {
                File::delete($this->pidFile);
            }
            
            // Verify it's stopped
            sleep(1);
            $stillRunning = $this->isProcessRunning($pid);
            
            if ($stillRunning) {
                // Try force kill
                $this->killProcess($pid, true);
                sleep(1);
                $stillRunning = $this->isProcessRunning($pid);
            }
            
            if ($stillRunning) {
                Log::warning("Failed to stop queue worker PID: {$pid}");
                return response()->json([
                    'success' => false,
                    'message' => "Failed to stop queue worker (PID: {$pid}). Try stopping manually.",
                    'data' => [
                        'is_running' => true,
                        'pid' => $pid,
                        'status' => 'running'
                    ]
                ], 500);
            }

            Log::info("Queue worker stopped successfully (PID: {$pid})");
            
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
        $pid = $this->getWorkerPid();
        if ($pid && $this->isProcessRunning($pid)) {
            return $this->stop();
        } else {
            return $this->start();
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

        $pid = (int) trim(File::get($this->pidFile));
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
