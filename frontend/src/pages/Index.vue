<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { usePostsStore } from '@/stores/posts'
import apiService from '@/services/api'
import TokenManager from '@/components/TokenManager.vue'

const authStore = useAuthStore()
const postsStore = usePostsStore()
const postContent = ref('')
const postTitle = ref('')
const submittingPost = ref(false)

const canCreatePosts = computed(() => authStore.isAuthenticated())

const fetchPosts = async () => {
  postsStore.loading = true
  try {
    const response = await apiService.getPosts(authStore.token)
    if (response.data) {
      postsStore.setPosts(response.data.posts)
    } else if (response.error) {
      console.error('Error fetching posts:', response.error)
    }
  } catch (error) {
    console.error('Error fetching posts:', error)
  } finally {
    postsStore.loading = false
  }
}

const createPost = async () => {
  if (!authStore.token || !postTitle.value.trim() || !postContent.value.trim()) {
    return
  }

  submittingPost.value = true
  try {
    const response = await apiService.createPost(
        {
          title: postTitle.value,
          content: postContent.value,
        },
        authStore.token
    )

    if (response.data) {
      postTitle.value = ''
      postContent.value = ''
      // WebSocket will handle the update
    } else if (response.error) {
      alert(`Error: ${response.error}`)
    }
  } catch (error) {
    console.error('Error creating post:', error)
    alert('Network error occurred')
  } finally {
    submittingPost.value = false
  }
}

onMounted(() => {
  fetchPosts()
  postsStore.connectWebSocket()
})

onUnmounted(() => {
  postsStore.disconnectWebSocket()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Content Moderation System</h1>

        <!-- WebSocket Status -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 mb-4">
          <div class="flex items-center gap-2">
            <div
                :class="[
                'w-3 h-3 rounded-full',
                postsStore.wsConnected ? 'bg-green-500' : 'bg-red-500'
              ]"
            ></div>
            <span class="text-sm text-gray-600">
              {{ postsStore.wsConnected ? 'Connected' : 'Disconnected' }}
            </span>
          </div>

          <div class="text-sm text-gray-500">
            {{ postsStore.posts.length }} posts •
            {{ postsStore.pendingPosts.length }} pending •
            {{ postsStore.approvedPosts.length }} approved •
            {{ postsStore.rejectedPosts.length }} rejected
          </div>
        </div>

        <!-- Token Management -->
        <TokenManager />
      </div>

      <!-- Split Layout: Mobile stacked, Desktop side-by-side -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
        <!-- Left Side: Post Creation Form -->
        <div class="space-y-6">
          <div v-if="canCreatePosts" class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">Create New Post</h2>
            <form @submit.prevent="createPost" class="space-y-4">
              <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                  Title
                </label>
                <input
                    id="title"
                    v-model="postTitle"
                    type="text"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter post title"
                >
              </div>

              <div>
                <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                  Content
                </label>
                <textarea
                    id="content"
                    v-model="postContent"
                    required
                    rows="6"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter post content (try some bad words to test moderation)"
                ></textarea>
              </div>

              <button
                  type="submit"
                  :disabled="submittingPost || !postTitle.trim() || !postContent.trim()"
                  class="w-full px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
              >
                {{ submittingPost ? 'Creating...' : 'Create Post' }}
              </button>
            </form>
          </div>

          <div v-else class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-yellow-800">
              Please set an API token to create posts. Use a token with "create_posts" ability to enable post creation.
            </p>
          </div>
        </div>

        <!-- Right Side: Posts List -->
        <div class="bg-white shadow-md rounded-lg p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Live Posts Feed</h2>
            <button
                @click="fetchPosts"
                :disabled="postsStore.loading"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 disabled:bg-gray-300 transition-colors"
            >
              {{ postsStore.loading ? 'Loading...' : 'Refresh' }}
            </button>
          </div>

          <div v-if="postsStore.loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          </div>

          <div v-else-if="postsStore.posts.length === 0" class="text-center text-gray-500 py-8">
            No posts found. Create your first post!
          </div>

          <div v-else class="space-y-3 max-h-80 lg:max-h-96 overflow-y-auto">
            <TransitionGroup name="post-list" tag="div" class="space-y-3">
              <div
                  v-for="post in postsStore.posts"
                  :key="post.id"
                  :class="[
                  'border rounded-lg p-4 transition-all duration-500 relative overflow-hidden',
                  {
                    'border-gray-200 bg-white': !post.isBlinking,
                    'border-green-500 bg-green-50 shadow-lg': post.isBlinking && post.blinkType === 'approved',
                    'border-red-500 bg-red-50 shadow-lg': post.isBlinking && post.blinkType === 'rejected',
                  }
                ]"
              >
                <!-- Animated background for status updates -->
                <div
                    v-if="post.isBlinking"
                    :class="[
                    'absolute inset-0 animate-ping opacity-25',
                    {
                      'bg-green-400': post.blinkType === 'approved',
                      'bg-red-400': post.blinkType === 'rejected',
                    }
                  ]"
                ></div>
                <div class="flex justify-between items-start mb-2">
                  <h3 class="text-lg font-semibold">{{ post.title }}</h3>
                  <span
                      :class="[
                    'px-2 py-1 text-xs rounded-full font-medium',
                    {
                      'bg-yellow-100 text-yellow-800': post.status === 'pending',
                      'bg-green-100 text-green-800': post.status === 'approved',
                      'bg-red-100 text-red-800': post.status === 'rejected',
                      'bg-gray-100 text-gray-800': !post.status,
                    }
                  ]"
                  >
                  {{ post.status?.toUpperCase() || 'UNKNOWN' }}
                </span>
                </div>

                <p class="text-gray-700 mb-3 text-sm">{{ post.content }}</p>

                <div v-if="post.moderation_reason" class="mb-3 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                  <strong>Reason:</strong> {{ post.moderation_reason }}
                </div>

                <div class="text-xs text-gray-500">
                  <span>ID: {{ post.id }}</span>
                  <span class="mx-1">•</span>
                  <span>{{ new Date(post.created_at).toLocaleString() }}</span>
                  <span v-if="post.updated_at !== post.created_at" class="mx-1">•</span>
                  <span v-if="post.updated_at !== post.created_at">Updated: {{ new Date(post.updated_at).toLocaleString() }}</span>
                </div>
              </div>
            </TransitionGroup>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Post list animations */
.post-list-enter-active {
  transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.post-list-enter-from {
  opacity: 0;
  transform: translateY(-30px) scale(0.95);
  background: linear-gradient(90deg, #ddd6fe, #bfdbfe, #ddd6fe);
  background-size: 200% 200%;
  animation: shimmer 1s ease-in-out;
}

.post-list-enter-to {
  opacity: 1;
  transform: translateY(0) scale(1);
}

.post-list-leave-active {
  transition: all 0.3s ease-in;
}

.post-list-leave-to {
  opacity: 0;
  transform: translateX(30px);
}

.post-list-move {
  transition: transform 0.5s ease;
}

/* Shimmer effect for new posts */
@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

/* Enhanced blinking animations */
.animate-ping {
  animation: ping-enhanced 1s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes ping-enhanced {
  0%, 100% {
    transform: scale(1);
    opacity: 0.4;
  }
  50% {
    transform: scale(1.05);
    opacity: 0.1;
  }
}
</style>