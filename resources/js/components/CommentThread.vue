<script setup>
import { computed } from 'vue';
import CommentItem from './CommentItem.vue';
import { faNum } from '../comment-state.js';

const props = defineProps({
    comment: { type: Object, required: true },
    kind: { type: String, default: 'product' },
    index: { type: Number, default: null },
    section: { type: Object, required: true },
});

const replyId = computed(() => `reply-body-${props.comment.id}`);
const helpfulBusy = computed(() => Boolean(props.section.helpfulBusy?.[props.comment.id]));
const showReplyThread = computed(() => Boolean(
    (props.comment.replies && props.comment.replies.length)
    || props.section.replySuccess?.commentId === props.comment.id
    || props.section.replyOpenFor === props.comment.id,
));
</script>

<template>
    <div>
        <CommentItem
            :comment="comment"
            :kind="kind"
            :index="index"
        />

        <div
            v-if="kind === 'product'"
            class="mt-1 flex flex-wrap items-center gap-2 border-t border-[color:var(--sf-border)] pt-2 pb-2 sm:ms-12"
        >
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11px] font-bold transition disabled:cursor-not-allowed disabled:opacity-60"
                :class="comment.is_helpful
                    ? 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300'
                    : 'border-[color:var(--sf-border)] text-[color:var(--sf-text-secondary)] hover:border-amber-300 hover:text-amber-600 dark:hover:border-amber-500/50 dark:hover:text-amber-300'"
                :aria-pressed="Boolean(comment.is_helpful)"
                :disabled="helpfulBusy"
                @click="section.toggleHelpful(comment)"
            >
                <i
                    :class="helpfulBusy
                        ? 'fa-solid fa-spinner fa-spin'
                        : comment.is_helpful
                            ? 'fa-solid fa-thumbs-up'
                            : 'fa-regular fa-thumbs-up'"
                    aria-hidden="true"
                />
                <span>مفید بود</span>
                <span v-if="comment.helpful_count" class="tabular-nums">
                    ({{ faNum(comment.helpful_count) }})
                </span>
            </button>

            <button
                v-if="section.replyOpenFor !== comment.id"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border border-[color:var(--sf-border)] px-3 py-1.5 text-[11px] font-bold text-[color:var(--sf-text-secondary)] transition hover:border-amber-300 hover:text-amber-600 dark:hover:border-amber-500/50 dark:hover:text-amber-300"
                @click="section.openReply(comment)"
            >
                <i class="fa-regular fa-comment-dots" aria-hidden="true" />
                پاسخ
            </button>
        </div>

        <div
            v-else
            class="mt-1 flex items-center pb-2 sm:ms-12"
        >
            <button
                v-if="section.replyOpenFor !== comment.id"
                type="button"
                class="inline-flex items-center gap-2 rounded px-3 py-2 text-xs font-bold text-[color:var(--sf-text-secondary)] transition hover:text-[color:var(--sf-text)] dark:hover:text-[color:var(--sf-text)]"
                @click="section.openReply(comment)"
            >
                <i class="fa-regular fa-comment-dots" aria-hidden="true" />
                پاسخ به این تجربه
            </button>
        </div>

        <div
            v-if="showReplyThread"
            class="ms-6 border-s border-[color:var(--sf-border)] ps-5 sm:ms-9 sm:ps-7"
        >
            <ul v-if="comment.replies && comment.replies.length">
                <li
                    v-for="(reply, i) in comment.replies"
                    :key="reply.id"
                    :class="{
                        'border-t border-[color:var(--sf-border)]': i > 0,
                    }"
                >
                    <CommentItem
                        :comment="reply"
                        :kind="kind"
                        :is-reply="true"
                    />
                </li>
            </ul>

            <p
                v-if="section.replySuccess && section.replySuccess.commentId === comment.id"
                class="flex items-center gap-2 py-4 text-xs font-bold text-emerald-700 dark:text-emerald-400"
            >
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6L9 17l-5-5" />
                </svg>
                پاسخ شما ثبت شد؛ پس از بررسی تیم ما نمایش داده خواهد شد.
                <button
                    type="button"
                    class="ms-2 rounded font-bold text-[color:var(--sf-text-muted)] underline decoration-[color:var(--sf-border-strong)] underline-offset-4 hover:text-[color:var(--sf-text)]"
                    @click="section.closeReply()"
                >
                    بستن
                </button>
            </p>

            <form
                v-else-if="section.replyOpenFor === comment.id"
                class="space-y-3 py-4"
                @submit.prevent="section.submitReply()"
            >
                <textarea
                    :id="replyId"
                    v-model="section.replyBody"
                    :rows="3"
                    maxlength="1000"
                    placeholder="پاسخ خود را بنویسید…"
                    class="w-full resize-y rounded-xl border border-[color:var(--sf-border-strong)] bg-[color:var(--sf-surface)] px-4 py-3 text-sm leading-6 text-[color:var(--sf-text)] placeholder:text-[color:var(--sf-text-muted)] focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/40"
                />
                <p
                    v-if="section.replyFieldErrors.body"
                    class="text-xs font-bold text-red-500"
                >
                    {{ section.replyFieldErrors.body }}
                </p>
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded px-3 py-2 text-xs font-bold text-[color:var(--sf-text-secondary)] transition hover:text-[color:var(--sf-text)]"
                        @click="section.closeReply()"
                    >
                        انصراف
                    </button>
                    <button
                        type="submit"
                        :disabled="section.replySubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2 text-xs font-black text-[#1a1a1a] transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg
                            v-if="section.replySubmitting"
                            class="h-3.5 w-3.5 animate-spin"
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        {{ section.replySubmitting ? 'در حال ارسال…' : 'ارسال پاسخ' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
