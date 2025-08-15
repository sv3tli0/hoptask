<template>
  <div class="bg-white shadow-md rounded-lg p-6 mb-6">
    <h2 class="text-xl font-bold mb-4">API Token Management</h2>
    
    <div v-if="!authStore.isAuthenticated()" class="space-y-4">
      <div>
        <label for="token-input" class="block text-sm font-medium text-gray-700 mb-2">
          Enter API Token
        </label>
        <input
          id="token-input"
          v-model="tokenInput"
          type="text"
          placeholder="Paste your API token here"
          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
        >
      </div>
      
      <div class="flex gap-2">
        <button
          @click="setToken"
          :disabled="!tokenInput.trim()"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
        >
          Set Token
        </button>
        
        <button
          @click="fetchToken"
          :disabled="loading"
          class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
        >
          {{ loading ? 'Fetching...' : 'Get Token from API' }}
        </button>
      </div>
      
      <div v-if="availableTokens.token_with_create_posts" class="mt-4 p-4 bg-gray-50 rounded-md">
        <h3 class="font-semibold text-sm mb-2">Available Tokens:</h3>
        <div class="space-y-2 text-xs">
          <div>
            <strong>With create_posts ability:</strong>
            <code class="block mt-1 p-2 bg-white border rounded text-xs break-all">
              {{ availableTokens.token_with_create_posts }}
            </code>
            <button
              @click="tokenInput = availableTokens.token_with_create_posts"
              class="mt-1 px-2 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600"
            >
              Use This Token
            </button>
          </div>
          <div>
            <strong>Without create_posts ability:</strong>
            <code class="block mt-1 p-2 bg-white border rounded text-xs break-all">
              {{ availableTokens.token_without_create_posts }}
            </code>
            <button
              @click="tokenInput = availableTokens.token_without_create_posts"
              class="mt-1 px-2 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600"
            >
              Use This Token
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <div v-else class="flex items-center justify-between">
      <div class="flex items-center text-green-600">
        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
        </svg>
        Token is set and active
      </div>
      <button
        @click="clearToken"
        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
      >
        Clear Token
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const tokenInput = ref('')
const loading = ref(false)
const availableTokens = ref<{
  token_with_create_posts: string
  token_without_create_posts: string
}>({ token_with_create_posts: '', token_without_create_posts: '' })

const setToken = () => {
  if (tokenInput.value.trim()) {
    authStore.setToken(tokenInput.value.trim())
    tokenInput.value = ''
  }
}

const clearToken = () => {
  authStore.clearToken()
  availableTokens.value = { token_with_create_posts: '', token_without_create_posts: '' }
}

const fetchToken = async () => {
  loading.value = true
  try {
    const response = await fetch('http://localhost:8000/api/token')
    if (response.ok) {
      const data = await response.json()
      availableTokens.value = data
    } else {
      console.error('Failed to fetch tokens')
    }
  } catch (error) {
    console.error('Error fetching tokens:', error)
  } finally {
    loading.value = false
  }
}
</script>