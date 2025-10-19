import { pinggy } from "@pinggy/pinggy";

const PINGGY_TOKEN = "PMLHOiWX5ES";
const LOCAL_PORT = 80
const LOCAL_HOST = "localhost";

async function createTunnel() {
  try {
    console.log(`🚀 Creating Pinggy tunnel for local server at ${LOCAL_HOST}:${LOCAL_PORT}`);
    
    // Enable debug logging
    pinggy.setDebugLogging(true);
    
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
    
    // Keep the tunnel alive
    process.on('SIGINT', async () => {
      console.log('\n🛑 Shutting down tunnel...');
      await tunnel.stop();
      process.exit(0);
    });

    process.on('SIGTERM', async () => {
      console.log('\n🛑 Shutting down tunnel...');
      await tunnel.stop();
      process.exit(0);
    });

  } catch (error) {
    console.error("❌ Failed to create tunnel:", error.message);
    process.exit(1);
  }
}

// Start the tunnel
createTunnel();
