export function usePermissions() {
  const auth = useAuthStore()

  const isAdmin = computed(() => {
    const roles = auth.roles
    return roles.includes('admin')
  })

  const permissions = computed(() => auth.user?.permissions || [])

  function can(name: string): boolean {
    if (isAdmin.value) return true
    return permissions.value.includes(name)
  }

  function canAny(names: string[]): boolean {
    if (isAdmin.value) return true
    return names.some(n => permissions.value.includes(n))
  }

  function canAll(names: string[]): boolean {
    if (isAdmin.value) return true
    return names.every(n => permissions.value.includes(n))
  }

  return {
    isAdmin,
    permissions,
    can,
    canAny,
    canAll,
  }
}
