<script setup>
import '../../../css/checkout.css';

defineProps({
    state: { type: Object, required: true },
    orderId: { type: [String, Number], default: '' },
    retrying: { type: Boolean, default: false },
    retryLabel: { type: String, default: 'تلاش دوباره برای پرداخت' },
});

defineEmits(['retry']);
</script>

<template>
    <div v-if="state.tone !== 'none'" class="co-result" :class="`co-result--${state.tone}`"
        :role="state.tone === 'failure' ? 'alert' : 'status'" aria-live="polite">
        <span class="co-result-mark" aria-hidden="true">
            <i class="fa-solid" :class="state.tone === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'" />
        </span>
        <div class="co-result-body">
            <h2 class="co-result-title sf-type-h3">{{ state.title }}</h2>
            <p class="co-result-message">{{ state.message }}</p>
            <p v-if="state.reference" class="co-result-ref sf-type-caption">
                شماره پیگیری پرداخت: <b dir="ltr">{{ state.reference }}</b>
            </p>
        </div>
        <div v-if="state.retry && orderId" class="co-result-actions">
            <button type="button" class="sf-button co-result-retry" :disabled="retrying" @click="$emit('retry')">
                {{ retrying ? 'در حال اتصال به درگاه…' : retryLabel }}
            </button>
            <a class="sf-text-link" :href="`/orders/${orderId}`">مشاهده سفارش</a>
        </div>
    </div>
</template>
