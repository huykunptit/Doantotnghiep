import { dashboardFor } from '~/types/auth'

/** Admin or instructor may edit course content (ownership enforced by API). */
export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()
  if (!auth.ready) auth.hydrate()
  if (auth.token && !auth.user) await auth.fetchMe()
  if (!auth.isAuthenticated) return navigateTo('/login')
  if (!auth.roles.some(role => role === 'admin' || role === 'instructor')) {
    return navigateTo(dashboardFor(auth.user))
  }
})
