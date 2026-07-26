<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface Permission { id: number, name: string }
interface Role {
  id: number
  name: string
  permissions?: Permission[]
}

const { t } = useI18n()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const roles = ref<Role[]>([])
const permissions = ref<Permission[]>([])
const matrix = ref<Record<number, Set<string>>>({})
const baseline = ref<Record<number, Set<string>>>({})

const roleOrder = ['admin', 'instructor', 'student']
const thesisRoles = new Set(roleOrder)

const permissionGroups = computed(() => {
  const groups = [
    {
      key: 'system',
      label: t('admin.roles.groups.system'),
      match: (n: string) => /^(view_dashboard|manage_users|manage_roles)$/.test(n),
    },
    {
      key: 'academic',
      label: t('admin.roles.groups.academic'),
      match: (n: string) => /(academic|enrollment|grade|advise)/.test(n),
    },
    {
      key: 'course',
      label: t('admin.roles.groups.course'),
      match: (n: string) => /(course|lesson)/.test(n),
    },
    {
      key: 'exam',
      label: t('admin.roles.groups.exam'),
      match: (n: string) => /(exam|review)/.test(n),
    },
    {
      key: 'finance',
      label: t('admin.roles.groups.finance'),
      match: (n: string) => /(finance|report)/.test(n),
    },
    {
      key: 'other',
      label: t('admin.roles.groups.other'),
      match: () => true,
    },
  ]

  const used = new Set<string>()
  return groups
    .map((group) => {
      const items = permissions.value.filter((p) => {
        if (used.has(p.name)) return false
        if (group.match(p.name)) {
          used.add(p.name)
          return true
        }
        return false
      })
      return { ...group, items }
    })
    .filter(group => group.items.length > 0)
})

const displayRoles = computed(() => {
  const filtered = roles.value.filter(role => thesisRoles.has(role.name))
  return [...filtered].sort((a, b) => {
    const ai = roleOrder.indexOf(a.name)
    const bi = roleOrder.indexOf(b.name)
    return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi)
  })
})

const dirty = computed(() => {
  return displayRoles.value.some((role) => {
    if (role.name === 'admin') return false
    const a = matrix.value[role.id] || new Set()
    const b = baseline.value[role.id] || new Set()
    if (a.size !== b.size) return true
    for (const name of a) if (!b.has(name)) return true
    return false
  })
})

function roleLabel(name: string) {
  const key = `admin.users.roles.${name}`
  const translated = t(key)
  return translated === key ? name : translated
}

function permLabel(name: string) {
  const key = `admin.roles.perms.${name}`
  const translated = t(key)
  if (translated !== key) return translated
  return name.replace(/_/g, ' ')
}

function isLocked(role: Role) {
  return role.name === 'admin'
}

function hasPerm(roleId: number, perm: string) {
  return matrix.value[roleId]?.has(perm) ?? false
}

function toggle(role: Role, perm: string, value: boolean) {
  if (isLocked(role)) return
  const set = matrix.value[role.id] || new Set<string>()
  if (value) set.add(perm)
  else set.delete(perm)
  matrix.value[role.id] = new Set(set)
}

function toggleGroup(role: Role, perms: Permission[], value: boolean) {
  if (isLocked(role)) return
  const set = matrix.value[role.id] || new Set<string>()
  for (const p of perms) {
    if (value) set.add(p.name)
    else set.delete(p.name)
  }
  matrix.value[role.id] = new Set(set)
}

function groupState(role: Role, perms: Permission[]) {
  const set = matrix.value[role.id] || new Set()
  const count = perms.filter(p => set.has(p.name)).length
  if (count === 0) return false
  if (count === perms.length) return true
  return null
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ roles: Role[], permissions: Permission[] }>('/admin/roles')
    roles.value = res.roles || []
    permissions.value = res.permissions || []
    const next: Record<number, Set<string>> = {}
    const base: Record<number, Set<string>> = {}
    for (const role of roles.value) {
      const names = new Set((role.permissions || []).map(p => p.name))
      next[role.id] = new Set(names)
      base[role.id] = new Set(names)
    }
    matrix.value = next
    baseline.value = base
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.roles.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function resetChanges() {
  const next: Record<number, Set<string>> = {}
  for (const [id, set] of Object.entries(baseline.value)) {
    next[Number(id)] = new Set(set)
  }
  matrix.value = next
}

async function save() {
  saving.value = true
  try {
    const jobs = displayRoles.value
      .filter(role => !isLocked(role))
      .map(role => useApi(`/admin/roles/${role.id}/permissions`, {
        method: 'PUT',
        body: { permissions: [...(matrix.value[role.id] || [])] },
      }))
    await Promise.all(jobs)
    toast.add({ severity: 'success', summary: t('admin.roles.saved'), life: 2500 })
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.roles.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page roles-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.people') }}</span>
        <h1>{{ t('admin.roles.title') }}</h1>
        <p>{{ t('admin.roles.subtitle') }}</p>
        <Message severity="info" :closable="false" class="roles-note">{{ t('admin.roles.superuserNote') }}</Message>
      </div>
      <div class="page-actions">
        <Button :label="t('admin.roles.reset')" icon="pi pi-replay" severity="secondary" outlined :disabled="!dirty || saving" @click="resetChanges" />
        <Button :label="t('admin.roles.save')" icon="pi pi-check" :loading="saving" :disabled="!dirty" @click="save" />
      </div>
    </header>

    <section class="table-panel">
      <div v-if="loading" class="loading-box">
        <ProgressSpinner style="width:36px;height:36px" stroke-width="4" />
        <span>{{ t('common.loading') }}</span>
      </div>

      <div v-else class="matrix-wrap">
        <table class="matrix">
          <thead>
            <tr>
              <th class="perm-col">{{ t('admin.roles.permission') }}</th>
              <th v-for="role in displayRoles" :key="role.id" class="role-col">
                <span class="role-name">{{ roleLabel(role.name) }}</span>
                <small v-if="isLocked(role)">{{ t('admin.roles.locked') }}</small>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in permissionGroups" :key="group.key">
              <tr class="group-row">
                <td class="perm-col">
                  <strong>{{ group.label }}</strong>
                </td>
                <td v-for="role in displayRoles" :key="`${group.key}-${role.id}`" class="role-col">
                  <Checkbox
                    :model-value="groupState(role, group.items)"
                    :binary="false"
                    :indeterminate="groupState(role, group.items) === null"
                    :disabled="isLocked(role) || saving"
                    @update:model-value="(v: boolean | null) => toggleGroup(role, group.items, !!v)"
                  />
                </td>
              </tr>
              <tr v-for="perm in group.items" :key="perm.id" class="perm-row">
                <td class="perm-col">
                  <span>{{ permLabel(perm.name) }}</span>
                  <code>{{ perm.name }}</code>
                </td>
                <td v-for="role in displayRoles" :key="`${perm.id}-${role.id}`" class="role-col">
                  <Checkbox
                    :model-value="hasPerm(role.id, perm.name)"
                    :binary="true"
                    :disabled="isLocked(role) || saving"
                    @update:model-value="(v: boolean) => toggle(role, perm.name, v)"
                  />
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<style scoped>
.roles-page { gap: 14px; }
.workspace-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }
.roles-note { margin-top: 12px; max-width: 720px; }
.page-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px; overflow: auto;
}

.loading-box {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  min-height: 220px; color: var(--text-muted);
}

.matrix {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 720px;
}
.matrix th, .matrix td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.matrix thead th {
  position: sticky; top: 0; z-index: 2;
  background: var(--surface-subtle);
  color: var(--text-muted);
  font-size: .78rem;
  font-weight: 700;
  text-align: center;
}
.perm-col { text-align: left !important; min-width: 240px; }
.role-col { text-align: center; width: 120px; }
.role-name { display: block; color: var(--text); font-size: .86rem; font-weight: 700; }
.role-col small { color: var(--warning); font-size: .68rem; font-weight: 600; }

.group-row td {
  background: color-mix(in srgb, var(--brand-soft) 55%, transparent);
}
.group-row strong { font-size: .88rem; }
.perm-row code {
  display: block; margin-top: 2px; color: var(--text-muted);
  font-size: .68rem; font-weight: 500;
}
.perm-row span { font-size: .86rem; font-weight: 600; }
</style>
