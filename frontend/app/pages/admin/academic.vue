<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import AcademicResourceManager from '~/components/admin/AcademicResourceManager.vue'
import AcademicSiblingNav from '~/components/admin/AcademicSiblingNav.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
  adminSearchPlaceholder: 'Tìm đơn vị, chương trình, học kỳ...',
})

const token = useAuthTokenCookie()
const user = useAuthUserCookie()

if (!user.value || !token.value) await navigateTo('/login', { replace: true })

const quickLinks = [
  { to: '/admin/academic/units', icon: 'account_tree', label: 'Đơn vị', description: 'Viện, khoa, bộ môn theo cấu trúc tổ chức.' },
  { to: '/admin/academic/programs', icon: 'school', label: 'Chương trình', description: 'Chương trình đào tạo, ngành và khung CT.' },
  { to: '/admin/academic/terms', icon: 'calendar_month', label: 'Học kỳ', description: 'Năm học, học kỳ và mốc thời gian học vụ.' },
  { to: '/admin/academic/cohorts', icon: 'groups', label: 'Khóa/Lớp', description: 'Khóa, lớp hành chính theo niên khóa.' },
  { to: '/admin/academic/class-sections', icon: 'class', label: 'Lớp học phần', description: 'Mở lớp học phần theo kỳ và phân công giảng viên.' },
  { to: '/admin/academic/outcomes', icon: 'flag', label: 'PLO / CLO / Skills', description: 'Chuẩn đầu ra chương trình, học phần và taxonomy kỹ năng.' },
]
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Tổ chức & Học vụ']"
    title="Quản lý Tổ chức & Học vụ"
    description="Quản lý cấu trúc tổ chức, học kỳ, chương trình đào tạo, ngành, khung chương trình và khóa/lớp học vụ."
  >
    <AcademicSiblingNav active="overview" />

    <section class="dashboard-card academic-quick">
      <div class="academic-quick-head">
        <div>
          <p class="section-kicker">Truy cập nhanh</p>
          <h3>Mở từng nhóm dữ liệu trong màn hình riêng</h3>
        </div>
        <span class="academic-quick-meta">{{ quickLinks.length }} nhóm chính</span>
      </div>
      <div class="academic-quick-grid">
        <NuxtLink
          v-for="link in quickLinks"
          :key="link.to"
          :to="link.to"
          class="academic-quick-card"
        >
          <div class="academic-quick-icon">
            <span class="material-symbols-outlined">{{ link.icon }}</span>
          </div>
          <div class="academic-quick-body">
            <strong>{{ link.label }}</strong>
            <p>{{ link.description }}</p>
          </div>
          <span class="material-symbols-outlined academic-quick-arrow">arrow_forward</span>
        </NuxtLink>
      </div>
    </section>

    <AcademicResourceManager />
  </AdminWorkspaceShell>
</template>

<style scoped>
.academic-quick { display: grid; gap: 16px; margin-bottom: 4px; }
.academic-quick-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}
.academic-quick-head h3 { margin: 0; font-size: 1.25rem; letter-spacing: -0.02em; }
.academic-quick-meta {
  font-size: 0.82rem;
  color: var(--muted);
  font-weight: 700;
  background: rgba(var(--green-rgb), 0.08);
  padding: 6px 12px;
  border-radius: 999px;
}

.academic-quick-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}

.academic-quick-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-radius: 20px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  background: #fff;
  text-decoration: none;
  color: inherit;
  transition: transform 180ms ease, border-color 180ms ease, background-color 180ms ease, box-shadow 180ms ease;
}
.academic-quick-card:hover {
  transform: translateY(-2px);
  border-color: rgba(var(--green-rgb), 0.32);
  background: rgba(var(--green-rgb), 0.04);
  box-shadow: 0 14px 28px -22px rgba(var(--green-rgb), 0.55);
}

.academic-quick-icon {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.academic-quick-icon .material-symbols-outlined { font-size: 24px; }

.academic-quick-body { display: grid; gap: 2px; min-width: 0; flex: 1; }
.academic-quick-body strong { font-size: 1rem; font-weight: 700; }
.academic-quick-body p {
  margin: 0;
  color: var(--muted);
  font-size: 0.84rem;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.academic-quick-arrow {
  color: var(--muted);
  font-size: 20px;
  transition: transform 180ms ease, color 180ms ease;
}
.academic-quick-card:hover .academic-quick-arrow {
  color: var(--green-deep);
  transform: translateX(2px);
}

@media (max-width: 640px) {
  .academic-quick-head { flex-direction: column; align-items: flex-start; }
}
</style>
