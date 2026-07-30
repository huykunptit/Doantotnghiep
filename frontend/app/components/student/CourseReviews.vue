<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

const props = defineProps<{
  courseId: number
  enrolled?: boolean
  hasReviewed?: boolean
  avgRating?: number
  reviews?: Array<{
    id: number
    rating: number
    comment?: string | null
    created_at?: string
    user?: { name?: string, avatar?: string | null } | null
  }>
}>()

const emit = defineEmits<{ refreshed: [] }>()

const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()
const rating = ref(5)
const comment = ref('')
const saving = ref(false)

async function submit() {
  if (!auth.isAuthenticated) {
    toast.add({ severity: 'warn', summary: t('student.reviews.loginRequired'), life: 2500 })
    return
  }
  if (!props.enrolled) {
    toast.add({ severity: 'warn', summary: t('student.reviews.enrollRequired'), life: 2500 })
    return
  }
  if (props.hasReviewed) {
    toast.add({ severity: 'info', summary: t('student.reviews.already'), life: 2500 })
    return
  }
  saving.value = true
  try {
    await useApi(`/courses/${props.courseId}/reviews`, {
      method: 'POST',
      body: { rating: rating.value, comment: comment.value.trim() || null },
    })
    toast.add({ severity: 'success', summary: t('student.reviews.posted'), life: 2200 })
    comment.value = ''
    emit('refreshed')
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('student.reviews.postError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="reviews">
    <header>
      <h2>{{ t('student.reviews.title') }}</h2>
      <p>
        {{ t('student.reviews.avg', { n: avgRating || 0 }) }}
      </p>
    </header>

    <div v-if="enrolled && !hasReviewed" class="composer">
      <div class="rating-row">
        <span>{{ t('student.reviews.yourRating') }}</span>
        <Rating v-model="rating" :stars="5" />
      </div>
      <Textarea v-model="comment" rows="3" :placeholder="t('student.reviews.commentPh')" class="w-full" />
      <Button :label="t('student.reviews.submit')" icon="pi pi-star" :loading="saving" @click="submit" />
    </div>
    <p v-else-if="hasReviewed" class="hint">{{ t('student.reviews.already') }}</p>
    <p v-else class="hint">{{ t('student.reviews.enrollRequired') }}</p>

    <div v-if="!(reviews || []).length" class="empty">{{ t('student.reviews.empty') }}</div>
    <div v-else class="list">
      <article v-for="item in reviews" :key="item.id" class="item">
        <div class="item-head">
          <strong>{{ item.user?.name || t('student.reviews.anonymous') }}</strong>
          <Rating :model-value="item.rating" readonly :cancel="false" />
        </div>
        <p>{{ item.comment || t('student.reviews.noComment') }}</p>
      </article>
    </div>
  </section>
</template>

<style scoped>
.reviews { display: flex; flex-direction: column; gap: .85rem; margin-top: 1.25rem; }
header h2 { margin: 0; font-size: 1.15rem; }
header p, .hint, .empty { margin: .2rem 0 0; color: var(--p-text-muted-color); }
.composer { display: flex; flex-direction: column; gap: .55rem; }
.rating-row { display: flex; align-items: center; gap: .75rem; }
.list { display: flex; flex-direction: column; gap: .55rem; }
.item { border: 1px solid var(--p-content-border-color); border-radius: 10px; padding: .75rem .9rem; }
.item-head { display: flex; justify-content: space-between; gap: .75rem; align-items: center; margin-bottom: .35rem; }
.item p { margin: 0; }
</style>
