<script setup>
import { orderSteps, orderIsCancelled, orderStatusLabel } from '../../account-presentation.js';

// Only statuses the backend can actually hold produce a timeline. A cancelled order
// has no reachable flow, so the timeline is replaced by the cancellation facts
// instead of showing future steps as if they were still expected.
const props = defineProps({
    order: { type: Object, required: true },
    formatDate: { type: Function, required: true },
});

const STEP_NOTES = {
    pending: 'سفارش ثبت شده و در انتظار بررسی است.',
    confirmed: 'پرداخت تأیید و سفارش نهایی شده است.',
    processing: 'سفارش در حال آماده‌سازی است.',
    shipped: 'سفارش به شرکت حمل تحویل داده شده است.',
    delivered: 'سفارش تحویل داده شده است.',
};
</script>

<template>
    <div>
        <ol v-if="orderSteps(order).length" class="ac-steps">
            <li v-for="step in orderSteps(order)" :key="step.key" class="ac-step" :data-state="step.state">
                <span class="ac-step-marker" aria-hidden="true">
                    <span class="ac-step-dot">
                        <i v-if="step.state === 'done'" class="fa-solid fa-check" />
                    </span>
                    <span class="ac-step-line" />
                </span>
                <span class="ac-step-copy">
                    <strong>
                        {{ step.label }}
                        <span v-if="step.state === 'current'" class="sf-type-caption">(وضعیت فعلی)</span>
                    </strong>
                    <small>{{ STEP_NOTES[step.key] }}</small>
                </span>
            </li>
        </ol>

        <div v-else-if="orderIsCancelled(order)" class="ac-notice ac-notice--error" role="status">
            <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
            <div>
                <strong>این سفارش لغو شده است</strong>
                <p class="ac-field-hint">
                    وضعیت نهایی سفارش: {{ orderStatusLabel(order.status) }}
                    <template v-if="order.cancelled_at"> · تاریخ لغو: {{ formatDate(order.cancelled_at) }}</template>
                </p>
                <p v-if="order.cancelled_reason" class="ac-field-hint">دلیل لغو: {{ order.cancelled_reason }}</p>
            </div>
        </div>

        <p v-else class="ac-field-hint">
            وضعیت ثبت‌شده سفارش: {{ orderStatusLabel(order.status) }}
        </p>
    </div>
</template>
