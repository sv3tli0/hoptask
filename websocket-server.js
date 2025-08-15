#!/usr/bin/env node

const http = require('http');
const WebSocket = require('ws');
const url = require('url');

// Create HTTP server for Laravel notifications
const server = http.createServer((req, res) => {
  // Enable CORS for Laravel backend
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  if (req.method === 'POST' && req.url === '/notify') {
    let body = '';
    
    req.on('data', chunk => {
      body += chunk.toString();
    });
    
    req.on('end', () => {
      try {
        const notification = JSON.parse(body);
        console.log('Notification from Laravel:', notification);
        
        // Broadcast to all connected Vue clients
        broadcast(notification);
        
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true, clients: wss.clients.size }));
      } catch (error) {
        console.error('Error processing notification:', error);
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Invalid JSON' }));
      }
    });
  } else {
    res.writeHead(404);
    res.end('Not Found');
  }
});

// Create WebSocket server for Vue clients
const wss = new WebSocket.Server({ server, path: '/ws' });

wss.on('connection', (ws, req) => {
  const clientIp = req.socket.remoteAddress;
  console.log(`[Vue] client connected from ${clientIp} (total: ${wss.clients.size})`);

  try {
    ws.send(JSON.stringify({
      type: 'connected',
      message: 'WebSocket connection established',
      timestamp: new Date().toISOString()
    }));
  } catch (error) {
    console.error('Failed to send welcome message:', error);
  }
  
  ws.on('close', (code, reason) => {
    console.log(`[Vue] client disconnected (remaining: ${wss.clients.size}) - Code: ${code}, Reason: ${reason?.toString() || 'none'}`);
  });
  
  ws.on('error', (error) => {
    console.error('WebSocket error:', error);
  });
});

// Broadcast message to all connected Vue clients
function broadcast(data) {
  console.log('Broadcasting:', {
    event: data.event,
    postId: data.data?.id,
    status: data.data?.status,
    totalClients: wss.clients.size
  });

  const message = JSON.stringify({
    type: data.event === 'post_created' ? 'post_created' : 'post_update',
    event: data.event,
    data: data.data,
    timestamp: new Date().toISOString()
  });
  
  let sent = 0;
  let failed = 0;
  
  wss.clients.forEach((client, index) => {
    if (client.readyState === WebSocket.OPEN) {
      try {
        client.send(message);
        sent++;
        console.log(`Sent to client ${index + 1}: SUCCESS`);
      } catch (error) {
        failed++;
        console.error(`Send to client ${index + 1}: FAILED -`, error.message);
      }
    } else {
      console.log(`Client ${index + 1}: NOT READY (state: ${client.readyState})`);
    }
  });
}

// Start server
const PORT = process.env.WS_PORT || 3001;
server.listen(PORT, () => {});

process.on('SIGTERM', () => {
  server.close(() => {
    process.exit(0);
  });
});