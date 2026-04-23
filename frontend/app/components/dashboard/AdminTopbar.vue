<script setup lang="ts">
const emit = defineEmits<{
  toggleSidebar: []
}>()

defineProps<{
  searchPlaceholder: string
  userName: string
  userRole: string
}>()

async function handleLogout() {
  const token = useAuthTokenCookie()

  try {
    if (token.value) {
      await useApi('/auth/logout', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token.value}`,
        },
      })
    }
  } catch {
    // Clear local session even if request fails.
  } finally {
    clearAuthSession()
    await navigateTo('/login')
  }
}
</script>

<template>
  <header class="dashboard-topbar">
    <button class="topbar-ghost" type="button" aria-label="Mo sidebar" @click="emit('toggleSidebar')">☰</button>

    <div class="topbar-search">
      <span>⌕</span>
      <input type="text" :placeholder="searchPlaceholder" />
    </div>

    <div class="topbar-actions">
      <button class="topbar-ghost" type="button">◐</button>
      <button class="topbar-ghost" type="button">🔔</button>
      <button class="topbar-logout" type="button" @click="handleLogout">Đăng xuất</button>
      <div class="topbar-user">
        <div class="avatar-chip is-small">{{ userName.slice(0, 2).toUpperCase() }}</div>
        <div>
          <strong>{{ userName }}</strong>
          <p>{{ userRole }}</p>
        </div>
      </div>
    </div>
  </header>
</template>
