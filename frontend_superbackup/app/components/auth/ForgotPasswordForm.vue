<script setup lang="ts">
import { ref } from 'vue'
import { useApi } from '~/composables/useApi'

const email = ref('')
const loading = ref(false)
const error = ref('')
const sent = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await useApi('/auth/forgot-password', { method: 'POST', body: { email: email.value } })
    sent.value = true
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể gửi email. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="fpf">
    <!-- Success state -->
    <div v-if="sent" class="fpf-success">
      <div class="fpf-success-icon">
        <i class="pi pi-check-circle" style="font-size:1.75rem" />
      </div>
      <h3 class="fpf-success-title">Email đã được gửi!</h3>
      <p class="fpf-success-body">
        Chúng tôi đã gửi hướng dẫn đặt lại mật khẩu đến <strong>{{ email }}</strong>. Vui lòng kiểm tra hộp thư của bạn.
      </p>
      <NuxtLink to="/login" class="fpf-back-btn">
        <i class="pi pi-arrow-left" style="font-size:0.9375rem" />
        Quay lại đăng nhập
      </NuxtLink>
    </div>

    <!-- Form state -->
    <template v-else>
      <div v-if="error" class="fpf-alert" role="alert">
        {{ error }}
      </div>

      <form class="fpf-form" novalidate @submit.prevent="handleSubmit">
        <div class="fpf-field">
          <label class="fpf-label" for="fpf-email">Email của bạn</label>
          <div class="fpf-input-wrap">
            <i class="pi pi-envelope fpf-input-icon" style="font-size:1rem" />
            <input
              id="fpf-email"
              v-model="email"
              class="fpf-input"
              type="email"
              name="email"
              placeholder="hocvien@eript.edu.vn"
              autocomplete="email"
              required
            >
          </div>
        </div>

        <button type="submit" :disabled="loading" class="fpf-submit">
          <i v-if="loading" class="pi pi-spin pi-spinner fpf-spinner" style="font-size:1rem" />
          <span>{{ loading ? 'Đang gửi...' : 'Gửi hướng dẫn đặt lại' }}</span>
        </button>

        <NuxtLink to="/login" class="fpf-cancel">
          <i class="pi pi-arrow-left" style="font-size:0.875rem" />
          Quay lại đăng nhập
        </NuxtLink>
      </form>
    </template>
  </div>
</template>

<style scoped>
.fpf { display: flex; flex-direction: column; gap: 12px; }

/* ── Alert ── */
.fpf-alert {
  padding: 12px 16px; border-radius: 8px;
  font-size: 0.875rem; font-weight: 500; line-height: 1.5;
  background: var(--danger-soft); color: var(--danger);
  border: 1px solid rgba(226, 75, 74, 0.2);
}

/* ── Form ── */
.fpf-form { display: flex; flex-direction: column; gap: 10px; }
.fpf-field { display: flex; flex-direction: column; gap: 4px; }
.fpf-label { font-size: 0.875rem; font-weight: 600; color: var(--text); }

.fpf-input-wrap { position: relative; }
.fpf-input-icon {
  position: absolute; left: 14px; top: 50%;
  transform: translateY(-50%); color: var(--muted); pointer-events: none;
}

.fpf-input {
  width: 100%; height: 44px; padding: 0 14px 0 42px;
  border: 1px solid var(--line); border-radius: 8px;
  background: var(--surface-strong, #fff); color: var(--text);
  font: inherit; font-size: 0.9rem; outline: none;
  transition: border-color 150ms, box-shadow 150ms;
}
.fpf-input::placeholder { color: var(--muted); }
.fpf-input:focus { border-color: var(--green); box-shadow: 0 0 0 3px var(--green-soft); }

.fpf-submit {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%; height: 46px; border-radius: 8px; border: none;
  background: var(--green); color: #fff; font: inherit;
  font-size: 0.9375rem; font-weight: 700; cursor: pointer;
  transition: background 150ms, transform 150ms, box-shadow 150ms;
  box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}
.fpf-submit:hover { background: var(--green-deep); transform: translateY(-1px); }
.fpf-submit:disabled { opacity: 0.65; cursor: wait; transform: none; }

.fpf-spinner { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.fpf-cancel {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  text-decoration: none; font-size: 0.875rem; font-weight: 600;
  color: var(--muted); transition: color 150ms;
}
.fpf-cancel:hover { color: var(--text); }

/* ── Success ── */
.fpf-success {
  display: flex; flex-direction: column; align-items: center;
  text-align: center; gap: 12px; padding: 12px 0;
}

.fpf-success-icon {
  display: flex; align-items: center; justify-content: center;
  width: 56px; height: 56px; border-radius: 50%;
  background: var(--green-soft); color: var(--green);
}

.fpf-success-title {
  margin: 0; font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 1.25rem; font-weight: 700; color: var(--text);
}

.fpf-success-body {
  margin: 0; font-size: 0.875rem; line-height: 1.65;
  color: var(--muted); max-width: 320px;
}

.fpf-success-body strong { color: var(--text); font-weight: 600; }

.fpf-back-btn {
  display: inline-flex; align-items: center; gap: 6px;
  margin-top: 6px; padding: 10px 20px; border-radius: 8px;
  border: 1px solid var(--line); background: var(--surface-strong, #fff);
  text-decoration: none; font-size: 0.875rem; font-weight: 600;
  color: var(--text); transition: background 150ms, transform 150ms;
}
.fpf-back-btn:hover { background: var(--surface); transform: translateY(-1px); }
</style>
