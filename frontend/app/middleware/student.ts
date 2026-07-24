import { dashboardFor } from '~/types/auth'

export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()
  if (!auth.ready) auth.hydrate()
  if (auth.token && !auth.user) await auth.fetchMe()
  if (!auth.isAuthenticated) return navigateTo('/login')
  // Students + admin preview; instructors have their own portal
  if (auth.roles.includes('instructor') && !auth.roles.includes('admin') && !auth.roles.includes('student')) {
    return navigateTo(dashboardFor(auth.user))
  }
})
