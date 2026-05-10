<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })

const contexts = [
  { id: 'student-instructor', label: 'Học viên - Giảng viên', icon: 'school' },
  { id: 'student-student', label: 'Học viên - Học viên', icon: 'group' },
  { id: 'admin-all', label: 'Admin - Tất cả', icon: 'admin_panel_settings' }
]

const currentContext = ref('admin-all')
</script>

<template>
  <AdminWorkspaceShell
    title="Hệ thống Trò chuyện"
    description="Quản lý và hỗ trợ giao tiếp giữa các vai trò trong hệ thống. Tích hợp đa ngữ cảnh giữa Học viên, Giảng viên và Quản trị viên."
    :breadcrumb="['Trang chủ', 'Hỗ trợ', 'Trò chuyện']"
  >
    <div class="chat-wrapper dashboard-card">
      <aside class="chat-sidebar">
        <div class="chat-context-switcher">
          <button 
            v-for="ctx in contexts" 
            :key="ctx.id"
            class="context-btn"
            :class="{ active: currentContext === ctx.id }"
            @click="currentContext = ctx.id"
          >
            <span class="material-symbols-outlined">{{ ctx.icon }}</span>
            <span>{{ ctx.label }}</span>
          </button>
        </div>

        <div class="contact-list">
          <div class="contact-search">
            <span class="material-symbols-outlined">search</span>
            <input type="text" placeholder="Tìm kiếm hội thoại...">
          </div>
          <div class="crud-empty" style="padding: 20px;">
            <p style="font-size: 0.8rem;">Chưa có hội thoại nào trong mục này.</p>
          </div>
        </div>
      </aside>

      <main class="chat-main">
        <div class="chat-empty-state">
          <span class="material-symbols-outlined" style="font-size: 64px; opacity: 0.1;">chat_bubble</span>
          <h3>Bắt đầu trò chuyện</h3>
          <p>Chọn một hội thoại từ danh sách bên trái để bắt đầu trao đổi tin nhắn.</p>
        </div>
      </main>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
.chat-wrapper {
  display: grid;
  grid-template-columns: 320px 1fr;
  height: 600px;
  padding: 0;
  overflow: hidden;
  border: 1px solid rgba(17, 17, 17, 0.08);
}

.chat-sidebar {
  border-right: 1px solid rgba(17, 17, 17, 0.08);
  display: flex;
  flex-direction: column;
}

.chat-context-switcher {
  padding: 12px;
  display: grid;
  gap: 4px;
  background: rgba(var(--green-rgb), 0.03);
  border-bottom: 1px solid rgba(17, 17, 17, 0.05);
}

.context-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 10px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--muted);
  transition: all 0.2s;
}

.context-btn:hover {
  background: rgba(var(--green-rgb), 0.05);
  color: var(--text);
}

.context-btn.active {
  background: var(--green);
  color: white;
}

.contact-search {
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.05);
}

.contact-search input {
  border: none;
  background: transparent;
  outline: none;
  width: 100%;
  font-size: 0.9rem;
}

.chat-main {
  display: grid;
  place-items: center;
  background: rgba(255, 255, 255, 0.5);
}

.chat-empty-state {
  text-align: center;
  color: var(--muted);
}

.chat-empty-state h3 {
  margin: 16px 0 8px;
  color: var(--text);
}
</style>
