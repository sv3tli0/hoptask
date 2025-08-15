import config from '@/config/api'

export interface ApiResponse<T = any> {
  data?: T
  message?: string
  error?: string
}

export interface Post {
  id: number
  title?: string
  content: string
  status: 'pending' | 'approved' | 'rejected'
  moderation_reason?: string | null
  created_at: string
  updated_at: string
}

export interface CreatePostRequest {
  title: string
  content: string
}

class ApiService {
  private baseUrl: string
  private timeout: number

  constructor() {
    this.baseUrl = config.api.baseUrl
    this.timeout = config.api.timeout
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<ApiResponse<T>> {
    const url = `${this.baseUrl}/api${endpoint}`
    
    const defaultHeaders = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    }

    const config: RequestInit = {
      ...options,
      headers: {
        ...defaultHeaders,
        ...options.headers,
      },
    }

    // Add timeout
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), this.timeout)
    config.signal = controller.signal

    try {
      const response = await fetch(url, config)
      clearTimeout(timeoutId)

      const data = await response.json()

      if (!response.ok) {
        return {
          error: data.message || `HTTP ${response.status}: ${response.statusText}`,
          data: undefined
        }
      }

      return { data }
    } catch (error) {
      clearTimeout(timeoutId)
      
      if (error instanceof Error) {
        if (error.name === 'AbortError') {
          return { error: 'Request timed out' }
        }
        return { error: error.message }
      }
      
      return { error: 'An unexpected error occurred' }
    }
  }

  private getAuthHeaders(token: string | null): HeadersInit {
    if (!token) return {}
    return { 'Authorization': `Bearer ${token}` }
  }

  async getTokens(): Promise<ApiResponse<{ token_with_create_posts: string; token_without_create_posts: string }>> {
    return this.request('/token', {
      method: 'GET',
    })
  }
  async getPosts(token?: string | null): Promise<ApiResponse<{ posts: Post[] }>> {
    return this.request('/posts', {
      method: 'GET',
      headers: this.getAuthHeaders(token || null),
    })
  }

  async createPost(
    data: CreatePostRequest,
    token: string
  ): Promise<ApiResponse<{ post: Post; message: string }>> {
    return this.request('/posts', {
      method: 'POST',
      headers: this.getAuthHeaders(token),
      body: JSON.stringify(data),
    })
  }

  // Utility methods
  getApiBaseUrl(): string {
    return this.baseUrl
  }

  getWebSocketUrl(): string {
    return config.websocket.url
  }
}

// Export singleton instance
export const apiService = new ApiService()

export { ApiService }
export default apiService