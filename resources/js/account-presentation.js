// Shared presentation helpers for the customer account area.
// Dependency free on purpose: labels, capabilities and steps here mirror what the
// backend actually stores and allows, so nothing has to be invented at render time.

export const ACCOUNT_NAV = [
    { key: 'profile', href: '/account', label: 'حساب کاربری', hint: 'نام و ایمیل', icon: 'fa-regular fa-user' },
    { key: 'orders', href: '/orders', label: 'سفارش‌های من', hint: 'پیگیری و پرداخت', icon: 'fa-regular fa-receipt' },
    { key: 'wishlist', href: '/wishlist', label: 'علاقه‌مندی‌ها', hint: 'محصولات ذخیره‌شده', icon: 'fa-regular fa-heart' },
];

// PUT /api/customer/profile validates exactly these two keys.
export const PROFILE_EDITABLE_FIELDS = [
    { key: 'name', label: 'نام', type: 'text', autocomplete: 'name', maxlength: 255, required: true, hint: 'نامی که روی بسته‌بندی سفارش درج می‌شود.' },
    { key: 'email', label: 'ایمیل', type: 'email', autocomplete: 'email', maxlength: 255, required: true, dir: 'ltr', hint: 'رسید سفارش‌ها به این نشانی ارسال می‌شود.' },
];

// /api/customer/me returns the mobile, but the update endpoint does not accept it,
// so it is displayed as a fact and never offered as an editable control.
export const PROFILE_FIXED_FIELDS = [
    { key: 'mobile', label: 'شماره موبایل', dir: 'ltr', hint: 'این شماره هنگام ثبت‌نام ثبت شده و از این بخش قابل تغییر نیست.' },
];

export const ORDER_FLOW = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

// Mirrors orderStatuses in customer-orders.js; kept here so presentation stays pure.
export const ORDER_STEP_LABELS = {
    pending: 'در انتظار تأیید',
    confirmed: 'تأیید شده',
    processing: 'در حال پردازش',
    shipped: 'ارسال شده',
    delivered: 'تحویل شده',
};

export const ORDER_STATUS_LABELS = {
    ...ORDER_STEP_LABELS,
    cancelled: 'لغو شده',
};

export function orderStatusLabel(status) {
    return ORDER_STATUS_LABELS[status] || String(status || '');
}

/* The timeline only exists for statuses the backend can actually hold. A cancelled
   order returns no steps rather than pretending its flow is still reachable, and an
   unknown status returns nothing instead of inventing a position. */
export function orderSteps(order) {
    const status = String(order?.status || '');
    const current = ORDER_FLOW.indexOf(status);
    if (current < 0) return [];

    return ORDER_FLOW.map((step, index) => ({
        key: step,
        label: ORDER_STEP_LABELS[step],
        state: index < current ? 'done' : index === current ? 'current' : 'upcoming',
    }));
}

export function orderIsCancelled(order) {
    return order?.status === 'cancelled';
}

export function orderIsPaid(order) {
    return Boolean(order?.paid_at || order?.payment_ref);
}

/* Only pending and confirmed orders may transition to cancelled, so the action is
   offered for exactly those two states. */
export function orderCanCancel(order) {
    return order?.status === 'pending' || order?.status === 'confirmed';
}

/* A payment is only outstanding while the order is still pending and unpaid;
   confirmed, shipped, delivered and cancelled orders never offer a retry. */
export function orderCanPay(order) {
    return order?.status === 'pending' && !orderIsPaid(order);
}

export function orderItemCount(order) {
    const count = Number(order?.items_count ?? order?.items?.length ?? 0);
    return Number.isFinite(count) && count > 0 ? count : 0;
}

/* Validation errors come from the same Laravel 422 shape the rest of the app uses. */
export function firstError(errors, key) {
    return errors?.[key]?.[0] || '';
}

export function errorSummary(errors) {
    return Object.values(errors || {})
        .flat()
        .filter((message) => typeof message === 'string' && message);
}

/* Validation before the request: the same rules the profile endpoint applies. */
export function profileValidation(values) {
    const errors = {};
    if (!values.name?.trim()) errors.name = ['نام را وارد کنید.'];
    if (!values.email?.trim()) errors.email = ['ایمیل را وارد کنید.'];
    else if (!/^\S+@\S+\.\S+$/.test(values.email.trim())) errors.email = ['قالب ایمیل معتبر نیست.'];
    return errors;
}

/* Reset the password, change the mobile and edit the address are not backend
   capabilities, so no account page may render controls for them. */
export const UNSUPPORTED_PROFILE_ACTIONS = ['رمز عبور', 'تغییر شماره موبایل', 'آدرس‌های ذخیره‌شده'];
