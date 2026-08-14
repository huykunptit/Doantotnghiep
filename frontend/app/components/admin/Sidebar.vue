<script setup lang="ts">
import { adminMenu } from '~/config/admin-menu'

defineProps<{ mobile?: boolean }>()
const emit = defineEmits<{ navigate: [] }>()

const route = useRoute()
const auth = useAuthStore()
const { settings } = useSiteSettings()
const { t } = useI18n()
const openGroups = ref<string[]>([])
const loggingOut = ref(false)

const brand = computed(() => settings.value.site_name || 'ERIPT LMS')
const userName = computed(() => auth.user?.name || 'Admin LMS')
const userInitials = computed(() =>
  userName.value
    .split(' ')
    .filter(Boolean)
    .slice(-2)
    .map(part => part[0])
    .join('')
    .toUpperCase() || 'AD',
)

function isChildActive(to: string) {
  // Exact match wins; prefix only when no longer sibling path exists under a
  // more specific menu entry (handled by preferring exact elsewhere).
  if (route.path === to) return true
  if (!route.path.startsWith(`${to}/`)) return false
  // Avoid /admin/academic lighting up for /admin/lnd/... etc. — only prefix
  // activate when this `to` is the longest matching menu child.
  const allTos = adminMenu.flatMap(item => [
    ...(item.to ? [item.to] : []),
    ...(item.children?.map(c => c.to) || []),
  ])
  const longer = allTos.some(other =>
    other !== to
    && other.length > to.length
    && (route.path === other || route.path.startsWith(`${other}/`))
    && other.startsWith(`${to}/`),
  )
  return !longer
}

function isGroupActive(item: (typeof adminMenu)[number]) {
  if (item.to) return route.path === item.to
  return item.children?.some(child => isChildActive(child.to)) ?? false
}

function toggleGroup(key: string) {
  openGroups.value = openGroups.value.includes(key)
    ? openGroups.value.filter(item => item !== key)
    : [...openGroups.value, key]
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

watch(
  () => route.path,
  () => {
    const active = adminMenu.find(item => isGroupActive(item))
    if (active?.children && !openGroups.value.includes(active.key)) {
      openGroups.value = [...openGroups.value, active.key]
    }
  },
  { immediate: true },
)

onMounted(() => {
  if (!auth.ready) auth.hydrate()
})
</script>

<template>
  <aside class="sidebar" :class="{ mobile }">
    <NuxtLink to="/admin" class="brand" @click="emit('navigate')">
      <CommonBrandMark />
      <span class="brand-copy">
        <strong>{{ brand }}</strong>
        <small>{{ t('admin.console') }}</small>
      </span>
    </NuxtLink>

    <nav class="nav" :aria-label="t('admin.console')">
      <template v-for="item in adminMenu" :key="item.key">
        <NuxtLink
          v-if="item.to"
          :to="item.to"
          class="link"
          :class="{ active: isGroupActive(item) }"
          @click="emit('navigate')"
        >
          <i :class="item.icon" />
          <span>{{ t(item.labelKey) }}</span>
        </NuxtLink>

        <div v-else class="group" :class="{ open: openGroups.includes(item.key), active: isGroupActive(item) }">
          <button type="button" class="link trigger" @click="toggleGroup(item.key)">
            <i :class="item.icon" />
            <span>{{ t(item.labelKey) }}</span>
            <i class="pi pi-angle-down chevron" />
          </button>

          <div v-show="openGroups.includes(item.key)" class="children">
            <NuxtLink
              v-for="child in item.children"
              :key="child.to"
              :to="child.to"
              class="child"
              :class="{ active: isChildActive(child.to) }"
              @click="emit('navigate')"
            >
              <span class="dot" />
              <span>{{ t(child.labelKey) }}</span>
            </NuxtLink>
          </div>
        </div>
      </template>
    </nav>

    <div class="account">
      <Avatar v-if="auth.avatarUrl" :image="auth.avatarUrl" shape="circle" />
      <Avatar v-else :label="userInitials" shape="circle" />
      <div class="account-copy">
        <strong>{{ userName }}</strong>
        <span>{{ t('admin.dashboard.adminFallback') }}</span>
      </div>
      <Button
        icon="pi pi-sign-out"
        severity="secondary"
        text
        rounded
        :loading="loggingOut"
        :aria-label="t('common.logout')"
        @click="logout"
      />
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  display: flex;
  flex-direction: column;
  width: var(--sidebar-width);
  height: 100dvh;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--brand) 8%, transparent) 0%, transparent 160px),
    color-mix(in srgb, var(--surface) 92%, transparent);
  border-right: 1px solid var(--border);
  backdrop-filter: blur(10px);
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: var(--topbar-height);
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
}

.brand-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.brand-copy strong {
  overflow: hidden;
  color: var(--text);
  font-size: 1.08rem;
  font-weight: 700;
  letter-spacing: -.02em;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.brand-copy small {
  color: var(--text-muted);
  font-size: .74rem;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.nav {
  flex: 1;
  min-height: 0;
  padding: 14px 10px;
  overflow-y: auto;
}

.link {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 42px;
  margin-bottom: 2px;
  padding: 0 12px;
  border: 0;
  border-radius: 11px;
  background: transparent;
  color: var(--text-muted);
  font-size: .94rem;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  transition: background .15s ease, color .15s ease, transform .15s ease;
}

.link > i:first-child {
  width: 18px;
  font-size: .88rem;
  text-align: center;
}

.link:hover {
  background: var(--surface-hover);
  color: var(--text);
}

.link.active {
  background: var(--brand-soft);
  color: var(--brand);
  font-weight: 700;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 18%, transparent);
}

.trigger span {
  flex: 1;
}

.chevron {
  font-size: .7rem;
  transition: transform .18s ease;
}

.group.open .chevron {
  transform: rotate(180deg);
}

.group.active > .trigger {
  color: var(--brand);
}

.children {
  display: grid;
  gap: 2px;
  margin: 2px 0 10px;
  padding: 4px 0 4px 16px;
}

.child {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 34px;
  padding: 0 10px;
  border-radius: 9px;
  color: var(--text-muted);
  font-size: .88rem;
  font-weight: 500;
  line-height: 1.3;
  transition: background .15s ease, color .15s ease;
}

.dot {
  width: 5px;
  height: 5px;
  flex: 0 0 5px;
  border-radius: 50%;
  background: var(--border-strong);
}

.child:hover {
  background: var(--surface-hover);
  color: var(--text);
}

.child.active {
  background: color-mix(in srgb, var(--brand-soft) 80%, transparent);
  color: var(--brand);
  font-weight: 700;
}

.child.active .dot {
  background: var(--brand);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent);
}

.account {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-top: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface-subtle) 80%, transparent);
}

.account-copy {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.account-copy strong {
  overflow: hidden;
  color: var(--text);
  font-size: .9rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-copy span {
  color: var(--text-muted);
  font-size: .76rem;
  font-weight: 500;
}

.sidebar.mobile {
  width: 100%;
  border-right: 0;
}
</style>
