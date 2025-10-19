<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class HL7ClientController extends Controller
{
    private $pidFile;
    private $logFile;

    public function __construct()
    {
        $this->pidFile = storage_path('app/hl7-client.pid');
        $this->logFile = storage_path('logs/hl7-client.log');
    }

    /**
     * Get HL7 client status
     */
    public function status(): JsonResponse
    {
        try {
            $isRunning = $this->isClientRunning();
            $pid = $this->getClientPid();
            
            // If no PID file but process is running, try to get the actual PID
            if ($isRunning && !$pid) {
                $pid = $this->getRunningHl7ClientPid();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_running' => $isRunning,
                    'pid' => $pid,
                    'status' => $isRunning ? 'connected' : 'disconnected'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking HL7 client status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking client status',
                'data' => [
                    'is_running' => false,
                    'status' => 'disconnected'
                ]
            ]);
        }
    }

    /**
     * Start HL7 client
     */
    public function start(Request $request): JsonResponse
    {
        try {
            // Check if already running
            if ($this->isClientRunning()) {
                return response()->json([
                    'success' => true,
                    'message' => 'HL7 client is already running',
                    'data' => [
                        'is_running' => true,
                        'status' => 'connected'
                    ]
                ]);
            }

            // Get host and port from request or use defaults
            $host = $request->input('host', '192.168.1.114');
            $port = $request->input('port', 5100);

            // Build the command
            $command = "php " . base_path('hl7-client.php') . " --host={$host} --port={$port}";
            
            // Start the process in background
            $process = new Process(explode(' ', $command));
            $process->start();
            
            // Save PID to file
            $this->saveClientPid($process->getPid());
            
            Log::info("HL7 client started with PID: {$process->getPid()}");
            
            return response()->json([
                'success' => true,
                'message' => 'HL7 client started successfully',
                'data' => [
                    'is_running' => true,
                    'pid' => $process->getPid(),
                    'status' => 'connected'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error starting HL7 client: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start HL7 client: ' . $e->getMessage(),
                'data' => [
                    'is_running' => false,
                    'status' => 'disconnected'
                ]
            ], 500);
        }
    }

    /**
     * Stop HL7 client
     */
    public function stop(): JsonResponse
    {
        try {
            if (!$this->isClientRunning()) {
                return response()->json([
                    'success' => true,
                    'message' => 'HL7 client is not running',
                    'data' => [
                        'is_running' => false,
                        'status' => 'disconnected'
                    ]
                ]);
            }

            $pid = $this->getClientPid();
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
                
                Log::info("HL7 client stopped (PID: {$pid})");
            }

            return response()->json([
                'success' => true,
                'message' => 'HL7 client stopped successfully',
                'data' => [
                    'is_running' => false,
                    'status' => 'disconnected'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error stopping HL7 client: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop HL7 client: ' . $e->getMessage(),
                'data' => [
                    'is_running' => false,
                    'status' => 'disconnected'
                ]
            ], 500);
        }
    }

    /**
     * Toggle HL7 client (start if stopped, stop if running)
     */
    public function toggle(Request $request): JsonResponse
    {
        if ($this->isClientRunning()) {
            return $this->stop();
        } else {
            return $this->start($request);
        }
    }

    /**
     * Check if HL7 client is running
     */
    private function isClientRunning(): bool
    {
        // First check if we have a PID file and the process is running
        $pid = $this->getClientPid();
        if ($pid && $this->isProcessRunning($pid)) {
            return true;
        }

        // If no PID file or process not found, check for any running hl7-client.php processes
        return $this->isHl7ClientProcessRunning();
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
     * Check if any hl7-client.php process is running
     */
    private function isHl7ClientProcessRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, use tasklist to find PHP processes, then check command line
            $result = new Process(['tasklist', '/FI', 'IMAGENAME eq php.exe', '/FO', 'CSV']);
            $result->run();
            $output = $result->getOutput();
            
            // If we found PHP processes, get their PIDs and check command lines
            if (str_contains($output, 'php.exe')) {
                // Use wmic to get command lines for PHP processes
                $result2 = new Process(['wmic', 'process', 'where', 'name="php.exe"', 'get', 'processid,commandline']);
                $result2->run();
                $output2 = $result2->getOutput();
                
                // Clean the output and check for hl7-client.php
                $cleanOutput = preg_replace('/[^\x20-\x7E]/', '', $output2);
                return str_contains($cleanOutput, 'hl7-client.php');
            }
            
            return false;
        } else {
            // On Linux/Unix, use ps to find hl7-client.php processes
            $result = new Process(['ps', 'aux']);
            $result->run();
            $output = $result->getOutput();
            
            return str_contains($output, 'hl7-client.php');
        }
    }

    /**
     * Get client PID from file
     */
    private function getClientPid(): ?int
    {
        if (!File::exists($this->pidFile)) {
            return null;
        }

        $pid = (int) File::get($this->pidFile);
        return $pid > 0 ? $pid : null;
    }

    /**
     * Save client PID to file
     */
    private function saveClientPid(int $pid): void
    {
        File::put($this->pidFile, (string)$pid);
    }

    /**
     * Get PID of running HL7 client process
     */
    private function getRunningHl7ClientPid(): ?int
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
                if (str_contains($line, 'hl7-client.php')) {
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
            
            // Parse the output to find hl7-client.php process
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (str_contains($line, 'hl7-client.php')) {
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
