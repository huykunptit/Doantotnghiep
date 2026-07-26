export default defineNuxtRouteMiddleware((to) => {
  const required = to.meta.permission as string | string[] | undefined
  if (!required) return

  const auth = useAuthStore()
  if (!auth.ready) auth.hydrate()

  const { can, canAny, isAdmin } = usePermissions()
  if (isAdmin.value) return

  const ok = Array.isArray(required) ? canAny(required) : can(required)
  if (!ok) {
    // Avoid redirect loop when the instructor home also requires a permission.
    if (to.path === '/instructor' || to.path === '/instructor/') {
      return navigateTo('/')
    }
    return navigateTo('/instructor')
  }
})
