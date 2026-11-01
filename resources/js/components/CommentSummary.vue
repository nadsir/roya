<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { faNum, faDecimal } from '../comment-state.js';
import StarRating from './StarRating.vue';

const props = defineProps({
    average: { type: [Number, String], default: null },
    count: { type: [Number, String], default: 0 },
    distribution: {
        type: Object,
        default: () => ({ 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 }),
    },
});

const ready = ref(false);

const total = computed(() => Number(props.count) || 0);
const hasRating = computed(() => total.value > 0);

const rows = computed(() => {
    const list = [];
    for (let i = 5; i >= 1; i--) {
        const value = Number(props.distribution[i]) || 0;
        list.push({
            stars: i,
            count: value,
            percent: total.value ? Math.round((value / total.value) * 100) : 0,
        });
    }
    return list;
});

function animateBars() {
    ready.value = true;
}

onMounted(() => {
    requestAnimationFrame(animateBars);
});

watch(hasRating, (value) => {
    if (value) ready.value = true;
});
</script>

<template>
    <div class="rounded-xl border border-[color:var(--sf-border)] bg-[color:var(--sf-surface)] p-5 sm:p-6">
        <div v-if="hasRating" class="flex items-start justify-between gap-4">
            <div>
                <div class="flex items-end gap-2">
                    <span class="serif text-6xl leading-none text-[color:var(--sf-text)]">
                        {{ faDecimal(average) }}
                    </span>
                    <span class="pb-1.5 text-sm font-bold text-[color:var(--sf-text-muted)]">
                        از ۵
                    </span>
                </div>
                <StarRating :rating="Number(average)" size="md" class="mt-3" />
                <p class="mt-2 text-xs leading-6 text-[color:var(--sf-text-secondary)]">
                    بر اساس {{ faNum(total) }} امتیاز ثبت‌شده
                </p>
            </div>
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-amber-400/15 text-amber-500" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                </svg>
            </span>
        </div>

        <div v-else class="space-y-1.5">
            <StarRating :rating="0" size="md" />
            <p class="text-sm font-bold text-[color:var(--sf-text)]">
                هنوز امتیازی ثبت نشده
            </p>
            <p class="text-xs leading-6 text-[color:var(--sf-text-secondary)]">
                اولین نفر باشید و تجربه خود را ثبت کنید.
            </p>
        </div>

        <dl v-if="hasRating" class="mt-6 space-y-3">
            <div
                v-for="row in rows"
                :key="row.stars"
                class="flex items-center gap-3"
            >
                <dt class="flex w-14 shrink-0 items-center gap-1 text-[11px] font-bold tabular-nums text-[color:var(--sf-text-secondary)]">
                    <span>{{ faNum(row.stars) }}</span>
                    <svg class="h-3 w-3 text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                </dt>
                <dd class="flex h-2 flex-1 items-center overflow-hidden rounded-full bg-[color:var(--sf-surface-soft)]">
                    <span
                        class="h-full rounded-full bg-amber-400 transition-[width] duration-700 ease-out"
                        :class="ready ? '' : 'w-0'"
                        :style="{ width: ready ? row.percent + '%' : '0%' }"
                    />
                </dd>
                <dd class="w-10 shrink-0 text-left text-[11px] tabular-nums text-[color:var(--sf-text-muted)]">
                    {{ faNum(row.count) }}
                </dd>
            </div>
        </dl>
    </div>
</template>
