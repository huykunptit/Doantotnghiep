<script setup lang="ts">
interface SummaryStat {
  label: string
  value: string
  note: string
  tone?: 'green' | 'neutral' | 'amber'
}

interface HeroItem {
  count: string
  label: string
  note: string
  tone?: 'green' | 'neutral' | 'blue'
}

interface MetricCard {
  title: string
  value: string
  delta: string
  subtitle: string
  type: 'bars' | 'ring' | 'progress' | 'wave'
  labels?: string[]
  bars?: number[]
  ring?: number
  progress?: number
  note?: string
  wavePoints?: string
  footLeft?: string
  footRight?: string
}

defineProps<{
  roleLabel: string
  workspaceLabel: string
  title: string
  intro: string
  heroTitle: string
  heroDescription: string
  searchPlaceholder: string
  userName: string
  userRole: string
  navItems: Array<{ label: string; icon: string; to?: string; active?: boolean }>
  supportItems: string[]
  summaryStats: SummaryStat[]
  heroItems: HeroItem[]
  metricCards: MetricCard[]
  activityRows: Array<{ title: string; time: string; status: string }>
}>()

const chartLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

function statusClass(status: string) {
  return status === 'Gap' ? 'is-warning' : 'is-positive'
}

function heroToneClass(tone?: string) {
  return tone ? `tone-${tone}` : 'tone-green'
}

function cardToneClass(tone?: string) {
  return tone ? `tone-${tone}` : ''
}

async function handleLogout() {
  const token = useAuthTokenCookie()

  try {
    if (token.value) {
      await useApi('/auth/logout', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token.value}`,
        },
      })
    }
  } catch {
    // Clear local session even if the API logout request fails.
  } finally {
    clearAuthSession()
    await navigateTo('/login')
  }
}
</script>

<template>
  <main class="dashboard-shell">
    <div class="dashboard-frame">
      <aside class="dashboard-sidebar">
        <div class="sidebar-brand">
          <div class="brand-line">
            <span class="brand-mark" />
            <span class="brand-name">PTIT LMS</span>
          </div>
          <div>
            <p class="sidebar-eyebrow">{{ roleLabel }}</p>
            <h1>{{ workspaceLabel }}</h1>
          </div>
        </div>

        <nav class="sidebar-nav">
          <NuxtLink
            v-for="item in navItems"
            :key="item.label"
            :to="item.to || getDashboardPath(userRole.toLowerCase())"
            class="sidebar-link"
            :class="{ 'is-active': item.active }"
          >
            <span class="sidebar-icon">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
          </NuxtLink>
        </nav>

        <section class="sidebar-section">
          <p class="sidebar-label">Ho tro</p>
          <ul>
            <li v-for="item in supportItems" :key="item">{{ item }}</li>
          </ul>
        </section>

        <div class="sidebar-profile">
          <div class="avatar-chip">{{ userName.slice(0, 2).toUpperCase() }}</div>
          <div>
            <strong>{{ userName }}</strong>
            <p>{{ userRole }}</p>
          </div>
          <button type="button" class="profile-action button-reset" @click="handleLogout">Thoat</button>
        </div>
      </aside>

      <section class="dashboard-main">
        <header class="dashboard-topbar">
          <button class="topbar-ghost" type="button">☰</button>

          <div class="topbar-search">
            <span>⌕</span>
            <input type="text" :placeholder="searchPlaceholder" />
          </div>

          <div class="topbar-actions">
            <button class="topbar-ghost" type="button">◐</button>
            <button class="topbar-ghost" type="button">🔔</button>
            <button class="topbar-logout" type="button" @click="handleLogout">Đăng xuất</button>
            <div class="topbar-user">
              <div class="avatar-chip is-small">{{ userName.slice(0, 2).toUpperCase() }}</div>
              <div>
                <strong>{{ userName }}</strong>
                <p>{{ userRole }}</p>
              </div>
            </div>
          </div>
        </header>

        <section class="dashboard-grid">
          <article class="dashboard-card hero-card">
            <div class="hero-copy">
              <p class="section-kicker">{{ title }}</p>
              <h2>{{ heroTitle }}</h2>
              <p>{{ heroDescription }}</p>

              <div class="status-list">
                <article
                  v-for="item in heroItems"
                  :key="item.label"
                  class="status-item"
                  :class="heroToneClass(item.tone)"
                >
                  <div class="status-dot" />
                  <div>
                    <strong>{{ item.count }} {{ item.label }}</strong>
                    <p>{{ item.note }}</p>
                  </div>
                </article>
              </div>
            </div>

            <div class="hero-figure" aria-hidden="true">
              <div class="figure-circle figure-circle-large" />
              <div class="figure-circle figure-circle-small" />
              <div class="figure-card">
                <div class="figure-head" />
                <div class="figure-body" />
                <div class="figure-laptop" />
              </div>
            </div>
          </article>

          <article
            v-for="stat in summaryStats"
            :key="stat.label"
            class="dashboard-card mini-card"
            :class="cardToneClass(stat.tone)"
          >
            <p class="mini-title">{{ stat.label }}</p>
            <div class="mini-head">
              <strong>{{ stat.value }}</strong>
              <span>{{ stat.note }}</span>
            </div>
          </article>

          <article class="dashboard-card chart-card">
            <div class="card-head">
              <div>
                <p class="section-kicker">{{ intro }}</p>
                <h3>Tong quan tien do tuan nay</h3>
              </div>
              <button class="pill-button" type="button">Tuan nay</button>
            </div>

            <div class="chart-shell">
              <div class="chart-gridlines">
                <span v-for="line in 4" :key="line" />
              </div>
              <svg viewBox="0 0 660 280" class="chart-svg" preserveAspectRatio="none">
                <path
                  d="M20 250 C70 252 82 122 136 120 C200 118 206 186 278 184 C342 182 362 110 422 116 C474 122 488 206 548 188 C604 170 614 106 642 92"
                  class="chart-line is-primary"
                />
                <path
                  d="M20 252 C72 236 88 168 140 172 C198 176 218 122 280 130 C332 136 354 210 420 170 C474 138 498 146 552 198 C598 242 620 188 642 178"
                  class="chart-line is-secondary"
                />
              </svg>
              <div class="chart-labels">
                <span v-for="label in chartLabels" :key="label">{{ label }}</span>
              </div>
            </div>
          </article>

          <article
            v-for="card in metricCards"
            :key="card.title"
            class="dashboard-card metric-card"
            :class="`${card.type}-card`"
          >
            <div class="card-head compact">
              <div>
                <h3>{{ card.title }}</h3>
                <p>{{ card.subtitle }}</p>
              </div>
              <div class="metric-value">
                <strong>{{ card.value }}</strong>
                <span class="pill-badge is-positive">{{ card.delta }}</span>
              </div>
            </div>

            <div v-if="card.type === 'bars'" class="bars-row">
              <div v-for="(bar, index) in card.bars" :key="`${bar}-${index}`" class="bar-stack">
                <div class="bar-track">
                  <span class="bar-fill" :style="{ height: `${bar}%` }" />
                </div>
                <small>{{ card.labels?.[index] }}</small>
              </div>
            </div>

            <div v-else-if="card.type === 'ring'" class="ring-wrap">
              <div class="progress-ring" :style="{ '--progress': `${card.ring}%` }">
                <span>{{ card.ring }}%</span>
              </div>
              <p>{{ card.note }}</p>
            </div>

            <div v-else-if="card.type === 'progress'" class="progress-wrap">
              <div class="progress-track">
                <span class="progress-fill" :style="{ width: `${card.progress}%` }" />
              </div>
              <p>{{ card.note }}</p>
            </div>

            <template v-else>
              <svg viewBox="0 0 340 120" class="wave-svg" preserveAspectRatio="none">
                <path :d="card.wavePoints" class="wave-line" />
              </svg>

              <div class="wave-foot">
                <span>{{ card.footLeft }}</span>
                <strong>{{ card.footRight }}</strong>
              </div>
            </template>
          </article>

          <article class="dashboard-card activity-card">
            <div class="card-head">
              <div>
                <p class="section-kicker">Cap nhat nhanh</p>
                <h3>Hoat dong gan nhat</h3>
              </div>
              <button class="pill-button" type="button">Xem tat ca</button>
            </div>

            <div class="activity-list">
              <article v-for="item in activityRows" :key="item.title" class="activity-item">
                <div>
                  <strong>{{ item.title }}</strong>
                  <p>{{ item.time }}</p>
                </div>
                <span class="pill-badge" :class="statusClass(item.status)">
                  {{ item.status }}
                </span>
              </article>
            </div>
          </article>
        </section>
      </section>
    </div>
  </main>
</template>
