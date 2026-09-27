<script setup>
import { ref, computed } from 'vue';
import { faNum } from '../comment-state.js';

const props = defineProps({
    modelValue: { type: Number, default: 0 },
    size: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const starRefs = [];
const focused = ref(0);

const active = computed(() => {
    return Math.min(5, Math.max(0, Math.round(Number(props.modelValue) || 0)));
});

const focusableIndex = computed(() => Math.max(1, focused.value || active.value || 1));

const sizeClass = {
    sm: 'h-5 w-5',
    md: 'h-6 w-6',
    lg: 'h-7 w-7',
};

function pick(i) {
    if (props.disabled) return;
    emit('update:modelValue', i);
}

function onKeydown(e) {
    if (props.disabled) return;
    let next = active.value || 1;
    switch (e.key) {
        case 'ArrowRight':
            next = Math.min(5, (focused.value || active.value || 1) + 1);
            break;
        case 'ArrowLeft':
            next = Math.max(1, (focused.value || active.value || 1) - 1);
            break;
        case 'ArrowUp':
            next = Math.min(5, (focused.value || active.value || 1) + 1);
            break;
        case 'ArrowDown':
            next = Math.max(1, (focused.value || active.value || 1) - 1);
            break;
        case 'Home':
            next = 1;
            break;
        case 'End':
            next = 5;
            break;
        default:
            return;
    }
    e.preventDefault();
    focused.value = next;
    emit('update:modelValue', next);
    const el = starRefs[next - 1];
    if (el) el.focus();
}

function focusStar(i) {
    focused.value = i;
}
</script>

<template>
    <div
        class="inline-flex items-center gap-1.5"
        role="radiogroup"
        aria-label="انتخاب امتیاز (۱ تا ۵)"
        @keydown="onKeydown"
    >
        <button
            v-for="i in 5"
            :key="i"
            :id="id ? `${id}-star-${i}` : undefined"
            type="button"
            role="radio"
            :aria-checked="i === active ? 'true' : 'false'"
            :aria-label="`امتیاز ${faNum(i)} از ۵`"
            :tabindex="i === focusableIndex ? 0 : -1"
            :disabled="disabled"
            :ref="(el) => { if (el) starRefs[i - 1] = el; }"
            class="rounded-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent focus-visible:ring-offset-2 focus-visible:ring-offset-white focus-visible:dark:ring-offset-black disabled:cursor-not-allowed disabled:opacity-50"
            @mouseenter="focusStar(i)"
            @focus="focusStar(i)"
            @click="pick(i)"
        >
            <svg
                :class="[
                    sizeClass[size],
                    i <= active
                        ? 'fill-brand-accent text-brand-accent'
                        : 'fill-transparent text-slate-300 dark:text-slate-600',
                ]"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
            </svg>
        </button>
    </div>
</template>