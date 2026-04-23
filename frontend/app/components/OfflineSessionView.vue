<template>
  <div class="offline-session-view">
    <div class="info-card shadow-premium">
      <div class="icon-header">
        <i class="fas fa-map-marker-alt"></i>
      </div>
      
      <div class="content">
        <h2>Học trực tiếp (Offline)</h2>
        <p class="desc">Vui lòng có mặt đúng giờ tại địa điểm đã thông báo.</p>
        
        <div class="details">
          <div class="detail-row">
            <span class="label">Địa điểm:</span>
            <span class="value">{{ data.location }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Ngày học:</span>
            <span class="value">{{ formatDate(data.start_at) }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Thời gian:</span>
            <span class="value">{{ formatTime(data.start_at) }}</span>
          </div>
          <div v-if="data.max_participants" class="detail-row">
            <span class="label">Số lượng giới hạn:</span>
            <span class="value">{{ data.max_participants }} học viên</span>
          </div>
        </div>
        
        <div class="note-box">
          <i class="fas fa-info-circle"></i>
          <span>Vui lòng mang theo giáo trình và laptop nếu có yêu cầu từ giảng viên.</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  data: any
}>()

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('vi-VN', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

function formatTime(dateStr: string) {
  return new Date(dateStr).toLocaleTimeString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<style scoped>
.offline-session-view {
  padding: 3rem;
  display: flex;
  justify-content: center;
  background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
  min-height: 450px;
}

.info-card {
  background: white;
  border-radius: 24px;
  width: 100%;
  max-width: 550px;
  display: flex;
  overflow: hidden;
}

.icon-header {
  background: #1e293b;
  width: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 2rem;
}

.content {
  padding: 2.5rem;
  flex: 1;
}

.content h2 {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.5rem;
}

.desc {
  color: #64748b;
  font-size: 0.95rem;
  margin-bottom: 2rem;
}

.details {
  border-top: 1px dashed #e2e8f0;
  padding-top: 1.5rem;
  display: grid;
  gap: 1.25rem;
  margin-bottom: 2rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.label {
  color: #64748b;
  font-size: 0.875rem;
}

.value {
  color: #0f172a;
  font-weight: 700;
  text-align: right;
}

.note-box {
  background: #f8fafc;
  padding: 1rem;
  border-radius: 12px;
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  font-size: 0.85rem;
  color: #475569;
  line-height: 1.5;
}

.note-box i {
  color: #3b82f6;
  margin-top: 0.2rem;
}
</style>
