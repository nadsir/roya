<script setup>
import {
    adminComments,
    adminCommentLoading,
    adminCommentError,
    adminCommentSearch,
    adminCommentStatusFilter,
    adminCommentTypeFilter,
    adminCommentPage,
    adminCommentTotalPages,
    adminCommentTotal,
    adminCommentBusy,
    loadAdminComments,
    updateAdminCommentStatus,
    deleteAdminComment,
} from './admin-state.js';

import {
    showAdminNotification,
    hideAdminNotification,
} from './admin-state.js';

import { onMounted, watch, computed, ref, onUnmounted } from 'vue';

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fa-IR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString('fa-IR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusOptions = [
    { value: '', label: 'همه وضعیت‌ها' },
    { value: 'pending', label: 'در انتظار بررسی' },
    { value: 'approved', label: 'تأیید شده' },
    { value: 'rejected', label: 'رد شده' },
];

const typeOptions = [
    { value: '', label: 'همه محتوا' },
    { value: 'product', label: 'محصول' },
    { value: 'article', label: 'مقاله' },
];

const summaryTabs = [
    { value: '', label: 'همه' },
    { value: 'pending', label: 'در انتظار بررسی' },
    { value: 'approved', label: 'تأیید شده' },
    { value: 'rejected', label: 'رد شده' },
];

const actionOptions = {
    pending: [
        { status: 'approved', label: 'تأیید', cls: 'action-approve' },
        { status: 'rejected', label: 'رد', cls: 'action-reject' },
    ],
    approved: [
        { status: 'rejected', label: 'رد', cls: 'action-reject' },
    ],
    rejected: [
        { status: 'approved', label: 'تأیید', cls: 'action-approve' },
    ],
};

const hasActiveFilters = computed(() => {
    return (
        adminCommentSearch.value ||
        adminCommentStatusFilter.value ||
        adminCommentTypeFilter.value
    );
});

const summaryCounts = computed(() => {
    const counts = { pending: 0, approved: 0, rejected: 0 };
    for (const comment of adminComments.value) {
        if (counts[comment.status] !== undefined) {
            counts[comment.status] += 1;
        }
    }
    return counts;
});

const localSearch = ref(adminCommentSearch.value);

let searchTimer = null;

watch(localSearch, (val) => {
    adminCommentSearch.value = val;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        adminCommentPage.value = 1;
        loadAdminComments();
    }, 300);
});

watch(() => adminCommentStatusFilter.value, () => {
    adminCommentPage.value = 1;
    loadAdminComments();
});

watch(() => adminCommentTypeFilter.value, () => {
    adminCommentPage.value = 1;
    loadAdminComments();
});

watch(() => adminCommentPage.value, () => {
    loadAdminComments();
});

onMounted(() => {
    loadAdminComments();
    document.addEventListener('keydown', onModalKeydown);
});

onUnmounted(() => {
    clearTimeout(searchTimer);
    document.removeEventListener('keydown', onModalKeydown);
});

function goToPage(page) {
    if (page < 1 || page > adminCommentTotalPages.value) return;
    adminCommentPage.value = page;
}

function clearFilters() {
    localSearch.value = '';
    adminCommentSearch.value = '';
    adminCommentStatusFilter.value = '';
    adminCommentTypeFilter.value = '';
    adminCommentPage.value = 1;
    loadAdminComments();
}

function setSummaryTab(value) {
    if (adminCommentStatusFilter.value === value) return;
    adminCommentStatusFilter.value = value;
    adminCommentPage.value = 1;
    loadAdminComments();
}

function isReply(comment) {
    return comment.parent_id !== null && comment.parent_id !== undefined;
}

function getCommentableKind(comment) {
    const type = comment.commentable_type || '';
    if (type.endsWith('Product')) return 'product';
    if (type.endsWith('Article')) return 'article';
    return type;
}

function getCommentableLabel(comment) {
    return getCommentableKind(comment) === 'product' ? 'محصول' : 'مقاله';
}

function getCommentableClass(comment) {
    return getCommentableKind(comment) === 'product' ? 'type-product' : 'type-article';
}

function getStatusLabel(status) {
    switch (status) {
        case 'pending': return 'در انتظار بررسی';
        case 'approved': return 'تأیید شده';
        case 'rejected': return 'رد شده';
        default: return status || '—';
    }
}

function getStatusClass(status) {
    switch (status) {
        case 'pending': return 'status-pending';
        case 'approved': return 'status-approved';
        case 'rejected': return 'status-rejected';
        default: return '';
    }
}

function isBusy(comment) {
    return !!adminCommentBusy.value[comment.id];
}

function renderRating(comment) {
    if (comment.rating === null || comment.rating === undefined) {
        return [];
    }
    const full = Math.max(0, Math.min(5, Number(comment.rating) || 0));
    return Array.from({ length: 5 }, (_, i) => i < full);
}

async function changeCommentStatus(comment, newStatus) {
    if (isBusy(comment)) return;
    try {
        await updateAdminCommentStatus(comment.id, newStatus);
        comment.status = newStatus;
        showAdminNotification('success', 'موفقیت', 'وضعیت نظر با موفقیت به‌روزرسانی شد.');
    } catch (error) {
        const message = error.response?.data?.message || error.message || 'تغییر وضعیت انجام نشد.';
        showAdminNotification('error', 'خطا', message);
    }
}

async function removeComment(comment) {
    if (isBusy(comment)) return;
    if (!confirm(`نظر #${comment.id} حذف شود؟`)) return;

    try {
        await deleteAdminComment(comment.id);
        adminComments.value = adminComments.value.filter(
            (item) => item.id !== comment.id
        );
        showAdminNotification('success', 'موفقیت', 'نظر با موفقیت حذف شد.');

        if (!adminComments.value.length && adminCommentPage.value > 1) {
            adminCommentPage.value -= 1;
            loadAdminComments();
        }
    } catch (error) {
        const message = error.response?.data?.message || error.message || 'حذف نظر انجام نشد.';
        showAdminNotification('error', 'خطا', message);
    }
}

const selectedComment = ref(null);
const selectedCommentBusy = ref(false);

function openDetail(comment) {
    selectedComment.value = comment;
    selectedCommentBusy.value = false;
}

function closeDetail() {
    selectedComment.value = null;
    selectedCommentBusy.value = false;
}

function onModalKeydown(e) {
    if (e.key === 'Escape' && selectedComment.value) {
        closeDetail();
    }
}

async function changeSelectedStatus(newStatus) {
    const comment = selectedComment.value;
    if (!comment || selectedCommentBusy.value) return;
    selectedCommentBusy.value = true;
    try {
        await updateAdminCommentStatus(comment.id, newStatus);
        comment.status = newStatus;
        selectedCommentBusy.value = false;
        showAdminNotification('success', 'موفقیت', 'وضعیت نظر با موفقیت به‌روزرسانی شد.');
    } catch (error) {
        selectedCommentBusy.value = false;
        const message = error.response?.data?.message || error.message || 'تغییر وضعیت انجام نشد.';
        showAdminNotification('error', 'خطا', message);
    }
}
</script>

<template>
    <section class="comments-section">
        <!-- Header -->
        <header class="comments-header">
            <div class="header-left">
                <h1 class="comments-title">نظرات کاربران</h1>
                <p class="comments-subtitle">مدیریت، بررسی و انتشار نظرات کاربران</p>
            </div>
            <div class="header-right">
                <span class="comments-count" v-if="adminCommentTotal > 0 && !adminCommentLoading">
                    {{ adminCommentTotal.toLocaleString('fa-IR') }} نظر
                </span>
                <button type="button" class="btn-refresh" @click="loadAdminComments" :disabled="adminCommentLoading">
                    <span v-if="adminCommentLoading" class="btn-spinner"></span>
                    <span v-else>↻</span>
                    بروزرسانی
                </button>
            </div>
        </header>

        <!-- Summary Tabs -->
        <div class="comments-summary">
            <button
                v-for="tab in summaryTabs"
                :key="tab.value"
                type="button"
                class="summary-tab"
                :class="{
                    active: adminCommentStatusFilter === tab.value,
                }"
                @click="setSummaryTab(tab.value)"
            >
                <span class="summary-tab-label">{{ tab.label }}</span>
                <span
                    class="summary-tab-count"
                    :class="tab.value ? 'count-' + tab.value : 'count-all'"
                >
                    {{
                        (
                            tab.value === ''
                                ? adminCommentTotal
                                : summaryCounts[tab.value]
                        ).toLocaleString('fa-IR')
                    }}
                </span>
            </button>
        </div>

        <!-- Filter Toolbar -->
        <div class="comments-toolbar">
            <div class="toolbar-main">
                <div class="toolbar-search-wrap">
                    <span class="search-icon">🔍</span>
                    <input
                        type="text"
                        class="search-input"
                        placeholder="جستجوی عنوان یا متن نظر..."
                        v-model="localSearch"
                    >
                    <button
                        v-if="localSearch"
                        type="button"
                        class="search-clear-btn"
                        @click="localSearch = ''"
                    >✕</button>
                </div>
                <div class="toolbar-filters">
                    <select v-model="adminCommentTypeFilter" class="filter-select">
                        <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <select v-model="adminCommentStatusFilter" class="filter-select">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
            </div>
            <div class="toolbar-actions">
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="btn-clear-filters"
                    @click="clearFilters"
                >
                    پاک کردن فیلترها
                </button>
            </div>
        </div>

        <!-- States -->
        <div v-if="adminCommentLoading && !adminComments.length" class="comments-state loading-state">
            <div class="spinner small-spinner"></div>
            <span>در حال بارگذاری نظرات...</span>
        </div>

        <div v-else-if="adminCommentError" class="comments-state alert error">
            {{ adminCommentError }}
            <button type="button" class="btn-retry" @click="loadAdminComments">تلاش دوباره</button>
        </div>

        <div v-else-if="!adminComments.length" class="comments-state empty-state">
            <div class="empty-icon">◎</div>
            <h3 v-if="hasActiveFilters">نظری یافت نشد</h3>
            <h3 v-else>نظری برای نمایش وجود ندارد.</h3>
            <p v-if="hasActiveFilters" class="empty-hint">فیلترهای فعلی نتیجه‌ای نداشت</p>
            <button v-if="hasActiveFilters" type="button" class="btn-clear-filters" @click="clearFilters">
                پاک کردن فیلترها
            </button>
        </div>

        <!-- Desktop Table & Mobile Cards -->
        <div v-else class="comments-content">
            <!-- Desktop Table -->
            <div class="comments-table-container">
                <table class="comments-table">
                    <thead>
                        <tr>
                            <th class="th-id">شناسه</th>
                            <th class="th-user">کاربر</th>
                            <th class="th-comment">نظر</th>
                            <th class="th-rating">امتیاز</th>
                            <th class="th-content">محتوا</th>
                            <th class="th-status">وضعیت</th>
                            <th class="th-date">تاریخ</th>
                            <th class="th-action">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="comment in adminComments" :key="comment.id" class="comment-row">
                            <td>
                                <div class="comment-meta-top">
                                    <span class="comment-id" dir="ltr">#{{ comment.id }}</span>
                                    <span
                                        v-if="isReply(comment)"
                                        class="reply-badge"
                                        title="پاسخ به نظر دیگر"
                                    >پاسخ</span>
                                </div>
                            </td>
                            <td class="user-name-cell">
                                <div class="user-name">{{ comment.user?.name || '—' }}</div>
                            </td>
                            <td class="comment-text-cell">
                                <div class="comment-title" v-if="comment.title">{{ comment.title }}</div>
                                <div class="comment-body">{{ comment.body || '—' }}</div>
                            </td>
                            <td>
                                <span
                                    v-if="getCommentableKind(comment) === 'product' && comment.rating !== null && comment.rating !== undefined"
                                    class="rating-stars"
                                    dir="ltr"
                                >
                                    <span
                                        v-for="(filled, index) in renderRating(comment)"
                                        :key="index"
                                        class="rating-star"
                                        :class="{ filled }"
                                    >{{ filled ? '★' : '☆' }}</span>
                                </span>
                                <span v-else class="rating-none">—</span>
                            </td>
                            <td>
                                <div class="content-type-cell">
                                    <span class="type-badge" :class="getCommentableClass(comment)">
                                        {{ getCommentableLabel(comment) }}
                                    </span>
                                    <span class="content-id" dir="ltr">#{{ comment.commentable_id }}</span>
                                </div>
                                <span v-if="isReply(comment)" class="parent-id" dir="ltr">
                                    پاسخ به #{{ comment.parent_id }}
                                </span>
                            </td>
                            <td>
                                <span class="status-badge" :class="getStatusClass(comment.status)">
                                    {{ getStatusLabel(comment.status) }}
                                </span>
                            </td>
                            <td>
                                <span class="date-cell">{{ formatDate(comment.created_at) }}</span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button
                                        type="button"
                                        class="btn-view"
                                        @click="openDetail(comment)"
                                    >
                                        مشاهده
                                    </button>
                                    <button
                                        v-for="action in actionOptions[comment.status] || []"
                                        :key="action.status"
                                        type="button"
                                        class="btn-moderation"
                                        :class="action.cls"
                                        :disabled="isBusy(comment)"
                                        @click="changeCommentStatus(comment, action.status)"
                                    >
                                        <span v-if="isBusy(comment)" class="btn-spinner"></span>
                                        <span v-else>{{ action.label }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-moderation action-delete"
                                        :disabled="isBusy(comment)"
                                        @click="removeComment(comment)"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="comments-mobile-list">
                <div v-for="comment in adminComments" :key="comment.id" class="comment-card">
                    <div class="comment-card-header">
                        <div class="comment-meta-top">
                            <span class="comment-id" dir="ltr">#{{ comment.id }}</span>
                            <span v-if="isReply(comment)" class="reply-badge">پاسخ</span>
                        </div>
                        <span class="status-badge status-sm" :class="getStatusClass(comment.status)">
                            {{ getStatusLabel(comment.status) }}
                        </span>
                    </div>
                    <div class="comment-card-body">
                        <div class="comment-card-row">
                            <span class="card-label">کاربر</span>
                            <span class="card-value">{{ comment.user?.name || '—' }}</span>
                        </div>
                        <div class="comment-card-row">
                            <span class="card-label">عنوان</span>
                            <span class="card-value">{{ comment.title || '—' }}</span>
                        </div>
                        <div class="comment-card-row">
                            <span class="card-label">متن</span>
                            <span class="card-value card-body-text">{{ comment.body || '—' }}</span>
                        </div>
                        <div class="comment-card-row">
                            <span class="card-label">امتیاز</span>
                            <span class="card-value">
                                <span
                                    v-if="getCommentableKind(comment) === 'product' && comment.rating !== null && comment.rating !== undefined"
                                    class="rating-stars"
                                    dir="ltr"
                                >
                                    <span
                                        v-for="(filled, index) in renderRating(comment)"
                                        :key="index"
                                        class="rating-star"
                                        :class="{ filled }"
                                    >{{ filled ? '★' : '☆' }}</span>
                                </span>
                                <span v-else>—</span>
                            </span>
                        </div>
                        <div class="comment-card-row">
                            <span class="card-label">محتوا</span>
                            <span class="card-value">
                                <span class="type-badge" :class="getCommentableClass(comment)">
                                    {{ getCommentableLabel(comment) }}
                                </span>
                                <span class="content-id" dir="ltr">#{{ comment.commentable_id }}</span>
                            </span>
                        </div>
                        <div class="comment-card-row">
                            <span class="card-label">تاریخ</span>
                            <span class="card-value date-cell">{{ formatDate(comment.created_at) }}</span>
                        </div>
                    </div>
                    <div class="comment-card-footer">
                        <button
                            type="button"
                            class="btn-view btn-view-full"
                            @click="openDetail(comment)"
                        >
                            مشاهده
                        </button>
                        <div class="card-action-row">
                            <button
                                v-for="action in actionOptions[comment.status] || []"
                                :key="action.status"
                                type="button"
                                class="btn-moderation btn-moderation-full"
                                :class="action.cls"
                                :disabled="isBusy(comment)"
                                @click="changeCommentStatus(comment, action.status)"
                            >
                                <span v-if="isBusy(comment)" class="btn-spinner"></span>
                                <span v-else>{{ action.label }}</span>
                            </button>
                            <button
                                type="button"
                                class="btn-moderation btn-moderation-full action-delete"
                                :disabled="isBusy(comment)"
                                @click="removeComment(comment)"
                            >
                                حذف
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="adminCommentTotalPages > 1" class="comments-pagination">
                <span class="pagination-info">
                    صفحه {{ adminCommentPage.toLocaleString('fa-IR') }} از {{ adminCommentTotalPages.toLocaleString('fa-IR') }}
                </span>
                <div class="pagination-buttons">
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="adminCommentPage <= 1"
                        @click="goToPage(adminCommentPage - 1)"
                    >
                        ◀ قبلی
                    </button>
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="adminCommentPage >= adminCommentTotalPages"
                        @click="goToPage(adminCommentPage + 1)"
                    >
                        بعدی ▶
                    </button>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <Teleport to="body">
            <Transition name="modal-fade">
                <div
                    v-if="selectedComment"
                    class="modal-overlay"
                    @click.self="closeDetail"
                >
                    <div class="modal-panel" role="dialog" aria-modal="true">
                        <div class="modal-header">
                            <div class="modal-title-wrap">
                                <h2 class="modal-title">جزئیات نظر</h2>
                                <span class="modal-id" dir="ltr">#{{ selectedComment.id }}</span>
                            </div>
                            <button
                                type="button"
                                class="modal-close"
                                @click="closeDetail"
                                aria-label="بستن"
                            >✕</button>
                        </div>

                        <div class="modal-body">
                            <div class="detail-block">
                                <div class="detail-item">
                                    <span class="detail-label">کاربر</span>
                                    <span class="detail-value">{{ selectedComment.user?.name || '—' }}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">عنوان</span>
                                    <span class="detail-value">{{ selectedComment.title || '—' }}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">متن نظر</span>
                                    <span class="detail-value detail-body">{{ selectedComment.body || '—' }}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">امتیاز</span>
                                    <span class="detail-value">
                                        <template v-if="getCommentableKind(selectedComment) === 'product' && selectedComment.rating !== null && selectedComment.rating !== undefined">
                                            <span class="rating-stars" dir="ltr">
                                                <span
                                                    v-for="(filled, index) in renderRating(selectedComment)"
                                                    :key="index"
                                                    class="rating-star"
                                                    :class="{ filled }"
                                                >{{ filled ? '★' : '☆' }}</span>
                                            </span>
                                            <span class="rating-num" dir="ltr">
                                                {{ selectedComment.rating }}
                                            </span>
                                        </template>
                                        <span v-else>—</span>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">نوع محتوا</span>
                                    <span class="detail-value">
                                        <span class="type-badge" :class="getCommentableClass(selectedComment)">
                                            {{ getCommentableLabel(selectedComment) }}
                                        </span>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">شناسه محتوا</span>
                                    <span class="detail-value" dir="ltr">#{{ selectedComment.commentable_id }}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">نظر والد</span>
                                    <span class="detail-value">
                                        <template v-if="isReply(selectedComment)">
                                            پاسخ به نظر <span dir="ltr">#{{ selectedComment.parent_id }}</span>
                                        </template>
                                        <template v-else>—</template>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">وضعیت</span>
                                    <span class="detail-value">
                                        <span class="status-badge" :class="getStatusClass(selectedComment.status)">
                                            {{ getStatusLabel(selectedComment.status) }}
                                        </span>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">تاریخ ثبت</span>
                                    <span class="detail-value date-cell">{{ formatDateTime(selectedComment.created_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <div class="modal-actions">
                                <button
                                    v-for="action in actionOptions[selectedComment.status] || []"
                                    :key="action.status"
                                    type="button"
                                    class="btn-moderation"
                                    :class="action.cls"
                                    :disabled="selectedCommentBusy"
                                    @click="changeSelectedStatus(action.status)"
                                >
                                    <span v-if="selectedCommentBusy" class="btn-spinner"></span>
                                    <span v-else>{{ action.label }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn-moderation action-delete"
                                    :disabled="selectedCommentBusy"
                                    @click="removeComment(selectedComment)"
                                >
                                    حذف
                                </button>
                            </div>
                            <button type="button" class="btn-modal-close" @click="closeDetail">
                                بستن
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </section>
</template>

<style scoped>
.comments-section {
    width: 100%;
    max-width: 1200px;
    margin-inline: auto;
    padding: 24px 20px;
    box-sizing: border-box;
    direction: rtl;
}

.comments-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.header-left {
    min-width: 0;
    flex: 1;
}

.comments-title {
    margin: 0 0 4px;
    color: #1a1a1a;
    font-size: 26px;
    font-weight: 700;
    line-height: 1.35;
}

.comments-subtitle {
    margin: 0;
    color: #888;
    font-size: 14px;
    line-height: 1.6;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.comments-count {
    padding: 6px 14px;
    border-radius: 20px;
    background: #f0f0ec;
    color: #888;
    font-size: 13px;
    white-space: nowrap;
}

.btn-refresh {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1.5px solid #e0e0dc;
    border-radius: 10px;
    background: #fff;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
    white-space: nowrap;
}

.btn-refresh:hover:not(:disabled) {
    border-color: #1a1a1a;
    background: #f5f5f2;
}

.btn-refresh:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.btn-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(0, 0, 0, .15);
    border-top-color: currentColor;
    border-radius: 50%;
    animation: comments-spin .6s linear infinite;
}

@keyframes comments-spin {
    to { transform: rotate(360deg); }
}

.comments-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}

.summary-tab {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 14px 18px;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    background: #fff;
    font-family: inherit;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
}

.summary-tab:hover {
    border-color: #1a1a1a;
    background: #f5f5f2;
}

.summary-tab.active {
    border-color: #6563d9;
    background: #f4f2ff;
}

.summary-tab-label {
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 700;
}

.summary-tab-count {
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.summary-tab-count.count-all { background: #f0f0ec; color: #555; }
.summary-tab-count.count-pending { background: #fef3cd; color: #856404; }
.summary-tab-count.count-approved { background: #d1fae5; color: #065f46; }
.summary-tab-count.count-rejected { background: #fee2e2; color: #991b1b; }

.comments-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    margin-bottom: 20px;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    background: #fff;
    flex-wrap: wrap;
    box-sizing: border-box;
}

.toolbar-main {
    display: flex;
    flex: 1;
    min-width: 0;
    gap: 10px;
    flex-wrap: wrap;
}

.toolbar-search-wrap {
    position: relative;
    flex: 1 1 260px;
    min-width: 0;
}

.toolbar-search-wrap .search-icon {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
    font-size: 14px;
    pointer-events: none;
}

.toolbar-search-wrap .search-input {
    width: 100%;
    padding: 10px 40px 10px 14px;
    border: 1.5px solid #e8e8e4;
    border-radius: 10px;
    background: #fafaf8;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 13px;
    box-sizing: border-box;
    transition: border-color .15s ease, background-color .15s ease;
}

.toolbar-search-wrap .search-input:focus {
    outline: none;
    border-color: #1a1a1a;
    background: #fff;
}

.toolbar-search-wrap .search-clear-btn {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    padding: 4px;
    border: 0;
    background: transparent;
    color: #aaa;
    cursor: pointer;
    font-size: 14px;
}

.toolbar-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-select {
    min-width: 160px;
    padding: 10px 14px;
    border: 1.5px solid #e8e8e4;
    border-radius: 10px;
    background: #fff;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 13px;
    cursor: pointer;
    box-sizing: border-box;
}

.filter-select:focus {
    outline: none;
    border-color: #1a1a1a;
}

.toolbar-actions {
    flex-shrink: 0;
}

.btn-clear-filters {
    padding: 10px 18px;
    border: 1.5px solid #e8e8e4;
    border-radius: 10px;
    background: #fff;
    color: #c9545e;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
    white-space: nowrap;
}

.btn-clear-filters:hover {
    border-color: #c9545e;
    background: #fef2f2;
}

.comments-state {
    min-height: 260px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 12px;
    padding: 60px 20px;
    color: #888;
    font-size: 14px;
    text-align: center;
}

.comments-state .alert {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    margin: 0;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 13px;
}

.alert.error {
    border: 1px solid #fecaca;
    background: #fee2e2;
    color: #991b1b;
}

.btn-retry {
    padding: 4px 12px;
    border: 1px solid currentColor;
    border-radius: 6px;
    background: transparent;
    color: inherit;
    font-family: inherit;
    font-size: 12px;
    cursor: pointer;
}

.empty-state {
    background: #fff;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
}

.empty-state .empty-icon {
    font-size: 48px;
    opacity: .5;
}

.empty-state h3 {
    margin: 0;
    color: #1a1a1a;
    font-size: 16px;
}

.empty-hint {
    margin: 0;
    color: #aaa;
    font-size: 13px;
}

.comments-content {
    display: grid;
    gap: 16px;
    min-width: 0;
}

.comments-table-container {
    width: 100%;
    min-width: 0;
    overflow-x: auto;
    border: 1px solid #e8e8e4;
    border-radius: 14px;
    background: #fff;
}

.comments-table {
    width: 100%;
    min-width: 1100px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    color: #1a1a1a;
    font-size: 13px;
}

.comments-table thead {
    background: #f8f8f6;
}

.comments-table th,
.comments-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0ec;
    text-align: right;
    vertical-align: middle;
    overflow-wrap: anywhere;
    box-sizing: border-box;
}

.comments-table th {
    color: #555;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
    border-bottom: 2px solid #e8e8e4;
}

.comments-table tbody tr {
    transition: background-color .15s ease;
}

.comments-table tbody tr:hover {
    background: #fafaf8;
}

.comments-table tbody tr:last-child td {
    border-bottom: 0;
}

.th-id { width: 96px; }
.th-user { width: 15%; min-width: 140px; }
.th-comment { width: 34%; min-width: 240px; }
.th-rating { width: 110px; }
.th-content { width: 150px; }
.th-status { width: 120px; }
.th-date { width: 110px; }
.th-action { width: 210px; }

.comment-id {
    direction: ltr;
    unicode-bidi: plaintext;
    color: #6563d9;
    font-family: SFMono-Regular, Consolas, monospace;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.comment-meta-top {
    display: flex;
    align-items: center;
    gap: 8px;
}

.reply-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 20px;
    background: #e8dafe;
    color: #692fc2;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.user-name-cell {
    min-width: 0;
}

.user-name {
    overflow: hidden;
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.comment-text-cell {
    min-width: 0;
}

.comment-title {
    overflow: hidden;
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.comment-body {
    overflow: hidden;
    margin-top: 3px;
    color: #777;
    font-size: 12px;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.rating-stars {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    color: #ddd;
    font-size: 15px;
    white-space: nowrap;
}

.rating-star.filled {
    color: #f6b21e;
}

.rating-none {
    color: #bbb;
    font-size: 13px;
}

.rating-num {
    margin-inline-start: 6px;
    color: #888;
    font-size: 12px;
}

.content-type-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.type-badge.type-product { background: #d1e7ff; color: #084298; }
.type-badge.type-article { background: #e8dafe; color: #692fc2; }

.content-id {
    color: #888;
    font-size: 11px;
    font-family: SFMono-Regular, Consolas, monospace;
    white-space: nowrap;
}

.parent-id {
    display: block;
    margin-top: 4px;
    color: #aaa;
    font-size: 11px;
    white-space: nowrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.status-badge.status-pending { background: #fef3cd; color: #856404; }
.status-badge.status-approved { background: #d1fae5; color: #065f46; }
.status-badge.status-rejected { background: #fee2e2; color: #991b1b; }

.status-badge.status-sm {
    padding: 2px 8px;
    font-size: 11px;
}

.date-cell {
    color: #888;
    font-size: 12px;
    white-space: nowrap;
}

.row-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.btn-view {
    padding: 7px 14px;
    border: 1.5px solid #e8e8e4;
    border-radius: 8px;
    background: #fff;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
    white-space: nowrap;
}

.btn-view:hover:not(:disabled) {
    border-color: #1a1a1a;
    background: #f5f5f2;
}

.btn-view-full {
    width: 100%;
    padding: 10px;
    font-size: 13px;
}

.btn-moderation {
    padding: 7px 14px;
    border: 1.5px solid;
    border-radius: 8px;
    background: transparent;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color .15s ease, color .15s ease, border-color .15s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-moderation:disabled {
    opacity: .4;
    cursor: not-allowed;
}

.btn-moderation.action-approve {
    border-color: #16a34a;
    color: #16a34a;
}

.btn-moderation.action-approve:hover:not(:disabled) {
    background: #f0fdf4;
}

.btn-moderation.action-reject {
    border-color: #d97706;
    color: #d97706;
}

.btn-moderation.action-reject:hover:not(:disabled) {
    background: #fffbeb;
}

.btn-moderation.action-delete {
    border-color: #c9545e;
    color: #c9545e;
}

.btn-moderation.action-delete:hover:not(:disabled) {
    background: #fef2f2;
}

.btn-moderation-full {
    flex: 1;
    padding: 10px;
    font-size: 13px;
}

.comments-mobile-list {
    display: none;
}

.comment-card {
    overflow: hidden;
    margin-bottom: 12px;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    background: #fff;
}

.comment-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0ec;
    background: #f8f8f6;
}

.comment-card-body {
    padding: 14px 16px;
}

.comment-card-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 6px 0;
}

.comment-card-row + .comment-card-row {
    border-top: 1px solid #f5f5f8;
}

.card-label {
    flex-shrink: 0;
    color: #888;
    font-size: 12px;
}

.card-value {
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 500;
    text-align: left;
    min-width: 0;
    overflow-wrap: anywhere;
}

.card-body-text {
    max-width: 70%;
    white-space: normal;
}

.comment-card-footer {
    display: grid;
    gap: 10px;
    padding: 0 14px 14px;
}

.card-action-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.comments-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
    border: 1px solid #e8e8e4;
    border-top: 0;
    border-radius: 0 0 14px 14px;
    background: #fafaf8;
    box-sizing: border-box;
}

.pagination-info {
    color: #888;
    font-size: 13px;
}

.pagination-buttons {
    display: flex;
    gap: 6px;
}

.pagination-btn {
    padding: 6px 14px;
    border: 1px solid #e8e8e4;
    border-radius: 8px;
    background: #fff;
    color: #1a1a1a;
    font-family: inherit;
    font-size: 13px;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
}

.pagination-btn:hover:not(:disabled) {
    border-color: #1a1a1a;
    background: #f5f5f2;
}

.pagination-btn:disabled {
    opacity: .4;
    cursor: not-allowed;
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0, 0, 0, .45);
    box-sizing: border-box;
}

.modal-panel {
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 20px 50px rgba(0, 0, 0, .2);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid #e8e8e4;
}

.modal-title-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-title {
    margin: 0;
    color: #1a1a1a;
    font-size: 16px;
    font-weight: 700;
}

.modal-id {
    direction: ltr;
    unicode-bidi: plaintext;
    color: #6563d9;
    font-family: SFMono-Regular, Consolas, monospace;
    font-size: 13px;
    font-weight: 700;
}

.modal-close {
    padding: 6px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #888;
    font-size: 14px;
    cursor: pointer;
    transition: background-color .15s ease, color .15s ease;
}

.modal-close:hover {
    background: #f0f0ec;
    color: #1a1a1a;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
}

.detail-block {
    display: grid;
    gap: 0;
}

.detail-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 10px 0;
}

.detail-item + .detail-item {
    border-top: 1px solid #f5f5f8;
}

.detail-label {
    flex-shrink: 0;
    width: 90px;
    color: #888;
    font-size: 12px;
}

.detail-value {
    flex: 1;
    min-width: 0;
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 500;
    overflow-wrap: anywhere;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.detail-body {
    line-height: 1.8;
    white-space: pre-wrap;
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 20px;
    border-top: 1px solid #e8e8e4;
    background: #fafaf8;
    flex-wrap: wrap;
}

.modal-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-modal-close {
    padding: 9px 18px;
    border: 1.5px solid #e8e8e4;
    border-radius: 8px;
    background: #fff;
    color: #555;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
}

.btn-modal-close:hover {
    border-color: #1a1a1a;
    background: #f5f5f2;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity .2s ease;
}

.modal-fade-enter-active .modal-panel,
.modal-fade-leave-active .modal-panel {
    transition: transform .2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-fade-enter-from .modal-panel,
.modal-fade-leave-to .modal-panel {
    transform: scale(.97) translateY(8px);
}

@media (max-width: 900px) {
    .comments-summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .comments-toolbar {
        align-items: stretch;
    }

    .toolbar-main {
        flex-direction: column;
    }

    .toolbar-filters {
        flex-direction: column;
    }

    .filter-select {
        width: 100%;
        min-width: 0;
    }

    .toolbar-actions {
        justify-content: flex-end;
    }
}

@media (max-width: 768px) {
    .comments-section {
        padding: 16px 12px;
    }

    .comments-header {
        flex-direction: column;
        gap: 14px;
    }

    .comments-title {
        font-size: 20px;
    }

    .header-right {
        width: 100%;
        justify-content: space-between;
    }

    .comments-table-container {
        display: none;
    }

    .comments-mobile-list {
        display: grid;
        gap: 12px;
    }

    .comments-toolbar {
        flex-direction: column;
    }

    .toolbar-actions {
        justify-content: flex-start;
    }

    .comments-pagination {
        flex-direction: column;
    }

    .pagination-buttons {
        justify-content: flex-end;
    }

    .modal-overlay {
        padding: 12px;
    }

    .modal-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .modal-actions {
        justify-content: stretch;
    }

    .btn-modal-close {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .comments-summary {
        grid-template-columns: 1fr;
    }

    .toolbar-search-wrap {
        flex-basis: 100%;
    }

    .header-right {
        align-items: stretch;
        flex-direction: column;
    }

    .btn-refresh,
    .comments-count {
        width: 100%;
        text-align: center;
    }

    .comments-pagination {
        padding: 14px;
    }
}

.dark .comments-section {
    color: #e5e5e5;
}

.dark .comments-header,
.dark .comments-toolbar,
.dark .comments-table-container,
.dark .comment-card {
    background: #161616;
    border-color: #2a2a2a;
}

.dark .comments-table thead {
    background: #1e1e1e;
    border-bottom-color: #2a2a2a;
}

.dark .comments-table th {
    border-bottom-color: #2a2a2a;
    color: #999;
}

.dark .comments-table td {
    border-bottom-color: #222;
    color: #e5e5e5;
}

.dark .comments-table tbody tr:hover {
    background: #1e1e1e;
}

.dark .user-name,
.dark .comment-title,
.dark .comment-id,
.dark .card-value {
    color: #e5e5e5;
}

.dark .comment-body,
.dark .date-cell,
.dark .card-label,
.dark .content-id,
.dark .parent-id,
.dark .rating-none {
    color: #999;
}

.dark .filter-select,
.dark .search-input,
.dark .btn-refresh,
.dark .pagination-btn,
.dark .btn-view,
.dark .btn-modal-close {
    border-color: #2a2a2a;
    background: #1e1e1e;
    color: #e5e5e5;
}

.dark .summary-tab {
    background: #161616;
    border-color: #2a2a2a;
}

.dark .summary-tab:hover {
    border-color: #e5e5e5;
    background: #1e1e1e;
}

.dark .summary-tab.active {
    border-color: #a5b4fc;
    background: #201c45;
}

.dark .summary-tab-label {
    color: #e5e5e5;
}

.dark .summary-tab-count.count-all { background: #272727; color: #bbb; }
.dark .summary-tab-count.count-pending { background: #422006; color: #fde68a; }
.dark .summary-tab-count.count-approved { background: #064e3b; color: #6ee7b7; }
.dark .summary-tab-count.count-rejected { background: #7f1d1d; color: #fca5a5; }

.dark .status-badge.status-pending { background: #422006; color: #fde68a; }
.dark .status-badge.status-approved { background: #064e3b; color: #6ee7b7; }
.dark .status-badge.status-rejected { background: #7f1d1d; color: #fca5a5; }

.dark .type-badge.type-product { background: #082f49; color: #7dd3fc; }
.dark .type-badge.type-article { background: #3f1f6b; color: #d6bcfa; }

.dark .reply-badge { background: #3f1f6b; color: #d6bcfa; }

.dark .btn-moderation.action-approve {
    border-color: #4ade80;
    color: #4ade80;
}

.dark .btn-moderation.action-approve:hover:not(:disabled) {
    background: #052e1b;
}

.dark .btn-moderation.action-reject {
    border-color: #fbbf24;
    color: #fbbf24;
}

.dark .btn-moderation.action-reject:hover:not(:disabled) {
    background: #422006;
}

.dark .btn-moderation.action-delete {
    border-color: #f87171;
    color: #f87171;
}

.dark .btn-moderation.action-delete:hover:not(:disabled) {
    background: #450a0a;
}

.dark .btn-clear-filters {
    border-color: #2a2a2a;
    background: #1e1e1e;
}

.dark .empty-state {
    background: #1e1e1e;
    border-color: #2a2a2a;
}

.dark .empty-state h3 {
    color: #e5e5e5;
}

.dark .modal-panel {
    background: #161616;
    box-shadow: 0 20px 50px rgba(0, 0, 0, .5);
}

.dark .modal-header {
    border-bottom-color: #2a2a2a;
}

.dark .modal-title {
    color: #e5e5e5;
}

.dark .modal-close:hover {
    background: #1e1e1e;
    color: #e5e5e5;
}

.dark .detail-item + .detail-item {
    border-top-color: #222;
}

.dark .detail-label {
    color: #999;
}

.dark .detail-value {
    color: #e5e5e5;
}

.dark .modal-footer {
    border-top-color: #2a2a2a;
    background: #1e1e1e;
}
</style>