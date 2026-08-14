<script setup lang="ts">
export interface CheckoutVoucherSuggestion {
  id: number
  code?: string
  savings: number
  recommended?: boolean
  expires_at?: string | null
  voucher?: {
    name?: string | null
    type?: string | null
    discount_value?: number | null
  } | null
}

const props = defineProps<{
  suggestions: CheckoutVoucherSuggestion[]
  modelValue: number | null
  formatPrice: (n: number) => string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const { t } = useI18n()

function percentLabel(item: CheckoutVoucherSuggestion) {
  if (item.voucher?.type === 'discount_percent' && item.voucher.discount_value) {
    return t('student.checkout.voucherPercent', { n: item.voucher.discount_value })
  }
  if (item.voucher?.type === 'discount_fixed' && item.voucher.discount_value) {
    return props.formatPrice(item.voucher.discount_value)
  }
  return item.voucher?.name || t('student.points.voucherGeneric')
}

function select(id: number) {
  emit('update:modelValue', props.modelValue === id ? null : id)
}
</script>

<template>
  <section v-if="suggestions.length" class="vouchers">
    <p>{{ t('student.checkout.voucherSuggest') }}</p>
    <button
      v-for="item in suggestions"
      :key="item.id"
      type="button"
      class="voucher"
      :class="{ on: modelValue === item.id, rec: item.recommended && modelValue !== item.id }"
      @click="select(item.id)"
    >
      <div>
        <strong>{{ item.voucher?.name || t('student.points.voucherGeneric') }}</strong>
        <span>{{ percentLabel(item) }} · {{ t('student.checkout.voucherSave', { price: formatPrice(item.savings) }) }}</span>
      </div>
      <Tag v-if="item.recommended" :value="t('student.checkout.voucherBest')" severity="success" />
      <Tag v-else-if="modelValue === item.id" :value="t('common.apply')" severity="info" />
    </button>
    <Button
      v-if="modelValue"
      :label="t('student.checkout.voucherClear')"
      text
      size="small"
      @click="emit('update:modelValue', null)"
    />
  </section>
</template>

<style scoped>
.vouchers { margin-top: 16px; display: grid; gap: 8px; }
.vouchers > p { margin: 0; font-weight: 700; color: var(--text-muted); font-size: .85rem; }
.voucher {
  display: flex; justify-content: space-between; align-items: center; gap: 10px;
  padding: 12px; border-radius: 12px; border: 1px solid var(--border);
  background: var(--surface-subtle); text-align: left; font: inherit; cursor: pointer;
}
.voucher strong { display: block; }
.voucher span { color: var(--text-muted); font-size: .82rem; font-weight: 500; }
.voucher.on { border-color: color-mix(in srgb, var(--brand) 45%, var(--border)); background: var(--brand-soft); }
.voucher.rec { border-color: color-mix(in srgb, #16a34a 40%, var(--border)); }
</style>
