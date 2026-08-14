<script setup lang="ts">
import { studentMenu } from '~/config/student-menu'

defineProps<{ mobile?: boolean }>()
const emit = defineEmits<{ navigate: [] }>()

const route = useRoute()
const auth = useAuthStore()
const { settings } = useSiteSettings()
const { t } = useI18n()
const loggingOut = ref(false)

const brand = computed(() => settings.value.site_name || 'ERIPT LMS')
const userName = computed(() => auth.user?.name || t('student.roleLabel'))
const userInitials = computed(() =>
  userName.value.split(' ').filter(Boolean).slice(-2).map(p => p[0]).join('').toUpperCase() || 'SV',
)

function isActive(to: string) {
  if (to === '/student') return route.path === '/student'
  if (to === '/courses') return route.path.startsWith('/courses')
  return route.path === to || route.path.startsWith(`${to}/`)
}

async function logout() {
  loggingOut.value = true
  try {
    await auth.logout()
    await navigateTo('/login')
  }
  finally {
    loggingOut.value = false
  }
}

onMounted(() => {
  if (!auth.ready) auth.hydrate()
})
</script>

<template>
  <aside class="sidebar" :class="{ mobile }">
    <NuxtLink to="/student" class="brand" @click="emit('navigate')">
      <CommonBrandMark />
      <span class="brand-copy">
        <strong>{{ brand }}</strong>
        <small>{{ t('student.console') }}</small>
      </span>
    </NuxtLink>

    <nav class="nav" :aria-label="t('student.console')">
      <NuxtLink
        v-for="item in studentMenu"
        :key="item.key"
        :to="item.to"
        class="link"
        :class="{ active: isActive(item.to) }"
        @click="emit('navigate')"
      >
        <i :class="item.icon" />
        <span>{{ t(item.labelKey) }}</span>
      </NuxtLink>
    </nav>

    <div class="account">
      <NuxtLink to="/student/profile" class="account-link" @click="emit('navigate')">
        <Avatar v-if="auth.avatarUrl" :image="auth.avatarUrl" shape="circle" />
        <Avatar v-else :label="userInitials" shape="circle" />
        <div class="account-copy">
          <strong>{{ userName }}</strong>
          <span>{{ t('student.roleLabel') }}</span>
        </div>
      </NuxtLink>
      <Button icon="pi pi-sign-out" severity="secondary" text rounded :loading="loggingOut" :aria-label="t('common.logout')" @click="logout" />
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  display: flex; flex-direction: column; width: var(--sidebar-width); height: 100dvh;
  background: linear-gradient(180deg, color-mix(in srgb, var(--brand) 8%, transparent) 0%, transparent 160px),
    color-mix(in srgb, var(--surface) 92%, transparent);
  border-right: 1px solid var(--border); backdrop-filter: blur(10px);
}
.brand { display: flex; align-items: center; gap: 12px; min-height: var(--topbar-height); padding: 14px 16px; border-bottom: 1px solid var(--border); }
.brand-copy { display: flex; flex-direction: column; min-width: 0; }
.brand-copy strong { overflow: hidden; color: var(--text); font-size: 1.08rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.brand-copy small { color: var(--text-muted); font-size: .74rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
.nav { flex: 1; min-height: 0; padding: 14px 10px; overflow-y: auto; }
.link {
  display: flex; align-items: center; gap: 10px; width: 100%; min-height: 42px; margin-bottom: 2px;
  padding: 0 12px; border-radius: 11px; color: var(--text-muted); font-size: .94rem; font-weight: 600;
}
.link > i:first-child { width: 18px; text-align: center; font-size: .88rem; }
.link:hover { background: var(--surface-hover); color: var(--text); }
.link.active { background: var(--brand-soft); color: var(--brand); font-weight: 700; box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 18%, transparent); }
.account { display: flex; align-items: center; gap: 10px; padding: 12px; border-top: 1px solid var(--border); }
.account-link { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; color: inherit; }
.account-copy { display: flex; flex: 1; flex-direction: column; min-width: 0; }
.account-copy strong { overflow: hidden; color: var(--text); font-size: .9rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.account-copy span { color: var(--text-muted); font-size: .76rem; font-weight: 500; }
.sidebar.mobile { width: 100%; border-right: 0; }
</style>
