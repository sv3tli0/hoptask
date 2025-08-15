import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [],
})

router.addRoute({
  name: 'home',
  path: '/',
  component: () => import('../pages/Index.vue'),
})
export default router
