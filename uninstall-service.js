const Service = require('node-windows').Service;
const path = require('path');

// Create a new service object
const svc = new Service({
    name: 'JawdaMedicalNotifications',
    script: path.join(__dirname, 'notifications-service.js')
});

// Listen for the "uninstall" event
svc.on('uninstall', function() {
    console.log('✅ Service uninstalled successfully!');
    console.log('');
    console.log('Note: Log files in the logs directory have been preserved.');
    console.log('You can manually delete them if no longer needed.');
});

svc.on('error', function(err) {
    console.error('❌ Service error:', err);
});

// Uninstall the service
console.log('Uninstalling Jawda Medical Notifications Queue Service...');
console.log('This may require administrator privileges.');
console.log('');

// Stop the service first
console.log('Stopping service...');
svc.stop();

// Wait a moment then uninstall
setTimeout(() => {
    console.log('Removing service...');
    svc.uninstall();
}, 2000);
