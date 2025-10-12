const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const { EventLog } = require('node-windows');

// Initialize Windows Event Log
const eventLog = new EventLog('Jawda Medical Notifications Service');

class NotificationsQueueService {
    constructor() {
        this.process = null;
        this.isRunning = false;
        this.restartCount = 0;
        this.maxRestarts = 3; // Reduced from 5 to 3
        this.restartDelay = 30000; // Increased to 30 seconds
        this.lastRestartTime = 0;
        this.minRestartInterval = 60000; // Minimum 60 seconds between restarts
        this.shouldRestart = true;
    }

    // Windows Event Logging methods
    logInfo(message) {
        eventLog.info(message);
        this.logToFile('notifications-service.log', `[INFO] ${message}`);
    }

    logWarning(message) {
        eventLog.warn(message);
        this.logToFile('notifications-service.log', `[WARNING] ${message}`);
    }

    logError(message) {
        eventLog.error(message);
        this.logToFile('notifications-service-error.log', `[ERROR] ${message}`);
    }

    logSuccess(message) {
        eventLog.info(message);
        this.logToFile('notifications-service.log', `[SUCCESS] ${message}`);
    }

    start() {
        this.logInfo('Starting Jawda Medical Notifications Queue Service...');
        this.logInfo(`Current working directory: ${process.cwd()}`);
        this.logInfo(`Script directory: ${__dirname}`);
        
        // Find the project root (where artisan file is located)
        this.projectRoot = this.findProjectRoot();
        this.logInfo(`Project root: ${this.projectRoot}`);
        
        // Ensure logs directory exists
        const logsDir = path.join(this.projectRoot, 'logs');
        if (!fs.existsSync(logsDir)) {
            fs.mkdirSync(logsDir, { recursive: true });
            this.logInfo('Created logs directory');
        }

        this.runQueueWorker();
    }

    findProjectRoot() {
        let currentDir = __dirname;
        
        // Walk up the directory tree to find the project root
        while (currentDir !== path.dirname(currentDir)) {
            const artisanPath = path.join(currentDir, 'artisan');
            if (fs.existsSync(artisanPath)) {
                return currentDir;
            }
            currentDir = path.dirname(currentDir);
        }
        
        // If not found, use the script directory
        this.logWarning('Could not find Laravel project root, using script directory');
        return __dirname;
    }

    runQueueWorker() {
        if (this.isRunning) {
            this.logInfo('Service is already running');
            return;
        }

        this.logInfo('Starting Laravel queue worker...');
        this.logInfo(`Working directory: ${this.projectRoot}`);
        
        // Spawn PHP process
        this.process = spawn('php', [
            'artisan',
            'queue:work',
            '--queue=notifications',
            '--sleep=1',
            '--tries=3',
            '--timeout=120'
        ], {
            cwd: this.projectRoot,
            stdio: ['pipe', 'pipe', 'pipe']
        });

        this.isRunning = true;
        this.restartCount = 0;
        this.processStartTime = Date.now();
        
        // Monitor process health
        this.startProcessMonitoring();

        // Handle process output
        this.process.stdout.on('data', (data) => {
            const message = data.toString().trim();
            if (message) {
                this.logInfo(`Queue Worker: ${message}`);
            }
        });

        this.process.stderr.on('data', (data) => {
            const message = data.toString().trim();
            if (message) {
                this.logError(`Queue Worker Error: ${message}`);
            }
        });

        // Handle process exit
        this.process.on('exit', (code, signal) => {
            this.logWarning(`Queue worker process exited with code ${code} and signal ${signal}`);
            this.isRunning = false;
            
            // Check if we should restart
            if (!this.shouldRestart) {
                this.logInfo('Service restart disabled. Exiting.');
                return;
            }
            
            // Check if we've exceeded max restarts
            if (this.restartCount >= this.maxRestarts) {
                this.logError('Maximum restart attempts reached. Service will not restart automatically.');
                return;
            }
            
            // Check minimum restart interval
            const now = Date.now();
            const timeSinceLastRestart = now - this.lastRestartTime;
            
            if (timeSinceLastRestart < this.minRestartInterval) {
                const waitTime = Math.round((this.minRestartInterval - timeSinceLastRestart) / 1000);
                this.logWarning(`Too soon to restart. Waiting ${waitTime} seconds more...`);
                
                setTimeout(() => {
                    this.restartQueueWorker();
                }, this.minRestartInterval - timeSinceLastRestart);
            } else {
                this.restartQueueWorker();
            }
        });

        // Handle process errors
        this.process.on('error', (error) => {
            this.logError(`Process error: ${error.message}`);
        });

        this.logSuccess('Queue worker started successfully');
    }

    stop() {
        this.logInfo('Stopping Jawda Medical Notifications Queue Service...');
        this.shouldRestart = false; // Disable restart when stopping
        
        // Stop process monitoring
        this.stopProcessMonitoring();
        
        if (this.process && this.isRunning) {
            this.process.kill('SIGTERM');
            
            // Force kill after 10 seconds if not stopped gracefully
            setTimeout(() => {
                if (this.isRunning && this.process) {
                    this.logWarning('Force killing process...');
                    this.process.kill('SIGKILL');
                }
            }, 10000);
        }
        
        this.isRunning = false;
        this.logInfo('Service stopped');
    }

    restartQueueWorker() {
        this.restartCount++;
        this.lastRestartTime = Date.now();
        
        // Stop current process monitoring
        this.stopProcessMonitoring();
        
        const delaySeconds = Math.round(this.restartDelay / 1000);
        this.logWarning(`Restarting queue worker (attempt ${this.restartCount}/${this.maxRestarts}) in ${delaySeconds} seconds...`);
        
        setTimeout(() => {
            if (this.shouldRestart) {
                this.runQueueWorker();
            }
        }, this.restartDelay);
    }

    logToFile(filename, message) {
        const logPath = path.join(this.projectRoot, 'logs', filename);
        const timestamp = new Date().toISOString();
        const logEntry = `[${timestamp}] ${message}`;
        
        fs.appendFileSync(logPath, logEntry, 'utf8');
    }

    startProcessMonitoring() {
        // Monitor the PHP process every 30 seconds
        this.processMonitor = setInterval(() => {
            if (this.process && this.isRunning) {
                // Check if process is still alive
                try {
                    // Send a null signal to check if process exists
                    process.kill(this.process.pid, 0);
                } catch (error) {
                    // Process is dead
                    this.logError('PHP process died unexpectedly, restarting...');
                    this.isRunning = false;
                    this.restartQueueWorker();
                }
            }
        }, 30000); // Check every 30 seconds
    }

    stopProcessMonitoring() {
        if (this.processMonitor) {
            clearInterval(this.processMonitor);
            this.processMonitor = null;
        }
    }

    getStatus() {
        return {
            isRunning: this.isRunning,
            restartCount: this.restartCount,
            pid: this.process ? this.process.pid : null,
            uptime: this.processStartTime ? Date.now() - this.processStartTime : 0
        };
    }
}

// Create service instance
const service = new NotificationsQueueService();

// Handle graceful shutdown
process.on('SIGTERM', () => {
    service.logInfo('Received SIGTERM, shutting down gracefully...');
    service.stop();
    process.exit(0);
});

process.on('SIGINT', () => {
    service.logInfo('Received SIGINT, shutting down gracefully...');
    service.stop();
    process.exit(0);
});

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
    service.logError(`Uncaught Exception: ${error.message}\n${error.stack}`);
    service.stop();
    process.exit(1);
});

process.on('unhandledRejection', (reason, promise) => {
    service.logError(`Unhandled Rejection: ${reason}`);
});

// Start the service
service.start();

// Keep the process alive and monitor health
let healthCheckInterval = setInterval(() => {
    // This keeps the Node.js process running
    // The service will only exit when explicitly stopped
    
    // Log health status every 5 minutes
    if (service.isRunning) {
        service.logInfo('Service health check: Running normally');
    } else {
        service.logWarning('Service health check: Not running');
    }
}, 300000); // Check every 5 minutes

// Handle process exit to clean up
process.on('exit', (code) => {
    service.logInfo(`Service process exiting with code: ${code}`);
    if (healthCheckInterval) {
        clearInterval(healthCheckInterval);
    }
});

// Export for testing
module.exports = service;
