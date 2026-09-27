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

watch(hasRating, (v) => {
    if (v) {
        ready.value = true;
    }
});
</script>

<template>
    <div>
        <div v-if="hasRating" class="flex items-center gap-4">
            <span class="serif text-5xl leading-none text-ink dark:text-slate-50">
                {{ faDecimal(average) }}
            </span>
            <div class="space-y-1.5">
                <StarRating :rating="Number(average)" size="md" />
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    از روی {{ faNum(total) }} امتیاز ثبت‌شده
                </p>
            </div>
        </div>
        <div v-else class="space-y-1">
            <StarRating :rating="0" size="md" />
            <p class="text-sm text-slate-500 dark:text-slate-400">
                هنوز امتیازی ثبت نشده؛ اولین نفر باشید.
            </p>
        </div>

        <dl v-if="hasRating" class="mt-6 space-y-2.5">
            <div
                v-for="row in rows"
                :key="row.stars"
                class="flex items-center gap-3"
            >
                <dt class="w-5 shrink-0 text-left text-[11px] font-bold tabular-nums text-slate-500 dark:text-slate-400">
                    {{ faNum(row.stars) }}
                </dt>
                <dd class="flex h-1.5 flex-1 items-center overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                    <span
                        class="h-full rounded-full bg-brand-accent transition-[width] duration-500 ease-out"
                        :class="ready ? '' : 'w-0'"
                        :style="{ width: ready ? row.percent + '%' : '0%' }"
                    />
                </dd>
                <dd class="w-8 shrink-0 text-left text-[11px] tabular-nums text-slate-400 dark:text-slate-500">
                    {{ faNum(row.count) }}
                </dd>
            </div>
        </dl>
    </div>
</template>