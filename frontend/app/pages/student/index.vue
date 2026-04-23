<script setup lang="ts">
const user = useAuthUserCookie()

if (!user.value) {
  await navigateTo('/login', { replace: true })
}

if (user.value && normalizeRole(user.value.role) !== 'student') {
  await navigateTo(getDashboardPath(user.value.role), { replace: true })
}

const viewUser = user.value
</script>

<template>
  <DashboardShell
    :activity-rows="[
      { title: 'Ban co 1 bai quiz can hoan thanh truoc 20:00', time: '07:45', status: 'Gap' },
      { title: 'Giang vien vua dang tai tai lieu moi cho mon UX', time: '09:10', status: 'Moi' },
      { title: 'Tien do hoc tap tuan nay da duoc cap nhat', time: '11:30', status: 'Da xong' },
    ]"
    :hero-items="[
      { count: '03', label: 'lop hoc sap bat dau', note: 'Uu tien theo lich trong ngay', tone: 'green' },
      { count: '01', label: 'bai quiz can hoan thanh', note: 'Han nop truoc toi nay', tone: 'neutral' },
      { count: '06', label: 'tai lieu moi', note: 'Da duoc cap nhat boi giang vien', tone: 'blue' },
    ]"
    hero-description="Khong gian hoc tap nay giup ban xem nhanh bai hoc dang hoc, lich sap toi va muc do hoan thanh trong tuan."
    hero-title="Chao mung ban quay lai, hom nay hoc gi cung that ro rang."
    intro="Tien do hoc tap"
    :metric-cards="[
      { title: 'Muc do hoan thanh', value: '68%', delta: '+8.3%', subtitle: 'Tuan nay', type: 'bars', bars: [22, 48, 56, 62, 70, 68, 74], labels: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] },
      { title: 'Thoi gian tap trung', value: '14h', delta: '+3.4h', subtitle: 'Hoc trong 7 ngay', type: 'ring', ring: 68, note: 'Ban dang hoc deu hon so voi tuan truoc' },
      { title: 'Diem trung binh', value: '8.7', delta: '+0.5%', subtitle: 'Cap nhat moi nhat', type: 'progress', progress: 82, note: 'Ket qua tot nhat den tu module thuc hanh' },
      { title: 'Ngay hoc lien tiep', value: '12', delta: '+2', subtitle: 'Thoi quen hoc tap', type: 'wave', wavePoints: 'M0 76 C24 76 34 42 64 42 C96 42 112 88 144 88 C174 88 186 46 216 46 C248 46 268 96 298 96 C316 96 330 72 340 64', footLeft: 'Chuoi hoc', footRight: '12 ngay' },
    ]"
    :nav-items="[
      { label: 'Tong quan', icon: '◧', active: true },
      { label: 'Khoa hoc cua toi', icon: '△' },
      { label: 'Lich hoc', icon: '◎' },
      { label: 'Ket qua', icon: '◌' },
    ]"
    role-label="Student role"
    search-placeholder="Tim bai hoc, tai lieu, deadline..."
    :summary-stats="[
      { label: 'Buoi hoc hom nay', value: '03', note: '01 buoi dang dien ra', tone: 'green' },
      { label: 'Bai tap can nop', value: '02', note: '01 bai den han toi nay', tone: 'amber' },
      { label: 'Tai lieu moi', value: '06', note: 'Da bo sung tu giang vien', tone: 'neutral' },
    ]"
    :support-items="['Lich hoc', 'Tai lieu', 'Thong bao', 'Ho tro hoc tap']"
    title="Khong gian hoc tap"
    :user-name="viewUser?.name || 'Student User'"
    user-role="Student"
    workspace-label="Student"
  />
</template>
