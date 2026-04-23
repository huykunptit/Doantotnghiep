import { useAuthStore } from '~/stores/auth'

export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()

  if (!auth.isReady) {
    auth.initFromStorage()
  }

  if (auth.token && !auth.user) {
    await auth.fetchMe()
  }

  const roles = auth.user?.roles || []
  if (!roles.includes('admin')) {
    return navigateTo('/')
  }
})
