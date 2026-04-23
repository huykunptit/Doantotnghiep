<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useCourseStore } from '~/stores/course'
import { useApi } from '~/composables/useApi'

const courseStore = useCourseStore()
const categoriesTree = ref<any[]>([])
const featuredCourses = ref<any[]>([])
const coursesLoading = ref(true)

// Mapping logic to map the generic 'code', 'paintbrush' etc to Material Symbols
const categoryIcons: Record<string, string> = { 
  code: 'terminal', 
  paintbrush: 'architecture', 
  briefcase: 'monitoring', 
  globe: 'public', 
  camera: 'photo_camera', 
  music: 'music_note',
  default: 'auto_graph'
}

onMounted(async () => {
  try {
    const [catData, courseData] = await Promise.all([
      useApi<any[]>('/categories').catch(() => []), 
      courseStore.fetchCourses({ per_page: 6 })
    ])
    categoriesTree.value = catData
    featuredCourses.value = courseData?.data || []
  } finally {
    coursesLoading.value = false
  }
})

const categories = computed(() => {
  const flattened: any[] = []

  const walk = (nodes: any[], depth = 0) => {
    for (const node of nodes || []) {
      flattened.push({
        ...node,
        depth,
        total_courses: Number(node.courses_count || 0) + (node.children || []).reduce((sum: number, child: any) => sum + Number(child.courses_count || 0), 0),
      })
      if (depth === 0) walk(node.children || [], depth + 1)
    }
  }

  walk(categoriesTree.value)
  return flattened.slice(0, 8)
})

// Optional: Number formatting utility
const formatPrice = (price?: number) => {
  if (!price) return 'Miễn phí'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
}
</script>

<template>
  <main class="pb-0">
    <!-- Hero Section -->
    <section class="relative min-h-[85vh] lg:min-h-[921px] flex items-center overflow-hidden px-4 md:px-8">
      <div class="max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-7 space-y-8 z-10 fade-in-up mt-10 lg:mt-0">
          <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-light/30 text-primary-dark text-sm font-semibold tracking-wide uppercase">
            <span class="material-symbols-outlined text-sm">auto_awesome</span>
            Chất lượng trong từng bài giảng
          </div>
          <h1 class="text-5xl md:text-7xl font-headline font-bold text-on-surface tracking-tight leading-[1.1]">
            Khám phá Tiềm năng <span class="text-primary italic">Trí tuệ</span> của bạn
          </h1>
          <p class="text-xl text-on-surface-variant max-w-xl leading-relaxed">
            Học tập, giảng dạy và quản lý tri thức theo nhịp điệu hoàn toàn mới. Một không gian giáo dục chuẩn editorial: nội dung , giao diện thoáng đãng.
          </p>
          <div class="flex flex-wrap gap-4 pt-4">
            <NuxtLink to="/courses" class="px-8 py-4 rounded-xl cta-gradient text-white font-bold text-lg shadow-xl hover:-translate-y-1 transition-all">Khám phá Khóa học</NuxtLink>
            <NuxtLink to="/register" class="px-8 py-4 rounded-xl bg-surface-high text-on-surface font-bold text-lg hover:bg-surface-highest transition-all border border-surface-dim/30">Gia nhập EduPress</NuxtLink>
          </div>
        </div>
        
        <div class="lg:col-span-5 relative hidden md:block">
          <div class="aspect-[4/5] rounded-[3rem] overflow-hidden shadow-2xl relative z-10 rotate-3 transform hover:rotate-0 transition-transform duration-700">
            <img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD8U7PWyHi--y0TzmK0qmosS4fl1kUQmJBwfuVaII7aYoDPKqBaX0lnf9SAdpG5ky2mYTShhSOzai_a_lIdL3aF2H0gJn2R8tyURP685Ux3aFdHWsO1WvuY8QwaRbUcaPphjJb5vYmKRtAmVVezX68kR3Up45Oe3k-V5XcnUEpTaUF3Sexf2HJIPKAWKZvnOwWVKzPQxOh5qzESWMGkiSVgaBqsFnGAh9B-pdWV27OWM_uu5xr-N4bvhdnPIcCeq3OiRyO_mpdzYsQ" alt="Professional Interface" />
          </div>
          <div class="absolute -top-12 -right-12 w-64 h-64 bg-secondary/10 rounded-full blur-3xl"></div>
          <div class="absolute -bottom-12 -left-12 w-80 h-80 bg-primary/10 rounded-full blur-3xl"></div>
        </div>
      </div>
    </section>

    <!-- Categories Section -->
    <section class="py-24 bg-surface-low rounded-t-[3rem]">
      <div class="max-w-7xl mx-auto px-4 md:px-8">
        <div class="flex flex-col md:flex-row justify-between items-end gap-6 mb-16 fade-in-up">
          <div class="max-w-2xl">
            <h2 class="text-4xl font-headline font-bold text-on-surface mb-4">Danh mục Đào tạo</h2>
            <p class="text-on-surface-variant text-lg">Mọi chủ đề đều được giám tuyển với chủ đích rõ ràng. Tìm thấy lộ trình phát triển phù hợp nhất cho dự định của bạn.</p>
          </div>
          <NuxtLink to="/categories" class="text-primary font-bold flex items-center gap-2 hover:gap-3 transition-all group">
            Xem toàn bộ Chuyên mục <span class="material-symbols-outlined transition-transform group-hover:translate-x-1">arrow_forward</span>
          </NuxtLink>
        </div>

        <div v-if="categories.length === 0" class="text-center py-10 fade-in-up">
           <p class="text-on-surface-variant">Hệ thống đang cập nhật danh mục...</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <NuxtLink 
            v-for="(cat, index) in categories" 
            :key="cat.id" 
            :to="`/categories/${cat.id}`"
            class="bg-surface-lowest p-8 rounded-xl shadow-sm hover:shadow-ambient hover:-translate-y-2 transition-all duration-300 group fade-in-up"
            :style="`animation-delay: ${index * 0.05}s`"
          >
            <div class="w-14 h-14 rounded-lg bg-primary/10 flex items-center justify-center mb-6 text-primary group-hover:bg-primary group-hover:text-white transition-colors duration-300">
              <span class="material-symbols-outlined text-3xl">{{ categoryIcons[cat.icon] || categoryIcons.default }}</span>
            </div>
            <h3 class="text-xl font-headline font-bold mb-2 text-on-surface">{{ cat.name }}</h3>
            <p class="text-on-surface-variant text-sm">{{ cat.total_courses || cat.courses_count || 0 }} khóa học </p>
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- Top-Rated Experiences -->
    <section class="py-24">
      <div class="max-w-7xl mx-auto px-4 md:px-8">
        <div class="text-center mb-20 fade-in-up">
          <h2 class="text-4xl font-headline font-bold mb-4 text-on-surface">Khám phá Trải nghiệm Học tập</h2>
          <p class="text-on-surface-variant max-w-xl mx-auto">Được chọn lọc bởi đội ngũ chuyên gia dựa trên tính thực tiễn và chất lượng giảng dạy vượt trội.</p>
        </div>

        <div v-if="coursesLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
          <div v-for="i in 3" :key="i" class="h-96 bg-surface-high animate-pulse rounded-xl"></div>
        </div>
        <div v-else-if="featuredCourses.length === 0" class="text-center py-10 fade-in-up">
           <p class="text-on-surface-variant">Các khóa học tiêu biểu đang được điều phối...</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
          <div v-for="(course, index) in featuredCourses.slice(0,6)" :key="course.id" class="group flex flex-col fade-in-up bg-surface-lowest rounded-xl overflow-hidden hover:shadow-ambient transition-all duration-300 border border-surface-dim" :style="`animation-delay: ${index * 0.1}s`">
            <div class="aspect-video overflow-hidden relative">
              <img :src="course.thumbnail || 'https://lh3.googleusercontent.com/aida-public/AB6AXuBr7Dul2jD-3sXFib-VEv2EsmWU2cEt9IAzm8kWj2AxEIsGn40DqOIWUeNLmBr-Q4JDc7Yhe3wqKj46rou8cfopzLZ_yEE19ouGoGUtec7psB5uCg48PTQB6NpGSr9oZB446TUg98NGF5EByjYlexxMU-Xh27UE9R0E5J6b-NcwkVw12KK9wp43ac5q8faWYBgXrpr0hBc2I4CFkNx2sM4qCXYpinDS_7A8DGkWCsfOZpITszMWK04q3CHHnzbuKTB6lVnjttfqHOI'" :alt="course.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
              <div class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur rounded-full text-xs font-bold uppercase tracking-widest text-primary">Tâm điểm</div>
            </div>
            <div class="flex-1 p-6 flex flex-col">
              <div class="flex items-center gap-2 mb-3 text-secondary">
                <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
                <span class="text-sm font-bold">{{ course.reviews_avg_rating ? Number(course.reviews_avg_rating).toFixed(1) : '4.9' }} ({{ course.enrollments_count || 0 }} học viên đang học)</span>
              </div>
              <NuxtLink :to="`/courses/${course.id}`" class="text-xl font-headline font-bold mb-3 group-hover:text-primary transition-colors text-on-surface line-clamp-2">{{ course.title }}</NuxtLink>
              <p class="text-on-surface-variant mb-6 text-sm flex-grow line-clamp-2">{{ course.description || course.short_description || 'Mô tả chi tiết và mục tiêu khóa học đang được cập nhật bởi EduPress...' }}</p>
              
              <div class="flex items-center justify-between pt-6 border-t border-surface-dim/30">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center text-primary font-bold text-xs uppercase">
                    {{ course.instructor?.name?.charAt(0) || 'A' }}
                  </div>
                  <span class="text-sm font-semibold text-on-surface">{{ course.instructor?.name || 'Chuyên gia EduPress' }}</span>
                </div>
                <span class="text-lg font-bold text-on-surface">{{ formatPrice(course.price) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- EduPress Works -->
    <section class="py-24 bg-surface-low overflow-hidden relative">
      <div class="max-w-7xl mx-auto px-4 md:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">
          <div class="fade-in-up">
            <h2 class="text-4xl font-headline font-bold mb-8 text-on-surface">Vận hành theo chuẩn EduPress</h2>
            <div class="space-y-12">
              <div class="flex gap-6">
                <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xl shrink-0">1</div>
                <div>
                  <h4 class="text-xl font-bold mb-2 text-on-surface">Xây dựng Tầm nhìn</h4>
                  <p class="text-on-surface-variant">AI hỗ trợ phân tích năng lực hiện tại và định hướng lộ trình thăng tiến nghề nghiệp một cách khoa học.</p>
                </div>
              </div>
              <div class="flex gap-6">
                <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xl shrink-0">2</div>
                <div>
                  <h4 class="text-xl font-bold mb-2 text-on-surface">Không gian Tập trung Tuyệt đối</h4>
                  <p class="text-on-surface-variant">Tận thưởng các bài giảng video mượt mà kết hợp hệ thống học liệu được trình bày theo phong cách tạp chí nghệ thuật cao cấp.</p>
                </div>
              </div>
              <div class="flex gap-6">
                <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xl shrink-0">3</div>
                <div>
                  <h4 class="text-xl font-bold mb-2 text-on-surface">Kết nối & Khẳng định</h4>
                  <p class="text-on-surface-variant">Tương tác trực tiếp với chuyên gia, hoàn thành thử thách thực tế và nhận chứng chỉ xác thực trên toàn hệ thống.</p>
                </div>
              </div>
            </div>
          </div>
          
          <div class="relative hidden md:block fade-in-up" style="animation-delay: 0.2s;">
            <div class="bg-surface-lowest p-8 rounded-[2.5rem] shadow-ambient rotate-2 hover:rotate-0 transition-all duration-500 border border-surface-dim">
              <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-secondary text-white flex items-center justify-center"><span class="material-symbols-outlined text-sm">school</span></div>
                  <div>
                    <div class="h-3 w-24 bg-surface-dim/80 rounded-full mb-2"></div>
                    <div class="h-2 w-16 bg-surface-high rounded-full"></div>
                  </div>
                </div>
                <div class="h-8 w-20 bg-primary/10 rounded-lg flex items-center justify-center"><span class="material-symbols-outlined text-primary text-sm">verified</span></div>
              </div>
              <div class="space-y-4">
                <div class="h-4 w-full bg-surface-high rounded-full"></div>
                <div class="h-4 w-5/6 bg-surface-high rounded-full"></div>
                <div class="h-32 w-full bg-surface-dim/50 rounded-xl mb-6 flex items-center justify-center transition-all duration-300 hover:bg-primary/5 cursor-pointer">
                  <span class="material-symbols-outlined text-4xl text-outline/50 hover:text-primary transition-colors">play_circle</span>
                </div>
                <div class="h-4 w-3/4 bg-surface-high rounded-full"></div>
              </div>
            </div>
            <div class="absolute -top-10 -right-10 w-48 h-48 bg-primary/5 rounded-full -z-10 blur-2xl"></div>
            <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-secondary/5 rounded-full -z-10 blur-2xl"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Call to Action -->
    <section class="py-24 px-4 md:px-8">
      <div class="max-w-7xl mx-auto rounded-[3rem] cta-gradient p-12 md:p-24 text-center text-white relative overflow-hidden shadow-2xl fade-in-up">
        <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full blur-3xl translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-black/10 rounded-full blur-3xl -translate-x-1/2 translate-y-1/2"></div>
        
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-headline font-bold mb-8 relative z-10 leading-tight">Bắt đầu hành trình tại EduPress?</h2>
        <p class="text-lg md:text-xl opacity-90 max-w-2xl mx-auto mb-12 relative z-10 font-medium">Gia nhập cộng đồng  cùng những chuyên gia dẫn đầu. Khai phóng tri thức theo cách chuyên nghiệp nhất.</p>
        
        <div class="flex flex-wrap justify-center gap-6 relative z-10">
          <NuxtLink to="/register" class="px-8 py-4 bg-surface-lowest text-primary font-bold text-lg rounded-xl shadow-xl hover:scale-105 transition-transform duration-300">Gia nhập Miễn phí</NuxtLink>
          <NuxtLink to="/courses" class="px-8 py-4 bg-transparent border-2 border-white/40 text-white font-bold text-lg rounded-xl hover:bg-white/10 transition-colors duration-300">Khám Phá Toàn bộ</NuxtLink>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.fade-in-up {
  opacity: 0;
  animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}


</style>
