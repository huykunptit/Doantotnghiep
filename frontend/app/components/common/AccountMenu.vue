<script setup lang="ts">
import { dashboardFor, profilePathFor } from '~/types/auth'

const auth = useAuthStore()
const { t } = useI18n()

const accountPanel = ref()
const loggingOut = ref(false)

const userInitials = computed(() =>
  (auth.user?.name || 'U')
    .split(' ')
    .filter(Boolean)
    .slice(-2)
    .map(part => part[0])
    .join('')
    .toUpperCase(),
)

const profileTo = computed(() => profilePathFor(auth.user))
const dashboardTo = computed(() => dashboardFor(auth.user))
const isStudent = computed(() => {
  const roles = auth.user?.roles || (auth.user?.role ? [auth.user.role] : [])
  return roles.includes('student') && !roles.includes('admin') && !roles.includes('instructor')
})

function toggleAccountPanel(event: Event) {
  accountPanel.value?.toggle(event)
}

async function go(path: string) {
  accountPanel.value?.hide()
  await navigateTo(path)
}

async function logout() {
  accountPanel.value?.hide()
  loggingOut.value = true
  try {
    await auth.logout()
    await navigateTo('/login')
  }
  finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <div class="account-wrap">
    <button type="button" class="account-trigger" :aria-label="t('common.myProfile')" @click="toggleAccountPanel">
      <Avatar v-if="auth.avatarUrl" :image="auth.avatarUrl" shape="circle" />
      <Avatar v-else :label="userInitials" shape="circle" />
    </button>
    <Popover ref="accountPanel" class="account-pop">
      <div class="account-menu">
        <div class="account-menu-head">
          <Avatar v-if="auth.avatarUrl" :image="auth.avatarUrl" shape="circle" />
          <Avatar v-else :label="userInitials" shape="circle" />
          <div class="account-menu-info">
            <strong>{{ auth.user?.name || '—' }}</strong>
            <span>{{ auth.user?.email }}</span>
          </div>
        </div>
        <button type="button" class="account-menu-item" @click="go(profileTo)">
          <i class="pi pi-user-edit" />
          <span>{{ t('common.myProfile') }}</span>
        </button>
        <button v-if="isStudent" type="button" class="account-menu-item" @click="go('/student/id-card')">
          <i class="pi pi-id-card" />
          <span>{{ t('student.menu.idCard') }}</span>
        </button>
        <button type="button" class="account-menu-item" @click="go(dashboardTo)">
          <i class="pi pi-th-large" />
          <span>{{ t('common.dashboard') }}</span>
        </button>
        <button type="button" class="account-menu-item" @click="go('/')">
          <i class="pi pi-home" />
          <span>{{ t('common.home') }}</span>
        </button>
        <button type="button" class="account-menu-item danger" :disabled="loggingOut" @click="logout">
          <i class="pi pi-sign-out" />
          <span>{{ t('common.logout') }}</span>
        </button>
      </div>
    </Popover>
  </div>
</template>

<style scoped>
.account-wrap { display: inline-flex; }
.account-trigger {
  display: inline-flex;
  border: 0;
  padding: 0;
  background: transparent;
  cursor: pointer;
  border-radius: 50%;
}
.account-menu {
  display: flex;
  flex-direction: column;
  width: min(260px, 86vw);
  gap: 4px;
}
.account-menu-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 4px 10px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--border);
}
.account-menu-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.account-menu-info strong {
  overflow: hidden;
  color: var(--text);
  font-size: .92rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.account-menu-info span {
  overflow: hidden;
  color: var(--text-muted);
  font-size: .78rem;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.account-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 38px;
  padding: 0 10px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: var(--text);
  font: inherit;
  font-size: .88rem;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  transition: background .15s ease;
}
.account-menu-item i {
  width: 16px;
  font-size: .85rem;
  text-align: center;
}
.account-menu-item:hover { background: var(--surface-hover); }
.account-menu-item.danger { color: #dc2626; }
.account-menu-item:disabled { opacity: .6; cursor: not-allowed; }
</style>
