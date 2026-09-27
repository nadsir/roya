import { reactive, computed } from 'vue';
import axios from 'axios';
import { isLoggedIn } from './auth-state.js';

const INTENT_KEY = 'turbopart-comment-intent';

export function faNum(n) {
    return (Number(n) ?? 0).toLocaleString('fa-IR');
}

export function faDecimal(n) {
    const value = Number(n);
    if (!Number.isFinite(value)) return '—';
    return value.toLocaleString('fa-IR', {
        maximumFractionDigits: 1,
    });
}

export function formatRelativeTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';

    const diff = Date.now() - d.getTime();
    const minute = 60 * 1000;
    const hour = 60 * minute;
    const day = 24 * hour;

    if (diff < minute) return 'لحظاتی پیش';
    if (diff < hour) return `${Math.floor(diff / minute).toLocaleString('fa-IR')} دقیقه پیش`;
    if (diff < day) return `${Math.floor(diff / hour).toLocaleString('fa-IR')} ساعت پیش`;
    if (diff < 7 * day) return `${Math.floor(diff / day).toLocaleString('fa-IR')} روز پیش`;

    return new Intl.DateTimeFormat('fa-IR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(d);
}

function normalizeDistribution(dist) {
    const base = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
    for (const [key, value] of Object.entries(dist || {})) {
        const k = Number(key);
        if (k >= 1 && k <= 5) base[k] = Number(value) || 0;
    }
    return base;
}

function storeIntent(intent) {
    try {
        sessionStorage.setItem(INTENT_KEY, JSON.stringify(intent));
    } catch {}
}

function peekIntent() {
    try {
        const raw = sessionStorage.getItem(INTENT_KEY);
        if (!raw) return null;
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

function clearIntent() {
    try {
        sessionStorage.removeItem(INTENT_KEY);
    } catch {}
}

export function peekCommentIntentPath() {
    const intent = peekIntent();
    if (!intent) return null;
    if (typeof intent.path === 'string' && intent.path.startsWith('/')) {
        return intent.path;
    }
    return null;
}

export function createCommentSection(kind) {
    const section = reactive({
        kind,
        ownerId: null,

        items: [],
        isLoading: false,
        error: '',
        loadingMore: false,
        loadMoreError: '',
        page: 1,
        lastPage: 1,
        total: 0,

        ratingSummary: {
            average: null,
            count: 0,
            distribution: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 },
        },

        composerOpen: false,
        composer: {
            rating: 0,
            title: '',
            body: '',
        },
        composerSubmitting: false,
        composerFieldErrors: {},
        composerSuccess: null,

        replyOpenFor: null,
        replyBody: '',
        replySubmitting: false,
        replyFieldErrors: {},
        replySuccess: null,
    });

    const hasMore = computed(() => section.page < section.lastPage);

    function commentsUrl() {
        if (kind === 'product') return `/api/products/${section.ownerId}/comments`;
        return `/api/articles/${section.ownerId}/comments`;
    }

    function applyPage(data, replace) {
        section.items = replace
            ? (data.data || [])
            : section.items.concat(data.data || []);
        section.page = data.current_page || 1;
        section.lastPage = data.last_page || 1;
        section.total = data.total || 0;

        if (data.rating_summary) {
            section.ratingSummary = {
                average: data.rating_summary.average,
                count: data.rating_summary.count || 0,
                distribution: normalizeDistribution(data.rating_summary.distribution),
            };
        }
    }

    async function loadFirstPage() {
        if (!section.ownerId) return;
        section.isLoading = true;
        section.error = '';
        section.loadingMore = false;
        section.loadMoreError = '';
        try {
            const { data } = await axios.get(commentsUrl(), {
                params: { page: 1 },
            });
            applyPage(data, true);
        } catch (e) {
            section.error = e.response?.data?.message || 'امکان دریافت تجربه‌ها وجود نداشت.';
        } finally {
            section.isLoading = false;
        }
    }

    async function loadMore() {
        if (!hasMore.value || section.loadingMore || section.isLoading) return;
        section.loadingMore = true;
        section.loadMoreError = '';
        try {
            const { data } = await axios.get(commentsUrl(), {
                params: { page: section.page + 1 },
            });
            applyPage(data, false);
        } catch (e) {
            section.loadMoreError = e.response?.data?.message || 'دریافت تجربه‌های بیشتر انجام نشد.';
        } finally {
            section.loadingMore = false;
        }
    }

    function mapValidationErrors(errors) {
        const mapped = {};
        for (const [key, messages] of Object.entries(errors || {})) {
            mapped[key] = Array.isArray(messages) ? messages[0] : messages;
        }
        return mapped;
    }

    function openComposer() {
        if (!isLoggedIn.value) {
            storeIntent({
                type: 'compose',
                kind,
                ownerId: section.ownerId,
                path: window.location.pathname,
            });
            window.location.href = '/login';
            return;
        }
        section.composerOpen = true;
    }

    function closeComposer() {
        section.composerOpen = false;
        section.composerFieldErrors = {};
        section.composerSuccess = null;
        section.composer = { rating: 0, title: '', body: '' };
    }

    async function submitComposer() {
        if (section.composerSubmitting) return;
        const errors = {};
        const body = section.composer.body.trim();

        if (kind === 'product' && !section.composer.rating) {
            errors.rating = 'لطفاً امتیاز خود را انتخاب کنید.';
        }
        if (!body) {
            errors.body = 'لطفاً متن تجربه را بنویسید.';
        } else if (body.length < 2) {
            errors.body = 'متن تجربه خیلی کوتاه است.';
        }

        if (Object.keys(errors).length) {
            section.composerFieldErrors = errors;
            return;
        }

        section.composerSubmitting = true;
        section.composerFieldErrors = {};
        section.composerSuccess = null;

        try {
            const payload = {
                title: section.composer.title.trim() || null,
                body,
            };
            if (kind === 'product') payload.rating = Number(section.composer.rating);

            await axios.post(commentsUrl(), payload);
            section.composerSuccess = true;
        } catch (e) {
            if (e.response?.status === 401) {
                window.location.href = '/login';
                return;
            }
            if (e.response?.status === 422 && e.response.data?.errors) {
                section.composerFieldErrors = mapValidationErrors(e.response.data.errors);
            } else {
                section.composerFieldErrors = {
                    body: e.response?.data?.message || 'ثبت تجربه انجام نشد. دوباره تلاش کنید.',
                };
            }
        } finally {
            section.composerSubmitting = false;
        }
    }

    function openReply(comment) {
        if (!isLoggedIn.value) {
            storeIntent({
                type: 'reply',
                kind,
                ownerId: section.ownerId,
                commentId: comment.id,
                path: window.location.pathname,
            });
            window.location.href = '/login';
            return;
        }
        section.replyOpenFor = comment.id;
        section.replyBody = '';
        section.replyFieldErrors = {};
        section.replySuccess = null;
    }

    function closeReply() {
        section.replyOpenFor = null;
        section.replyBody = '';
        section.replyFieldErrors = {};
        section.replySuccess = null;
    }

    async function submitReply() {
        if (!section.replyOpenFor || section.replySubmitting) return;
        const body = section.replyBody.trim();
        const errors = {};

        if (!body) {
            errors.body = 'لطفاً متن پاسخ را بنویسید.';
        } else if (body.length < 2) {
            errors.body = 'متن پاسخ خیلی کوتاه است.';
        }

        if (Object.keys(errors).length) {
            section.replyFieldErrors = errors;
            return;
        }

        section.replySubmitting = true;
        section.replyFieldErrors = {};
        section.replySuccess = null;

        try {
            await axios.post(`/api/comments/${section.replyOpenFor}/replies`, {
                title: null,
                body,
            });
            section.replySuccess = { commentId: section.replyOpenFor, ok: true };
        } catch (e) {
            if (e.response?.status === 401) {
                window.location.href = '/login';
                return;
            }
            if (e.response?.status === 422 && e.response.data?.errors) {
                section.replyFieldErrors = mapValidationErrors(e.response.data.errors);
            } else {
                section.replyFieldErrors = {
                    body: e.response?.data?.message || 'ثبت پاسخ انجام نشد. دوباره تلاش کنید.',
                };
            }
        } finally {
            section.replySubmitting = false;
        }
    }

    function restoreIntent() {
        if (!isLoggedIn.value) return;
        const intent = peekIntent();
        if (!intent) return;
        if (intent.kind !== kind) return;
        if (intent.ownerId && intent.ownerId !== section.ownerId) return;
        clearIntent();

        if (intent.type === 'reply' && intent.commentId) {
            const exists = section.items.some((item) => item.id === intent.commentId);
            if (exists) {
                section.replyOpenFor = intent.commentId;
            }
        } else {
            section.composerOpen = true;
        }
    }

    section.openComposer = openComposer;
    section.closeComposer = closeComposer;
    section.submitComposer = submitComposer;
    section.openReply = openReply;
    section.closeReply = closeReply;
    section.submitReply = submitReply;
    section.loadFirstPage = loadFirstPage;
    section.loadMore = loadMore;

    return {
        section,
        hasMore,
        loadFirstPage,
        loadMore,
        openComposer,
        closeComposer,
        submitComposer,
        openReply,
        closeReply,
        submitReply,
        restoreIntent,
    };
}