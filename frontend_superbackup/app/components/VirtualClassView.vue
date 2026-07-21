<template>
  <section class="flex h-full min-h-[32rem] flex-col bg-surface-lowest text-on-surface">
    <div class="border-b border-surface-dim/30 px-6 py-5 sm:px-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
          <div
            class="flex h-14 w-14 items-center justify-center rounded-3xl text-white shadow-lg"
            :class="providerClass"
          >
            <span class="material-symbols-outlined text-[28px]">{{ providerIcon }}</span>
          </div>
          <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-outline">Live Session</p>
            <h2 class="mt-1 text-2xl font-headline font-bold tracking-tight">{{ providerName }}</h2>
          </div>
        </div>

        <div class="inline-flex items-center gap-2 self-start rounded-full border px-3 py-1.5 text-xs font-bold uppercase tracking-[0.2em]" :class="statusPillClass">
          <span class="h-2 w-2 rounded-full" :class="statusDotClass"></span>
          {{ statusText }}
        </div>
      </div>
    </div>

    <div class="grid flex-1 gap-8 px-6 py-8 sm:px-8 lg:grid-cols-[1.2fr_0.8fr]">
      <div class="flex flex-col justify-between rounded-[2rem] border border-surface-dim/30 bg-surface p-6 sm:p-8">
        <div>
          <p class="max-w-xl text-base leading-7 text-on-surface-variant">
            Phòng học trực tuyến dành cho buổi học đồng bộ. Bạn có thể tham gia trực tiếp bằng link dưới đây hoặc dùng mã phòng để vào nhanh trên ứng dụng.
          </p>

          <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <article class="rounded-[1.5rem] border border-surface-dim/30 bg-surface-low p-5">
              <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-outline">Thời gian</p>
              <p class="mt-3 text-lg font-semibold">{{ formatDateTime(data.start_at) }}</p>
            </article>
            <article class="rounded-[1.5rem] border border-surface-dim/30 bg-surface-low p-5">
              <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-outline">Thời lượng</p>
              <p class="mt-3 text-lg font-semibold">{{ data.duration }} phút</p>
            </article>
            <article v-if="data.meeting_id" class="rounded-[1.5rem] border border-surface-dim/30 bg-surface-low p-5">
              <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-outline">Meeting ID</p>
              <p class="mt-3 text-lg font-semibold">{{ data.meeting_id }}</p>
            </article>
            <article v-if="data.meeting_password" class="rounded-[1.5rem] border border-surface-dim/30 bg-surface-low p-5">
              <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-outline">Mật khẩu</p>
              <p class="mt-3 text-lg font-semibold">{{ data.meeting_password }}</p>
            </article>
          </div>
        </div>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
          <a
            :href="data.join_url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-12 items-center justify-center rounded-2xl px-6 text-sm font-bold transition-all"
            :class="isJoinable ? 'cta-gradient text-white shadow-lg hover:-translate-y-0.5 hover:shadow-xl' : 'cursor-not-allowed bg-surface-high text-outline'"
            @click="isJoinable ? null : $event.preventDefault()"
          >
            Tham gia phòng học
          </a>
          <a
            v-if="data.start_url"
            :href="data.start_url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-surface-dim/40 bg-surface-low px-6 text-sm font-bold text-on-surface transition hover:border-primary/30 hover:text-primary"
          >
            Mở link host
          </a>
        </div>
      </div>

      <aside class="space-y-4 rounded-[2rem] border border-surface-dim/30 bg-surface-low p-6">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-outline">Hướng dẫn nhanh</p>
          <h3 class="mt-2 text-xl font-headline font-bold">Trước giờ học 10 phút</h3>
        </div>

        <div class="space-y-3">
          <div class="rounded-[1.4rem] border border-surface-dim/30 bg-surface-lowest p-4">
            <p class="text-sm font-semibold">1. Kiểm tra camera và microphone</p>
            <p class="mt-1 text-sm text-on-surface-variant">Đảm bảo quyền truy cập mic/camera đã được cấp trong trình duyệt hoặc ứng dụng Zoom/Meet.</p>
          </div>
          <div class="rounded-[1.4rem] border border-surface-dim/30 bg-surface-lowest p-4">
            <p class="text-sm font-semibold">2. Vào phòng bằng link chính</p>
            <p class="mt-1 text-sm text-on-surface-variant">Nếu link chưa mở, bạn có thể copy mã phòng và đợi tới khi hệ thống cho phép tham gia.</p>
          </div>
          <div class="rounded-[1.4rem] border border-surface-dim/30 bg-surface-lowest p-4">
            <p class="text-sm font-semibold">3. Chuẩn bị tài liệu song song</p>
            <p class="mt-1 text-sm text-on-surface-variant">Tài liệu lesson và bài tập vẫn nằm trong tab học tập để bạn mở lại bất cứ lúc nào.</p>
          </div>
        </div>

        <p v-if="!isJoinable" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
          Phòng học sẽ mở trước 10 phút tính từ thời điểm bắt đầu.
        </p>
      </aside>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  data: {
    provider: string
    meeting_id?: string
    meeting_password?: string
    join_url: string
    start_url?: string
    start_at: string
    duration: number
  }
}>()

const providerName = computed(() => ({
  zoom: 'Zoom Meeting',
  google_meet: 'Google Meet',
  jitsi: 'Jitsi Meet',
  other: 'Phòng học trực tuyến',
}[props.data.provider] || 'Phòng học trực tuyến'))

const providerIcon = computed(() => ({
  zoom: 'videocam',
  google_meet: 'duo',
  jitsi: 'groups',
  other: 'hub',
}[props.data.provider] || 'hub'))

const providerClass = computed(() => ({
  zoom: 'bg-sky-500',
  google_meet: 'bg-emerald-500',
  jitsi: 'bg-slate-900',
  other: 'bg-slate-500',
}[props.data.provider] || 'bg-slate-500'))

const isJoinable = computed(() => {
  const now = new Date()
  const start = new Date(props.data.start_at)
  return now.getTime() >= start.getTime() - 10 * 60 * 1000
})

const statusText = computed(() => {
  const now = new Date()
  const start = new Date(props.data.start_at)
  const end = new Date(start.getTime() + props.data.duration * 60 * 1000)
  const joinGate = start.getTime() - 10 * 60 * 1000

  if (now.getTime() < joinGate) return 'Sắp mở'
  if (now.getTime() > end.getTime()) return 'Đã kết thúc'
  return 'Đang diễn ra'
})

const statusDotClass = computed(() => ({
  'Sắp mở': 'bg-amber-500',
  'Đang diễn ra': 'bg-emerald-500',
  'Đã kết thúc': 'bg-slate-400',
}[statusText.value] || 'bg-slate-400'))

const statusPillClass = computed(() => ({
  'Sắp mở': 'border-amber-200 bg-amber-50 text-amber-700',
  'Đang diễn ra': 'border-emerald-200 bg-emerald-50 text-emerald-700',
  'Đã kết thúc': 'border-slate-200 bg-slate-100 text-slate-600',
}[statusText.value] || 'border-slate-200 bg-slate-100 text-slate-600'))

function formatDateTime(dateStr: string) {
  if (!dateStr) return '---'
  return new Intl.DateTimeFormat('vi-VN', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(dateStr))
}
</script>
