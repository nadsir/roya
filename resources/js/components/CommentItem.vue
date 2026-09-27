<script setup>
import { computed } from 'vue';
import { faNum, formatRelativeTime } from '../comment-state.js';
import StarRating from './StarRating.vue';

const props = defineProps({
    comment: { type: Object, required: true },
    kind: { type: String, default: 'product' },
    index: { type: Number, default: null },
    isReply: { type: Boolean, default: false },
});

const isProduct = props.kind === 'product';

const initial = computed(() => {
    const name = (props.comment.user && props.comment.user.name) || '';
    return name.trim().charAt(0) || '—';
});

const displayName = computed(() => {
    const name = (props.comment.user && props.comment.user.name) || '';
    if (!name) return 'کاربر مهمان';
    return name;
});

const date = computed(() => formatRelativeTime(props.comment.created_at));

const indexBadge = computed(() => {
    if (props.index == null) return null;
    return String(props.index).padStart(2, '0');
});
</script>

<template>
    <article
        :class="[
            'group',
            isReply
                ? 'py-5'
                : 'py-6 sm:py-8',
        ]"
        :aria-label="isReply ? 'پاسخ' : 'تجربه'"
    >
        <div class="relative">
            <span
                v-if="indexBadge"
                class="pointer-events-none absolute -top-1 end-0 serif text-5xl leading-none text-slate-100 transition-colors group-hover:text-brand-accent/30 dark:text-white/[0.04] dark:group-hover:text-brand-accent/20"
                :aria-hidden="true"
            >
                {{ indexBadge }}
            </span>

            <div class="flex items-center gap-3">
                <span
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-slate-200 bg-white text-[12px] font-black text-ink dark:border-slate-600 dark:bg-black/40 dark:text-slate-100"
                    :aria-hidden="true"
                >
                    {{ initial }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-ink dark:text-slate-100">
                        {{ displayName }}
                    </p>
                    <p
                        class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500"
                        :title="new Date(comment.created_at).toLocaleString('fa-IR')"
                    >
                        {{ date }}
                    </p>
                </div>
            </div>

            <div class="mt-3.5 ms-12">
                <StarRating
                    v-if="isProduct && !isReply && comment.rating"
                    :rating="comment.rating"
                    size="sm"
                    class="mb-2"
                />

                <h4
                    v-if="comment.title"
                    class="text-[15px] font-black leading-snug text-ink dark:text-slate-50"
                >
                    {{ comment.title }}
                </h4>

                <p class="mt-1.5 text-sm leading-8 text-slate-600 dark:text-slate-300">
                    {{ comment.body }}
                </p>
            </div>
        </div>
    </article>
</template>