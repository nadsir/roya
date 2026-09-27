<script setup>
import { computed } from 'vue';
import CommentItem from './CommentItem.vue';

const props = defineProps({
    comment: { type: Object, required: true },
    kind: { type: String, default: 'product' },
    index: { type: Number, default: null },
    section: { type: Object, required: true },
});

const replyId = computed(() => `reply-body-${props.comment.id}`);
</script>

<template>
    <div>
        <CommentItem
            :comment="comment"
            :kind="kind"
            :index="index"
        />

        <div class="ms-6 border-s border-slate-200 ps-5 sm:ms-9 sm:ps-7 dark:border-slate-700">
            <ul v-if="comment.replies && comment.replies.length">
                <li
                    v-for="(reply, i) in comment.replies"
                    :key="reply.id"
                    :class="{
                        'border-t border-slate-200 dark:border-slate-700': i > 0,
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
                    class="ms-2 rounded font-bold text-slate-400 underline decoration-slate-300 underline-offset-4 hover:text-slate-600 dark:text-slate-500 dark:decoration-slate-600"
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
                    class="w-full resize-y rounded-md border border-slate-300 bg-white px-4 py-3 text-sm leading-6 text-ink placeholder:text-slate-400 focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/40 dark:border-slate-600 dark:bg-black/30 dark:text-slate-100 dark:placeholder:text-slate-500"
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
                        class="rounded px-3 py-2 text-xs font-bold text-slate-500 transition hover:text-ink dark:text-slate-400 dark:hover:text-slate-100"
                        @click="section.closeReply()"
                    >
                        انصراف
                    </button>
                    <button
                        type="submit"
                        :disabled="section.replySubmitting"
                        class="inline-flex items-center gap-2 rounded-md bg-ink px-4 py-2 text-xs font-bold text-cream transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-ink"
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

            <button
                v-else
                type="button"
                class="mt-2 inline-flex items-center gap-2 rounded px-3 py-2 text-xs font-bold text-slate-500 transition hover:text-brand-accent dark:text-slate-400"
                @click="section.openReply(comment)"
            >
                <svg class="h-4 w-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                </svg>
                پاسخ به این تجربه
            </button>
        </div>
    </div>
</template>