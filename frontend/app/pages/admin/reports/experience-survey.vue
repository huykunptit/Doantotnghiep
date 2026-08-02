<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'admin', middleware: ['auth', 'admin'] })

interface ChartItem { id?: string, key?: string, label: string, title?: string, avg?: number | null, value?: number }
interface Summary {
  total_responses: number
  section_averages: Record<string, { title: string, avg: number | null }>
  charts: {
    section_avg: ChartItem[]
    personalization_likert: ChartItem[]
    ai_usage: ChartItem[]
    most_useful: ChartItem[]
    satisfaction: ChartItem[]
    nps: ChartItem[]
    personalization: ChartItem[]
  }
  recent: Array<{
    id: number
    student_code?: string | null
    student_name?: string | null
    A2?: string | null
    E1?: number | null
    E4?: number | null
    submitted_at?: string | null
  }>
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const exportingCsv = ref(false)
const exportingHtml = ref(false)
const summary = ref<Summary | null>(null)

const palette = ['#0f766e', '#2563eb', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#65a30d', '#db2777']

const chartOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' as const } },
}

const sectionChart = computed(() => {
  const rows = (summary.value?.charts.section_avg || []).filter(r => r.avg != null)
  return {
    data: {
      labels: rows.map(r => r.label),
      datasets: [{
        label: t('admin.experienceSurvey.avgScore'),
        data: rows.map(r => r.avg),
        backgroundColor: palette[0],
        borderRadius: 8,
      }],
    },
    options: {
      ...chartOpts,
      scales: { y: { beginAtZero: true, max: 5 } },
      plugins: { ...chartOpts.plugins, legend: { display: false } },
    },
  }
})

const personalizationLikertChart = computed(() => {
  const rows = (summary.value?.charts.personalization_likert || []).filter(r => r.avg != null)
  return {
    data: {
      labels: rows.map(r => r.label),
      datasets: [{
        label: t('admin.experienceSurvey.avgScore'),
        data: rows.map(r => r.avg),
        backgroundColor: palette[1],
        borderRadius: 8,
      }],
    },
    options: {
      ...chartOpts,
      scales: { y: { beginAtZero: true, max: 5 } },
      plugins: { ...chartOpts.plugins, legend: { display: false } },
    },
  }
})

function doughnutFrom(items: ChartItem[], colors = palette) {
  const rows = items.filter(i => (i.value || 0) > 0)
  return {
    data: {
      labels: rows.map(r => r.label),
      datasets: [{
        data: rows.map(r => r.value || 0),
        backgroundColor: rows.map((_, i) => colors[i % colors.length]),
      }],
    },
    options: chartOpts,
  }
}

const aiChart = computed(() => doughnutFrom(summary.value?.charts.ai_usage || []))
const usefulChart = computed(() => doughnutFrom(summary.value?.charts.most_useful || [], [...palette].reverse()))
const personalizationChart = computed(() => doughnutFrom(summary.value?.charts.personalization || []))

const satisfactionChart = computed(() => {
  const rows = summary.value?.charts.satisfaction || []
  return {
    data: {
      labels: rows.map(r => r.label),
      datasets: [{
        label: 'E1',
        data: rows.map(r => r.value || 0),
        backgroundColor: palette[2],
        borderRadius: 6,
      }],
    },
    options: {
      ...chartOpts,
      plugins: { ...chartOpts.plugins, legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  }
})

const npsChart = computed(() => {
  const rows = summary.value?.charts.nps || []
  return {
    data: {
      labels: rows.map(r => r.label),
      datasets: [{
        label: 'NPS',
        data: rows.map(r => r.value || 0),
        backgroundColor: palette[4],
        borderRadius: 6,
      }],
    },
    options: {
      ...chartOpts,
      plugins: { ...chartOpts.plugins, legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  }
})

async function load() {
  loading.value = true
  try {
    summary.value = await useApi<Summary>('/admin/experience-survey/summary')
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('admin.experienceSurvey.loadError'), detail: e?.data?.message, life: 4000 })
  }
  finally {
    loading.value = false
  }
}

async function exportCsv() {
  exportingCsv.value = true
  try {
    await useApiDownload('/admin/experience-survey/export', {
      filename: `experience_survey_${new Date().toISOString().slice(0, 10)}.csv`,
    })
    toast.add({ severity: 'success', summary: t('admin.experienceSurvey.exportOk'), life: 3000 })
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('admin.experienceSurvey.exportError'), detail: e?.message, life: 4000 })
  }
  finally {
    exportingCsv.value = false
  }
}

function exportHtmlReport() {
  if (!summary.value) return
  exportingHtml.value = true
  try {
    const s = summary.value
    const payload = JSON.stringify(s.charts)
    const html = `<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Báo cáo khảo sát trải nghiệm LMS</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"><\/script>
<style>
  body{font-family:Segoe UI,Arial,sans-serif;margin:24px;color:#111;background:#fff}
  h1{margin:0 0 6px} .muted{color:#666;margin:0 0 20px}
  .stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px}
  .stat{border:1px solid #ddd;border-radius:12px;padding:12px 16px;min-width:120px}
  .stat strong{display:block;font-size:1.4rem}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .card{border:1px solid #ddd;border-radius:14px;padding:14px}
  .card h2{margin:0 0 10px;font-size:1rem}
  canvas{max-height:280px}
  table{width:100%;border-collapse:collapse;margin-top:18px}
  th,td{border:1px solid #ddd;padding:8px;text-align:left;font-size:.9rem}
  th{background:#f5f5f5}
  @media print{.grid{grid-template-columns:1fr 1fr}}
  @media(max-width:800px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
  <h1>Báo cáo khảo sát trải nghiệm LMS</h1>
  <p class="muted">Xuất lúc ${new Date().toLocaleString('vi-VN')} — Tổng phản hồi: <strong>${s.total_responses}</strong></p>
  <div class="stats">
    <div class="stat"><span>Tổng phản hồi</span><strong>${s.total_responses}</strong></div>
    ${(s.charts.section_avg || []).map(x => `<div class="stat"><span>${x.label}</span><strong>${x.avg ?? '—'}</strong></div>`).join('')}
  </div>
  <div class="grid">
    <div class="card"><h2>Điểm TB theo nhóm</h2><canvas id="c1"></canvas></div>
    <div class="card"><h2>Cảm nhận cá nhân hoá</h2><canvas id="c2"></canvas></div>
    <div class="card"><h2>AI đã dùng</h2><canvas id="c3"></canvas></div>
    <div class="card"><h2>AI hữu ích nhất</h2><canvas id="c4"></canvas></div>
    <div class="card"><h2>Hài lòng (E1)</h2><canvas id="c5"></canvas></div>
    <div class="card"><h2>NPS (E4)</h2><canvas id="c6"></canvas></div>
  </div>
  <h2>Phản hồi gần đây</h2>
  <table>
    <thead><tr><th>Mã SV</th><th>Họ tên</th><th>Ngành</th><th>E1</th><th>NPS</th><th>Thời gian</th></tr></thead>
    <tbody>
      ${s.recent.map(r => `<tr>
        <td>${r.student_code || ''}</td>
        <td>${r.student_name || ''}</td>
        <td>${r.A2 || ''}</td>
        <td>${r.E1 ?? ''}</td>
        <td>${r.E4 ?? ''}</td>
        <td>${r.submitted_at ? new Date(r.submitted_at).toLocaleString('vi-VN') : ''}</td>
      </tr>`).join('')}
    </tbody>
  </table>
<script>
const charts = ${payload};
const colors = ${JSON.stringify(palette)};
function bar(id, labels, values, color){
  new Chart(document.getElementById(id), {type:'bar', data:{labels, datasets:[{data:values, backgroundColor:color, borderRadius:8}]}, options:{responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}});
}
function dough(id, labels, values){
  new Chart(document.getElementById(id), {type:'doughnut', data:{labels, datasets:[{data:values, backgroundColor:labels.map((_,i)=>colors[i%colors.length])}]}, options:{responsive:true, plugins:{legend:{position:'bottom'}}}});
}
const sa = charts.section_avg||[];
bar('c1', sa.map(x=>x.label), sa.map(x=>x.avg||0), colors[0]);
const pl = charts.personalization_likert||[];
bar('c2', pl.map(x=>x.label), pl.map(x=>x.avg||0), colors[1]);
const ai = (charts.ai_usage||[]).filter(x=>x.value>0);
dough('c3', ai.map(x=>x.label), ai.map(x=>x.value));
const mu = (charts.most_useful||[]).filter(x=>x.value>0);
dough('c4', mu.map(x=>x.label), mu.map(x=>x.value));
const sat = charts.satisfaction||[];
bar('c5', sat.map(x=>x.label), sat.map(x=>x.value||0), colors[2]);
const nps = charts.nps||[];
bar('c6', nps.map(x=>x.label), nps.map(x=>x.value||0), colors[4]);
<\/script>
</body>
</html>`
    const blob = new Blob([html], { type: 'text/html;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `experience_survey_report_${new Date().toISOString().slice(0, 10)}.html`
    a.click()
    URL.revokeObjectURL(url)
    toast.add({ severity: 'success', summary: t('admin.experienceSurvey.exportHtmlOk'), life: 3500 })
  }
  finally {
    exportingHtml.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.reports') }}</span>
        <h1>{{ t('admin.experienceSurvey.title') }}</h1>
        <p>{{ t('admin.experienceSurvey.subtitle') }}</p>
      </div>
      <div class="actions">
        <Button :label="t('admin.experienceSurvey.refresh')" icon="pi pi-refresh" severity="secondary" outlined :loading="loading" @click="load" />
        <Button :label="t('admin.experienceSurvey.export')" icon="pi pi-file-excel" :loading="exportingCsv" @click="exportCsv" />
        <Button :label="t('admin.experienceSurvey.exportHtml')" icon="pi pi-chart-bar" severity="help" :loading="exportingHtml" @click="exportHtmlReport" />
      </div>
    </header>

    <div v-if="loading && !summary" class="empty">…</div>
    <template v-else-if="summary">
      <div class="stats">
        <div class="stat">
          <span>{{ t('admin.experienceSurvey.total') }}</span>
          <strong>{{ summary.total_responses }}</strong>
        </div>
        <div v-for="card in summary.charts.section_avg" :key="card.label" class="stat">
          <span>{{ card.label }}</span>
          <strong>{{ card.avg ?? '—' }}</strong>
          <small>{{ card.title }}</small>
        </div>
      </div>

      <div class="charts">
        <section class="panel">
          <h2>{{ t('admin.experienceSurvey.sectionAvgChart') }}</h2>
          <ChartsUiChart type="bar" :data="sectionChart.data" :options="sectionChart.options" height="240px" />
        </section>
        <section class="panel">
          <h2>{{ t('admin.experienceSurvey.personalizationLikertChart') }}</h2>
          <ChartsUiChart type="bar" :data="personalizationLikertChart.data" :options="personalizationLikertChart.options" height="240px" />
        </section>
        <section class="panel">
          <h2>{{ t('admin.experienceSurvey.aiUsage') }}</h2>
          <ChartsUiChart type="doughnut" :data="aiChart.data" :options="aiChart.options" height="240px" />
        </section>
        <section class="panel">
          <h2>{{ t('admin.experienceSurvey.mostUseful') }}</h2>
          <ChartsUiChart type="doughnut" :data="usefulChart.data" :options="usefulChart.options" height="240px" />
        </section>
        <section class="panel">
          <h2>{{ t('admin.experienceSurvey.personalizationChart') }}</h2>
          <ChartsUiChart type="doughnut" :data="personalizationChart.data" :options="personalizationChart.options" height="240px" />
        </section>
        <section class="panel">
          <h2>{{ t('admin.experienceSurvey.satisfactionChart') }}</h2>
          <ChartsUiChart type="bar" :data="satisfactionChart.data" :options="satisfactionChart.options" height="240px" />
        </section>
        <section class="panel wide">
          <h2>{{ t('admin.experienceSurvey.npsChart') }}</h2>
          <ChartsUiChart type="bar" :data="npsChart.data" :options="npsChart.options" height="240px" />
        </section>
      </div>

      <section class="panel">
        <h2>{{ t('admin.experienceSurvey.recent') }}</h2>
        <DataTable :value="summary.recent" size="small" striped-rows>
          <Column field="student_code" :header="t('admin.experienceSurvey.studentCode')" style="width:8rem" />
          <Column field="student_name" :header="t('admin.experienceSurvey.studentName')" />
          <Column field="A2" :header="t('admin.experienceSurvey.major')" />
          <Column field="E1" :header="t('admin.experienceSurvey.satisfaction')" style="width:7rem" />
          <Column field="E4" header="NPS" style="width:5rem" />
          <Column :header="t('admin.experienceSurvey.submittedAt')" style="width:11rem">
            <template #body="{ data }">
              {{ data.submitted_at ? new Date(data.submitted_at).toLocaleString() : '—' }}
            </template>
          </Column>
        </DataTable>
      </section>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: flex-start; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.35rem, 2vw, 1.7rem); }
.workspace-head p { margin: 0; color: var(--text-muted); }
.actions { display: flex; gap: 8px; flex-wrap: wrap; }
.stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
.stat {
  padding: 12px; border: 1px solid var(--border); border-radius: 12px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.stat span { display: block; color: var(--text-muted); font-size: .75rem; font-weight: 650; }
.stat strong { display: block; font-size: 1.35rem; margin-top: 4px; }
.stat small { display: block; margin-top: 4px; color: var(--text-muted); font-size: .72rem; line-height: 1.3; }
.charts { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.panel { border: 1px solid var(--border); border-radius: 16px; padding: 16px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.panel.wide { grid-column: 1 / -1; }
.panel h2 { margin: 0 0 10px; font-size: 1rem; }
.empty { color: var(--text-muted); padding: 24px; text-align: center; }
@media (max-width: 900px) { .charts { grid-template-columns: 1fr; } .panel.wide { grid-column: auto; } }
</style>
