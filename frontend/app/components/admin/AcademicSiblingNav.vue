<script setup lang="ts">
withDefaults(defineProps<{
  active: 'overview' | 'units' | 'programs' | 'terms' | 'cohorts' | 'class-sections' | 'outcomes'
  basePath?: string
}>(), {
  basePath: '/admin/academic',
})

const links = [
  { id: 'overview', icon: 'dashboard', label: 'Tổng quan', path: '' },
  { id: 'units', icon: 'account_tree', label: 'Đơn vị', path: '/units' },
  { id: 'programs', icon: 'school', label: 'Chương trình', path: '/programs' },
  { id: 'terms', icon: 'calendar_month', label: 'Học kỳ', path: '/terms' },
  { id: 'cohorts', icon: 'groups', label: 'Khóa/Lớp', path: '/cohorts' },
  { id: 'class-sections', icon: 'class', label: 'Lớp học phần', path: '/class-sections' },
  { id: 'outcomes', icon: 'flag', label: 'PLO / CLO / Skills', path: '/outcomes' },
] as const
</script>

<template>
  <nav class="academic-sibling-nav dashboard-card" aria-label="Điều hướng học vụ">
    <NuxtLink
      v-for="link in links"
      :key="link.id"
      :to="`${basePath}${link.path}`"
      :class="['academic-sibling-link', { 'is-active': active === link.id }]"
    >
      <span class="material-symbols-outlined">{{ link.icon }}</span>
      <span>{{ link.label }}</span>
    </NuxtLink>
  </nav>
</template>

<style scoped>
.academic-sibling-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 8px;
  margin-bottom: 4px;
}

.academic-sibling-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 14px;
  font-size: 0.92rem;
  font-weight: 600;
  color: var(--muted);
  text-decoration: none;
  transition: background-color 180ms ease, color 180ms ease, transform 180ms ease;
}
.academic-sibling-link:hover {
  background: rgba(var(--green-rgb), 0.06);
  color: var(--green-deep);
}
.academic-sibling-link.is-active {
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
}
.academic-sibling-link .material-symbols-outlined { font-size: 18px; }

@media (max-width: 600px) {
  .academic-sibling-nav { overflow-x: auto; flex-wrap: nowrap; }
  .academic-sibling-link { white-space: nowrap; }
}
</style>
