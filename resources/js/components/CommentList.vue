<script setup>
import { computed } from 'vue';
import CommentThread from './CommentThread.vue';
import { faNum } from '../comment-state.js';

const props = defineProps({
    section: { type: Object, required: true },
    kind: { type: String, default: 'product' },
});

const hasMore = computed(() => props.section.page < props.section.lastPage);

const isProduct = props.kind === 'product';

const emptyTitle = isProduct
    ? 'اولین تجربه را شما ثبت کنید'
    : 'اولین نظر را شما بنویسید';

const emptyHint = isProduct
    ? 'هنوز کسی تجربه‌ای درباره این محصول ثبت نکرده است. نگاه شما برای خریداران دیگر ارزشمند است.'
    : 'هنوز گفتگویی درباره این مقاله شکل نگرفته است. دیدگاه شما می‌تواند گفتگو را آغاز کند.';
</script>

<template>
    <div>
        <div v-if="section.error && !section.items.length" class="py-10 text-center">
            <svg class="mx-auto h-9 w-9 text-[color:var(--sf-text-muted)] opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                <path d="M12 3a9 9 0 109 9M21 3l-9 9M21 3v6M21 3h-6" />
            </svg>
            <p class="mt-4 text-sm font-bold text-[color:var(--sf-text)]">
                امکان دریافت اطلاعات وجود نداشت.
            </p>
            <p class="mt-1 text-xs text-[color:var(--sf-text-secondary)]">
                {{ section.error }}
            </p>
            <button
                type="button"
                class="mt-5 rounded-md border border-[color:var(--sf-border-strong)] px-5 py-2.5 text-xs font-bold text-[color:var(--sf-text)] transition hover:border-[color:var(--sf-text)]"
                @click="section.loadFirstPage()"
            >
                تلاش دوباره
            </button>
        </div>

        <div v-else-if="!section.isLoading && !section.items.length" class="border-t border-[color:var(--sf-border)] py-10 text-center">
            <p class="text-sm font-black text-[color:var(--sf-text)]">
                {{ emptyTitle }}
            </p>
            <p class="mx-auto mt-2 max-w-xs text-xs leading-6 text-[color:var(--sf-text-secondary)]">
                {{ emptyHint }}
            </p>
        </div>

        <div v-else-if="section.isLoading && !section.items.length" class="border-t border-[color:var(--sf-border)]" aria-hidden="true">
            <div
                v-for="i in 3"
                :key="i"
                class="animate-pulse border-b border-[color:var(--sf-border)] py-8"
                :class="i > 1 ? 'border-t border-[color:var(--sf-border)]' : ''"
            >
                <div class="flex items-center gap-3">
                    <span class="h-9 w-9 rounded-full bg-[color:var(--sf-surface-soft)]" />
                    <div class="space-y-2">
                        <span class="block h-3 w-24 rounded bg-[color:var(--sf-surface-soft)]" />
                        <span class="block h-2 w-14 rounded bg-[color:var(--sf-border)]" />
                    </div>
                </div>
                <div class="ms-12 mt-4 space-y-2.5">
                    <span class="block h-3 w-1/2 rounded bg-[color:var(--sf-surface-soft)]" />
                    <span class="block h-3 w-2/3 rounded bg-[color:var(--sf-surface-soft)]" />
                    <span class="block h-3 w-1/3 rounded bg-[color:var(--sf-border)]" />
                </div>
            </div>
        </div>

        <template v-else>
            <ul :class="isProduct ? 'space-y-4' : ''">
                <li
                    v-for="(comment, i) in section.items"
                    :key="comment.id"
                    :class="isProduct
                        ? 'rounded-xl border border-[color:var(--sf-border)] bg-[color:var(--sf-surface)] px-4 sm:px-5'
                        : 'border-t border-[color:var(--sf-border)]'"
                >
                    <CommentThread
                        :comment="comment"
                        :kind="kind"
                        :index="i + 1"
                        :section="section"
                    />
                </li>
            </ul>

            <p
                v-if="section.total"
                class="mt-4 border-t border-[color:var(--sf-border)] pt-4 text-[11px] font-bold text-[color:var(--sf-text-muted)]"
            >
                {{
                    isProduct
                        ? `${faNum(section.total)} تجربه ثبت شده است`
                        : `${faNum(section.total)} نظر ثبت شده است`
                }}
            </p>

            <div v-if="hasMore" class="py-8 text-center">
                <p
                    v-if="section.loadMoreError"
                    role="alert"
                    class="mx-auto mb-4 flex max-w-sm flex-col items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-600 sm:flex-row dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01" />
                        </svg>
                        {{ section.loadMoreError }}
                    </span>
                    <button
                        type="button"
                        class="rounded-md border border-red-200 px-3 py-1.5 text-[11px] font-bold text-red-700 transition hover:border-red-300 hover:bg-red-100 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/20"
                        @click="section.loadMore()"
                    >
                        تلاش دوباره
                    </button>
                </p>
                <button
                    type="button"
                    :disabled="section.loadingMore"
                    class="inline-flex items-center gap-2 rounded-md border border-[color:var(--sf-text)] px-6 py-3 text-sm font-bold text-[color:var(--sf-text)] transition hover:bg-[color:var(--sf-surface-soft)] disabled:cursor-not-allowed disabled:opacity-50"
                    @click="section.loadMore()"
                >
                    <svg
                        v-if="section.loadingMore"
                        class="h-4 w-4 animate-spin"
                        viewBox="0 0 24 24"
                        fill="none"
                        aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    {{
                        section.loadingMore
                            ? 'در حال دریافت…'
                            : isProduct
                                ? 'مشاهده تجربه‌های بیشتر'
                                : 'مشاهده نظرات بیشتر'
                    }}
                </button>
            </div>
        </template>
    </div>
</template>
