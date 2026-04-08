<template>
  <div class="profile-page">
    <!-- Sidebar -->
    <aside class="sidebar">
      <!-- Avatar block -->
      <div class="avatar-block">
        <div class="avatar-wrap">
          <img v-if="auth.user?.avatar" :src="auth.user.avatar" :alt="auth.user.name" class="avatar-img" />
          <div v-else class="avatar-fallback">
            {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div class="avatar-ring" />
        </div>
        <div class="user-info">
          <h2>{{ auth.user?.name }}</h2>
          <p class="user-email">{{ auth.user?.email }}</p>
          <span :class="['role-badge', roleBadgeClass]">{{ roleLabel }}</span>
        </div>
      </div>

      <!-- Nav menu -->
      <nav class="side-nav">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="['nav-item', activeTab === tab.id ? 'active' : '']"
          @click="activeTab = tab.id"
        >
          <span class="nav-icon">{{ tab.icon }}</span>
          <span>{{ tab.label }}</span>
        </button>
      </nav>

      <!-- Quick links -->
      <div class="quick-links">
        <NuxtLink to="/my-courses" class="ql">
          <span>📚</span> Khóa học của tôi
        </NuxtLink>
        <NuxtLink to="/orders" class="ql">
          <span>🧾</span> Lịch sử đơn hàng
        </NuxtLink>
      </div>
    </aside>

    <!-- Content -->
    <main class="content">

      <!-- Tab: Thông tin cá nhân -->
      <section v-if="activeTab === 'info'" class="section-card">
        <div class="section-header">
          <div>
            <h3>Thông tin cá nhân</h3>
            <p>Cập nhật tên hiển thị và ảnh đại diện của bạn</p>
          </div>
        </div>

        <form @submit.prevent="saveProfile" class="form-grid">
          <div class="field">
            <label>Họ và tên</label>
            <input v-model="profileForm.name" type="text" required placeholder="Nguyễn Văn A" />
          </div>

          <div class="field">
            <label>Email <span class="readonly-hint">(chỉ đọc)</span></label>
            <input :value="auth.user?.email" type="email" disabled class="disabled-input" />
          </div>

          <div class="field full">
            <label>URL ảnh đại diện</label>
            <input
              v-model="profileForm.avatar"
              type="url"
              placeholder="https://example.com/avatar.jpg"
            />
          </div>

          <!-- Avatar preview -->
          <div v-if="profileForm.avatar" class="field full">
            <label>Xem trước</label>
            <div class="preview-wrap">
              <img :src="profileForm.avatar" alt="preview" class="preview-img" @error="profileForm.avatar = ''" />
              <div class="preview-info">
                <p class="preview-name">{{ profileForm.name }}</p>
                <p class="preview-email">{{ auth.user?.email }}</p>
              </div>
            </div>
          </div>

          <div v-if="profileMsg || profileErr" class="field full">
            <div v-if="profileMsg" class="success-alert">✓ {{ profileMsg }}</div>
            <div v-if="profileErr" class="error-alert">⚠ {{ profileErr }}</div>
          </div>

          <div class="field full form-actions">
            <button type="submit" :disabled="savingProfile" class="btn-save">
              <span v-if="savingProfile" class="spinner" />
              {{ savingProfile ? 'Đang lưu...' : 'Lưu thay đổi' }}
            </button>
          </div>
        </form>
      </section>

      <!-- Tab: Đổi mật khẩu -->
      <section v-if="activeTab === 'password'" class="section-card">
        <div class="section-header">
          <div>
            <h3>Đổi mật khẩu</h3>
            <p>Bảo mật tài khoản với mật khẩu mạnh</p>
          </div>
          <div class="security-icon">🔐</div>
        </div>

        <form @submit.prevent="savePassword" class="form-grid password-form">
          <div class="field full">
            <label>Mật khẩu hiện tại</label>
            <div class="input-wrap">
              <input
                v-model="passForm.current_password"
                :type="showCurrent ? 'text' : 'password'"
                required
                placeholder="••••••••"
              />
              <button type="button" class="toggle-pass" @click="showCurrent = !showCurrent">
                {{ showCurrent ? '🙈' : '👁' }}
              </button>
            </div>
          </div>

          <div class="field">
            <label>Mật khẩu mới</label>
            <div class="input-wrap">
              <input
                v-model="passForm.password"
                :type="showNew ? 'text' : 'password'"
                required
                minlength="6"
                placeholder="Tối thiểu 6 ký tự"
                @input="calcStrength"
              />
              <button type="button" class="toggle-pass" @click="showNew = !showNew">
                {{ showNew ? '🙈' : '👁' }}
              </button>
            </div>
            <div v-if="passForm.password" class="strength-wrap">
              <div class="strength-bar">
                <div class="strength-fill" :style="{ width: strengthPct + '%', background: strengthColor }" />
              </div>
              <span :style="{ color: strengthColor }" class="strength-label">{{ strengthLabel }}</span>
            </div>
          </div>

          <div class="field">
            <label>Xác nhận mật khẩu mới</label>
            <div class="input-wrap">
              <input
                v-model="passForm.password_confirmation"
                :type="showConfirm ? 'text' : 'password'"
                required
                placeholder="Nhập lại mật khẩu"
                :class="{ 'match-error': passForm.password_confirmation && passForm.password !== passForm.password_confirmation }"
              />
              <button type="button" class="toggle-pass" @click="showConfirm = !showConfirm">
                {{ showConfirm ? '🙈' : '👁' }}
              </button>
            </div>
            <span
              v-if="passForm.password_confirmation && passForm.password !== passForm.password_confirmation"
              class="mismatch-hint"
            >Mật khẩu không khớp</span>
          </div>

          <div v-if="passMsg || passErr" class="field full">
            <div v-if="passMsg" class="success-alert">✓ {{ passMsg }}</div>
            <div v-if="passErr" class="error-alert">⚠ {{ passErr }}</div>
          </div>

          <div class="field full form-actions">
            <button
              type="submit"
              :disabled="savingPass || passForm.password !== passForm.password_confirmation"
              class="btn-save"
            >
              <span v-if="savingPass" class="spinner" />
              {{ savingPass ? 'Đang đổi...' : 'Đổi mật khẩu' }}
            </button>
          </div>
        </form>

        <!-- Password tips -->
        <div class="tips-box">
          <h4>💡 Gợi ý mật khẩu mạnh</h4>
          <ul>
            <li>Ít nhất 8 ký tự</li>
            <li>Kết hợp chữ hoa, chữ thường và số</li>
            <li>Thêm ký tự đặc biệt (@, #, !, …)</li>
            <li>Không dùng thông tin cá nhân</li>
          </ul>
        </div>
      </section>

    </main>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const auth = useAuthStore()

const activeTab = ref<'info' | 'password'>('info')

const tabs = [
  { id: 'info', label: 'Thông tin cá nhân', icon: '👤' },
  { id: 'password', label: 'Đổi mật khẩu', icon: '🔒' },
]

// Profile form
const profileForm = reactive({
  name: auth.user?.name ?? '',
  avatar: auth.user?.avatar ?? '',
})
const savingProfile = ref(false)
const profileMsg = ref('')
const profileErr = ref('')

watch(() => auth.user, (u) => {
  if (u) { profileForm.name = u.name; profileForm.avatar = u.avatar ?? '' }
}, { immediate: true })

async function saveProfile() {
  savingProfile.value = true
  profileMsg.value = ''
  profileErr.value = ''
  try {
    await auth.updateProfile({ name: profileForm.name, avatar: profileForm.avatar || null })
    profileMsg.value = 'Cập nhật thành công!'
    setTimeout(() => { profileMsg.value = '' }, 3000)
  } catch (e: any) {
    profileErr.value = e?.data?.message || 'Không thể cập nhật.'
  } finally {
    savingProfile.value = false
  }
}

// Password form
const passForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const savingPass = ref(false)
const passMsg = ref('')
const passErr = ref('')
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)
const strengthPct = ref(0)
const strengthLabel = ref('')
const strengthColor = ref('')

function calcStrength() {
  const p = passForm.password
  let s = 0
  if (p.length >= 6) s++
  if (p.length >= 10) s++
  if (/[A-Z]/.test(p)) s++
  if (/[0-9]/.test(p)) s++
  if (/[^A-Za-z0-9]/.test(p)) s++
  if (s <= 1) { strengthPct.value = 20; strengthLabel.value = 'Rất yếu'; strengthColor.value = '#ef4444' }
  else if (s === 2) { strengthPct.value = 40; strengthLabel.value = 'Yếu'; strengthColor.value = '#f97316' }
  else if (s === 3) { strengthPct.value = 60; strengthLabel.value = 'Trung bình'; strengthColor.value = '#eab308' }
  else if (s === 4) { strengthPct.value = 80; strengthLabel.value = 'Mạnh'; strengthColor.value = '#22c55e' }
  else { strengthPct.value = 100; strengthLabel.value = 'Rất mạnh'; strengthColor.value = '#10b981' }
}

async function savePassword() {
  if (passForm.password !== passForm.password_confirmation) return
  savingPass.value = true
  passMsg.value = ''
  passErr.value = ''
  try {
    await auth.changePassword({ ...passForm })
    passForm.current_password = ''
    passForm.password = ''
    passForm.password_confirmation = ''
    strengthPct.value = 0
    passMsg.value = 'Đổi mật khẩu thành công!'
    setTimeout(() => { passMsg.value = '' }, 3000)
  } catch (e: any) {
    passErr.value = e?.data?.message || 'Không thể đổi mật khẩu.'
  } finally {
    savingPass.value = false
  }
}

const roleLabel = computed(() => {
  const r = auth.user?.role ?? auth.user?.roles?.[0]
  return { admin: 'Quản trị viên', instructor: 'Giảng viên', student: 'Học viên' }[r ?? ''] ?? 'Học viên'
})

const roleBadgeClass = computed(() => {
  const r = auth.user?.role ?? auth.user?.roles?.[0]
  return { admin: 'badge-admin', instructor: 'badge-instructor', student: 'badge-student' }[r ?? ''] ?? 'badge-student'
})
</script>

<style scoped>
.profile-page {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 24px;
  max-width: 1000px;
  margin: 0 auto;
  align-items: start;
}

/* Sidebar */
.sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.avatar-block {
  background: linear-gradient(135deg, #0f172a, #1e3a5f);
  border-radius: 16px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  text-align: center;
  color: #fff;
}

.avatar-wrap { position: relative; }
.avatar-img {
  width: 80px; height: 80px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
  border: 3px solid rgba(255,255,255,0.3);
}
.avatar-fallback {
  width: 80px; height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex; align-items: center; justify-content: center;
  font-size: 32px; font-weight: 800; color: #fff;
  border: 3px solid rgba(255,255,255,0.2);
}
.avatar-ring {
  position: absolute; inset: -5px;
  border-radius: 50%;
  border: 2px dashed rgba(255,255,255,0.2);
}

.user-info { display: flex; flex-direction: column; gap: 4px; align-items: center; }
.user-info h2 { font-size: 16px; font-weight: 700; color: #f8fafc; }
.user-email { font-size: 12px; color: #94a3b8; }

.role-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 12px;
  border-radius: 20px;
  margin-top: 2px;
}
.badge-admin { background: rgba(239,68,68,0.2); color: #fca5a5; }
.badge-instructor { background: rgba(245,158,11,0.2); color: #fcd34d; }
.badge-student { background: rgba(16,185,129,0.2); color: #6ee7b7; }

.side-nav {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}
.nav-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 13px 16px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  background: none;
  border: none;
  border-bottom: 1px solid #f3f4f6;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}
.nav-item:last-child { border-bottom: none; }
.nav-item:hover { background: #f9fafb; }
.nav-item.active {
  background: #eff6ff;
  color: #2563eb;
  font-weight: 700;
  border-left: 3px solid #2563eb;
}
.nav-icon { font-size: 16px; }

.quick-links {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}
.ql {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  text-decoration: none;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.15s;
}
.ql:last-child { border-bottom: none; }
.ql:hover { background: #f9fafb; color: #111827; }

/* Content */
.content { display: flex; flex-direction: column; gap: 20px; }

.section-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px 18px;
  border-bottom: 1px solid #f3f4f6;
}
.section-header h3 { font-size: 17px; font-weight: 700; color: #111827; margin-bottom: 3px; }
.section-header p { font-size: 13px; color: #6b7280; }
.security-icon { font-size: 32px; }

/* Forms */
.form-grid {
  padding: 24px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.password-form { grid-template-columns: 1fr; max-width: 480px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field.full { grid-column: 1 / -1; }
.field label { font-size: 13px; font-weight: 600; color: #374151; }
.field input {
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  outline: none;
  color: #0f172a;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.field input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.disabled-input { background: #f8fafc; color: #9ca3af; cursor: not-allowed; }
.readonly-hint { color: #9ca3af; font-weight: 400; font-size: 11px; }

.input-wrap { position: relative; display: flex; align-items: center; }
.input-wrap input {
  width: 100%;
  padding: 10px 40px 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  outline: none;
  color: #0f172a;
  transition: border-color 0.15s;
}
.input-wrap input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.input-wrap input.match-error { border-color: #f87171; }
.toggle-pass { position: absolute; right: 10px; background: none; border: none; cursor: pointer; font-size: 14px; color: #94a3b8; padding: 4px; }
.mismatch-hint { font-size: 12px; color: #ef4444; }

.strength-wrap { display: flex; align-items: center; gap: 10px; margin-top: 4px; }
.strength-bar { flex: 1; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
.strength-fill { height: 100%; border-radius: 2px; transition: width 0.3s; }
.strength-label { font-size: 11px; font-weight: 600; flex-shrink: 0; }

/* Preview */
.preview-wrap {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}
.preview-img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
.preview-name { font-size: 14px; font-weight: 700; color: #111827; }
.preview-email { font-size: 12px; color: #6b7280; margin-top: 2px; }

/* Alerts */
.success-alert {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
}
.error-alert {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
}

.form-actions { display: flex; justify-content: flex-end; }
.btn-save {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 11px 28px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s, transform 0.1s;
}
.btn-save:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner {
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Tips box */
.tips-box {
  margin: 0 24px 24px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 10px;
  padding: 16px;
}
.tips-box h4 { font-size: 13px; font-weight: 700; color: #0369a1; margin-bottom: 10px; }
.tips-box ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
.tips-box li { font-size: 12px; color: #0c4a6e; display: flex; align-items: center; gap: 6px; }
.tips-box li::before { content: '✓'; color: #0284c7; font-weight: 700; }

@media (max-width: 768px) {
  .profile-page { grid-template-columns: 1fr; }
  .form-grid { grid-template-columns: 1fr; }
  .password-form { max-width: 100%; }
}
</style>
