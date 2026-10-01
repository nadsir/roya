<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import CheckoutAlert from './components/checkout/CheckoutAlert.vue';
import { state as authState } from './auth-state.js';
import { formatPrice } from './product-presentation.js';
import { submitLabel, CHECKOUT_PHASES } from './checkout-presentation.js';
import '../css/checkout.css';

let savedReceipt = null;
try {
    savedReceipt = JSON.parse(sessionStorage.getItem('turbopart-order-receipt') || 'null');
} catch {}
const receipt = computed(() => savedReceipt && authState.user
    && String(savedReceipt.user_id) === String(authState.user.id) ? savedReceipt : null);

const phase = ref(CHECKOUT_PHASES.IDLE);
const payError = ref('');
const paying = computed(() => phase.value === CHECKOUT_PHASES.PAYING);

async function payOrder() {
    if (!receipt.value || paying.value) return;
    phase.value = CHECKOUT_PHASES.PAYING;
    payError.value = '';
    try {
        const { data } = await axios.post(`/api/customer/orders/${receipt.value.id}/pay`);
        if (data.payment_url) {
            window.location.href = data.payment_url;
            return;
        }
        payError.value = 'آدرس پرداخت دریافت نشد. لطفاً مجدداً تلاش کنید.';
    } catch (e) {
        payError.value = e.response?.data?.message || 'خطا در اتصال به درگاه پرداخت. لطفاً مجدداً تلاش کنید.';
    } finally {
        phase.value = CHECKOUT_PHASES.IDLE;
    }
}
</script>

<template>
    <div class="co-page" dir="rtl">
        <SiteHeader />

        <main class="sf-container co-main">
            <p v-if="authState.loading" class="sf-shell-state" role="status">در حال بارگذاری…</p>

            <section v-else-if="receipt" class="co-receipt">
                <span class="co-receipt-mark" aria-hidden="true"><i class="fa-solid fa-receipt" /></span>
                <h1 class="sf-type-h1">سفارش شما ثبت شد</h1>
                <p class="sf-type-body">برای نهایی شدن سفارش، پرداخت را از طریق درگاه بانکی انجام دهید.</p>

                <dl class="co-receipt-rows">
                    <div class="co-receipt-row">
                        <dt class="sf-type-small">شماره سفارش</dt>
                        <dd class="sf-type-price" dir="ltr">#{{ receipt.id }}</dd>
                    </div>
                    <div class="co-receipt-row">
                        <dt class="sf-type-small">وضعیت</dt>
                        <dd class="sf-type-price">در انتظار پرداخت</dd>
                    </div>
                    <div class="co-receipt-row co-receipt-total">
                        <dt class="sf-type-small">مبلغ قابل پرداخت</dt>
                        <dd class="sf-type-price">{{ formatPrice(receipt.total) }} <small>تومان</small></dd>
                    </div>
                </dl>

                <p class="co-receipt-note sf-type-caption">
                    مبلغ نهایی در مرحله ثبت سفارش توسط سرور محاسبه شده است. سفارش پس از تأیید پرداخت نهایی می‌شود.
                </p>

                <CheckoutAlert v-if="payError" :message="payError" />

                <div class="co-receipt-actions">
                    <button type="button" class="sf-button co-submit" :disabled="paying" @click="payOrder">
                        {{ paying ? submitLabel(CHECKOUT_PHASES.PAYING) : 'پرداخت سفارش' }}
                    </button>
                </div>

                <nav class="co-receipt-links sf-type-caption" aria-label="پیوندهای سفارش">
                    <a class="sf-text-link" :href="`/orders/${receipt.id}`">مشاهده جزئیات سفارش</a>
                    <a class="sf-text-link" href="/orders">همه سفارش‌های من</a>
                    <a class="sf-text-link" href="/account">حساب کاربری</a>
                    <a class="sf-text-link" href="/store">ادامه خرید</a>
                </nav>
            </section>

            <section v-else class="co-empty sf-shell-state">
                <span class="co-empty-mark" aria-hidden="true"><i class="fa-regular fa-receipt" /></span>
                <h2 class="sf-type-h3">اطلاعات سفارش در دسترس نیست</h2>
                <p class="sf-type-body">سفارش ثبت‌شده در این مرورگر یافت نشد. سفارش‌های شما در بخش سفارش‌های من قابل مشاهده است.</p>
                <a class="sf-button" href="/orders">مشاهده سفارش‌ها</a>
                <a class="sf-text-link" href="/store">بازگشت به فروشگاه</a>
            </section>
        </main>

        <SiteFooter />
    </div>
</template>
