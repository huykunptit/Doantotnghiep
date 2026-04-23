<script setup lang="ts">
defineProps<{
  userName: string
  userRole: string
}>()

const route = useRoute()
const { groups, supportItems } = useAdminNavigation()

function isActive(path: string) {
  if (path === '/admin') {
    return route.path === '/admin'
  }

  return route.path.startsWith(path)
}

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
  <aside class="dashboard-sidebar">
    <div class="sidebar-brand">
      <div class="brand-line">
        <span class="brand-mark" />
        <span class="brand-name">PTIT LMS</span>
      </div>
      <div>
        <p class="sidebar-eyebrow">Admin role</p>
        <h1>Admin</h1>
      </div>
    </div>

    <nav class="sidebar-nav-grouped">
      <section v-for="group in groups" :key="group.label" class="sidebar-nav-section">
        <p class="sidebar-label">{{ group.label }}</p>
        <div class="sidebar-nav">
          <NuxtLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="sidebar-link"
            :class="{ 'is-active': isActive(item.to) }"
          >
            <span class="sidebar-icon">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
          </NuxtLink>
        </div>
      </section>
    </nav>

    <section class="sidebar-section">
      <p class="sidebar-label">Ho tro</p>
      <ul>
        <li v-for="item in supportItems" :key="item">{{ item }}</li>
      </ul>
    </section>

    <div class="sidebar-profile">
      <div class="avatar-chip">{{ userName.slice(0, 2).toUpperCase() }}</div>
      <div>
        <strong>{{ userName }}</strong>
        <p>{{ userRole }}</p>
      </div>
      <button type="button" class="profile-action button-reset" @click="handleLogout">Thoat</button>
    </div>
  </aside>
</template>
