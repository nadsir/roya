<script setup>
import {
    adminOrders,
    adminOrderLoading,
    adminOrderError,
    adminOrderSearch,
    adminOrderStatusFilter,
    adminOrderPaymentFilter,
    adminOrderPage,
    adminOrderTotalPages,
    adminOrderTotal,
    loadAdminOrders,
    getStatusLabel,
} from './admin-state.js';

import { onMounted, watch, computed, ref } from 'vue';

const emit = defineEmits(['open-detail']);

const formatPrice = (value) => {
    if (value === null || value === undefined || value === '') return '۰';
    return Number(value).toLocaleString('fa-IR');
};

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
    { value: 'pending', label: 'در انتظار پرداخت' },
    { value: 'confirmed', label: 'تأیید شده' },
    { value: 'processing', label: 'در حال پردازش' },
    { value: 'shipped', label: 'ارسال شده' },
    { value: 'delivered', label: 'تحویل شده' },
    { value: 'cancelled', label: 'لغو شده' },
];

const paymentOptions = [
    { value: '', label: 'همه وضعیت‌های پرداخت' },
    { value: 'paid', label: 'پرداخت شده' },
    { value: 'unpaid', label: 'پرداخت نشده' },
];

const hasActiveFilters = computed(() => {
    return adminOrderSearch.value || adminOrderStatusFilter.value || adminOrderPaymentFilter.value;
});

const localSearch = ref(adminOrderSearch.value);

watch(localSearch, (val) => {
    adminOrderSearch.value = val;
    adminOrderPage.value = 1;
    loadAdminOrders();
});

watch(() => adminOrderStatusFilter.value, () => {
    adminOrderPage.value = 1;
    loadAdminOrders();
});

watch(() => adminOrderPaymentFilter.value, () => {
    adminOrderPage.value = 1;
    loadAdminOrders();
});

watch(() => adminOrderPage.value, () => {
    loadAdminOrders();
});

onMounted(() => {
    loadAdminOrders();
});

function goToPage(page) {
    if (page < 1 || page > adminOrderTotalPages.value) return;
    adminOrderPage.value = page;
}

function clearFilters() {
    localSearch.value = '';
    adminOrderSearch.value = '';
    adminOrderStatusFilter.value = '';
    adminOrderPaymentFilter.value = '';
    adminOrderPage.value = 1;
    loadAdminOrders();
}

function viewOrder(orderId) {
    emit('open-detail', orderId);
}

function getStatusClass(status) {
    const map = {
        pending: 'status-pending',
        confirmed: 'status-confirmed',
        processing: 'status-processing',
        shipped: 'status-shipped',
        delivered: 'status-delivered',
        cancelled: 'status-cancelled',
    };
    return map[status] || '';
}
</script>

<template>
    <section class="orders-section">
        <!-- Header -->
        <header class="orders-header">
            <div class="header-left">
                <h1 class="orders-title">سفارش‌ها</h1>
                <p class="orders-subtitle">مدیریت، پیگیری و تغییر وضعیت سفارش‌های مشتریان</p>
            </div>
            <div class="header-right">
                <span class="orders-count" v-if="adminOrderTotal > 0 && !adminOrderLoading">
                    {{ adminOrderTotal.toLocaleString('fa-IR') }} سفارش
                </span>
                <button type="button" class="btn-refresh" @click="loadAdminOrders" :disabled="adminOrderLoading">
                    <span v-if="adminOrderLoading" class="btn-spinner"></span>
                    <span v-else>↻</span>
                    بروزرسانی
                </button>
            </div>
        </header>

        <!-- Filter Toolbar -->
        <div class="orders-toolbar">
            <div class="toolbar-main">
                <div class="toolbar-search-wrap">
                    <span class="search-icon">🔍</span>
                    <input
                        type="text"
                        class="search-input"
                        placeholder="جستجوی شماره سفارش یا مشتری..."
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
                    <select v-model="adminOrderStatusFilter" class="filter-select">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <select v-model="adminOrderPaymentFilter" class="filter-select">
                        <option v-for="opt in paymentOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
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
        <div v-if="adminOrderLoading && !adminOrders.length" class="orders-state loading-state">
            <div class="spinner small-spinner"></div>
            <span>در حال بارگذاری سفارش‌ها...</span>
        </div>

        <div v-else-if="adminOrderError" class="orders-state alert error">
            {{ adminOrderError }}
            <button type="button" class="btn-retry" @click="loadAdminOrders">تلاش دوباره</button>
        </div>

        <div v-else-if="!adminOrders.length" class="orders-state empty-state">
            <div class="empty-icon">📦</div>
            <h3 v-if="hasActiveFilters">سفارشی یافت نشد</h3>
            <h3 v-else>هنوز سفارشی ثبت نشده است</h3>
            <p v-if="hasActiveFilters" class="empty-hint">فیلترهای فعلی نتیجه‌ای نداشت</p>
            <button v-if="hasActiveFilters" type="button" class="btn-clear-filters" @click="clearFilters">
                پاک کردن فیلترها
            </button>
        </div>

        <!-- Desktop Table & Mobile Cards -->
        <div v-else class="orders-content">
            <!-- Desktop Table -->
            <div class="orders-table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th class="th-id">سفارش</th>
                            <th class="th-customer">مشتری</th>
                            <th class="th-amount">مبلغ</th>
                            <th class="th-payment">پرداخت</th>
                            <th class="th-status">وضعیت</th>
                            <th class="th-date">تاریخ</th>
                            <th class="th-action">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in adminOrders" :key="order.id" class="order-row">
                            <td>
                                <span class="order-id" dir="ltr">#{{ order.id }}</span>
                            </td>
                            <td class="customer-cell">
                                <div class="customer-name">{{ order.customer?.name || '—' }}</div>
                                <div class="customer-phone" v-if="order.customer?.phone" dir="ltr">{{ order.customer.phone }}</div>
                            </td>
                            <td>
                                <span class="amount">{{ formatPrice(order.total) }} <small>تومان</small></span>
                            </td>
                            <td>
                                <span class="payment-badge" :class="order.paid_at ? 'paid' : 'unpaid'">
                                    {{ order.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}
                                </span>
                            </td>
                            <td>
                                <span class="status-badge" :class="getStatusClass(order.status)">
                                    {{ getStatusLabel(order.status) }}
                                </span>
                            </td>
                            <td>
                                <span class="date-cell">{{ formatDate(order.created_at) }}</span>
                            </td>
                            <td>
                                <button type="button" class="btn-view" @click="viewOrder(order.id)">
                                    مشاهده
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="orders-mobile-list">
                <div v-for="order in adminOrders" :key="order.id" class="order-card">
                    <div class="order-card-header">
                        <span class="order-id" dir="ltr">#{{ order.id }}</span>
                        <span class="status-badge status-sm" :class="getStatusClass(order.status)">
                            {{ getStatusLabel(order.status) }}
                        </span>
                    </div>
                    <div class="order-card-body">
                        <div class="order-card-row">
                            <span class="card-label">مشتری</span>
                            <span class="card-value">{{ order.customer?.name || '—' }}</span>
                        </div>
                        <div class="order-card-row">
                            <span class="card-label">مبلغ</span>
                            <span class="card-value amount">{{ formatPrice(order.total) }} تومان</span>
                        </div>
                        <div class="order-card-row">
                            <span class="card-label">پرداخت</span>
                            <span class="payment-badge" :class="order.paid_at ? 'paid' : 'unpaid'">
                                {{ order.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}
                            </span>
                        </div>
                        <div class="order-card-row">
                            <span class="card-label">تاریخ</span>
                            <span class="card-value date-cell">{{ formatDate(order.created_at) }}</span>
                        </div>
                    </div>
                    <div class="order-card-footer">
                        <button type="button" class="btn-view btn-view-full" @click="viewOrder(order.id)">
                            مشاهده جزئیات
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="adminOrderTotalPages > 1" class="orders-pagination">
                <span class="pagination-info">
                    صفحه {{ adminOrderPage.toLocaleString('fa-IR') }} از {{ adminOrderTotalPages.toLocaleString('fa-IR') }}
                </span>
                <div class="pagination-buttons">
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="adminOrderPage <= 1"
                        @click="goToPage(adminOrderPage - 1)"
                    >
                        ◀ قبلی
                    </button>
                    <button
                        type="button"
                        class="pagination-btn"
                        :disabled="adminOrderPage >= adminOrderTotalPages"
                        @click="goToPage(adminOrderPage + 1)"
                    >
                        بعدی ▶
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.orders-section {
    width: 100%;
    max-width: 1200px;
    margin-inline: auto;
    padding: 24px 20px;
    box-sizing: border-box;
    direction: rtl;
}

.orders-header {
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

.orders-title {
    margin: 0 0 4px;
    color: #1a1a1a;
    font-size: 26px;
    font-weight: 700;
    line-height: 1.35;
}

.orders-subtitle {
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

.orders-count {
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
    animation: orders-spin .6s linear infinite;
}

@keyframes orders-spin {
    to { transform: rotate(360deg); }
}

.orders-toolbar {
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

.orders-state {
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

.orders-state .alert {
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

.orders-content {
    display: grid;
    gap: 16px;
    min-width: 0;
}

.orders-table-container {
    width: 100%;
    min-width: 0;
    overflow-x: auto;
    border: 1px solid #e8e8e4;
    border-radius: 14px;
    background: #fff;
}

.orders-table {
    width: 100%;
    min-width: 980px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    color: #1a1a1a;
    font-size: 13px;
}

.orders-table thead {
    background: #f8f8f6;
}

.orders-table th,
.orders-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0ec;
    text-align: right;
    vertical-align: middle;
    overflow-wrap: anywhere;
    box-sizing: border-box;
}

.orders-table th {
    color: #555;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
    border-bottom: 2px solid #e8e8e4;
}

.orders-table tbody tr {
    transition: background-color .15s ease;
}

.orders-table tbody tr:hover {
    background: #fafaf8;
}

.orders-table tbody tr:last-child td {
    border-bottom: 0;
}

.orders-table th:first-child,
.orders-table td:first-child {
    border-start-start-radius: 0;
}

.th-id { width: 88px; }
.th-customer { width: 20%; min-width: 170px; }
.th-amount { width: 130px; }
.th-status { width: 145px; }
.th-payment { width: 120px; }
.th-date { width: 120px; }
.th-action { width: 96px; }

.order-id {
    direction: ltr;
    unicode-bidi: plaintext;
    color: #6563d9;
    font-family: SFMono-Regular, Consolas, monospace;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.customer-cell {
    min-width: 0;
}

.customer-name {
    overflow: hidden;
    color: #1a1a1a;
    font-size: 13px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.customer-phone {
    margin-top: 2px;
    color: #888;
    font-size: 11px;
    direction: ltr;
    unicode-bidi: plaintext;
}

.amount {
    direction: ltr;
    unicode-bidi: plaintext;
    color: #1a1a1a;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
}

.amount small {
    display: block;
    color: #888;
    font-size: 11px;
    font-weight: 400;
}

.date-cell {
    color: #888;
    font-size: 12px;
    white-space: nowrap;
}

.status-badge,
.payment-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.status-badge.status-pending { background: #fef3cd; color: #856404; }
.status-badge.status-confirmed { background: #d1e7ff; color: #084298; }
.status-badge.status-processing { background: #e8dafe; color: #692fc2; }
.status-badge.status-shipped { background: #cffafe; color: #0e7490; }
.status-badge.status-delivered { background: #d1fae5; color: #065f46; }
.status-badge.status-cancelled { background: #fee2e2; color: #991b1b; }

.status-badge.status-sm {
    padding: 2px 8px;
    font-size: 11px;
}

.payment-badge.paid { background: #d1fae5; color: #065f46; }
.payment-badge.unpaid { background: #fee2e2; color: #991b1b; }

.btn-view {
    padding: 7px 16px;
    border: 1.5px solid #6563d9;
    border-radius: 8px;
    background: transparent;
    color: #6563d9;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color .15s ease, color .15s ease;
    white-space: nowrap;
}

.btn-view:hover {
    background: #6563d9;
    color: #fff;
}

.btn-view-full {
    width: 100%;
    padding: 10px;
    font-size: 13px;
}

.orders-mobile-list {
    display: none;
}

.order-card {
    overflow: hidden;
    margin-bottom: 12px;
    border: 1.5px solid #e8e8e4;
    border-radius: 14px;
    background: #fff;
}

.order-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0ec;
    background: #f8f8f6;
}

.order-card-body {
    padding: 14px 16px;
}

.order-card-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 6px 0;
}

.order-card-row + .order-card-row {
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

.order-card-footer {
    padding: 0 14px 14px;
}

.orders-pagination {
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
    .orders-toolbar {
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
    .orders-section {
        padding: 16px 12px;
    }

    .orders-header {
        flex-direction: column;
        gap: 14px;
    }

    .orders-title {
        font-size: 20px;
    }

    .header-right {
        width: 100%;
        justify-content: space-between;
    }

    .orders-table-container {
        display: none;
    }

    .orders-mobile-list {
        display: grid;
        gap: 12px;
    }

    .orders-toolbar {
        flex-direction: column;
    }

    .toolbar-actions {
        justify-content: flex-start;
    }

    .orders-pagination {
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
    .orders-count {
        width: 100%;
        text-align: center;
    }

    .orders-pagination {
        padding: 14px;
    }
}

.dark .orders-section {
    color: #e5e5e5;
}

.dark .orders-header,
.dark .orders-toolbar,
.dark .orders-table-container,
.dark .order-card {
    background: #161616;
    border-color: #2a2a2a;
}

.dark .orders-table thead {
    background: #1e1e1e;
    border-bottom-color: #2a2a2a;
}

.dark .orders-table th {
    border-bottom-color: #2a2a2a;
    color: #999;
}

.dark .orders-table td {
    border-bottom-color: #222;
    color: #e5e5e5;
}

.dark .orders-table tbody tr:hover {
    background: #1e1e1e;
}

.dark .customer-name,
.dark .order-id,
.dark .amount,
.dark .card-value {
    color: #e5e5e5;
}

.dark .customer-phone,
.dark .date-cell,
.dark .card-label {
    color: #999;
}

.dark .filter-select,
.dark .search-input,
.dark .btn-refresh,
.dark .btn-view,
.dark .pagination-btn {
    border-color: #2a2a2a;
    background: #1e1e1e;
    color: #e5e5e5;
}
</style>
