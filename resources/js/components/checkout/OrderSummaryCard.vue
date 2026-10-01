<script setup>
import CheckoutLineRow from './CheckoutLineRow.vue';
import { formatPrice } from '../../product-presentation.js';
import { CHECKOUT_AUTHORITY_NOTE } from '../../checkout-presentation.js';

defineProps({
    items: { type: Array, required: true },
    count: { type: Number, default: 0 },
    subtotal: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    lineErrors: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="co-summary" aria-labelledby="co-summary-title">
        <h2 id="co-summary-title" class="co-summary-title sf-type-h3">خلاصه سفارش</h2>
        <ul class="co-lines">
            <CheckoutLineRow v-for="(item, index) in items" :key="item.key" :item="item" :errors="lineErrors[index] || []" />
        </ul>
        <dl class="co-totals">
            <div class="co-total-row">
                <dt class="sf-type-small">تعداد کالاها</dt>
                <dd class="sf-type-price">{{ count.toLocaleString('fa-IR') }}</dd>
            </div>
            <div class="co-total-row">
                <dt class="sf-type-small">جمع کالاها</dt>
                <dd class="sf-type-price">{{ formatPrice(subtotal) }} <small>تومان</small></dd>
            </div>
            <div class="co-total-row">
                <dt class="sf-type-small">تخفیف</dt>
                <dd class="sf-type-price">۰ <small>تومان</small></dd>
            </div>
            <div class="co-total-row">
                <dt class="sf-type-small">هزینه ارسال</dt>
                <dd class="sf-type-price">۰ <small>تومان</small></dd>
            </div>
            <div class="co-total-row co-total-row--final">
                <dt class="sf-type-small">مبلغ نهایی</dt>
                <dd class="sf-type-price">{{ formatPrice(total) }} <small>تومان</small></dd>
            </div>
        </dl>
        <p class="co-summary-note sf-type-caption">{{ CHECKOUT_AUTHORITY_NOTE }}</p>
    </section>
</template>
