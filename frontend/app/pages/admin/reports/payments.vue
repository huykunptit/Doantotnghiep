<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })

const loading = ref(false)
const revenueData = ref([
  { month: 'Jan', value: 4500000 },
  { month: 'Feb', value: 5200000 },
  { month: 'Mar', value: 4800000 },
  { month: 'Apr', value: 6100000 },
  { month: 'May', value: 5900000 },
  { month: 'Jun', value: 7200000 },
])

const stats = ref({
  totalRevenue: 34500000,
  growth: 12.5,
  ordersCount: 156,
  averageOrder: 221153,
})

const topCourses = ref([
  { title: 'Khóa học Vue.js nâng cao', revenue: 12000000, share: 35 },
  { title: 'Thiết kế UI/UX chuyên nghiệp', revenue: 8500000, share: 25 },
  { title: 'Node.js Backend Master', revenue: 7200000, share: 21 },
  { title: 'Laravel & Nuxt Thực chiến', revenue: 6800000, share: 19 },
])

function formatMoney(value: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const maxRevenue = computed(() => Math.max(...revenueData.value.map(d => d.value)))
</script>

<template>
  <AdminWorkspaceShell
    title="Báo cáo thanh toán"
    description="Phân tích chuyên sâu về doanh thu, dòng tiền và hiệu suất kinh doanh của hệ thống."
    :breadcrumb="['Trang chủ', 'Tài chính', 'Báo cáo thanh toán']"
  >
    <!-- ── Overview Grid ── -->
    <section class="dashboard-grid" style="margin-bottom: 24px;">
      <article class="dashboard-card mini-card tone-green">
        <p class="mini-title">Tổng doanh thu</p>
        <div class="mini-head">
          <strong>{{ formatMoney(stats.totalRevenue) }}</strong>
          <span class="trend-up">+{{ stats.growth }}% so với tháng trước</span>
        </div>
      </article>

      <article class="dashboard-card mini-card tone-blue">
        <p class="mini-title">Tổng đơn hàng</p>
        <div class="mini-head">
          <strong>{{ stats.ordersCount }}</strong>
          <span>Đã hoàn tất thanh toán</span>
        </div>
      </article>

      <article class="dashboard-card mini-card tone-amber">
        <p class="mini-title">Giá trị trung bình</p>
        <div class="mini-head">
          <strong>{{ formatMoney(stats.averageOrder) }}</strong>
          <span>Mỗi đơn hàng thành công</span>
        </div>
      </article>
    </section>

    <div class="report-layout">
      <!-- ── Chart Section ── -->
      <section class="dashboard-card chart-container">
        <div class="card-head">
          <h3>Biểu đồ doanh thu (6 tháng qua)</h3>
          <p>Dữ liệu tổng hợp từ các giao dịch thực tế trên hệ thống.</p>
        </div>
        
        <div class="revenue-chart">
          <div v-for="item in revenueData" :key="item.month" class="chart-bar-wrap">
            <div 
              class="chart-bar" 
              :style="{ height: `${(item.value / maxRevenue) * 100}%` }"
              :title="formatMoney(item.value)"
            >
              <span class="bar-value">{{ formatMoney(item.value) }}</span>
            </div>
            <span class="bar-label">{{ item.month }}</span>
          </div>
        </div>
      </section>

      <!-- ── Side Panels ── -->
      <aside class="report-side">
        <section class="dashboard-card">
          <div class="card-head" style="margin-bottom: 20px;">
            <h3>Khóa học doanh thu cao</h3>
          </div>
          <div class="top-courses-list">
            <div v-for="course in topCourses" :key="course.title" class="top-course-item">
              <div class="course-info">
                <strong>{{ course.title }}</strong>
                <span>{{ formatMoney(course.revenue) }}</span>
              </div>
              <div class="progress-track">
                <div class="progress-fill" :style="{ width: `${course.share}%` }"></div>
              </div>
            </div>
          </div>
        </section>

        <section class="dashboard-card promo-card">
          <span class="material-symbols-outlined">auto_graph</span>
          <h4>Tối ưu doanh thu</h4>
          <p>Hệ thống gợi ý bạn nên đẩy mạnh quảng bá các khóa học trong danh mục Công nghệ thông tin.</p>
          <button class="crud-primary-btn" style="width: 100%; margin-top: 12px;">Xem chi tiết gợi ý</button>
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

.chart-container {
  min-height: 400px;
  display: flex;
  flex-direction: column;
}

.revenue-chart {
  margin-top: auto;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 240px;
  padding: 20px 10px;
  border-bottom: 2px solid var(--line);
}

.chart-bar-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.chart-bar {
  width: 42px;
  background: var(--green);
  border-radius: 8px 8px 0 0;
  position: relative;
  transition: all 0.3s ease;
  cursor: pointer;
}

.chart-bar:hover {
  background: var(--green-deep);
  transform: scaleX(1.1);
}

.bar-value {
  position: absolute;
  top: -24px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 0.7rem;
  font-weight: 700;
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.2s;
}

.chart-bar:hover .bar-value {
  opacity: 1;
}

.bar-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--muted);
}

.report-side {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.top-courses-list {
  display: grid;
  gap: 16px;
}

.top-course-item {
  display: grid;
  gap: 8px;
}

.course-info {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
}

.course-info strong {
  max-width: 20ch;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.progress-track {
  height: 6px;
  background: rgba(17, 17, 17, 0.05);
  border-radius: 999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--green);
  border-radius: 999px;
}

.promo-card {
  background: linear-gradient(135deg, var(--green-deep), var(--green));
  color: white;
  text-align: center;
}

.promo-card span {
  font-size: 48px;
  margin-bottom: 12px;
  opacity: 0.8;
}

.promo-card h4 {
  margin: 0 0 8px;
}

.promo-card p {
  font-size: 0.85rem;
  opacity: 0.9;
  line-height: 1.5;
}

.trend-up {
  font-size: 0.8rem;
  font-weight: 700;
  color: #16a34a;
  background: rgba(22, 163, 74, 0.1);
  padding: 2px 8px;
  border-radius: 999px;
}

@media (max-width: 1100px) {
  .report-layout {
    grid-template-columns: 1fr;
  }
}
</style>
