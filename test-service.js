const path = require('path');
const fs = require('fs');

console.log('🧪 Testing Jawda Medical Notifications Service');
console.log('==============================================');
console.log('');

// Test 1: Check if we can find the project root
console.log('1. Testing project root detection...');
const service = require('./notifications-service.js');

// Wait a moment for the service to initialize
setTimeout(() => {
    console.log('Project root found:', service.projectRoot);
    
    // Test 2: Check if artisan file exists
    const artisanPath = path.join(service.projectRoot, 'artisan');
    if (fs.existsSync(artisanPath)) {
        console.log('✅ Laravel artisan file found');
    } else {
        console.log('❌ Laravel artisan file not found');
    }
    
    // Test 3: Check if logs directory was created
    const logsDir = path.join(service.projectRoot, 'logs');
    if (fs.existsSync(logsDir)) {
        console.log('✅ Logs directory created');
    } else {
        console.log('❌ Logs directory not created');
    }
    
    // Test 4: Check service status
    const status = service.getStatus();
    console.log('Service status:', status);
    
    // Test 5: Check if PHP is available
    const { exec } = require('child_process');
    exec('php --version', (error, stdout, stderr) => {
        if (error) {
            console.log('❌ PHP not available:', error.message);
        } else {
            console.log('✅ PHP available:', stdout.split('\n')[0]);
        }
        
        console.log('');
        console.log('🎯 Test completed. Service should be running in the background.');
        console.log('Check the logs directory for service output.');
        console.log('');
        console.log('To stop the test, press Ctrl+C');
    });
    
}, 2000);
