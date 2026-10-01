<script setup>
import OrderStatusChip from './OrderStatusChip.vue';
import { formatPrice } from '../../product-presentation.js';
import { orderItemCount, orderIsPaid } from '../../account-presentation.js';

// The list endpoint returns id, status, created_at, total and items_count only,
// so the card renders exactly those and links to the detail page for the rest.
const props = defineProps({
    order: { type: Object, required: true },
    formatDate: { type: Function, required: true },
});
</script>

<template>
    <article class="ac-order">
        <div class="ac-order-top">
            <h2 class="ac-order-id">
                <span dir="ltr">#{{ order.id }}</span>
                <small>{{ formatDate(order.created_at) }}</small>
            </h2>
            <OrderStatusChip :order="order" />
        </div>

        <p class="ac-order-facts">
            <span>{{ orderItemCount(order).toLocaleString('fa-IR') }} قلم کالا</span>
            <span v-if="orderIsPaid(order)">پرداخت تأییدشده</span>
        </p>

        <div class="ac-order-actions">
            <p class="ac-order-total">
                {{ formatPrice(order.total) }} <small class="sf-type-caption">تومان</small>
            </p>
            <a class="sf-button" :href="`/orders/${order.id}`" :aria-label="`مشاهده جزئیات سفارش ${order.id}`">مشاهده جزئیات</a>
        </div>
    </article>
</template>
