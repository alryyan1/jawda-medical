import { spawn } from 'child_process';
import { pinggy } from "@pinggy/pinggy";

const PINGGY_TOKEN = "PMLHOiWX5ES";
const LOCAL_PORT = process.env.PORT || 4001;
const LOCAL_HOST = "localhost";

let serverProcess = null;
let tunnelProcess = null;

// Function to start the realtime server
function startServer() {
  console.log("🚀 Starting realtime-events server...");
  
  serverProcess = spawn('node', ['server.js'], {
    stdio: 'inherit',
    cwd: process.cwd()
  });

  serverProcess.on('error', (error) => {
    console.error('❌ Failed to start server:', error);
    process.exit(1);
  });

  serverProcess.on('exit', (code) => {
    console.log(`🛑 Server exited with code ${code}`);
    if (tunnelProcess) {
      tunnelProcess.kill();
    }
    process.exit(code);
  });

  // Wait a bit for server to start
  setTimeout(startTunnel, 3000);
}

// Function to start the Pinggy tunnel
async function startTunnel() {
  try {
    console.log(`🌐 Creating Pinggy tunnel for ${LOCAL_HOST}:${LOCAL_PORT}...`);
    
    const tunnel = pinggy.createTunnel({
      forwarding: `${LOCAL_HOST}:${LOCAL_PORT}`,
      token: PINGGY_TOKEN
    });

    await tunnel.start();
    
    const urls = tunnel.urls();
    console.log("✅ Tunnel created successfully!");
    console.log("🌐 Public URLs:");
    urls.forEach((url, index) => {
      console.log(`   ${index + 1}. ${url}`);
    });
    
    console.log("\n📡 Your realtime-events server is now accessible from the internet!");
    console.log("🔗 Use these URLs in your frontend to connect to the realtime server");
    console.log("\n💡 Example Socket.IO connection:");
    console.log(`   const socket = io('${urls[0]}');`);
    
    // Handle graceful shutdown
    process.on('SIGINT', async () => {
      console.log('\n🛑 Shutting down server and tunnel...');
      if (serverProcess) {
        serverProcess.kill();
      }
      await tunnel.stop();
      process.exit(0);
    });

    process.on('SIGTERM', async () => {
      console.log('\n🛑 Shutting down server and tunnel...');
      if (serverProcess) {
        serverProcess.kill();
      }
      await tunnel.stop();
      process.exit(0);
    });

  } catch (error) {
    console.error("❌ Failed to create tunnel:", error.message);
    if (serverProcess) {
      serverProcess.kill();
    }
    process.exit(1);
  }
}

// Start the server first
startServer();
