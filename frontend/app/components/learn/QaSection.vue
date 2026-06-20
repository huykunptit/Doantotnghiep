<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useCourseStore, type CourseQa, type CourseQaReply, type QaReactionKind } from '~/stores/course'
import { useToast } from '~/composables/useToast'

const toast = useToast()

const props = defineProps<{
  courseId: number
  lessonId: number
}>()

const auth = useAuthStore()
const courseStore = useCourseStore()
const qas = ref<CourseQa[]>([])
const loading = ref(true)
const submitting = ref(false)

const newQuestion = ref('')
const newQuestionFocused = ref(false)
const replyDrafts = ref<Record<number, string>>({})
const expandedQa = ref<Record<number, boolean>>({})

const canAsk = computed(() => auth.isLoggedIn)
const totalCount = computed(() => qas.value.length + qas.value.reduce((acc, q) => acc + (q.replies?.length || 0), 0))
const userInitial = computed(() => auth.user?.name?.charAt(0)?.toUpperCase() || 'U')

async function loadQas() {
  loading.value = true
  try {
    qas.value = await courseStore.fetchQas(props.courseId, props.lessonId)
  } catch (e) {
    console.error('Failed to load Q&A', e)
  } finally {
    loading.value = false
  }
}

async function submitQuestion() {
  const content = newQuestion.value.trim()
  if (!content) return
  submitting.value = true
  try {
    // We collapse subject + content into a single field by deriving subject
    // from the first ~80 chars and storing the full text as content.
    const subject = content.length > 80 ? content.slice(0, 80).replace(/\s+\S*$/, '') + '…' : content
    const created = await courseStore.createQa(props.courseId, {
      subject,
      content,
      lesson_id: props.lessonId,
    })
    qas.value.unshift({ ...created, replies: [], like_count: 0, dislike_count: 0, my_reaction: null })
    newQuestion.value = ''
    newQuestionFocused.value = false
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể gửi câu hỏi.')
  } finally {
    submitting.value = false
  }
}

async function submitReply(qa: CourseQa) {
  const content = (replyDrafts.value[qa.id] || '').trim()
  if (!content) return
  submitting.value = true
  try {
    const reply = await courseStore.createQaReply(props.courseId, qa.id, content)
    if (!qa.replies) qa.replies = []
    qa.replies.push({ ...reply, like_count: reply.like_count ?? 0, dislike_count: reply.dislike_count ?? 0, my_reaction: reply.my_reaction ?? null })
    replyDrafts.value[qa.id] = ''
    expandedQa.value[qa.id] = true
  } catch {
    toast.error('Không thể gửi phản hồi.')
  } finally {
    submitting.value = false
  }
}

function toggleReplyBox(qaId: number) {
  expandedQa.value[qaId] = !expandedQa.value[qaId]
  if (expandedQa.value[qaId] && replyDrafts.value[qaId] === undefined) {
    replyDrafts.value[qaId] = ''
  }
}

async function react(target: CourseQa | CourseQaReply, type: 'qa' | 'reply', kind: QaReactionKind) {
  if (!canAsk.value) return
  // Optimistic update so the UI feels instant.
  const prevState = { like_count: target.like_count, dislike_count: target.dislike_count, my_reaction: target.my_reaction }
  applyOptimisticReaction(target, kind)
  try {
    const fresh = await courseStore.reactToQa(props.courseId, { type, id: target.id, kind })
    target.like_count = fresh.like_count
    target.dislike_count = fresh.dislike_count
    target.my_reaction = fresh.my_reaction
  } catch {
    // Roll back if the server rejected.
    target.like_count = prevState.like_count
    target.dislike_count = prevState.dislike_count
    target.my_reaction = prevState.my_reaction
  }
}

function applyOptimisticReaction(target: CourseQa | CourseQaReply, kind: QaReactionKind) {
  const wasMine = target.my_reaction
  target.like_count = target.like_count ?? 0
  target.dislike_count = target.dislike_count ?? 0
  // Remove old vote
  if (wasMine === 'like') target.like_count = Math.max(0, target.like_count - 1)
  if (wasMine === 'dislike') target.dislike_count = Math.max(0, target.dislike_count - 1)
  // Toggle off if same kind, else add new vote
  if (wasMine === kind) {
    target.my_reaction = null
  } else {
    if (kind === 'like') target.like_count++
    else target.dislike_count++
    target.my_reaction = kind
  }
}

function relativeTime(iso: string) {
  const d = new Date(iso)
  const diff = (Date.now() - d.getTime()) / 1000
  if (diff < 60) return 'vừa xong'
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`
  if (diff < 2592000) return `${Math.floor(diff / 86400)} ngày trước`
  if (diff < 31104000) return `${Math.floor(diff / 2592000)} tháng trước`
  return `${Math.floor(diff / 31104000)} năm trước`
}

function avatarInitial(name?: string | null) {
  return name?.charAt(0).toUpperCase() || 'U'
}

watch(() => props.lessonId, loadQas)
onMounted(loadQas)
</script>

<template>
  <div class="qa">
    <!-- Header -->
    <header class="qa-head">
      <p class="qa-kicker">Trao đổi với giảng viên</p>
      <h3 class="qa-title">{{ totalCount }} bình luận</h3>
      <p class="qa-subtitle">Đặt câu hỏi theo bài học. Nếu thấy bình luận spam, bấm báo cáo giúp admin nhé.</p>
    </header>

    <!-- New question composer -->
    <div v-if="canAsk" class="qa-composer" :class="{ 'is-focused': newQuestionFocused || newQuestion }">
      <div class="qa-avatar qa-avatar--me">{{ userInitial }}</div>
      <div class="qa-composer-body">
        <textarea
          v-model="newQuestion"
          :rows="newQuestionFocused || newQuestion ? 3 : 1"
          class="qa-composer-input"
          placeholder="Nhập bình luận mới của bạn..."
          @focus="newQuestionFocused = true"
        ></textarea>
        <div v-if="newQuestionFocused || newQuestion" class="qa-composer-actions">
          <button type="button" class="qa-btn-ghost" @click="newQuestion = ''; newQuestionFocused = false">Hủy</button>
          <button
            type="button"
            class="qa-btn-primary"
            :disabled="submitting || !newQuestion.trim()"
            @click="submitQuestion"
          >
            <span v-if="submitting" class="material-symbols-outlined qa-spin">progress_activity</span>
            <span v-else class="material-symbols-outlined">send</span>
            Gửi câu hỏi
          </button>
        </div>
      </div>
    </div>
    <NuxtLink v-else to="/login" class="qa-login-prompt">
      <span class="material-symbols-outlined">login</span>
      Đăng nhập để bình luận
    </NuxtLink>

    <!-- Loading skeleton -->
    <div v-if="loading" class="qa-loading">
      <div v-for="i in 3" :key="i" class="qa-skeleton"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="qas.length === 0" class="qa-empty">
      <span class="material-symbols-outlined">forum</span>
      <p>Chưa có bình luận nào. Hãy bắt đầu đặt câu hỏi đầu tiên!</p>
    </div>

    <!-- Thread -->
    <ul v-else class="qa-list">
      <li v-for="qa in qas" :key="qa.id" class="qa-item">
        <div class="qa-avatar">{{ avatarInitial(qa.user?.name) }}</div>
        <div class="qa-body">
          <div class="qa-meta">
            <span class="qa-author">{{ qa.user?.name || 'Học viên' }}</span>
            <span class="qa-time">{{ relativeTime(qa.created_at) }}</span>
          </div>
          <div class="qa-content">{{ qa.content }}</div>

          <div class="qa-actions">
            <button
              type="button"
              :class="['qa-action', { 'is-active': qa.my_reaction === 'like' }]"
              @click="react(qa, 'qa', 'like')"
            >
              <span class="material-symbols-outlined">thumb_up</span>
              <span>{{ qa.like_count || 0 }}</span>
            </button>
            <button
              type="button"
              :class="['qa-action', { 'is-active is-dislike': qa.my_reaction === 'dislike' }]"
              @click="react(qa, 'qa', 'dislike')"
            >
              <span class="material-symbols-outlined">thumb_down</span>
              <span>{{ qa.dislike_count || 0 }}</span>
            </button>
            <button type="button" class="qa-action" @click="toggleReplyBox(qa.id)">
              <span class="material-symbols-outlined">reply</span>
              <span>Phản hồi</span>
            </button>
            <button
              v-if="qa.replies?.length"
              type="button"
              class="qa-action qa-action-toggle"
              @click="expandedQa[qa.id] = !expandedQa[qa.id]"
            >
              <span class="material-symbols-outlined">{{ expandedQa[qa.id] ? 'expand_less' : 'expand_more' }}</span>
              <span>{{ expandedQa[qa.id] ? 'Ẩn' : 'Xem' }} {{ qa.replies.length }} phản hồi</span>
            </button>
          </div>

          <!-- Replies -->
          <ul v-if="expandedQa[qa.id] && qa.replies?.length" class="qa-replies">
            <li v-for="reply in qa.replies" :key="reply.id" class="qa-reply">
              <div class="qa-avatar qa-avatar--sm">{{ avatarInitial(reply.user?.name) }}</div>
              <div class="qa-body">
                <div class="qa-meta">
                  <span class="qa-author">{{ reply.user?.name || 'Học viên' }}</span>
                  <span class="qa-time">{{ relativeTime(reply.created_at) }}</span>
                </div>
                <div class="qa-content">{{ reply.content }}</div>
                <div class="qa-actions qa-actions--compact">
                  <button
                    type="button"
                    :class="['qa-action', { 'is-active': reply.my_reaction === 'like' }]"
                    @click="react(reply, 'reply', 'like')"
                  >
                    <span class="material-symbols-outlined">thumb_up</span>
                    <span>{{ reply.like_count || 0 }}</span>
                  </button>
                  <button
                    type="button"
                    :class="['qa-action', { 'is-active is-dislike': reply.my_reaction === 'dislike' }]"
                    @click="react(reply, 'reply', 'dislike')"
                  >
                    <span class="material-symbols-outlined">thumb_down</span>
                    <span>{{ reply.dislike_count || 0 }}</span>
                  </button>
                </div>
              </div>
            </li>
          </ul>

          <!-- Reply composer -->
          <div v-if="expandedQa[qa.id] && canAsk" class="qa-reply-composer">
            <div class="qa-avatar qa-avatar--sm qa-avatar--me">{{ userInitial }}</div>
            <div class="qa-composer-body">
              <input
                v-model="replyDrafts[qa.id]"
                type="text"
                class="qa-reply-input"
                placeholder="Phản hồi..."
                @keyup.enter="submitReply(qa)"
              />
              <button
                type="button"
                class="qa-btn-primary qa-btn-primary--sm"
                :disabled="submitting || !(replyDrafts[qa.id] || '').trim()"
                @click="submitReply(qa)"
              >
                Gửi
              </button>
            </div>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.qa {
  background: #fff;
  color: #0f172a;
  border-radius: 18px;
  padding: 24px 28px;
  border: 1px solid #e2e8f0;
}

.qa-head {
  margin-bottom: 18px;
  padding-bottom: 18px;
  border-bottom: 1px solid #f1f5f9;
}
.qa-kicker {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #94a3b8;
}
.qa-title {
  margin: 6px 0 4px;
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
}
.qa-subtitle {
  margin: 0;
  font-size: 0.85rem;
  color: #64748b;
}

/* ───── Avatars ───── */
.qa-avatar {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: #e0f2fe;
  color: #0369a1;
  font-weight: 700;
  font-size: 0.95rem;
}
.qa-avatar--me {
  background: var(--green);
  color: #fff;
}
.qa-avatar--sm {
  width: 32px;
  height: 32px;
  font-size: 0.82rem;
}

/* ───── Composer ───── */
.qa-composer {
  display: flex;
  gap: 12px;
  padding: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  margin-bottom: 24px;
  transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.qa-composer.is-focused {
  background: #fff;
  border-color: rgba(var(--green-rgb), 0.42);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.08);
}

.qa-composer-body {
  flex: 1;
  min-width: 0;
}

.qa-composer-input {
  width: 100%;
  border: none;
  background: transparent;
  outline: none;
  font: inherit;
  font-size: 0.95rem;
  color: #0f172a;
  resize: none;
  padding: 8px 4px;
}
.qa-composer-input::placeholder { color: #94a3b8; }

.qa-composer-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 8px;
}

/* ───── Buttons ───── */
.qa-btn-primary,
.qa-btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 38px;
  padding: 0 16px;
  border-radius: 999px;
  border: none;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  transition: filter 0.15s, background 0.15s, transform 0.15s;
}
.qa-btn-primary {
  background: var(--green);
  color: #fff;
  box-shadow: 0 6px 14px rgba(var(--green-rgb), 0.32);
}
.qa-btn-primary:hover:not(:disabled) { filter: brightness(1.06); transform: translateY(-1px); }
.qa-btn-primary:disabled {
  background: #cbd5e1;
  box-shadow: none;
  cursor: not-allowed;
}
.qa-btn-primary--sm { height: 34px; padding: 0 14px; font-size: 0.8rem; }
.qa-btn-primary .material-symbols-outlined { font-size: 16px; }

.qa-btn-ghost {
  background: transparent;
  color: #475569;
}
.qa-btn-ghost:hover { background: #f1f5f9; }

.qa-spin { animation: qa-spin 1.2s linear infinite; }
@keyframes qa-spin { to { transform: rotate(360deg); } }

.qa-login-prompt {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: #fff7ed;
  color: #c2410c;
  border: 1px solid #fed7aa;
  border-radius: 999px;
  text-decoration: none;
  font-weight: 700;
  font-size: 0.85rem;
  margin-bottom: 24px;
}
.qa-login-prompt:hover { background: #ffedd5; }
.qa-login-prompt .material-symbols-outlined { font-size: 18px; }

/* ───── Loading & Empty ───── */
.qa-loading { display: grid; gap: 12px; }
.qa-skeleton {
  height: 92px;
  border-radius: 14px;
  background: #f1f5f9;
  background-size: 200% 100%;
  animation: qa-shimmer 1.4s linear infinite;
}
@keyframes qa-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.qa-empty {
  text-align: center;
  padding: 48px 24px;
  color: #94a3b8;
  background: #f8fafc;
  border-radius: 14px;
  border: 1px dashed #e2e8f0;
}
.qa-empty .material-symbols-outlined { font-size: 44px; color: #cbd5e1; }
.qa-empty p { margin: 8px 0 0; font-size: 0.92rem; font-weight: 600; }

/* ───── Thread ───── */
.qa-list { list-style: none; padding: 0; margin: 0; }

.qa-item {
  display: flex;
  gap: 12px;
  padding: 18px 0;
  border-top: 1px solid #f1f5f9;
}
.qa-item:first-child { border-top: 0; padding-top: 0; }

.qa-body { flex: 1; min-width: 0; }

.qa-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}
.qa-author {
  font-weight: 700;
  font-size: 0.92rem;
  color: #0f172a;
}
.qa-time {
  font-size: 0.78rem;
  color: #94a3b8;
}

.qa-content {
  font-size: 0.92rem;
  line-height: 1.6;
  color: #1f2937;
  white-space: pre-wrap;
}

/* ───── Action row (like/dislike/reply) ───── */
.qa-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 8px;
}
.qa-actions--compact { margin-top: 6px; }

.qa-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border: none;
  background: transparent;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.qa-action:hover { background: #f1f5f9; color: #0f172a; }
.qa-action .material-symbols-outlined { font-size: 16px; }

.qa-action.is-active {
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green);
}
.qa-action.is-active.is-dislike {
  background: rgba(220, 38, 38, 0.1);
  color: #dc2626;
}

.qa-action-toggle {
  margin-left: auto;
  color: var(--green);
}

/* ───── Replies ───── */
.qa-replies {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  border-left: 2px solid #f1f5f9;
}

.qa-reply {
  display: flex;
  gap: 10px;
  padding: 12px 0 12px 14px;
}

.qa-reply-composer {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  padding-left: 14px;
}
.qa-reply-composer .qa-composer-body {
  display: flex;
  align-items: center;
  gap: 8px;
}
.qa-reply-input {
  flex: 1;
  height: 36px;
  padding: 0 14px;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  outline: none;
  font: inherit;
  font-size: 0.85rem;
  background: #f8fafc;
  transition: border-color 0.15s, background 0.15s;
}
.qa-reply-input:focus {
  border-color: var(--green);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.1);
}

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .qa-shell { background: var(--surface); color: var(--text); border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .qa-header h3 { color: var(--text); }
[data-theme="dark"] .qa-composer-box { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .qa-composer-input { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
[data-theme="dark"] .qa-composer-input:focus { background: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .qa-card { background: rgba(255, 255, 255, 0.02); border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .qa-card-body p { color: var(--text); }
[data-theme="dark"] .qa-action:hover { background: rgba(255, 255, 255, 0.05); color: var(--text); }
[data-theme="dark"] .qa-reply-input { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
[data-theme="dark"] .qa-reply-input:focus { background: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .qa-replies { border-color: rgba(255, 255, 255, 0.1); }
</style>
