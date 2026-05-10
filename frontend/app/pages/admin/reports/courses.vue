<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })

const loading = ref(false)
const categoryStats = ref([
  { name: 'Công nghệ thông tin', count: 42, percentage: 45 },
  { name: 'Kinh doanh & Marketing', count: 28, percentage: 30 },
  { name: 'Ngoại ngữ', count: 15, percentage: 16 },
  { name: 'Kỹ năng mềm', count: 8, percentage: 9 },
])

const performanceMetrics = ref([
  { label: 'Tỷ lệ hoàn thành', value: '68%', icon: 'task_alt', color: 'tone-green' },
  { label: 'Đánh giá trung bình', value: '4.8/5', icon: 'grade', color: 'tone-amber' },
  { label: 'Giảng viên hoạt động', value: '12', icon: 'person_book', color: 'tone-blue' },
])

const recentEnrollments = ref([
  { user: 'Nguyễn Văn A', course: 'Lập trình Nuxt.js Masterclass', time: '2 phút trước' },
  { user: 'Trần Thị B', course: 'Thiết kế Figma cơ bản', time: '15 phút trước' },
  { user: 'Lê Văn C', course: 'Tiếng Anh giao tiếp công sở', time: '1 giờ trước' },
  { user: 'Phạm Thị D', course: 'Digital Marketing 2024', time: '3 giờ trước' },
])
</script>

<template>
  <AdminWorkspaceShell
    title="Báo cáo theo khóa học"
    description="Theo dõi sự phân bổ danh mục, mức độ tương tác và hiệu quả đào tạo của toàn bộ hệ thống khóa học."
    :breadcrumb="['Trang chủ', 'Báo cáo', 'Báo cáo khóa học']"
  >
    <!-- ── Performance Metrics ── -->
    <section class="dashboard-grid" style="margin-bottom: 24px;">
      <article v-for="metric in performanceMetrics" :key="metric.label" class="dashboard-card mini-card" :class="metric.color">
        <p class="mini-title">{{ metric.label }}</p>
        <div class="mini-head">
          <strong>{{ metric.value }}</strong>
          <span class="material-symbols-outlined" style="opacity: 0.5;">{{ metric.icon }}</span>
        </div>
      </article>
    </section>

    <div class="report-layout">
      <!-- ── Category Distribution ── -->
      <section class="dashboard-card">
        <div class="card-head" style="margin-bottom: 24px;">
          <h3>Phân bổ theo danh mục</h3>
          <p>Thống kê số lượng khóa học hiện có theo từng lĩnh vực đào tạo.</p>
        </div>

        <div class="category-bars">
          <div v-for="cat in categoryStats" :key="cat.name" class="category-item">
            <div class="cat-label">
              <strong>{{ cat.name }}</strong>
              <span>{{ cat.count }} khóa học ({{ cat.percentage }}%)</span>
            </div>
            <div class="cat-track">
              <div class="cat-fill" :style="{ width: `${cat.percentage}%` }"></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Recent Activity ── -->
      <aside class="report-side">
        <section class="dashboard-card">
          <div class="card-head" style="margin-bottom: 20px;">
            <h3>Đăng ký mới nhất</h3>
          </div>
          <div class="activity-timeline">
            <div v-for="(item, idx) in recentEnrollments" :key="idx" class="timeline-item">
              <div class="timeline-dot"></div>
              <div class="timeline-content">
                <strong>{{ item.user }}</strong>
                <p>vừa đăng ký: <em>{{ item.course }}</em></p>
                <small>{{ item.time }}</small>
              </div>
            </div>
          </div>
        </section>

        <section class="dashboard-card stat-highlight">
          <h4>Khóa học triển vọng</h4>
          <div class="highlight-info">
            <strong>850+</strong>
            <span>Học viên mới trong tuần này</span>
          </div>
          <p>Tăng trưởng 20% so với tuần trước nhờ chiến dịch Marketing tháng 5.</p>
        </section>
      </aside>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
.report-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
}

.category-bars {
  display: grid;
  gap: 24px;
}

.category-item {
  display: grid;
  gap: 10px;
}

.cat-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.9rem;
}

.cat-label span {
  color: var(--muted);
  font-size: 0.8rem;
}

.cat-track {
  height: 12px;
  background: rgba(var(--green-rgb), 0.05);
  border-radius: 999px;
  overflow: hidden;
}

.cat-fill {
  height: 100%;
  background: var(--green);
  border-radius: 999px;
  transition: width 1s ease-out;
}

.report-side {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.activity-timeline {
  display: grid;
  gap: 20px;
  position: relative;
  padding-left: 10px;
}

.activity-timeline::before {
  content: '';
  position: absolute;
  left: 13px;
  top: 5px;
  bottom: 5px;
  width: 2px;
  background: rgba(17, 17, 17, 0.05);
}

.timeline-item {
  display: flex;
  gap: 16px;
  position: relative;
  z-index: 1;
}

.timeline-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--green);
  margin-top: 6px;
  flex-shrink: 0;
  box-shadow: 0 0 0 4px white;
}

.timeline-content strong {
  font-size: 0.85rem;
  display: block;
}

.timeline-content p {
  font-size: 0.8rem;
  margin: 2px 0;
  color: var(--muted);
}

.timeline-content small {
  font-size: 0.7rem;
  color: #94a3b8;
}

.stat-highlight {
  background: var(--green-soft);
  border: 1px dashed var(--green);
  text-align: center;
}

.highlight-info {
  margin: 12px 0;
}

.highlight-info strong {
  font-size: 2rem;
  color: var(--green-deep);
  display: block;
}

.highlight-info span {
  font-size: 0.8rem;
  color: var(--green);
  font-weight: 700;
}

.stat-highlight p {
  font-size: 0.8rem;
  color: var(--muted);
  line-height: 1.5;
}

@media (max-width: 1100px) {
  .report-layout {
    grid-template-columns: 1fr;
  }
}
</style>
