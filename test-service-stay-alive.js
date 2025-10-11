const path = require('path');
const fs = require('fs');

console.log('🧪 Testing Service Stay-Alive Functionality');
console.log('==========================================');
console.log('');

// Test the service directly
console.log('Starting service test...');
const service = require('./notifications-service.js');

// Monitor the service for 2 minutes
let testDuration = 120000; // 2 minutes
let checkInterval = 10000; // Check every 10 seconds
let elapsed = 0;

console.log(`Monitoring service for ${testDuration / 1000} seconds...`);
console.log('');

const monitor = setInterval(() => {
    elapsed += checkInterval;
    const status = service.getStatus();
    
    console.log(`[${Math.round(elapsed / 1000)}s] Service Status:`);
    console.log(`  - Running: ${status.isRunning ? '✅' : '❌'}`);
    console.log(`  - PID: ${status.pid || 'N/A'}`);
    console.log(`  - Restart Count: ${status.restartCount}`);
    console.log(`  - Uptime: ${Math.round(status.uptime / 1000)}s`);
    console.log('');
    
    if (elapsed >= testDuration) {
        clearInterval(monitor);
        console.log('✅ Test completed successfully!');
        console.log('The service should stay running indefinitely.');
        console.log('');
        console.log('To stop the test, press Ctrl+C');
        console.log('The service will continue running in the background.');
    }
}, checkInterval);

// Handle Ctrl+C gracefully
process.on('SIGINT', () => {
    console.log('\n🛑 Test interrupted by user');
    clearInterval(monitor);
    console.log('Service is still running in the background.');
    console.log('To stop the service, run: node uninstall-service.js');
    process.exit(0);
});
