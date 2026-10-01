<script setup>
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import CheckoutField from './components/checkout/CheckoutField.vue';
import CheckoutAlert from './components/checkout/CheckoutAlert.vue';
import PendingPaymentPanel from './components/checkout/PendingPaymentPanel.vue';
import OrderSummaryCard from './components/checkout/OrderSummaryCard.vue';
import { getCartItems, clearCart, cartTotal } from './cart-state.js';
import { state as authState, isLoggedIn, loadUser } from './auth-state.js';
import {
    CHECKOUT_CONTACT_FIELDS,
    CHECKOUT_PAYMENT_NOTE,
    CHECKOUT_PHASES,
    CHECKOUT_SHIPPING_FIELDS,
    checkoutTotals,
    errorSummary,
    fieldError,
    lineErrorMessages,
    submitLabel,
} from './checkout-presentation.js';
import '../css/checkout.css';

const items = computed(() => getCartItems());
const totals = computed(() => checkoutTotals(items.value, cartTotal.value));
const form = reactive({
    customer_name: '', customer_phone: '', customer_email: '',
    shipping_province: '', shipping_city: '', shipping_postal_code: '',
    shipping_address: '', notes: '',
});

const phase = ref(CHECKOUT_PHASES.IDLE);
const error = ref('');
const validationErrors = ref({});
const createdOrder = ref(null);
const payError = ref('');
const retrying = ref(false);
const busy = computed(() => phase.value === CHECKOUT_PHASES.ORDERING || phase.value === CHECKOUT_PHASES.PAYING);
const summaryErrors = computed(() => errorSummary(validationErrors.value));
const lineErrors = computed(() => items.value.map((item, index) => lineErrorMessages(validationErrors.value, index)));
const locked = computed(() => busy.value || Boolean(createdOrder.value));

function goToLogin() {
    window.location.replace('/login?redirect=/checkout');
}

watch([() => authState.loading, () => authState.user?.id], ([loading, userId]) => {
    if (loading) return;
    if (!userId) {
        goToLogin();
        return;
    }
    if (!form.customer_name) form.customer_name = authState.user.name || '';
    if (!form.customer_email) form.customer_email = authState.user.email || '';
}, { immediate: true });

// The existing pay endpoint is the only payment entry point; the gateway is never called from Vue.
async function startPayment(order) {
    const { data } = await axios.post(`/api/customer/orders/${order.id}/pay`);
    if (!data?.payment_url) throw new Error('missing payment url');
    window.location.href = data.payment_url;
}

function rememberReceipt(order) {
    try {
        sessionStorage.setItem('turbopart-order-receipt', JSON.stringify({
            id: order.id, user_id: order.user_id, total: order.total,
        }));
    } catch {}
}

async function submitOrder() {
    if (locked.value || !isLoggedIn.value || !items.value.length) return;
    phase.value = CHECKOUT_PHASES.ORDERING;
    error.value = '';
    payError.value = '';
    validationErrors.value = {};

    let order;
    try {
        const { data } = await axios.post('/api/customer/checkout', {
            ...form,
            items: items.value.map((item) => ({
                product_id: item.product_id,
                variant_id: item.variant_id || null,
                quantity: item.quantity,
            })),
        });
        order = data.order;
    } catch (failure) {
        if (failure.response?.status === 401) {
            await loadUser();
            if (!isLoggedIn.value) {
                goToLogin();
                return;
            }
        }
        validationErrors.value = failure.response?.data?.errors || {};
        error.value = failure.response?.data?.message || 'ثبت سفارش انجام نشد. سبد خرید شما حفظ شده است؛ لطفاً اتصال را بررسی کنید.';
        phase.value = CHECKOUT_PHASES.IDLE;
        return;
    }

    createdOrder.value = { id: order.id, total: order.total };
    rememberReceipt(order);
    clearCart();
    phase.value = CHECKOUT_PHASES.PAYING;
    try {
        await startPayment(order);
    } catch (failure) {
        payError.value = failure.response?.data?.message || 'اتصال به درگاه پرداخت انجام نشد. مبلغی از حساب شما کسر نشده است.';
        phase.value = CHECKOUT_PHASES.PAY_FAILED;
    }
}

// Retries the existing order, never a second one.
async function retryPayment() {
    const order = createdOrder.value;
    if (!order || retrying.value) return;
    retrying.value = true;
    payError.value = '';
    try {
        await startPayment(order);
    } catch (failure) {
        payError.value = failure.response?.data?.message || 'اتصال به درگاه پرداخت انجام نشد. مبلغی از حساب شما کسر نشده است.';
    } finally {
        retrying.value = false;
    }
}
</script>

<template>
    <div class="co-page" dir="rtl">
        <SiteHeader />

        <main class="sf-container co-main">
            <nav class="co-breadcrumb sf-type-caption" aria-label="مسیر صفحه">
                <a href="/store">فروشگاه</a>
                <span aria-hidden="true">/</span>
                <a href="/cart">سبد خرید</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">تکمیل خرید</span>
            </nav>

            <header class="co-header">
                <div>
                    <p class="co-eyebrow" lang="en" dir="ltr">CHECKOUT</p>
                    <h1 class="sf-type-h1">تکمیل خرید</h1>
                </div>
                <p v-if="items.length" class="co-header-count sf-type-small">
                    {{ totals.count.toLocaleString('fa-IR') }} کالا در سبد
                </p>
            </header>

            <ol class="co-steps" aria-label="مراحل خرید">
                <li class="co-step co-step--done">
                    <span class="co-step-index" aria-hidden="true">۱</span>
                    <span>سبد خرید</span>
                </li>
                <li class="co-step co-step--current" aria-current="step">
                    <span class="co-step-index" aria-hidden="true">۲</span>
                    <span>اطلاعات ارسال و پرداخت</span>
                </li>
                <li class="co-step">
                    <span class="co-step-index" aria-hidden="true">۳</span>
                    <span>تأیید پرداخت</span>
                </li>
            </ol>

            <p v-if="authState.loading || !isLoggedIn" class="sf-shell-state" role="status">در حال بررسی حساب کاربری…</p>

            <div v-else-if="!items.length" class="co-empty sf-shell-state">
                <span class="co-empty-mark" aria-hidden="true"><i class="fa-regular fa-bag" /></span>
                <h2 class="sf-type-h3">سبد خرید شما خالی است</h2>
                <p class="sf-type-body">برای ثبت سفارش ابتدا کالایی انتخاب کنید.</p>
                <a class="sf-button" href="/store">مشاهده محصولات</a>
            </div>

            <div v-else class="co-layout">
                <div class="co-column">
                    <form id="checkout-form" class="co-form" @submit.prevent="submitOrder">
                        <PendingPaymentPanel v-if="createdOrder" :order-id="createdOrder.id" :total="createdOrder.total"
                            :error="payError" :retrying="retrying" @retry="retryPayment" />

                        <CheckoutAlert v-else-if="error" :message="error" :errors="summaryErrors" />

                        <template v-if="!createdOrder">
                            <section class="co-section" aria-labelledby="co-contact-title">
                                <div class="co-section-head">
                                    <span class="co-section-index" aria-hidden="true">۰۱</span>
                                    <h2 id="co-contact-title" class="co-section-title sf-type-h3">اطلاعات گیرنده</h2>
                                </div>
                                <div class="co-grid">
                                    <CheckoutField v-for="field in CHECKOUT_CONTACT_FIELDS" :key="field.key" :field="field"
                                        v-model="form[field.key]" :error="fieldError(validationErrors, field.key)" />
                                </div>
                            </section>
    
                            <section class="co-section" aria-labelledby="co-shipping-title">
                                <div class="co-section-head">
                                    <span class="co-section-index" aria-hidden="true">۰۲</span>
                                    <h2 id="co-shipping-title" class="co-section-title sf-type-h3">آدرس ارسال</h2>
                                </div>
                                <div class="co-grid">
                                    <CheckoutField v-for="field in CHECKOUT_SHIPPING_FIELDS" :key="field.key" :field="field"
                                        v-model="form[field.key]" :error="fieldError(validationErrors, field.key)" />
                                </div>
                            </section>
    
                            <section class="co-section" aria-labelledby="co-payment-title">
                                <div class="co-section-head">
                                    <span class="co-section-index" aria-hidden="true">۰۳</span>
                                    <h2 id="co-payment-title" class="co-section-title sf-type-h3">روش پرداخت</h2>
                                </div>
                                <div class="co-pay">
                                    <span class="co-pay-mark" aria-hidden="true"><i class="fa-solid fa-credit-card" /></span>
                                    <div>
                                        <p class="co-pay-title">پرداخت اینترنتی از طریق درگاه بانکی</p>
                                        <p class="co-pay-text">{{ CHECKOUT_PAYMENT_NOTE }}</p>
                                    </div>
                                </div>
                            </section>
    
                            <div class="co-actions">
                                <button type="submit" class="sf-button co-submit" :disabled="locked || !items.length">
                                    {{ submitLabel(phase) }}
                                </button>
                                <a class="sf-text-link" href="/cart">بازگشت به سبد خرید</a>
                            </div>
                        </template>
                    </form>
                </div>

                <OrderSummaryCard class="co-column" :items="items" :count="totals.count" :subtotal="totals.subtotal"
                    :total="totals.total" :line-errors="lineErrors" />
            </div>
        </main>

        <div v-if="items.length && !createdOrder" class="co-sticky">
            <div class="co-sticky-inner">
                <div class="co-sticky-total">
                    <span class="sf-type-caption">مبلغ نهایی</span>
                    <strong class="sf-type-price">{{ totals.total.toLocaleString('fa-IR') }} <small>تومان</small></strong>
                </div>
                <button type="submit" form="checkout-form" class="sf-button co-sticky-cta" :disabled="locked">
                    {{ submitLabel(phase) }}
                </button>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>
