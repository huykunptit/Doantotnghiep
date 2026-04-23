<script setup lang="ts">
const user = useAuthUserCookie()

if (!user.value) {
  await navigateTo('/login', { replace: true })
}

if (user.value && normalizeRole(user.value.role) !== 'instructor') {
  await navigateTo(getDashboardPath(user.value.role), { replace: true })
}

const viewUser = user.value
</script>

<template>
  <DashboardShell
    :activity-rows="[
      { title: 'Lop UI/UX co 18 bai nop moi can cham', time: '08:00', status: 'Gap' },
      { title: 'Buoi livestream toi nay da duoc nhac lich cho hoc vien', time: '09:20', status: 'Moi' },
      { title: 'Tai lieu Module 4 da duoc cap nhat thanh cong', time: '11:10', status: 'Da xong' },
    ]"
    :hero-items="[
      { count: '05', label: 'lop hoc dang quan ly', note: 'Sap xep theo lich trong ngay', tone: 'green' },
      { count: '18', label: 'bai nop cho cham', note: 'Can xu ly truoc 22:00', tone: 'neutral' },
      { count: '42', label: 'hoc vien dang online', note: 'Tap trung o cac lop thuc hanh', tone: 'blue' },
    ]"
    hero-description="Bang dieu khien nay giup giang vien theo doi lop hoc, bai nop va muc do tham gia cua hoc vien ma khong bi roi mat."
    hero-title="Xin chao, hom nay la mot ngay day hoc rat gon va ro."
    intro="Tien do giang day"
    :metric-cards="[
      { title: 'Ty le hoan thanh bai hoc', value: '78%', delta: '+5.1%', subtitle: 'Tuan nay', type: 'bars', bars: [36, 62, 58, 70, 74, 78, 76], labels: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] },
      { title: 'Muc do tham gia', value: '284', delta: '+9.6%', subtitle: 'Tin nhan va thao luan', type: 'ring', ring: 76, note: 'Hoc vien dang tuong tac tot o cac buoi hoc' },
      { title: 'Danh gia mon hoc', value: '9.5', delta: '+0.4%', subtitle: 'Tu hoc vien', type: 'progress', progress: 91, note: 'Nhom hoc vien phan hoi tich cuc ve nhom bai tap' },
      { title: 'Nguoi hoc quay lai', value: '81%', delta: '+3.1%', subtitle: '7 ngay gan day', type: 'wave', wavePoints: 'M0 70 C24 70 44 24 72 24 C96 24 118 84 144 84 C176 84 192 34 220 34 C248 34 268 92 296 92 C316 92 328 74 340 68', footLeft: 'Hoc vien quay lai', footRight: '842' },
    ]"
    :nav-items="[
      { label: 'Tong quan', icon: '◧', active: true },
      { label: 'Lop hoc', icon: '△' },
      { label: 'Bai tap', icon: '◎' },
      { label: 'Lich day', icon: '◌' },
    ]"
    role-label="Instructor role"
    search-placeholder="Tim lop hoc, bai nop, hoc vien..."
    :summary-stats="[
      { label: 'Buoi day hom nay', value: '04', note: '2 buoi sap bat dau', tone: 'green' },
      { label: 'Bai nop cho cham', value: '18', note: 'Tap trung o lop UI/UX', tone: 'amber' },
      { label: 'Tin nhan hoc vien', value: '26', note: 'Can phan hoi trong ngay', tone: 'neutral' },
    ]"
    :support-items="['Quan ly lop hoc', 'Noi dung bai giang', 'Cham bai', 'Thong bao hoc vien']"
    title="Tong quan giang day"
    :user-name="viewUser?.name || 'Instructor User'"
    user-role="Instructor"
    workspace-label="Instructor"
  />
</template>
