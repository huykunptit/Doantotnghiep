<script setup lang="ts">
import { resolveMediaUrl } from '~/utils/media-url'

const { t, locale } = useI18n()
const cart = useCartStore()
const panel = ref()

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
function formatPrice(price = 0) {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(price)
}

function openPanel(event: Event) {
  cart.hydrate()
  panel.value?.toggle(event)
}

function goCart() {
  panel.value?.hide()
  return navigateTo('/cart')
}

onMounted(() => cart.hydrate())
</script>

<template>
  <div class="cart-wrap">
    <Button
      icon="pi pi-shopping-cart"
      severity="secondary"
      text
      rounded
      :aria-label="t('common.cart')"
      @click="openPanel"
    />
    <span v-if="cart.count > 0" class="badge">{{ cart.count > 99 ? '99+' : cart.count }}</span>

    <Popover ref="panel" class="cart-pop">
      <div class="panel">
        <header>
          <strong>{{ t('student.cart.title') }}</strong>
          <small v-if="cart.count">{{ t('student.cart.count', { n: cart.count }) }}</small>
        </header>
        <div v-if="!cart.items.length" class="empty">{{ t('student.cart.empty') }}</div>
        <ul v-else>
          <li v-for="item in cart.items" :key="item.id">
            <img v-if="resolveMediaUrl(item.thumbnail)" :src="resolveMediaUrl(item.thumbnail)" :alt="item.title">
            <div>
              <strong>{{ item.title }}</strong>
              <span>{{ formatPrice(item.price) }}</span>
            </div>
            <Button icon="pi pi-times" text rounded size="small" :aria-label="t('student.cart.remove')" @click="cart.remove(item.id)" />
          </li>
        </ul>
        <footer v-if="cart.items.length">
          <div class="sum">
            <span>{{ t('student.checkout.total') }}</span>
            <strong>{{ formatPrice(cart.subtotal) }}</strong>
          </div>
          <Button :label="t('student.cart.view')" class="w-full" @click="goCart" />
        </footer>
        <Button v-else :label="t('student.cart.browse')" text size="small" @click="panel?.hide(); navigateTo('/courses')" />
      </div>
    </Popover>
  </div>
</template>

<style scoped>
.cart-wrap { position: relative; }
.badge {
  position: absolute; top: 2px; right: 2px; min-width: 16px; height: 16px; padding: 0 4px;
  border-radius: 999px; background: var(--brand); color: #fff; font-size: .65rem; font-weight: 800;
  display: grid; place-items: center; pointer-events: none;
}
.panel { width: min(360px, 86vw); display: grid; gap: 8px; }
.panel header { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.panel header small { color: var(--text-muted); font-weight: 600; }
.panel ul { list-style: none; margin: 0; padding: 0; max-height: 280px; overflow: auto; display: grid; gap: 6px; }
.panel li {
  display: grid; grid-template-columns: 48px minmax(0, 1fr) auto; gap: 8px; align-items: center;
  padding: 8px; border-radius: 10px; border: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 94%, transparent);
}
.panel li img { width: 48px; height: 36px; object-fit: cover; border-radius: 8px; }
.panel li strong { display: block; font-size: .82rem; line-height: 1.3; }
.panel li span { color: var(--text-muted); font-size: .78rem; font-weight: 650; }
.empty { color: var(--text-muted); padding: 8px 0; font-weight: 500; }
.sum { display: flex; justify-content: space-between; font-weight: 700; margin-bottom: 8px; }
.w-full { width: 100%; }
</style>
