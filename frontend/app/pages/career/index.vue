<template>
  <NuxtLayout name="default">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 space-y-12">
      <input ref="fileInput" type="file" class="hidden" accept=".pdf,.doc,.docx" @change="handleFileUpload">

      <!-- Premium Page Header -->
      <AppPageHeader 
        eyebrow="AI Career Advisor" 
        title="Định hướng Sự nghiệp AI" 
        description="Phân tích CV thông minh để xác định khoảng cách kỹ năng và xây dựng lộ trình thăng tiến cá nhân hóa."
      >
        <template #actions>
          <div v-if="cvData" class="flex items-center gap-3">
            <p class="hidden md:block text-[10px] font-bold text-outline-variant uppercase tracking-widest">CV hiện tại: {{ cvData.file_name }}</p>
            <button @click="(fileInput as any)?.click()" class="btn-secondary py-2 text-xs">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              Cập nhật CV
            </button>
          </div>
        </template>
      </AppPageHeader>

      <!-- Main Studio Layout -->
      <div v-if="cvData" class="grid grid-cols-12 gap-8">
        
        <!-- Left: Skills Profile & Analysis -->
        <div class="col-span-12 lg:col-span-5 space-y-8">
          
          <!-- Skills Bento Card -->
          <div class="bg-surface-lowest rounded-[2.5rem] p-8 border border-surface-dim shadow-sm">
            <div class="flex items-center gap-4 mb-8">
              <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary shadow-inner">
                <span class="material-symbols-outlined">psychology</span>
              </div>
              <div>
                <h2 class="font-headline text-2xl font-bold text-on-surface">Kỹ năng hiện có</h2>
                <p class="text-xs font-bold text-outline-variant uppercase tracking-widest mt-1">Trích xuất từ CV của bạn</p>
              </div>
            </div>

            <div class="flex flex-wrap gap-2.5">
              <div v-for="skill in cvData.skills" :key="skill" class="px-4 py-2 bg-surface-low rounded-xl border border-surface-dim/40 text-sm font-bold text-on-surface transition-all hover:border-primary/30 hover:bg-white text-center">
                {{ skill }}
              </div>
            </div>

            <div class="mt-10 pt-6 border-t border-surface-dim/30">
              <p class="text-[11px] font-bold text-outline-variant uppercase tracking-widest mb-2">Thông tin hồ sơ</p>
              <div class="flex items-center justify-between text-sm">
                <span class="text-on-surface-variant">Ngày cập nhật:</span>
                <span class="font-bold text-on-surface">{{ formatDate(cvData.created_at) }}</span>
              </div>
            </div>
          </div>

          <!-- Analysis Trigger -->
          <div class="bg-surface-lowest rounded-[2.5rem] p-8 border border-surface-dim shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-16 -mt-16 transition-transform group-hover:scale-110"></div>
            
            <h3 class="font-headline text-xl font-bold text-on-surface mb-6 relative z-10">Mục tiêu tiếp theo?</h3>
            <div class="space-y-4 relative z-10">
              <UiInput v-model="targetJob" placeholder="Ví dụ: Senior Frontend Developer..." />
              <UiButton @click="getRecommendations" class="w-full py-4 rounded-2xl flex items-center justify-center gap-3 active:scale-95 transition-transform" :loading="loadingRecommendations">
                <span class="material-symbols-outlined text-[20px]">analytics</span>
                Bắt đầu Phân tích AI
              </UiButton>
            </div>
          </div>
        </div>

        <!-- Right: AI Results & Roadmap -->
        <div class="col-span-12 lg:col-span-7 space-y-8">
          <div v-if="analysis" class="space-y-8 animate-fade-in-up">
            
            <!-- Compatibility Card -->
            <div class="bg-surface-lowest rounded-[2.5rem] border border-surface-dim shadow-sm overflow-hidden flex flex-col md:flex-row">
              <div class="p-8 md:w-48 bg-secondary/5 flex flex-col items-center justify-center border-r border-surface-dim">
                <span class="text-[10px] font-bold text-secondary uppercase tracking-widest mb-2">Độ tương thích</span>
                <div class="text-5xl font-bold font-headline text-on-surface tracking-tighter">{{ analysis.match_score }}%</div>
              </div>
              <div class="p-8 flex-1">
                <h4 class="font-bold text-on-surface mb-3 flex items-center gap-2">
                  <span class="material-symbols-outlined text-secondary">verified</span>
                  Tổng quan từ chuyên gia AI
                </h4>
                <p class="text-sm leading-relaxed text-on-surface-variant">{{ expertAnalysis.overview || analysis.ai_summary }}</p>
              </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
              <div v-if="expertAnalysis.strengths.length" class="bg-surface-lowest rounded-[2.5rem] p-8 border border-surface-dim shadow-sm">
                <h3 class="font-headline text-xl font-bold text-on-surface mb-6 flex items-center gap-3">
                  <span class="material-symbols-outlined text-primary">military_tech</span>
                  Điểm mạnh hiện có
                </h3>
                <div class="space-y-3">
                  <div v-for="item in expertAnalysis.strengths" :key="item" class="rounded-2xl border border-primary/10 bg-primary/5 px-4 py-3 text-sm leading-relaxed text-on-surface">
                    {{ item }}
                  </div>
                </div>
              </div>

              <div v-if="expertAnalysis.weaknesses.length || analysis.skill_gaps?.length" class="bg-surface-lowest rounded-[2.5rem] p-8 border border-surface-dim shadow-sm">
                <h3 class="font-headline text-xl font-bold text-on-surface mb-6 flex items-center gap-3">
                  <span class="material-symbols-outlined text-error">warning</span>
                  Điểm yếu và khoảng trống
                </h3>
                <div class="space-y-3">
                  <div v-for="item in expertAnalysis.weaknesses" :key="item" class="rounded-2xl border border-error/10 bg-error-container/10 px-4 py-3 text-sm leading-relaxed text-on-surface">
                    {{ item }}
                  </div>
                </div>
                <div v-if="analysis.skill_gaps?.length" class="mt-6 pt-6 border-t border-surface-dim/30">
                  <p class="text-[11px] font-bold text-outline-variant uppercase tracking-widest mb-3">Kỹ năng nên ưu tiên bổ sung</p>
                  <div class="flex flex-wrap gap-2.5">
                    <div v-for="gap in analysis.skill_gaps" :key="gap" class="px-3 py-1.5 bg-error-container/20 text-error border border-error/10 rounded-lg text-xs font-bold uppercase tracking-wide">
                      {{ gap }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
              <div v-if="expertAnalysis.cv_additions.length" class="bg-surface-lowest rounded-[2.5rem] p-8 border border-surface-dim shadow-sm">
                <h3 class="font-headline text-xl font-bold text-on-surface mb-6 flex items-center gap-3">
                  <span class="material-symbols-outlined text-secondary">note_add</span>
                  Nên bổ sung gì vào CV
                </h3>
                <div class="space-y-3">
                  <div v-for="item in expertAnalysis.cv_additions" :key="item" class="rounded-2xl border border-secondary/10 bg-secondary/5 px-4 py-3 text-sm leading-relaxed text-on-surface">
                    {{ item }}
                  </div>
                </div>
              </div>

              <div v-if="expertAnalysis.cv_improvements.length" class="bg-surface-lowest rounded-[2.5rem] p-8 border border-surface-dim shadow-sm">
                <h3 class="font-headline text-xl font-bold text-on-surface mb-6 flex items-center gap-3">
                  <span class="material-symbols-outlined text-tertiary">edit_document</span>
                  Nên sửa CV như thế nào
                </h3>
                <div class="space-y-3">
                  <div v-for="item in expertAnalysis.cv_improvements" :key="item" class="rounded-2xl border border-tertiary/10 bg-tertiary/5 px-4 py-3 text-sm leading-relaxed text-on-surface">
                    {{ item }}
                  </div>
                </div>
              </div>
            </div>

            <div v-if="expertAnalysis.learning_priorities.length" class="bg-surface-lowest rounded-[2.5rem] p-8 border border-surface-dim shadow-sm">
              <h3 class="font-headline text-xl font-bold text-on-surface mb-6 flex items-center gap-3">
                <span class="material-symbols-outlined text-primary">checklist</span>
                Thứ tự ưu tiên học và hoàn thiện hồ sơ
              </h3>
              <div class="grid gap-3 md:grid-cols-2">
                <div v-for="(item, index) in expertAnalysis.learning_priorities" :key="item" class="rounded-2xl border border-surface-dim/50 bg-surface-low px-4 py-4 text-sm leading-relaxed text-on-surface">
                  <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-outline-variant">Ưu tiên {{ index + 1 }}</p>
                  {{ item }}
                </div>
              </div>
            </div>

            <div>
              <h3 class="font-headline text-xl font-bold text-on-surface mb-6 flex items-center gap-3 px-4">
                <span class="material-symbols-outlined text-primary">local_library</span>
                Lộ trình học tập đề xuất
              </h3>
              <div class="grid gap-6 sm:grid-cols-2">
                <NuxtLink 
                  v-for="course in analysis.suggested_courses_data" 
                  :key="course.id" 
                  :to="`/courses/${course.id}`" 
                  class="group flex flex-col bg-surface-lowest rounded-3xl border border-surface-dim shadow-sm hover:border-primary/40 hover:shadow-md transition-all overflow-hidden"
                >
                  <div class="h-40 overflow-hidden relative">
                    <img v-if="course.thumbnail" :src="course.thumbnail" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110">
                    <div v-else class="h-full w-full bg-surface-low flex items-center justify-center">
                      <span class="material-symbols-outlined text-outline text-4xl">book</span>
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent flex items-end p-4">
                       <p class="text-white text-sm font-bold line-clamp-2">{{ course.title }}</p>
                    </div>
                  </div>
                  <div class="p-4 space-y-3 border-t border-surface-dim/30">
                    <p class="text-sm leading-relaxed text-on-surface-variant min-h-[48px]">
                      {{ course.recommendation_reason || 'Khóa học này phù hợp để lấp khoảng trống kỹ năng và giúp CV của bạn có thêm minh chứng thực tế.' }}
                    </p>
                    <div class="flex items-center justify-between">
                      <p class="text-xs font-bold text-on-surface-variant truncate">{{ course.instructor?.name || 'EduPress Elite' }}</p>
                      <span class="material-symbols-outlined text-primary group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                  </div>
                </NuxtLink>
              </div>
              <div v-if="!analysis.suggested_courses_data?.length" class="p-8 text-center bg-surface-lowest rounded-3xl border border-surface-dim/40 text-on-surface-variant text-sm">
                Chúng tôi đang cập nhật các khóa học phù hợp với khoảng cách kỹ năng này.
              </div>
            </div>

          </div>

          <div v-else class="h-full min-h-[400px] flex flex-col items-center justify-center text-center p-12 bg-surface-lowest rounded-[3rem] border-2 border-dashed border-surface-dim/40 shadow-inner">
            <div class="w-20 h-20 bg-surface-low rounded-3xl flex items-center justify-center mb-6 text-outline/30">
              <span class="material-symbols-outlined text-4xl">travel_explore</span>
            </div>
            <h3 class="text-2xl font-headline font-bold text-on-surface">Đang chờ phân tích</h3>
            <p class="text-on-surface-variant mt-2 max-w-sm">Hãy nhập vị trí công việc bạn hằng mong ước bên trái để AI định hướng lộ trình thỉnh giảng.</p>
          </div>
        </div>
      </div>

      <!-- Initial Upload State -->
      <div v-else class="max-w-2xl mx-auto py-20">
         <div 
           class="group p-16 rounded-[4rem] bg-surface-lowest border border-surface-dim shadow-ambient text-center cursor-pointer hover:border-primary transition-all duration-500"
           @click="(fileInput as any)?.click()"
         >
            <div class="w-24 h-24 bg-primary/5 rounded-[2.5rem] flex items-center justify-center mx-auto mb-8 text-primary shadow-inner group-hover:scale-110 transition-transform">
               <span class="material-symbols-outlined text-5xl">{{ uploading ? 'hourglass_top' : 'cloud_upload' }}</span>
            </div>
            <h2 class="text-3xl font-headline font-bold text-on-surface mb-4">{{ uploading ? 'Đang đọc CV của bạn...' : 'Bắt đầu ngay hôm nay' }}</h2>
            <p class="text-on-surface-variant max-w-md mx-auto mb-10 leading-relaxed">Tải lên hồ sơ năng lực (CV) để EduPress AI khám phá tiềm năng và xây dựng tương lai sự nghiệp của bạn.</p>
            
            <div class="flex items-center justify-center gap-6 pt-10 border-t border-surface-dim/30">
               <div class="text-center">
                  <p class="text-xs font-bold text-outline-variant uppercase tracking-widest mb-1">Dung lượng</p>
                  <p class="text-sm font-bold text-on-surface">Tối đa 10MB</p>
               </div>
               <div class="h-8 w-[1px] bg-surface-dim/30"></div>
               <div class="text-center">
                  <p class="text-xs font-bold text-outline-variant uppercase tracking-widest mb-1">Định dạng</p>
                  <p class="text-sm font-bold text-on-surface">PDF / DOCX</p>
               </div>
            </div>
         </div>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import UiInput from '~/components/ui/UiInput.vue'
import UiButton from '~/components/ui/UiButton.vue'
import AppPageHeader from '~/components/common/AppPageHeader.vue'

definePageMeta({ middleware: 'auth' })
const auth = useAuthStore()
const cvData = ref<any>(null)
const analysis = ref<any>(null)
const targetJob = ref('')
const uploading = ref(false)
const loadingRecommendations = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const expertAnalysis = computed(() => ({
  overview: analysis.value?.expert_analysis?.overview || '',
  strengths: analysis.value?.expert_analysis?.strengths || [],
  weaknesses: analysis.value?.expert_analysis?.weaknesses || [],
  cv_additions: analysis.value?.expert_analysis?.cv_additions || [],
  cv_improvements: analysis.value?.expert_analysis?.cv_improvements || [],
  learning_priorities: analysis.value?.expert_analysis?.learning_priorities || [],
}))

const loadInitialData = async () => {
  try {
    const data = await useApi<any>('/career/advisor', { token: auth.token })
    cvData.value = data.cv
    if (data.recommendations?.length > 0) analysis.value = data.recommendations[0]
  } catch (err) {
    console.error('Failed to load career advisor data', err)
  }
}

const handleFileUpload = async (event: any) => {
  const file = event.target.files[0]
  if (!file) return
  const formData = new FormData()
  formData.append('cv', file)
  uploading.value = true
  try {
    const res = await useApi<any>('/career/upload-cv', { method: 'POST', body: formData, token: auth.token })
    cvData.value = res.cv
    analysis.value = null
  } catch {
    alert('Không thể tải CV lên. Vui lòng thử lại.')
  } finally {
    uploading.value = false
    if (fileInput.value) {
      fileInput.value.value = ''
    }
  }
}

const getRecommendations = async () => {
  if (!targetJob.value) return
  loadingRecommendations.value = true
  try {
    const res = await useApi<any>('/career/recommend', { method: 'POST', body: { job_title: targetJob.value }, token: auth.token })
    analysis.value = res.recommendation
  } catch (err) {
     alert('Có lỗi xảy ra trong quá trình phân tích AI.')
  } finally {
    loadingRecommendations.value = false
  }
}

const formatDate = (date: string) => !date ? 'N/A' : new Date(date).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })

onMounted(loadInitialData)
</script>
