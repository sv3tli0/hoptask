import { ref } from 'vue'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('api_token'))

  const setToken = (newToken: string) => {
    token.value = newToken
    localStorage.setItem('api_token', newToken)
  }

  const clearToken = () => {
    token.value = null
    localStorage.removeItem('api_token')
  }

  const isAuthenticated = () => {
    return token.value !== null
  }

  return {
    token,
    setToken,
    clearToken,
    isAuthenticated
  }
})