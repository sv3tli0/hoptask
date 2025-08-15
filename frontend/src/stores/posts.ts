import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiService, { type Post } from '@/services/api'
import config from '@/config/api'

export interface PostWithBlink extends Post {
  isBlinking?: boolean
  blinkType?: 'approved' | 'rejected'
}

export const usePostsStore = defineStore('posts', () => {
  const posts = ref<PostWithBlink[]>([])
  const loading = ref(false)
  const wsConnected = ref(false)
  const wsError = ref<string | null>(null)

  let ws: WebSocket | null = null
  let reconnectTimer: number | null = null

  const connectWebSocket = () => {
    if (ws && ws.readyState === WebSocket.CONNECTING) {
      console.log('WebSocket connection already in progress')
      return
    }
    
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.close()
    }
    
    try {
      ws = new WebSocket(config.websocket.url)
      
      ws.onopen = () => {
        wsConnected.value = true
        wsError.value = null
        
        // Clear any reconnection timer
        if (reconnectTimer) {
          clearTimeout(reconnectTimer)
          reconnectTimer = null
        }
      }
      
      ws.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data)
          if (message.type === 'post_created') {
            handlePostUpdate(message.data)
          } else if (message.type === 'post_update') {
            handlePostUpdate(message.data)
          }
        } catch (error) {
          console.error('Error parsing WebSocket message:', error, 'Raw data:', event.data)
        }
      }
      
      ws.onclose = (event) => {
        wsConnected.value = false
        
        if (!event.wasClean && !reconnectTimer) {
          reconnectTimer = setTimeout(() => {
            reconnectTimer = null
            connectWebSocket()
          }, config.websocket.reconnectDelay)
        }
      }
      
      ws.onerror = (error) => {
        console.error('WebSocket error:', error)
        wsError.value = 'WebSocket connection failed'
      }
    } catch (error) {
      console.error('Failed to create WebSocket connection:', error)
      wsError.value = 'Failed to create WebSocket connection'
    }
  }

  const disconnectWebSocket = () => {
    if (reconnectTimer) {
      clearTimeout(reconnectTimer)
      reconnectTimer = null
    }
    
    if (ws) {
      ws.onclose = null // Prevent reconnection
      ws.close(1000, 'Component unmounted')
      ws = null
    }
    
    wsConnected.value = false
  }

  const handlePostUpdate = (postData: Post) => {
    const existingIndex = posts.value.findIndex(p => p.id === postData.id)
    
    if (existingIndex >= 0) {
      const oldStatus = posts.value[existingIndex].status
      const newStatus = postData.status

      posts.value[existingIndex] = {
        ...postData, 
        isBlinking: false,
        blinkType: undefined 
      }
      
      if (oldStatus === 'pending' && newStatus !== 'pending') {
        posts.value[existingIndex].isBlinking = true
        posts.value[existingIndex].blinkType = newStatus as 'approved' | 'rejected'
        
        setTimeout(() => {
          if (posts.value[existingIndex]) {
            posts.value[existingIndex].isBlinking = false
            posts.value[existingIndex].blinkType = undefined
          }
        }, 3000)
      }
    } else {
      posts.value.unshift({
        ...postData,
        isBlinking: false,
        blinkType: undefined
      })
    }
  }

  const setPosts = (newPosts: Post[]) => {
    posts.value = newPosts.map(post => ({
      ...post,
      isBlinking: false,
      blinkType: undefined
    }))
  }

  const addPost = (post: Post) => {
    posts.value.unshift({
      ...post,
      isBlinking: false,
      blinkType: undefined
    })
  }

  // Computed properties
  const pendingPosts = computed(() => 
    posts.value.filter(post => post.status === 'pending')
  )
  
  const approvedPosts = computed(() => 
    posts.value.filter(post => post.status === 'approved')
  )
  
  const rejectedPosts = computed(() => 
    posts.value.filter(post => post.status === 'rejected')
  )

  return {
    // State
    posts,
    loading,
    wsConnected,
    wsError,
    
    // Actions
    connectWebSocket,
    disconnectWebSocket,
    setPosts,
    addPost,
    handlePostUpdate,
    
    // Getters
    pendingPosts,
    approvedPosts,
    rejectedPosts
  }
})