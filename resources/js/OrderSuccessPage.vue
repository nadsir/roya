<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { state as authState } from './auth-state.js';

let savedReceipt = null;
try {
    savedReceipt = JSON.parse(sessionStorage.getItem('turbopart-order-receipt') || 'null');
} catch {}
const receipt = computed(() => savedReceipt && authState.user
    && String(savedReceipt.user_id) === String(authState.user.id) ? savedReceipt : null);

const paying = ref(false);
const payError = ref('');

async function payOrder() {
    if (!receipt.value || paying.value) return;
    paying.value = true;
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
        paying.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased" dir="rtl">
        <SiteHeader />
        <main class="max-w-xl mx-auto px-4 py-16">
            <section class="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <p v-if="authState.loading" role="status">در حال بارگذاری…</p>
                <template v-else-if="receipt">
                    <h1 class="text-xl font-black mb-5">سفارش شما با موفقیت ثبت شد.</h1>
                    <p class="text-sm mb-3">شماره سفارش: <b dir="ltr">#{{ receipt.id }}</b></p>
                    <p class="text-sm mb-3">مبلغ نهایی: {{ Number(receipt.total).toLocaleString('fa-IR') }} تومان</p>
                    <p class="text-xs text-slate-500 mb-6">سفارش در انتظار تأیید است.</p>
                    <div v-if="payError" class="mb-4 text-xs text-red-600 bg-red-50 rounded-lg p-3" role="alert">
                        {{ payError }}
                    </div>
                    <button
                        v-if="receipt"
                        type="button"
                        :disabled="paying"
                        class="w-full sm:w-auto rounded-lg bg-brand-accent px-6 py-3 text-sm font-bold text-dark-900 hover:bg-brand-hover disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        @click="payOrder"
                    >
                        <span v-if="paying" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            در حال اتصال به درگاه…
                        </span>
                        <span v-else>پرداخت سفارش</span>
                    </button>
                </template>
                <p v-else class="text-sm mb-6">اطلاعات سفارش ثبت‌شده در این مرورگر در دسترس نیست.</p>
                <div class="flex flex-wrap justify-center gap-3 text-xs font-bold">
                    <a v-if="receipt" :href="`/orders/${receipt.id}`" class="rounded-lg bg-brand-accent px-4 py-2.5 text-dark-900 hover:bg-brand-hover">مشاهده جزئیات سفارش</a>
                    <a href="/orders" class="rounded-lg border border-gray-200 px-4 py-2.5 hover:bg-gray-50">مشاهده همه سفارش‌ها</a>
                    <a href="/account" class="rounded-lg bg-brand-accent px-4 py-2.5 text-dark-900 hover:bg-brand-hover">مشاهده حساب کاربری</a>
                    <a href="/store" class="rounded-lg border border-gray-200 px-4 py-2.5 hover:bg-gray-50">بازگشت به فروشگاه</a>
                </div>
            </section>
        </main>
        <SiteFooter />
    </div>
</template>
