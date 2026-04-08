export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()

  if (!auth.isReady) {
    auth.initFromStorage()
  }

  if (auth.token && !auth.user) {
    await auth.fetchMe()
  }

  if (!auth.isLoggedIn || !auth.user) {
    return navigateTo('/login')
  }
})
