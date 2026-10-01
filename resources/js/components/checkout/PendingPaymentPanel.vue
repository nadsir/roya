<script setup>
import { formatPrice } from '../../product-presentation.js';

defineProps({
    orderId: { type: [String, Number], required: true },
    total: { type: [String, Number], default: '' },
    error: { type: String, default: '' },
    retrying: { type: Boolean, default: false },
});

defineEmits(['retry']);
</script>

<template>
    <section class="co-pending" role="alert">
        <h2 class="co-pending-title sf-type-h3">سفارش شما ثبت شد، پرداخت انجام نشد</h2>
        <p class="co-pending-order sf-type-small">
            شماره سفارش: <b dir="ltr">#{{ orderId }}</b>
        </p>
        <p v-if="total" class="co-pending-order sf-type-small">
            مبلغ قابل پرداخت: <b class="sf-type-price">{{ formatPrice(total) }} <small>تومان</small></b>
        </p>
        <p v-if="error" class="co-pay-text">{{ error }}</p>
        <div class="co-pending-actions">
            <button type="button" class="sf-button" :disabled="retrying" @click="$emit('retry')">
                {{ retrying ? 'در حال اتصال به درگاه…' : 'تلاش دوباره برای پرداخت' }}
            </button>
            <a class="sf-text-link" :href="`/orders/${orderId}`">مشاهده سفارش</a>
        </div>
    </section>
</template>
