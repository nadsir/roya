<script setup>
import {
    adminUsers,
    adminUserLoading,
    adminUserError,
    adminUserSearch,
    adminUserStatusFilter,
    adminUserPage,
    adminUserTotalPages,
    adminUserTotal,
    loadAdminUsers,
    updateAdminUserStatus,
} from './admin-state.js';

import {
    showAdminNotification,
    hideAdminNotification,
} from './admin-state.js';

import { onMounted, watch, computed, ref } from 'vue';

const emit = defineEmits(['open-detail']);

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fa-IR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const statusOptions = [
    { value: '', label: 'همه وضعیت‌ها' },
    { value: 'active', label: 'فعال' },
    { value: 'inactive', label: 'غیرفعال' },
];

const hasActiveFilters = computed(() => {
    return adminUserSearch.value || adminUserStatusFilter.value;
});

const localSearch = ref(adminUserSearch.value);

watch(localSearch, (val) => {
    adminUserSearch.value = val;
    adminUserPage.value = 1;
    loadAdminUsers();
});

watch(() => adminUserStatusFilter.value, () => {
    adminUserPage.value = 1;
    loadAdminUsers();
});

watch(() => adminUserPage.value, () => {
    loadAdminUsers();
});

onMounted(() => {
    loadAdminUsers();
});

function goToPage(page) {
    if (page < 1 || page > adminUserTotalPages.value) return;
    adminUserPage.value = page;
}

function clearFilters() {
    localSearch.value = '';
    adminUserSearch.value = '';
    adminUserStatusFilter.value = '';
    adminUserPage.value = 1;
    loadAdminUsers();
}

async function toggleUserStatus(user) {
    const newStatus = !user.is_active;
    try {
        await updateAdminUserStatus(user.id, newStatus);
        showAdminNotification('success', 'موفقیت', 'وضعیت کاربر با موفقیت تغییر کرد.');
        loadAdminUsers();
    } catch (error) {
        const message = error.message || 'تغییر وضعیت انجام نشد.';
        showAdminNotification('error', 'خطا', message);
    }
}

function getStatusClass(isActive) {
    return isActive ? 'status-active' : 'status-inactive';
}

function getStatusLabel(isActive) {
    return isActive ? 'فعال' : 'غیرفعال';
}
</script>

<template>
    <section class="users-section">
        <!-- Header -->
        <header class="users-header">
            <div class="header-left">
                <h1 class="users-title">کاربران</h1>
                <p class="users-subtitle">مدیریت کاربران و وضعیت حساب‌های کاربری</p>
            </div>
            <div class="header-right">
                <span class="users-count" v-if="adminUserTotal > 0 && !adminUserLoading">
                    {{ adminUserTotal.toLocaleString('fa-IR') }} کاربر
                </span>
                <button type="button" class="btn-refresh" @click="loadAdminUsers" :disabled="adminUserLoading">
                    <span v-if="adminUserLoading" class="btn-spinner"></span>
                    <span v-else>↻</span>
                    بروزرسانی
                </button>
            </div>
        </header>

        <!-- Filter Toolbar -->
        <div class="users-toolbar">
            <div class="toolbar-main">
                <div class="toolbar-search-wrap">
                    <span class="search-icon">🔍</span>
                    <input
                        type="text"
                        class="search-input"
                        placeholder="جستجوی نام، ایمیل یا موبایل..."
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
                    <select v-model="adminUserStatusFilter" class="filter-select">
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
        <div v-if="adminUserLoading && !adminUsers.length" class="users-state loading-state">
            <div class="spinner small-spinner"></div>
            <span>در حال بارگذاری کاربران...</span>
        </div>

        <div v-else-if="adminUserError" class="users-state alert error">
            {{ adminUserError }}
            <button type="button" class="btn-retry" @click="loadAdminUsers">تلاش دوباره</button>
        </div>

        <div v-else-if="!adminUsers.length" class="users-state empty-state">
            <div class="empty-icon">◎</div>
            <h3 v-if="hasActiveFilters">کاربری یافت نشد</h3>
            <h3 v-else>هنوز کاربری ثبت نشده است</h3>
            <p v-if="hasActiveFilters" class="empty-hint">فیلترهای فعلی نتیجه‌ای نداشت</p>
            <button v-if="hasActiveFilters" type="button" class="btn-clear-filters" @click="clearFilters">
                پاک کردن فیلترها
            </button>
        </div>

        <!-- Desktop Table & Mobile Cards -->
        <div v-else class="users-content">
            <!-- Desktop Table -->
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th class="th-id">شناسه</th>
                            <th class="th-name">نام</th>
                            <th class="th-email">ایمیل</th>
                            <th class="th-mobile">موبایل</th>
                            <th class="th-role">نقش</th>
                            <th class="th-status">وضعیت</th>
                            <th class="th-orders">تعداد سفارش</th>
                            <th class="th-date">تاریخ ثبت‌نام</th>
                            <th class="th-action">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in adminUsers" :key="user.id" class="user-row">
                            <td>
                                <span class="user-id" dir="ltr">#{{ user.id }}</span>
                            </td>
                            <td class="user-name-cell">
                                <div class="user-name">{{ user.name || '—' }}</div>
                            </td>
                            <td>
                                <span class="user-email" v-if="user.email" dir="ltr">{{ user.email }}</span>
                                <span class="user-email" v-else style="color:#aaa">—</span>
                            </td>
                            <td>
                                <span class="user-mobile" v-if="user.mobile" dir="ltr">{{ user.mobile }}</span>
                                <span class="user-mobile" v-else style="color:#aaa">—</span>
                            </td>
                            <td>
                                <span class="role-badge" :class="user.role === 'admin' ? 'admin' : 'user'">
                                    {{ user.role === 'admin' ? 'مدیر' : 'کاربر' }}
                                </span>
                            </td>
                            <td>
                                <span class="status-badge" :class="getStatusClass(user.is_active)">
                                    {{ getStatusLabel(user.is_active) }}
                                </span>
                            </td>
                            <td>
                                <span class="orders-count" dir="ltr">{{ user.orders_count || 0 }}</span>
                            </td>
                            <td>
                                <span class="date-cell">{{ formatDate(user.created_at) }}</span>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn-toggle-status"
                                    :class="user.is_active ? 'active' : 'inactive'"
                                    @click="toggleUserStatus(user)"
                                    :disabled="user.role === 'admin'"
                                    :title="user.role === 'admin' ? 'امکان تغییر وضعیت مدیر وجود ندارد' : ''"
                                >
                                    {{ user.is_active ? 'غیرفعال کردن' : 'فعال کردن' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="users-mobile-list">
                <div v-for="user in adminUsers" :key="user.id" class="user-card">
                    <div class="user-card-header">
                        <span class="user-id" dir="ltr">#{{ user.id }}</span>
                        <span class="status-badge status-sm" :class="getStatusClass(user.is_active)">
                            {{ getStatusLabel(user.is_active) }}
                        </span>
                    </div>
                    <div class="user-card-body">
                        <div class="user-card-row">
                            <span class="card-label">نام</span>
                            <span class="card-value">{{ user.name || '—' }}</span>
                        </div>
                        <div class="user-card-row">
                            <span class="card-label">ایمیل</span>
                            <span class="card-value" dir="ltr">{{ user.email || '—' }}</span>
                        </div>
                        <div class="user-card-row">
                            <span class="card-label">موبایل</span>
                            <span class="card-value" dir="ltr">{{ user.mobile || '—' }}</span>
                        </div>
                        <div class="user-card-row">
                            <span class="card-label">نقش</span>
                            <span class="role-badge" :class="user.role === 'admin' ? 'admin' : 'user'">
                                {{ user.role === 'admin' ? 'مدیر' : 'کاربر' }}
                            </span>
                        </div>
                        <div class="user-card-row">
                            <span class="card-label">تعداد سفارش</span>
                            <span class="card-value" dir="ltr">{{ user.orders_count || 0 }}</span>
                        </div>
                        <div class="user-card-row">
                            <span class="card-label">تاریخ ثبت‌نام</span>
                            <span class="card-value date-cell">{{ formatDate(user.created_at) }}</span>
                        </div>
                    </div>
                    <div class="user-card-footer">
                        <button
                            type="button"
                            class="btn-toggle-status btn-toggle-full"
                            :class="user.is_active ? 'active' : 'inactive'"
                            @click="toggleUserStatus(user)"
                            :disabled="user.role === 'admin'"
                        >
                            {{ user.is_active ? 'غیرفعال کردن' : 'فعال کردن' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="adminUserTotalPages > 1" class="users-pagination">
                <span class="pagination-info">
                    صفحه {{ adminUserPage.toLocaleString('fa-IR') }} از {{ adminUserTotalPages.toLocaleString('fa-IR') }}
                </span>
                <div class="pagination-buttons">
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="adminUserPage <= 1"
                        @click="goToPage(adminUserPage - 1)"
                    >
                        ◀ قبلی
                    </button>
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="adminUserPage >= adminUserTotalPages"
                        @click="goToPage(adminUserPage + 1)"
                    >
                        بعدی ▶
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.users-section {
    width: 100%;
    max-width: 1200px;
    margin-inline: auto;
    padding: 24px 20px;
    box-sizing: border-box;
    direction: rtl;
}

.users-header {
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

.users-title {
    margin: 0 0 4px;
    color: #1a1a1a;
    font-size: 26px;
    font-weight: 700;
    line-height: 1.35;
}

.users-subtitle {
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

.users-count {
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
    animation: users-spin .6s linear infinite;
}

@keyframes users-spin {
    to { transform: rotate(360deg); }
}

.users-toolbar {
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

.users-state {
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

.users-state .alert {
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

.users-content {
    display: grid;
    gap: 16px;
    min-width: 0;
}

.users-table-container {
    width: 100%;
    min-width: 0;
    overflow-x: auto;
    border: 1px solid #e8e8e4;
    border-radius: 14px;
    background: #fff;
}

.users-table {
    width: 100%;
    min-width: 1100px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    color: #1a1a1a;
    font-size: 13px;
}

.users-table thead {
    background: #f8f8f6;
}

.users-table th,
.users-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0ec;
    text-align: right;
    vertical-align: middle;
    overflow-wrap: anywhere;
    box-sizing: border-box;
}

.users-table th {
    color: #555;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
    border-bottom: 2px solid #e8e8e4;
}

.users-table tbody tr {
    transition: background-color .15s ease;
}

.users-table tbody tr:hover {
    background: #fafaf8;
}

.users-table tbody tr:last-child td {
    border-bottom: 0;
}

.th-id { width: 64px; }
.th-name { width: 18%; min-width: 140px; }
.th-email { width: 18%; min-width: 160px; }
.th-mobile { width: 120px; }
.th-role { width: 80px; }
.th-status { width: 100px; }
.th-orders { width: 90px; }
.th-date { width: 110px; }
.th-action { width: 130px; }

.user-id {
    direction: ltr;
    unicode-bidi: plaintext;
    color: #6563d9;
    font-family: SFMono-Regular, Consolas, monospace;
    font-size: 13px;
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

.user-email,
.user-mobile {
    color: #555;
    font-size: 12px;
    direction: ltr;
    unicode-bidi: plaintext;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.role-badge.admin {
    background: #fef3cd;
    color: #856404;
}

.role-badge.user {
    background: #e8dafe;
    color: #692fc2;
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

.status-badge.status-active { background: #d1fae5; color: #065f46; }
.status-badge.status-inactive { background: #fee2e2; color: #991b1b; }

.status-badge.status-sm {
    padding: 2px 8px;
    font-size: 11px;
}

.orders-count {
    direction: ltr;
    unicode-bidi: plaintext;
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 600;
}

.date-cell {
    color: #888;
    font-size: 12px;
    white-space: nowrap;
}

.btn-toggle-status {
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
}

.btn-toggle-status.active {
    border-color: #c9545e;
    color: #c9545e;
}

.btn-toggle-status.active:hover:not(:disabled) {
    background: #fef2f2;
}

.btn-toggle-status.inactive {
    border-color: #6563d9;
    color: #6563d9;
}

.btn-toggle-status.inactive:hover:not(:disabled) {
    background: #ede9fe;
}

.btn-toggle-status:disabled {
    opacity: .4;
    cursor: not-allowed;
    border-color: #e8e8e4;
    color: #aaa;
}

.btn-toggle-full {
    width: 100%;
    padding: 10px;
    font-size: 13px;
}

.users-mobile-list {
    display: none;
}

.user-card {
    overflow: hidden;
    margin-bottom: 12px;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    background: #fff;
}

.user-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0ec;
    background: #f8f8f6;
}

.user-card-body {
    padding: 14px 16px;
}

.user-card-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 6px 0;
}

.user-card-row + .user-card-row {
    border-top: 1px solid #f5f5f8;
}

.card-label {
    color: #888;
    font-size: 12px;
}

.card-value {
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 500;
    text-align: left;
}

.user-card-footer {
    padding: 0 14px 14px;
}

.users-pagination {
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

@media (max-width: 1024px) {
    .users-toolbar {
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
    .users-section {
        padding: 16px 12px;
    }

    .users-header {
        flex-direction: column;
        gap: 14px;
    }

    .users-title {
        font-size: 20px;
    }

    .header-right {
        width: 100%;
        justify-content: space-between;
    }

    .users-table-container {
        display: none;
    }

    .users-mobile-list {
        display: grid;
        gap: 12px;
    }

    .users-toolbar {
        flex-direction: column;
    }

    .toolbar-actions {
        justify-content: flex-start;
    }

    .users-pagination {
        flex-direction: column;
    }

    .pagination-buttons {
        justify-content: flex-end;
    }
}

@media (max-width: 480px) {
    .toolbar-search-wrap {
        flex-basis: 100%;
    }

    .header-right {
        align-items: stretch;
        flex-direction: column;
    }

    .btn-refresh,
    .users-count {
        width: 100%;
        text-align: center;
    }

    .users-pagination {
        padding: 14px;
    }
}

.dark .users-section {
    color: #e5e5e5;
}

.dark .users-header,
.dark .users-toolbar,
.dark .users-table-container,
.dark .user-card {
    background: #161616;
    border-color: #2a2a2a;
}

.dark .users-table thead {
    background: #1e1e1e;
    border-bottom-color: #2a2a2a;
}

.dark .users-table th {
    border-bottom-color: #2a2a2a;
    color: #999;
}

.dark .users-table td {
    border-bottom-color: #222;
    color: #e5e5e5;
}

.dark .users-table tbody tr:hover {
    background: #1e1e1e;
}

.dark .user-name,
.dark .user-id,
.dark .card-value {
    color: #e5e5e5;
}

.dark .user-email,
.dark .user-mobile,
.dark .date-cell,
.dark .card-label {
    color: #999;
}

.dark .filter-select,
.dark .search-input,
.dark .btn-refresh,
.dark .pagination-btn {
    border-color: #2a2a2a;
    background: #1e1e1e;
    color: #e5e5e5;
}

.dark .role-badge.admin {
    background: #422006;
    color: #fde68a;
}

.dark .role-badge.user {
    background: #3f1f6b;
    color: #d6bcfa;
}

.dark .status-badge.status-active { background: #064e3b; color: #6ee7b7; }
.dark .status-badge.status-inactive { background: #7f1d1d; color: #fca5a5; }

.dark .btn-toggle-status.active {
    border-color: #f87171;
    color: #f87171;
}

.dark .btn-toggle-status.active:hover:not(:disabled) {
    background: #450a0a;
}

.dark .btn-toggle-status.inactive {
    border-color: #a5b4fc;
    color: #a5b4fc;
}

.dark .btn-toggle-status.inactive:hover:not(:disabled) {
    background: #1e1b4b;
}

.dark .empty-state {
    background: #1e1e1e;
    border-color: #2a2a2a;
}
</style>