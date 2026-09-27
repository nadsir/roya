import { ref, watch, onScopeDispose } from 'vue';
import axios from 'axios';
import { state as authState, isLoggedIn, getToken, loadUser } from './auth-state.js';

export const orderStatuses = {
    pending: 'در انتظار تأیید',
    confirmed: 'تأیید شده',
    processing: 'در حال پردازش',
    shipped: 'ارسال شده',
    delivered: 'تحویل شده',
    cancelled: 'لغو شده',
};

export function statusLabel(status) {
    return orderStatuses[status] || status;
}

export function formatPrice(value) {
    return Number(value || 0).toLocaleString('fa-IR');
}

export function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('fa-IR') : '—';
}

export async function cancelOrder(orderId, reason = null) {
    const token = getToken();
    const payload = {};
    if (reason) payload.reason = reason;
    const response = await axios.patch(`/api/customer/orders/${orderId}/cancel`, payload, {
        headers: { Authorization: `Bearer ${token}` },
    });
    return response.data;
}

// Each page owns its data; logout/account changes clear it and cancel old reads.
export function useCustomerOrders(url) {
    const data = ref(null);
    const loading = ref(true);
    const error = ref('');
    let controller;

    async function load(params = {}) {
        if (!isLoggedIn.value || authState.loading) return;
        controller?.abort();
        const request = new AbortController();
        controller = request;
        const token = getToken();
        const userId = authState.user.id;
        const current = () => !request.signal.aborted
            && authState.user?.id === userId && getToken() === token;
        loading.value = true;
        error.value = '';
        data.value = null;
        try {
            const response = await axios.get(url, { params, signal: request.signal });
            if (current()) data.value = response.data;
        } catch (failure) {
            if (!current()) return;
            if (failure.response?.status === 401) {
                await loadUser();
                if (!current()) return;
            }
            error.value = failure.response?.status === 404
                ? 'سفارش مورد نظر پیدا نشد.'
                : 'دریافت اطلاعات سفارش انجام نشد. لطفاً دوباره تلاش کنید.';
        } finally {
            if (current()) loading.value = false;
        }
    }

    watch([() => authState.user?.id, () => authState.loading], ([userId, authLoading]) => {
        controller?.abort();
        data.value = null;
        error.value = '';
        loading.value = authLoading;
        if (authLoading) return;
        if (!userId) {
            window.location.replace('/login');
            return;
        }
        load();
    }, { immediate: true });

    onScopeDispose(() => controller?.abort());
    return { data, loading, error, load, isLoggedIn };
}
