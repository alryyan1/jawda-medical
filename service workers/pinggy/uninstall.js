const Service = require('node-windows').Service;

// Create a new service object (same as during install)
const svc = new Service({
  name: 'jawda pinggy start-with-tunnel',
  description: 'start-with-tunnel pinggy',
  script: 'C:/xampp/htdocs/jawda-medical/realtime-events/start-with-tunnel.js',
  nodeOptions: [
    '--harmony',
    '--max_old_space_size=4096'
  ]
});

// Listen for the "uninstall" event so you know when it's done
svc.on('uninstall', function() {
  console.log('Service uninstalled!');
  console.log('Service exists:', svc.exists);
});

// Call uninstall
svc.uninstall();
