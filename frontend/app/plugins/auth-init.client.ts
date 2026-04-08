export default defineNuxtPlugin(async () => {
  const auth = useAuthStore()
  auth.initFromStorage()
  await auth.fetchMe()
})
