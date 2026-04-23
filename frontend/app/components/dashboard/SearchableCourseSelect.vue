<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

interface CourseOption {
  id: number;
  title: string;
  category?: { name: string } | null;
}

const props = defineProps<{
  modelValue: number | null;
  courses: CourseOption[];
  loading?: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void;
  (e: 'change', value: number | null): void;
}>();

const isOpen = ref(false);
const searchQuery = ref('');
const selectRef = ref<HTMLElement | null>(null);

const coursesByCategory = computed(() => {
  const groups: Record<string, CourseOption[]> = {};
  
  const filtered = props.courses.filter(c => {
    const searchLower = searchQuery.value.toLowerCase();
    return c.title.toLowerCase().includes(searchLower) || 
           (c.category?.name || '').toLowerCase().includes(searchLower);
  });

  filtered.forEach(course => {
    const catName = course.category?.name || 'Khác / Chưa phân loại';
    if (!groups[catName]) groups[catName] = [];
    groups[catName].push(course);
  });
  
  return groups;
});

const selectedCourse = computed(() => props.courses.find(c => c.id === props.modelValue));

const toggleOpen = () => {
  if (props.loading) return;
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    searchQuery.value = '';
    setTimeout(() => {
       const input = selectRef.value?.querySelector('.select-search') as HTMLInputElement | null;
       if (input) input.focus();
    }, 50);
  }
};

const selectOption = (id: number) => {
  emit('update:modelValue', id);
  emit('change', id);
  isOpen.value = false;
};

// Close when clicking outside
const handleClickOutside = (e: MouseEvent) => {
  if (selectRef.value && !selectRef.value.contains(e.target as Node)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside);
});
onUnmounted(() => {
  document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
  <div class="searchable-select" ref="selectRef">
    <div class="select-trigger" :class="{ 'is-loading': loading, 'is-open': isOpen }" @click="toggleOpen">
      <span class="select-value" v-if="loading">Đang tải khóa học...</span>
      <span class="select-value" v-else-if="selectedCourse">{{ selectedCourse.title }}</span>
      <span class="select-placeholder" v-else>Chọn khóa học...</span>
      <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
    </div>
    
    <div class="select-dropdown" v-if="isOpen">
      <div class="select-search-wrap">
        <input v-model="searchQuery" type="text" class="select-search" placeholder="Tìm kiếm khóa học hoặc danh mục..." @click.stop>
      </div>
      <div class="select-options-wrap">
        <div v-if="Object.keys(coursesByCategory).length === 0" class="select-empty">
          Không tìm thấy kết quả nào.
        </div>
        <template v-for="(groupCourses, categoryName) in coursesByCategory" :key="categoryName">
          <div class="select-group">
            <div class="select-group-label">{{ categoryName }}</div>
            <div 
              v-for="course in groupCourses" 
              :key="course.id" 
              class="select-option" 
              :class="{ 'is-selected': modelValue === course.id }"
              @click="selectOption(course.id)"
            >
              <svg v-if="modelValue === course.id" class="select-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              <span>{{ course.title }}</span>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.searchable-select {
  position: relative;
  min-width: 320px;
  flex: 1;
}

.select-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 52px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.92);
  padding: 0 16px;
  cursor: pointer;
  user-select: none;
  transition: border-color 0.2s, background-color 0.2s;
}

.select-trigger:hover, .select-trigger.is-open {
  border-color: rgba(17, 17, 17, 0.2);
  background: #fff;
}

.select-trigger.is-loading {
  opacity: 0.7;
  cursor: not-allowed;
}

.select-value {
  font-weight: 500;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 90%;
}

.select-placeholder {
  color: var(--muted);
}

.select-arrow {
  width: 18px;
  height: 18px;
  color: var(--muted);
  transition: transform 0.2s;
}

.is-open .select-arrow {
  transform: rotate(180deg);
}

.select-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid rgba(17, 17, 17, 0.08);
  border-radius: 18px;
  box-shadow: 0 15px 35px -10px rgba(17, 17, 17, 0.15);
  z-index: 100;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.select-search-wrap {
  padding: 12px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.05);
  background: #fdfdfd;
}

.select-search {
  width: 100%;
  min-height: 44px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  border-radius: 12px;
  padding: 0 14px;
  outline: none;
  font-size: 0.95rem;
  background: #fff;
  transition: border-color 0.2s;
}

.select-search:focus {
  border-color: var(--green-deep, #1f5d33);
  box-shadow: 0 0 0 3px rgba(47, 122, 69, 0.1);
}

.select-options-wrap {
  max-height: 380px;
  overflow-y: auto;
  padding: 8px 0;
}

.select-empty {
  padding: 24px 16px;
  text-align: center;
  color: var(--muted);
  font-size: 0.95rem;
}

.select-group {
  margin-bottom: 8px;
}

.select-group:last-child {
  margin-bottom: 0;
}

.select-group-label {
  padding: 8px 20px;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: rgba(17, 17, 17, 0.03);
  position: sticky;
  top: 0;
  z-index: 1;
}

.select-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  cursor: pointer;
  transition: background-color 0.15s;
  font-size: 0.95rem;
  color: var(--text);
}

.select-option > span {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.select-option:hover {
  background: rgba(47, 122, 69, 0.06);
}

.select-option.is-selected {
  background: rgba(47, 122, 69, 0.1);
  color: var(--green-deep, #1f5d33);
  font-weight: 600;
}

.select-check {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}
</style>
