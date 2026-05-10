<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import AcademicResourceManager from '~/components/admin/AcademicResourceManager.vue'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor'],
})

const overviewLinks = [
  { icon: 'account_tree', label: 'Đơn vị', description: 'Viện, khoa, bộ môn — bao gồm các đơn vị bạn được phân quyền.' },
  { icon: 'school', label: 'Chương trình', description: 'Chương trình đào tạo, ngành và khung chương trình theo đơn vị.' },
  { icon: 'calendar_month', label: 'Học kỳ', description: 'Năm học, học kỳ và các mốc thời gian học vụ hiện hành.' },
  { icon: 'groups', label: 'Khóa/Lớp', description: 'Khóa và lớp hành chính theo niên khóa và ngành.' },
]
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Giảng viên', 'Tổ chức & Học vụ']"
    title="Tổ chức & Học vụ"
    description="Tham khảo cấu trúc tổ chức và dữ liệu học vụ trong phạm vi đơn vị bạn được phân quyền. Mọi thao tác chỉnh sửa do quản trị viên thực hiện."
  >
    <section class="dashboard-card instructor-academic-banner">
      <div class="instructor-academic-banner-icon">
        <span class="material-symbols-outlined">visibility</span>
      </div>
      <div class="instructor-academic-banner-body">
        <p class="section-kicker">Chế độ chỉ xem</p>
        <h3>Bạn đang xem dữ liệu học vụ ở chế độ tham khảo</h3>
        <p>Dữ liệu được đồng bộ với hệ thống quản trị. Liên hệ quản trị viên nếu cần điều chỉnh đơn vị, chương trình, học kỳ hoặc khóa/lớp.</p>
      </div>
      <span class="academic-readonly-chip">
        <span class="material-symbols-outlined">lock</span>
        Read-only
      </span>
    </section>

    <section class="dashboard-card instructor-academic-quick">
      <div class="instructor-academic-quick-head">
        <div>
          <p class="section-kicker">Phạm vi dữ liệu</p>
          <h3>Các nhóm học vụ hiển thị</h3>
        </div>
      </div>
      <div class="instructor-academic-quick-grid">
        <article v-for="link in overviewLinks" :key="link.label" class="instructor-academic-quick-card">
          <div class="instructor-academic-quick-icon">
            <span class="material-symbols-outlined">{{ link.icon }}</span>
          </div>
          <div>
            <strong>{{ link.label }}</strong>
            <p>{{ link.description }}</p>
          </div>
        </article>
      </div>
    </section>

    <AcademicResourceManager readonly />
  </AdminWorkspaceShell>
</template>

<style scoped>
.instructor-academic-banner {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 4px;
}
.instructor-academic-banner-icon {
  width: 56px;
  height: 56px;
  border-radius: 18px;
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.instructor-academic-banner-icon .material-symbols-outlined { font-size: 26px; }
.instructor-academic-banner-body { flex: 1; min-width: 0; }
.instructor-academic-banner-body h3 { margin: 0; font-size: 1.2rem; letter-spacing: -0.02em; }
.instructor-academic-banner-body p {
  margin: 4px 0 0;
  color: var(--muted);
  line-height: 1.55;
  font-size: 0.92rem;
}

.academic-readonly-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 999px;
  background: rgba(17, 17, 17, 0.06);
  color: var(--muted);
  font-size: 0.78rem;
  font-weight: 700;
  flex-shrink: 0;
}
.academic-readonly-chip .material-symbols-outlined { font-size: 16px; }

.instructor-academic-quick { display: grid; gap: 14px; margin-bottom: 4px; }
.instructor-academic-quick-head h3 { margin: 0; font-size: 1.2rem; letter-spacing: -0.02em; }

.instructor-academic-quick-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}
.instructor-academic-quick-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  border-radius: 18px;
  background: rgba(17, 17, 17, 0.03);
  border: 1px solid rgba(17, 17, 17, 0.04);
}
.instructor-academic-quick-icon {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.instructor-academic-quick-icon .material-symbols-outlined { font-size: 22px; }
.instructor-academic-quick-card strong { font-size: 0.95rem; font-weight: 700; }
.instructor-academic-quick-card p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.84rem;
  line-height: 1.45;
}

@media (max-width: 720px) {
  .instructor-academic-banner { flex-wrap: wrap; }
  .academic-readonly-chip { order: -1; }
}
</style>
