<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="page stub-page">
    <header class="page-heading">
      <div>
        <h1>{{ title }}</h1>
        <p>{{ description || 'Màn hình này đang trống. Yêu cầu thiết kế từng trang khi sẵn sàng.' }}</p>
      </div>
    </header>

    <div class="stub-box">
      <i class="pi pi-objects-column" />
      <strong>Chưa có nội dung</strong>
      <span>Sidebar đã sẵn sàng. Giao diện trang sẽ được dựng lại theo từng yêu cầu.</span>
    </div>
  </div>
</template>

<style scoped>
.stub-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 320px;
  padding: 32px;
  border: 1px dashed var(--border-strong);
  border-radius: 18px;
  background: var(--surface);
  color: var(--text-muted);
  text-align: center;
}

.stub-box i {
  margin-bottom: 6px;
  color: var(--brand);
  font-size: 1.8rem;
  opacity: .7;
}

.stub-box strong {
  color: var(--text);
  font-size: .95rem;
}

.stub-box span {
  max-width: 36ch;
  font-size: .78rem;
  line-height: 1.6;
}
</style>
