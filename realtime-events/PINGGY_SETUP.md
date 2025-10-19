# Pinggy Tunnel Setup for Realtime Events Server

This document explains how to use Pinggy to expose your local realtime-events server to the internet.

## What is Pinggy?

Pinggy is a tunneling service that allows you to expose your local development server to the internet through secure tunnels. This is perfect for:

- Testing your realtime server with external clients
- Sharing your development server with team members
- Testing mobile apps that need to connect to your local server
- Webhook testing from external services

## Setup

### 1. Install Dependencies

The Pinggy package is already installed in this project:

```bash
npm install @pinggy/pinggy
```

### 2. Your Token

Your Pinggy token is already configured: `PMLHOiWX5ES`

## Usage Options

### Option 1: Start Server + Tunnel Together (Recommended)

This starts both the realtime server and creates the tunnel in one command:

```bash
# Using npm script
npm run start-with-tunnel

# Or using the batch file (Windows)
start-with-tunnel.bat

# Or directly
node start-with-tunnel.js
```

### Option 2: Tunnel Only (Server Already Running)

If your server is already running on port 4001, you can just create the tunnel:

```bash
# Using npm script
npm run tunnel

# Or directly
node tunnel.js
```

### Option 3: Development Mode with Auto-restart

For development with auto-restart:

```bash
npm run tunnel:dev
```

## What Happens When You Start

1. **Server Starts**: The realtime-events server starts on `localhost:4001`
2. **Tunnel Created**: Pinggy creates a secure tunnel to expose your server
3. **Public URLs**: You'll see public URLs like:
   - `https://abc123.tcp.pinggy.io`
   - `https://abc123.tcp.pinggy.online`

## Using the Public URLs

### In Your Frontend (Socket.IO)

Replace your local Socket.IO connection:

```javascript
// Before (local only)
const socket = io('http://localhost:4001');

// After (accessible from anywhere)
const socket = io('https://abc123.tcp.pinggy.io');
```

### In Your Laravel Backend

Update your realtime server URL in your Laravel configuration:

```php
// In your .env or config
REALTIME_SERVER_URL=https://abc123.tcp.pinggy.io
```

### Testing Webhooks

You can now receive webhooks from external services:

```
https://abc123.tcp.pinggy.io/emit/patient-registered
https://abc123.tcp.pinggy.io/emit/lab-payment
```

## Security Notes

- The tunnel is secure and encrypted
- Your token `PMLHOiWX5ES` is already configured
- The tunnel only forwards traffic to your local server
- Your local server's authentication still applies

## Troubleshooting

### Connection Issues

1. **Check if server is running**: Make sure port 4001 is available
2. **Check firewall**: Ensure Windows Firewall allows Node.js
3. **Check token**: Verify your Pinggy token is correct

### Tunnel Not Working

1. **Restart tunnel**: Stop and restart the tunnel script
2. **Check logs**: Look for error messages in the console
3. **Verify server**: Make sure your realtime server is responding on localhost:4001

### Testing Connection

Test if your tunnel is working:

```bash
# Test the health endpoint
curl https://your-tunnel-url.tcp.pinggy.io/health

# Should return: {"status":"ok"}
```

## Stopping the Tunnel

Press `Ctrl+C` to stop both the server and tunnel gracefully.

## Files Created

- `tunnel.js` - Standalone tunnel script
- `start-with-tunnel.js` - Combined server + tunnel script
- `start-with-tunnel.bat` - Windows batch file for easy execution
- `PINGGY_SETUP.md` - This documentation

## Next Steps

1. Start your tunnel: `npm run start-with-tunnel`
2. Copy the public URL from the console output
3. Update your frontend to use the public URL
4. Test the connection from external devices
5. Update your Laravel backend configuration if needed

Your realtime-events server is now accessible from anywhere on the internet! 🌐
