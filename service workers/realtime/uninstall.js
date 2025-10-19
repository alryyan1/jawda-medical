const Service = require('node-windows').Service;

// Create a new service object (same as during install)
const svc = new Service({
  name: 'jawda realtime-server ',
  description: 'start-realtime-server',
  script: 'C:/realtime/start-realtime-server',
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
