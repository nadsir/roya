<script setup>
import { computed } from 'vue';
import { faNum } from '../comment-state.js';

const props = defineProps({
    rating: { type: [Number, String], default: null },
    size: { type: String, default: 'md' },
    showValue: { type: Boolean, default: false },
});

const filled = computed(() => {
    const r = Number(props.rating);
    if (!Number.isFinite(r)) return 0;
    return Math.min(5, Math.max(0, Math.round(r)));
});

const label = computed(() =>
    props.rating
        ? `امتیاز ${faNum(props.rating)} از ۵`
        : 'بدون امتیاز',
);

const sizeClass = {
    sm: 'h-4 w-4',
    md: 'h-5 w-5',
    lg: 'h-6 w-6',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1"
        role="img"
        :aria-label="label"
    >
        <svg
            v-for="i in 5"
            :key="i"
            :class="[
                sizeClass[size],
                i <= filled
                    ? 'fill-brand-accent text-brand-accent'
                    : 'fill-transparent text-slate-300 dark:text-slate-600',
            ]"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
        </svg>
        <span
            v-if="showValue"
            class="ms-1 text-xs font-bold text-ink dark:text-slate-100"
        >
            {{ faNum(rating) }}
        </span>
    </span>
</template>