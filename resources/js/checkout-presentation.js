// Checkout presentation helpers. Field rules mirror CustomerCheckoutController::store,
// the backend stays authoritative for price, stock and the final order total.
import { cartItemQuantity } from './cart-presentation.js';

export const CHECKOUT_PHASES = {
    IDLE: 'idle',
    ORDERING: 'ordering',
    PAYING: 'paying',
    PAY_FAILED: 'pay-failed',
};

function field(key, label, options = {}) {
    return {
        key,
        label,
        control: 'input',
        span: 1,
        optional: false,
        autocomplete: 'off',
        maxlength: 255,
        ...options,
    };
}

// Mirrors the server rules: customer_name, customer_phone, customer_email.
export const CHECKOUT_CONTACT_FIELDS = [
    field('customer_name', 'نام و نام خانوادگی', { autocomplete: 'name', maxlength: 255 }),
    field('customer_phone', 'شماره تلفن', { type: 'tel', autocomplete: 'tel', maxlength: 32, inputmode: 'tel', dir: 'ltr' }),
    field('customer_email', 'ایمیل', { type: 'email', autocomplete: 'email', maxlength: 255, dir: 'ltr' }),
];

// Mirrors the server rules: province, city, postal_code, address, notes.
export const CHECKOUT_SHIPPING_FIELDS = [
    field('shipping_province', 'استان', { autocomplete: 'address-level1', maxlength: 100 }),
    field('shipping_city', 'شهر', { autocomplete: 'address-level2', maxlength: 100 }),
    field('shipping_postal_code', 'کد پستی', { autocomplete: 'postal-code', maxlength: 20, inputmode: 'numeric', dir: 'ltr' }),
    field('shipping_address', 'آدرس کامل', { control: 'textarea', rows: 4, span: 2, maxlength: 2000, autocomplete: 'street-address' }),
    field('notes', 'توضیحات سفارش', { control: 'textarea', rows: 3, span: 2, maxlength: 2000, optional: true }),
];

export const CHECKOUT_PAYMENT_NOTE = 'پس از ثبت سفارش به درگاه بانکی هدایت می‌شوید. سفارش تنها پس از تأیید درگاه نهایی می‌شود.';

export const CHECKOUT_AUTHORITY_NOTE = 'موجودی، قیمت واحد و مبلغ نهایی در سمت سرور بررسی و ثبت می‌شود؛ مبلغ این صفحه پیش‌نمایش است.';

export function submitLabel(phase) {
    switch (phase) {
        case CHECKOUT_PHASES.ORDERING: return 'در حال ثبت سفارش…';
        case CHECKOUT_PHASES.PAYING: return 'در حال اتصال به درگاه پرداخت…';
        case CHECKOUT_PHASES.PAY_FAILED: return 'پرداخت انجام نشد';
        default: return 'پرداخت و تکمیل سفارش';
    }
}

export function checkoutCount(items) {
    return (items || []).reduce((sum, item) => sum + cartItemQuantity(item), 0);
}

// Discount and shipping are hardcoded to zero by the backend, so the page never invents them.
export function checkoutTotals(items, cartTotal) {
    const subtotal = Number(cartTotal);
    return {
        count: checkoutCount(items),
        subtotal: Number.isFinite(subtotal) ? subtotal : 0,
        discount: 0,
        shipping: 0,
        total: Number.isFinite(subtotal) ? subtotal : 0,
    };
}

function messagesFor(errors, key) {
    const value = errors?.[key];
    return Array.isArray(value) ? value.filter(Boolean).map(String) : value ? [String(value)] : [];
}

export function fieldError(errors, key) {
    return messagesFor(errors, key)[0] || '';
}

export function lineErrorMessages(errors, index) {
    return Object.entries(errors || {})
        .filter(([key]) => key.startsWith(`items.${index}.`))
        .flatMap(([key, value]) => messagesFor({ [key]: value }, key).map((message) => ({ field: key.split('.').pop(), message })));
}

export function errorSummary(errors) {
    return Object.entries(errors || {}).flatMap(([key, value]) => {
        const messages = messagesFor({ [key]: value }, key);
        return messages.map((message) => ({ key, message }));
    });
}

export function isOrderPaid(order) {
    return Boolean(order?.paid_at || order?.payment_ref);
}

export function canRetryPayment(order) {
    return order?.status === 'pending' && !isOrderPaid(order);
}

const PAYMENT_FAILURES = {
    no_authority: 'درگاه پرداخت شناسه تراکنش را برنگرداند. مبلغی از حساب شما کسر نشده است.',
    order_not_found: 'سفارش مورد نظر یافت نشد. با پشتیبانی تماس بگیرید.',
    verification_failed: 'تراکنش توسط درگاه پرداخت تأیید نشد. می‌توانید دوباره تلاش کنید.',
};

// The query string is only a hint; order.paid_at and order.payment_ref decide the outcome.
export function paymentReturnState(search, order) {
    const params = new URLSearchParams(String(search || '').replace(/^\?/, ''));
    const hint = params.get('payment');
    const paid = isOrderPaid(order);

    if (hint === 'success' && paid) {
        return {
            tone: 'success',
            title: 'پرداخت با موفقیت تأیید شد',
            message: 'پرداخت شما توسط درگاه بانکی تأیید شد و سفارش در حال پردازش است.',
            reference: order.payment_ref || '',
            retry: false,
        };
    }

    if (hint === 'success' && !paid) {
        return {
            tone: 'failure',
            title: 'پرداخت تأیید نشد',
            message: 'درگاه پرداخت پرداخت شما را تأیید نکرد. در صورت کسر وجه، مبلغ تا پایان روز به حساب شما بازگردانده می‌شود.',
            reference: '',
            retry: canRetryPayment(order),
        };
    }

    if (hint === 'failed' && paid) {
        return {
            tone: 'success',
            title: 'پرداخت این سفارش تأیید شده است',
            message: 'تراکنش دیگری برای این سفارش با موفقیت تأیید شده و نیازی به پرداخت مجدد نیست.',
            reference: order.payment_ref || '',
            retry: false,
        };
    }

    if (hint === 'failed') {
        return {
            tone: 'failure',
            title: 'پرداخت انجام نشد',
            message: PAYMENT_FAILURES[params.get('reason')] || 'تراکنش پرداخت تکمیل نشد. می‌توانید دوباره تلاش کنید.',
            reference: '',
            retry: canRetryPayment(order),
        };
    }

    return { tone: 'none', title: '', message: '', reference: '', retry: false };
}
