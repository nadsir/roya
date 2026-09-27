<script setup>
import {
    adminOrder,
    adminOrderLoading,
    adminOrderError,
    adminOrderSuccess,
    loadAdminOrder,
    updateAdminOrderStatus,
    getAllowedTransitions,
    getStatusLabel,
} from './admin-state.js';

import { computed, ref, watch } from 'vue';

const props = defineProps({
    orderId: { type: [String, Number], required: true },
});

const emit = defineEmits(['back']);

const showCancelModal = ref(false);
const showConfirmModal = ref(false);
const cancelledReason = ref('');
const statusLoading = ref(false);
const pendingAction = ref(null);

const formatPrice = (value) => {
    if (value === null || value === undefined || value === '') return '۰';
    return Number(value).toLocaleString('fa-IR');
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fa-IR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatShortDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fa-IR', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusFlow = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

const allowedTransitions = computed(() => {
    if (!adminOrder.value) return [];
    return getAllowedTransitions(adminOrder.value.status);
});

const currentStatusIndex = computed(() => {
    if (!adminOrder.value) return -1;
    return statusFlow.indexOf(adminOrder.value.status);
});

const timelineProgress = computed(() => {
    if (currentStatusIndex.value === -1) return 0;
    return ((currentStatusIndex.value + 1) / statusFlow.length) * 100;
});

const isFinalStatus = computed(() => {
    return adminOrder.value && (adminOrder.value.status === 'delivered' || adminOrder.value.status === 'cancelled');
});

const actionConfig = computed(() => {
    const status = adminOrder.value?.status;
    if (!status) return { buttons: [], primary: [] };

    if (status === 'pending') {
        return {
            buttons: [
                { key: 'confirmed', label: '✓ تایید سفارش', type: 'primary' },
                { key: 'cancelled', label: '× لغو سفارش', type: 'danger' },
            ],
            primary: ['confirmed'],
        };
    }
    if (status === 'confirmed') {
        return {
            buttons: [
                { key: 'processing', label: 'شروع پردازش', type: 'primary' },
                { key: 'cancelled', label: 'لغو سفارش', type: 'danger' },
            ],
            primary: ['processing'],
        };
    }
    if (status === 'processing') {
        return {
            buttons: [
                { key: 'shipped', label: 'علامت‌گذاری به عنوان ارسال شده', type: 'primary' },
            ],
            primary: ['shipped'],
        };
    }
    if (status === 'shipped') {
        return {
            buttons: [
                { key: 'delivered', label: 'علامت‌گذاری به عنوان تحویل شده', type: 'primary' },
            ],
            primary: ['delivered'],
        };
    }
    return { buttons: [], primary: [] };
});

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

watch(() => props.orderId, (newId) => {
    if (newId) {
        loadAdminOrder(newId);
    }
}, { immediate: true });

function handleAction(key) {
    if (key === 'cancelled') {
        cancelledReason.value = '';
        showCancelModal.value = true;
        return;
    }
    pendingAction.value = key;
    showConfirmModal.value = true;
}

async function confirmAction() {
    const status = pendingAction.value;
    if (!status) return;
    pendingAction.value = null;
    showConfirmModal.value = false;
    const success = await executeStatusChange(status, null);
    if (!success) {
        pendingAction.value = null;
    }
}

async function confirmCancel() {
    const success = await executeStatusChange('cancelled', cancelledReason.value || null);
    if (success) {
        showCancelModal.value = false;
        cancelledReason.value = '';
    }
}

function closeCancelModal() {
    showCancelModal.value = false;
    cancelledReason.value = '';
}

function closeConfirmModal() {
    showConfirmModal.value = false;
    pendingAction.value = null;
}

async function executeStatusChange(status, reason) {
    statusLoading.value = true;
    try {
        await updateAdminOrderStatus(props.orderId, status, reason);
        await loadAdminOrder(props.orderId);
        return true;
    } catch (error) {
        return false;
    } finally {
        statusLoading.value = false;
    }
}

function onImgError(event) {
    event.target.onerror = null;
    event.target.src = '/images/placeholder.svg';
}
</script>

<template>
    <section class="order-detail-section">
        <!-- Header -->
        <header class="detail-header">
            <button type="button" class="btn-back" @click="$emit('back')">
                ← بازگشت به سفارش‌ها
            </button>
            <div class="detail-title-block">
                <h1 class="detail-title">
                    سفارش <span class="order-id-large" dir="ltr">#{{ adminOrder?.id || orderId }}</span>
                </h1>
                <span class="detail-date" v-if="adminOrder">
                    {{ formatDate(adminOrder.created_at) }}
                </span>
            </div>
            <div class="detail-badges" v-if="adminOrder">
                <span class="status-badge status-lg" :class="getStatusClass(adminOrder.status)">
                    {{ getStatusLabel(adminOrder.status) }}
                </span>
                <span class="payment-badge payment-lg" :class="adminOrder.paid_at ? 'paid' : 'unpaid'">
                    {{ adminOrder.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}
                </span>
            </div>
        </header>

        <!-- Loading -->
        <div v-if="adminOrderLoading && !adminOrder" class="loading-state">
            <div class="spinner"></div>
            <span>در حال بارگذاری سفارش...</span>
        </div>

        <!-- Error -->
        <div v-else-if="adminOrderError && !adminOrder" class="alert error">
            {{ adminOrderError }}
            <button type="button" class="btn-retry" @click="loadAdminOrder(orderId)">تلاش دوباره</button>
        </div>

        <template v-else-if="adminOrder">
            <!-- Toast Messages -->
            <div v-if="adminOrderSuccess" class="alert success order-toast">
                {{ adminOrderSuccess }}
            </div>
            <div v-if="adminOrderError" class="alert error order-toast">
                {{ adminOrderError }}
            </div>

            <div class="detail-grid">
                <!-- Main Column -->
                <div class="detail-main">
                    <!-- Status Management Section -->
                    <div class="detail-card status-management-card">
                        <h2 class="card-title">مدیریت وضعیت سفارش</h2>

                        <!-- Current Status -->
                        <div class="current-status-row">
                            <span class="current-status-label">وضعیت فعلی</span>
                            <span class="status-badge status-lg" :class="getStatusClass(adminOrder.status)">
                                {{ getStatusLabel(adminOrder.status) }}
                            </span>
                        </div>

                        <!-- Timeline -->
                        <div class="status-timeline">
                            <div class="timeline-track">
                                <div class="timeline-progress" :style="{ width: timelineProgress + '%' }"></div>
                            </div>
                            <div class="timeline-steps">
                                <div
                                    v-for="(step, index) in statusFlow"
                                    :key="step"
                                    class="timeline-step"
                                    :class="{
                                        completed: currentStatusIndex > index,
                                        current: adminOrder.status === step,
                                    }"
                                >
                                    <div class="step-marker">
                                        <span class="step-dot"></span>
                                    </div>
                                    <span class="step-label">{{ getStatusLabel(step) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="actions-section" v-if="!isFinalStatus">
                            <h3 class="actions-title">اقدام بعدی</h3>
                            <div class="action-buttons">
                                <button
                                    v-for="btn in actionConfig.buttons"
                                    :key="btn.key"
                                    type="button"
                                    class="action-btn"
                                    :class="btn.type === 'danger' ? 'action-btn-danger' : 'action-btn-primary'"
                                    :disabled="statusLoading"
                                    @click="handleAction(btn.key)"
                                >
                                    <span v-if="statusLoading && pendingAction === btn.key" class="btn-spinner"></span>
                                    {{ btn.label }}
                                </button>
                            </div>
                        </div>

                        <!-- No actions -->
                        <div class="no-actions" v-else>
                            <span class="no-actions-icon">{{ adminOrder.status === 'delivered' ? '✔' : '✕' }}</span>
                            <span>این سفارش دیگر قابل تغییر وضعیت نیست.</span>
                        </div>

                        <!-- Cancelled Info -->
                        <div v-if="adminOrder.status === 'cancelled'" class="cancelled-info-bar">
                            <div class="cancelled-icon">✕</div>
                            <div class="cancelled-details">
                                <strong>این سفارش لغو شده</strong>
                                <span v-if="adminOrder.cancelled_at">{{ formatDate(adminOrder.cancelled_at) }}</span>
                            </div>
                            <div v-if="adminOrder.cancelled_reason" class="cancelled-reason">
                                دلیل: {{ adminOrder.cancelled_reason }}
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="detail-card items-card">
                        <h3 class="card-title">اقلام سفارش</h3>
                        <div class="card-body items-body">
                            <div class="items-table-wrapper">
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th>محصول</th>
                                            <th class="th-qty">تعداد</th>
                                            <th class="th-price">قیمت واحد</th>
                                            <th class="th-subtotal">مجموع</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in adminOrder.items" :key="item.id" class="item-row">
                                            <td>
                                                <div class="item-cell">
                                                    <div class="item-image">
                                                        <img
                                                            v-if="item.image"
                                                            :src="`/storage/${item.image}`"
                                                            :alt="item.product_name"
                                                            class="item-thumb"
                                                            @error="onImgError"
                                                        />
                                                        <div v-else class="item-thumb-placeholder">📦</div>
                                                    </div>
                                                    <div class="item-details">
                                                        <span class="item-name">{{ item.product_name }}</span>
                                                        <span v-if="item.sku" class="item-sku" dir="ltr">SKU: {{ item.sku }}</span>
                                                        <span v-if="item.attributes?.length" class="item-attrs">
                                                            {{ item.attributes.map(a => a.label || a.value).join('، ') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="td-qty">{{ item.quantity }}</td>
                                            <td class="td-price">{{ formatPrice(item.unit_price) }} تومان</td>
                                            <td class="td-subtotal"><strong>{{ formatPrice(item.subtotal) }} تومان</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Column -->
                <aside class="detail-sidebar">
                    <!-- Customer Info -->
                    <div class="detail-card">
                        <h3 class="card-title">اطلاعات مشتری</h3>
                        <div class="card-body">
                            <div class="info-row" v-if="adminOrder.user">
                                <span class="info-label">کاربر سایت</span>
                                <span class="info-value">{{ adminOrder.user.name }} ({{ adminOrder.user.email }})</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">نام گیرنده</span>
                                <span class="info-value">{{ adminOrder.customer_name }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">تلفن</span>
                                <span class="info-value" dir="ltr">{{ adminOrder.customer_phone }}</span>
                            </div>
                            <div class="info-row" v-if="adminOrder.customer_email">
                                <span class="info-label">ایمیل</span>
                                <span class="info-value">{{ adminOrder.customer_email }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">استان و شهر</span>
                                <span class="info-value">{{ adminOrder.shipping_province }}، {{ adminOrder.shipping_city }}</span>
                            </div>
                            <div class="info-row info-row-full">
                                <span class="info-label">آدرس</span>
                                <span class="info-value whitespace-pre-line">{{ adminOrder.shipping_address }}</span>
                            </div>
                            <div class="info-row" v-if="adminOrder.shipping_postal_code">
                                <span class="info-label">کد پستی</span>
                                <span class="info-value" dir="ltr">{{ adminOrder.shipping_postal_code }}</span>
                            </div>
                            <div class="info-row info-row-full" v-if="adminOrder.notes">
                                <span class="info-label">توضیحات مشتری</span>
                                <span class="info-value whitespace-pre-line">{{ adminOrder.notes }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="detail-card summary-card">
                        <h3 class="card-title">خلاصه مالی</h3>
                        <div class="card-body summary-body">
                            <div class="summary-row">
                                <span class="summary-label">مجموع اقلام</span>
                                <span class="summary-value">{{ formatPrice(adminOrder.subtotal) }} تومان</span>
                            </div>
                            <div class="summary-row" v-if="adminOrder.discount > 0">
                                <span class="summary-label">تخفیف</span>
                                <span class="summary-value discount">{{ formatPrice(adminOrder.discount) }} تومان</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">هزینه ارسال</span>
                                <span class="summary-value">{{ formatPrice(adminOrder.shipping_cost) }} تومان</span>
                            </div>
                            <div class="summary-row total">
                                <span class="summary-label">مبلغ نهایی</span>
                                <span class="summary-value">{{ formatPrice(adminOrder.total) }} تومان</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div class="detail-card">
                        <h3 class="card-title">پرداخت</h3>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">روش پرداخت</span>
                                <span class="info-value">{{ adminOrder.payment_method || '—' }}</span>
                            </div>
                            <div class="info-row" v-if="adminOrder.payment_ref">
                                <span class="info-label">شماره مرجع</span>
                                <span class="info-value font-mono" dir="ltr">{{ adminOrder.payment_ref }}</span>
                            </div>
                            <div class="info-row" v-if="adminOrder.paid_at">
                                <span class="info-label">تاریخ پرداخت</span>
                                <span class="info-value">{{ formatDate(adminOrder.paid_at) }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">وضعیت</span>
                                <span class="payment-badge" :class="adminOrder.paid_at ? 'paid' : 'unpaid'">
                                    {{ adminOrder.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Attempts -->
                    <div v-if="adminOrder.payment_attempts?.length" class="detail-card attempts-card">
                        <h3 class="card-title">تلاش‌های پرداخت</h3>
                        <div class="card-body">
                            <div class="attempts-list">
                                <div v-for="attempt in adminOrder.payment_attempts" :key="attempt.id" class="attempt-row">
                                    <div class="attempt-main">
                                        <span class="attempt-gateway">{{ attempt.gateway }}</span>
                                        <span class="attempt-amount" dir="ltr">{{ formatPrice(attempt.amount / 10) }} تومان</span>
                                        <span class="status-badge status-sm" :class="attempt.status === 'verified' ? 'status-delivered' : attempt.status === 'failed' ? 'status-cancelled' : ''">
                                            {{ attempt.status }}
                                        </span>
                                    </div>
                                    <div class="attempt-meta">
                                        <span v-if="attempt.authority" class="attempt-code" dir="ltr">Authority: {{ attempt.authority }}</span>
                                        <span v-if="attempt.reference" class="attempt-code" dir="ltr">Ref: {{ attempt.reference }}</span>
                                        <span class="attempt-date">{{ formatShortDate(attempt.verified_at || attempt.created_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </template>

        <!-- Cancel Modal -->
        <div v-if="showCancelModal" class="modal-overlay" @click.self="closeCancelModal">
            <div class="modal">
                <div class="modal-head">
                    <h3>لغو سفارش</h3>
                    <button type="button" class="modal-close" @click="closeCancelModal">✕</button>
                </div>
                <div class="modal-body">
                    <div class="cancel-warning">
                        <span class="cancel-warning-icon">⚠</span>
                        <span>آیا از لغو سفارش <strong dir="ltr">#{{ adminOrder?.id }}</strong> مطمئن هستید؟</span>
                    </div>
                    <div class="form-field">
                        <label>دلیل لغو (اختیاری)</label>
                        <textarea
                            v-model="cancelledReason"
                            rows="3"
                            class="form-textarea"
                            placeholder="دلیل لغو سفارش را وارد کنید..."
                        ></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel-modal" @click="closeCancelModal" :disabled="statusLoading">
                            انصراف
                        </button>
                        <button
                            type="button"
                            class="btn-confirm-cancel"
                            @click="confirmCancel"
                            :disabled="statusLoading"
                        >
                            <span v-if="statusLoading" class="btn-spinner"></span>
                            {{ statusLoading ? 'در حال لغو...' : 'لغو سفارش' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Action Modal -->
        <div v-if="showConfirmModal" class="modal-overlay" @click.self="closeConfirmModal">
            <div class="modal">
                <div class="modal-head">
                    <h3>تأیید عملیات</h3>
                    <button type="button" class="modal-close" @click="closeConfirmModal">✕</button>
                </div>
                <div class="modal-body">
                    <div class="confirm-warning">
                        <span class="confirm-warning-icon">⚠</span>
                        <span>{{ actionConfig.buttons.find(b => b.key === pendingAction)?.label || 'آیا از انجام این عملیات مطمئن هستید؟' }}</span>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel-modal" @click="closeConfirmModal" :disabled="statusLoading">
                            انصراف
                        </button>
                        <button
                            type="button"
                            class="btn-confirm-action"
                            @click="confirmAction"
                            :disabled="statusLoading"
                        >
                            <span v-if="statusLoading" class="btn-spinner"></span>
                            {{ statusLoading ? 'در حال انجام...' : 'تأیید' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.order-detail-section {
    direction: rtl;
    color: #1e293b;
    max-width: 1440px;
    margin: 0 auto;
    padding: 24px;
    font-family: inherit;
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 22px;
    margin-bottom: 22px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgb(15 23 42 / 4%);
}

.detail-title-block { min-width: 0; flex: 1; }
.detail-title { margin: 0; font-size: 1.35rem; font-weight: 800; line-height: 1.65; color: #0f172a; }
.order-id-large { color: #2563eb; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
.detail-date { display: block; margin-top: 3px; color: #64748b; font-size: .825rem; }
.detail-badges { display: flex; align-items: center; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }

.btn-back,
.btn-retry,
.action-btn,
.modal-close,
.btn-cancel-modal,
.btn-confirm-cancel,
.btn-confirm-action {
    border: 0;
    font: inherit;
    cursor: pointer;
    transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease, color .18s ease, transform .18s ease;
}

.btn-back { flex: 0 0 auto; padding: 10px 14px; color: #334155; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 9px; font-size: .875rem; font-weight: 700; }
.btn-back:hover { color: #1d4ed8; background: #eff6ff; border-color: #93c5fd; }
.btn-back:focus-visible,
.btn-retry:focus-visible,
.action-btn:focus-visible,
.modal-close:focus-visible,
.btn-cancel-modal:focus-visible,
.btn-confirm-cancel:focus-visible,
.btn-confirm-action:focus-visible,
.form-textarea:focus-visible { outline: 3px solid rgb(59 130 246 / 30%); outline-offset: 2px; }

.status-badge,
.payment-badge { display: inline-flex; align-items: center; justify-content: center; width: fit-content; border-radius: 999px; white-space: nowrap; font-size: .75rem; font-weight: 800; line-height: 1.3; }
.status-badge { padding: 5px 10px; background: #f1f5f9; color: #475569; }
.status-lg,
.payment-lg { padding: 7px 12px; font-size: .8rem; }
.status-sm { padding: 3px 8px; font-size: .68rem; }
.status-pending { color: #92400e; background: #fef3c7; }
.status-confirmed { color: #1d4ed8; background: #dbeafe; }
.status-processing { color: #6d28d9; background: #ede9fe; }
.status-shipped { color: #0369a1; background: #e0f2fe; }
.status-delivered { color: #047857; background: #d1fae5; }
.status-cancelled { color: #b91c1c; background: #fee2e2; }
.payment-badge.paid { color: #047857; background: #d1fae5; }
.payment-badge.unpaid { color: #b45309; background: #fef3c7; }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; min-height: 260px; color: #64748b; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; }
.spinner,
.btn-spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid #bfdbfe; border-top-color: #2563eb; border-radius: 50%; animation: order-spin .7s linear infinite; }
.btn-spinner { width: 14px; height: 14px; border-width: 2px; border-color: rgb(255 255 255 / 45%); border-top-color: #fff; vertical-align: -2px; margin-left: 5px; }
@keyframes order-spin { to { transform: rotate(360deg); } }

.alert { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 15px; margin-bottom: 18px; border: 1px solid transparent; border-radius: 11px; font-size: .9rem; font-weight: 600; }
.alert.success { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }
.alert.error { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
.order-toast { animation: order-slide-in .25s ease-out; }
@keyframes order-slide-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
.btn-retry { flex: 0 0 auto; padding: 7px 10px; color: #fff; background: #dc2626; border-radius: 7px; font-size: .8rem; font-weight: 700; }
.btn-retry:hover { background: #b91c1c; }

.detail-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(290px, 360px); align-items: start; gap: 22px; }
.detail-main,
.detail-sidebar { display: grid; gap: 22px; min-width: 0; }
.detail-card { overflow: hidden; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 4px 16px rgb(15 23 42 / 4%); }
.card-title { padding: 15px 18px; margin: 0; color: #0f172a; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 1rem; font-weight: 800; }
.card-body { padding: 16px 18px; }

.status-management-card { padding: 20px; }
.status-management-card .card-title { padding: 0 0 14px; background: transparent; }
.current-status-row { display: flex; align-items: center; gap: 10px; }
.current-status-label { color: #64748b; font-size: .875rem; font-weight: 700; }
.status-timeline { position: relative; margin: 30px 10px 22px; }
.timeline-track { position: absolute; right: 0; left: 0; top: 13px; height: 4px; overflow: hidden; background: #e2e8f0; border-radius: 999px; }
.timeline-progress { height: 100%; background: linear-gradient(90deg, #2563eb, #38bdf8); border-radius: inherit; transition: width .35s ease; }
.timeline-steps { position: relative; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); }
.timeline-step { display: grid; justify-items: center; gap: 8px; min-width: 0; color: #94a3b8; text-align: center; }
.step-marker { display: grid; place-items: center; width: 28px; height: 28px; background: #fff; border: 3px solid #cbd5e1; border-radius: 50%; z-index: 1; }
.step-dot { width: 8px; height: 8px; background: #cbd5e1; border-radius: 50%; }
.timeline-step.completed,
.timeline-step.current { color: #1d4ed8; }
.timeline-step.completed .step-marker,
.timeline-step.current .step-marker { border-color: #2563eb; }
.timeline-step.completed .step-marker { background: #2563eb; }
.timeline-step.completed .step-dot { background: #fff; }
.timeline-step.current .step-dot { background: #2563eb; box-shadow: 0 0 0 4px #dbeafe; }
.step-label { max-width: 90px; font-size: .72rem; font-weight: 700; line-height: 1.55; }
.actions-section { padding-top: 18px; margin-top: 20px; border-top: 1px dashed #cbd5e1; }
.actions-title { margin: 0 0 12px; color: #475569; font-size: .875rem; font-weight: 800; }
.action-buttons { display: flex; flex-wrap: wrap; gap: 10px; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; gap: 5px; min-height: 40px; padding: 9px 14px; border-radius: 9px; font-size: .85rem; font-weight: 800; }
.action-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 5px 12px rgb(15 23 42 / 12%); }
.action-btn:disabled,
.btn-cancel-modal:disabled,
.btn-confirm-cancel:disabled,
.btn-confirm-action:disabled { cursor: not-allowed; opacity: .6; }
.action-btn-primary,
.btn-confirm-action { color: #fff; background: #2563eb; }
.action-btn-primary:hover:not(:disabled),
.btn-confirm-action:hover:not(:disabled) { background: #1d4ed8; }
.action-btn-danger,
.btn-confirm-cancel { color: #fff; background: #dc2626; }
.action-btn-danger:hover:not(:disabled),
.btn-confirm-cancel:hover:not(:disabled) { background: #b91c1c; }
.no-actions { display: flex; align-items: center; gap: 9px; padding: 13px; margin-top: 20px; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: .85rem; }
.no-actions-icon { display: grid; place-items: center; width: 24px; height: 24px; color: #fff; background: #16a34a; border-radius: 50%; font-weight: 900; }
.cancelled-info-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px; margin-top: 16px; color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; }
.cancelled-icon { display: grid; place-items: center; width: 30px; height: 30px; color: #fff; background: #dc2626; border-radius: 50%; font-weight: 900; }
.cancelled-details { display: grid; gap: 2px; font-size: .83rem; }
.cancelled-reason { flex: 1 1 180px; padding-right: 12px; border-right: 1px solid #fecaca; font-size: .82rem; }

.items-body { padding: 0; }
.items-table-wrapper { width: 100%; overflow-x: auto; overscroll-behavior-inline: contain; }
.items-table { width: 100%; min-width: 650px; border-collapse: collapse; text-align: right; }
.items-table th { padding: 12px 16px; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: .75rem; font-weight: 800; white-space: nowrap; }
.items-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: .85rem; vertical-align: middle; }
.items-table tbody .item-row:last-child td { border-bottom: 0; }
.item-row:hover { background: #f8fafc; }
.th-qty,
.td-qty { width: 80px; text-align: center; }
.th-price,
.th-subtotal,
.td-price,
.td-subtotal { white-space: nowrap; }
.item-cell { display: flex; align-items: center; gap: 12px; min-width: 230px; }
.item-image { flex: 0 0 auto; }
.item-thumb,
.item-thumb-placeholder { display: grid; place-items: center; width: 48px; height: 48px; overflow: hidden; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 9px; object-fit: cover; }
.item-details { display: grid; gap: 3px; min-width: 0; }
.item-name { overflow: hidden; color: #1e293b; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
.item-sku,
.item-attrs { color: #64748b; font-size: .74rem; }
.td-subtotal { color: #0f172a; }

.info-row { display: grid; grid-template-columns: minmax(90px, .7fr) minmax(0, 1.3fr); gap: 12px; padding: 11px 0; border-bottom: 1px solid #f1f5f9; font-size: .84rem; }
.card-body .info-row:last-child { padding-bottom: 0; border-bottom: 0; }
.info-row-full { grid-template-columns: 1fr; gap: 5px; }
.info-label { color: #64748b; font-weight: 700; }
.info-value { min-width: 0; color: #1e293b; font-weight: 600; overflow-wrap: anywhere; }
.whitespace-pre-line { white-space: pre-line; line-height: 1.85; }
.font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: .78rem; }

.summary-body { display: grid; gap: 0; }
.summary-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; color: #64748b; font-size: .85rem; }
.summary-value { color: #334155; font-weight: 750; white-space: nowrap; }
.summary-value.discount { color: #dc2626; }
.summary-row.total { padding: 14px 12px; margin: 7px -2px -2px; color: #0f172a; background: #eff6ff; border-radius: 10px; }
.summary-row.total .summary-label,
.summary-row.total .summary-value { color: #1d4ed8; font-size: .96rem; font-weight: 900; }

.attempts-list { display: grid; gap: 10px; }
.attempt-row { padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; }
.attempt-main { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; }
.attempt-gateway { color: #1e293b; font-size: .82rem; font-weight: 800; }
.attempt-amount { color: #475569; font-size: .78rem; font-weight: 700; }
.attempt-meta { display: flex; flex-wrap: wrap; gap: 7px 12px; margin-top: 8px; color: #64748b; font-size: .72rem; }
.attempt-code { overflow-wrap: anywhere; }
.attempt-date { margin-right: auto; }

.modal-overlay { position: fixed; inset: 0; display: grid; place-items: center; z-index: 1000; padding: 20px; background: rgb(15 23 42 / 55%); backdrop-filter: blur(2px); }
.modal { width: min(100%, 480px); overflow: hidden; color: #1e293b; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 24px 50px rgb(15 23 42 / 28%); animation: modal-enter .2s ease-out; }
@keyframes modal-enter { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.modal-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.modal-head h3 { margin: 0; color: #0f172a; font-size: 1rem; font-weight: 850; }
.modal-close { display: grid; place-items: center; width: 30px; height: 30px; padding: 0; color: #64748b; background: transparent; border-radius: 7px; font-size: 1rem; }
.modal-close:hover { color: #b91c1c; background: #fee2e2; }
.modal-body { padding: 18px; }
.cancel-warning,
.confirm-warning { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border-radius: 10px; font-size: .87rem; line-height: 1.8; }
.cancel-warning { color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; }
.confirm-warning { color: #92400e; background: #fffbeb; border: 1px solid #fde68a; }
.cancel-warning-icon,
.confirm-warning-icon { flex: 0 0 auto; font-size: 1.1rem; line-height: 1.45; }
.form-field { display: grid; gap: 7px; margin-top: 16px; }
.form-field label { color: #475569; font-size: .83rem; font-weight: 800; }
.form-textarea { width: 100%; min-height: 86px; padding: 10px 12px; resize: vertical; color: #1e293b; background: #fff; border: 1px solid #cbd5e1; border-radius: 9px; font: inherit; line-height: 1.7; }
.form-textarea:focus { border-color: #3b82f6; outline: 0; box-shadow: 0 0 0 3px rgb(59 130 246 / 14%); }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
.btn-cancel-modal,
.btn-confirm-cancel,
.btn-confirm-action { min-height: 40px; padding: 9px 15px; border-radius: 9px; font-size: .85rem; font-weight: 800; }
.btn-cancel-modal { color: #475569; background: #f8fafc; border: 1px solid #cbd5e1; }
.btn-cancel-modal:hover:not(:disabled) { color: #1e293b; background: #f1f5f9; }

:global(.dark) .order-detail-section { color: #e2e8f0; }
:global(.dark) .detail-header,
:global(.dark) .detail-card,
:global(.dark) .modal { background: #172033; border-color: #334155; box-shadow: 0 5px 18px rgb(0 0 0 / 18%); }
:global(.dark) .detail-title,
:global(.dark) .card-title,
:global(.dark) .info-value,
:global(.dark) .item-name,
:global(.dark) .td-subtotal,
:global(.dark) .modal-head h3 { color: #f8fafc; }
:global(.dark) .detail-date,
:global(.dark) .current-status-label,
:global(.dark) .info-label,
:global(.dark) .item-sku,
:global(.dark) .item-attrs,
:global(.dark) .summary-row,
:global(.dark) .attempt-meta { color: #94a3b8; }
:global(.dark) .btn-back,
:global(.dark) .card-title,
:global(.dark) .items-table th,
:global(.dark) .modal-head,
:global(.dark) .no-actions,
:global(.dark) .attempt-row { background: #1e293b; border-color: #334155; }
:global(.dark) .btn-back { color: #cbd5e1; }
:global(.dark) .items-table td,
:global(.dark) .info-row { color: #cbd5e1; border-color: #293548; }
:global(.dark) .item-row:hover { background: #1e293b; }
:global(.dark) .summary-value,
:global(.dark) .attempt-gateway { color: #e2e8f0; }
:global(.dark) .summary-row.total { background: #172554; }
:global(.dark) .timeline-track { background: #334155; }
:global(.dark) .step-marker { background: #172033; border-color: #475569; }
:global(.dark) .form-textarea { color: #e2e8f0; background: #0f172a; border-color: #475569; }
:global(.dark) .loading-state { color: #cbd5e1; background: #172033; border-color: #334155; }

@media (max-width: 900px) {
    .order-detail-section { padding: 16px; }
    .detail-grid { grid-template-columns: 1fr; }
    .detail-sidebar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .detail-sidebar .detail-card:last-child:nth-child(odd) { grid-column: 1 / -1; }
}

@media (max-width: 640px) {
    .order-detail-section { padding: 12px; }
    .detail-header { flex-wrap: wrap; padding: 16px; margin-bottom: 16px; border-radius: 13px; }
    .detail-title-block { order: -1; flex-basis: calc(100% - 120px); }
    .detail-title { font-size: 1.13rem; }
    .detail-badges { width: 100%; justify-content: flex-start; }
    .btn-back { padding: 8px 10px; font-size: .78rem; }
    .detail-main,
    .detail-sidebar { gap: 16px; }
    .detail-sidebar { grid-template-columns: 1fr; }
    .detail-sidebar .detail-card:last-child:nth-child(odd) { grid-column: auto; }
    .detail-card { border-radius: 13px; }
    .status-management-card { padding: 16px; }
    .status-timeline { margin-right: 0; margin-left: 0; overflow-x: auto; padding-bottom: 5px; }
    .timeline-track,
    .timeline-steps { min-width: 410px; }
    .step-label { font-size: .66rem; }
    .action-buttons,
    .action-btn { width: 100%; }
    .cancelled-info-bar { align-items: flex-start; }
    .cancelled-reason { flex-basis: 100%; padding: 9px 0 0; border-top: 1px solid #fecaca; border-right: 0; }
    .items-table-wrapper { overflow: visible; }
    .items-table,
    .items-table tbody,
    .items-table .item-row,
    .items-table td { display: block; width: 100%; min-width: 0; }
    .items-table { min-width: 0; }
    .items-table thead { display: none; }
    .items-table .item-row { padding: 13px; border-bottom: 1px solid #e2e8f0; }
    .items-table tbody .item-row:last-child { border-bottom: 0; }
    .items-table td { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border: 0; text-align: left; }
    .items-table td::before { flex: 0 0 auto; color: #64748b; font-size: .75rem; font-weight: 800; text-align: right; content: attr(data-label); }
    .items-table td:first-child { display: block; padding-top: 0; }
    .items-table td:first-child::before { display: none; }
    .items-table .td-qty::before { content: 'تعداد'; }
    .items-table .td-price::before { content: 'قیمت واحد'; }
    .items-table .td-subtotal::before { content: 'مجموع'; }
    .item-cell { min-width: 0; }
    .item-name { white-space: normal; }
    .td-qty,
    .td-price,
    .td-subtotal { text-align: left; }
    .info-row { grid-template-columns: 1fr; gap: 4px; padding: 10px 0; }
    .summary-row.total { margin-right: 0; margin-left: 0; }
    .attempt-date { width: 100%; margin-right: 0; }
    .modal-overlay { align-items: end; padding: 0; }
    .modal { width: 100%; max-height: 92dvh; overflow-y: auto; border-radius: 16px 16px 0 0; }
    .modal-actions { flex-direction: column-reverse; }
    .btn-cancel-modal,
    .btn-confirm-cancel,
    .btn-confirm-action { width: 100%; }
}

@media (max-width: 380px) {
    .detail-title-block { flex-basis: 100%; }
    .detail-header { align-items: flex-start; }
}
</style>
