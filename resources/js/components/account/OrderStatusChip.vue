<script setup>
import { orderStatusLabel, orderIsCancelled, orderIsPaid } from '../../account-presentation.js';

// The chip only reports what the backend stored: the status, whether a payment
// reference exists, and whether the order was cancelled. It never guesses a state.
const props = defineProps({ order: { type: Object, required: true } });

function chip() {
    if (orderIsCancelled(props.order)) return { class: 'ac-chip ac-chip--cancelled', icon: 'fa-solid fa-circle-xmark' };
    if (orderIsPaid(props.order)) return { class: 'ac-chip ac-chip--paid', icon: 'fa-solid fa-circle-check' };
    if (props.order.status === 'pending') return { class: 'ac-chip ac-chip--current', icon: 'fa-solid fa-clock' };
    return { class: 'ac-chip', icon: 'fa-solid fa-circle-dot' };
}
</script>

<template>
    <span :class="chip().class">
        <i :class="chip().icon" aria-hidden="true"></i>
        {{ orderStatusLabel(order.status) }}
    </span>
</template>
