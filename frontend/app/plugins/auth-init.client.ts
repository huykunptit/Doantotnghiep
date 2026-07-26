export default defineNuxtPlugin(() => {
  const auth = useAuthStore()
  if (!auth.ready) auth.hydrate()
  // Non-blocking: do not await fetchMe during plugin boot.
  // Sessions stored before permissions were serialized have no `permissions`
  // field, so refresh them too instead of gating the UI on stale data.
  if (auth.token && (!auth.user || !auth.user.permissions)) {
    auth.fetchMe().catch(() => undefined)
  }
})
