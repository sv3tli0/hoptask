export const config = {
  // API Configuration
  api: {
    baseUrl: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000',
    timeout: 10000,
  },
  
  // WebSocket Configuration
  websocket: {
    url: import.meta.env.VITE_WS_URL || 'ws://localhost:3001/ws',
    reconnectDelay: 3000,
  },
  
  // App Configuration
  app: {
    name: 'Content Moderation System',
    version: '1.0.0',
  }
}

// Environment-specific overrides
if (import.meta.env.PROD) {
  // Production overrides
  config.api.baseUrl = import.meta.env.VITE_API_BASE_URL || 'https://api.yourapp.com'
  config.websocket.url = import.meta.env.VITE_WS_URL || 'wss://ws.yourapp.com/ws'
}

export default config