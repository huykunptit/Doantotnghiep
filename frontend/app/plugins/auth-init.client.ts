export default defineNuxtPlugin(async () => {
  const auth = useAuthStore()
  if (!auth.ready) auth.hydrate()
  if (auth.token && !auth.user) await auth.fetchMe()
})
