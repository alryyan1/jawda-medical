var Service = require('node-windows').Service;

// Create a new service object
var svc = new Service({
  name:'jawda pinggy start-with-tunnel',
  description: 'start-with-tunnel pinggy',
  script: 'C:/xampp/htdocs/jawda-medical/realtime-events/tunnel.js',
  nodeOptions: [
    '--harmony',
    '--max_old_space_size=4096'
  ]
  //, workingDirectory: '...'
  //, allowServiceLogon: true
});

// Listen for the "install" event, which indicates the
// process is available as a service.
svc.on('install',function(){
  svc.start();
});

svc.install();