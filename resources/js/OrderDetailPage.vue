<script setup>
import { computed, ref } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { useCustomerOrders, statusLabel, formatDate, formatPrice, cancelOrder } from './customer-orders.js';

const id = window.location.pathname.match(/^\/orders\/(\d+)\/?$/)?.[1];
const { data, loading, error, load, isLoggedIn } = useCustomerOrders(`/api/customer/orders/${id}`);
const order = computed(() => data.value?.order);
const steps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
const cancelling = ref(false);
const cancelError = ref('');

function onImgError(event) {
    event.target.onerror = null;
    event.target.src = '/images/placeholder.svg';
}

async function handleCancel() {
    if (cancelling.value) return;
    const confirmed = window.confirm('آیا از لغو این سفارش اطمینان دارید؟');
    if (!confirmed) return;
    cancelling.value = true;
    cancelError.value = '';
    try {
        const result = await cancelOrder(id);
        data.value = { order: result.order };
    } catch (e) {
        cancelError.value = e.response?.data?.message || 'لغو سفارش انجام نشد. لطفاً دوباره تلاش کنید.';
    } finally {
        cancelling.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased" dir="rtl">
        <SiteHeader />
        <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 min-h-[60vh]">
            <a href="/orders" class="inline-block mb-5 text-xs text-slate-500 hover:text-ink">بازگشت به سفارش‌های من</a>
            <p v-if="loading || !isLoggedIn" role="status" class="text-center py-16 text-sm text-slate-500">در حال بارگذاری…</p>
            <div v-else-if="error" role="alert" class="rounded-lg border border-red-200 bg-white p-5 text-sm text-red-600">
                <p>{{ error }}</p>
                <button type="button" class="mt-3 underline" @click="load()">تلاش دوباره</button>
            </div>
            <template v-else-if="order">
                <h1 class="text-xl font-black mb-3">سفارش <span dir="ltr">#{{ order.id }}</span></h1>
                <p class="text-xs text-slate-500 mb-6">تاریخ ثبت: {{ formatDate(order.created_at) }} · {{ statusLabel(order.status) }}</p>

                <section aria-label="وضعیت سفارش" class="rounded-xl border border-gray-200 bg-white p-5 mb-6">
                    <h2 class="text-sm font-bold mb-4">وضعیت سفارش</h2>
                    <template v-if="order.status === 'cancelled'">
                        <p class="text-sm text-red-600 mb-2">این سفارش لغو شده است.</p>
                        <p v-if="order.cancelled_at" class="text-xs text-slate-500">تاریخ لغو: {{ formatDate(order.cancelled_at) }}</p>
                        <p v-if="order.cancelled_reason" class="text-xs text-slate-500 mt-1">دلیل لغو: {{ order.cancelled_reason }}</p>
                    </template>
                    <template v-else>
                        <ol class="flex flex-wrap items-center gap-2 text-xs">
                            <li class="text-slate-500">ثبت سفارش</li>
                            <li v-for="step in steps" :key="step" :aria-current="order.status === step ? 'step' : undefined" class="flex items-center gap-2">
                                <span aria-hidden="true" class="text-slate-400">←</span>
                                <span class="rounded-lg px-3 py-2" :class="order.status === step ? 'bg-brand-accent text-dark-900 font-bold' : 'bg-gray-100 text-slate-500'">{{ statusLabel(step) }}{{ order.status === step ? ' (فعلی)' : '' }}</span>
                            </li>
                        </ol>
                    </template>
                    <p v-if="cancelError" class="text-xs text-red-600 mt-3">{{ cancelError }}</p>
                    <button
                        v-if="order.status === 'pending'"
                        type="button"
                        :disabled="cancelling"
                        class="mt-4 rounded-lg border border-red-300 text-red-600 px-4 py-2 text-xs font-bold hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="handleCancel"
                    >
                        {{ cancelling ? 'در حال لغو…' : 'لغو سفارش' }}
                    </button>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 mb-6">
                    <h2 class="text-sm font-bold mb-4">اطلاعات ارسال</h2>
                    <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                        <div><dt class="text-xs text-slate-500">نام گیرنده</dt><dd>{{ order.customer_name }}</dd></div>
                        <div><dt class="text-xs text-slate-500">تلفن</dt><dd dir="ltr" class="text-right">{{ order.customer_phone }}</dd></div>
                        <div><dt class="text-xs text-slate-500">ایمیل</dt><dd class="break-all">{{ order.customer_email }}</dd></div>
                        <div><dt class="text-xs text-slate-500">استان و شهر</dt><dd>{{ order.shipping_province }}، {{ order.shipping_city }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-slate-500">آدرس</dt><dd class="whitespace-pre-line">{{ order.shipping_address }}</dd></div>
                        <div><dt class="text-xs text-slate-500">کد پستی</dt><dd>{{ order.shipping_postal_code }}</dd></div>
                        <div v-if="order.notes" class="sm:col-span-2"><dt class="text-xs text-slate-500">توضیحات</dt><dd class="whitespace-pre-line">{{ order.notes }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 mb-6">
                    <h2 class="text-sm font-bold mb-4">اقلام سفارش</h2>
                    <article v-for="item in order.items" :key="item.id" class="flex gap-4 py-4 border-b border-gray-200 last:border-0">
                        <img :src="item.image ? `/storage/${item.image}` : '/images/placeholder.svg'" :alt="item.product_name" class="w-16 h-16 sm:w-20 sm:h-20 rounded-lg object-contain shrink-0" @error="onImgError" />
                        <div class="space-y-2 min-w-0 text-xs">
                            <h3 class="text-sm font-bold">{{ item.product_name }}</h3>
                            <p v-if="item.sku" class="text-slate-500">SKU: {{ item.sku }}</p>
                            <p v-for="(attribute, index) in item.attributes || []" :key="index" class="text-slate-500">{{ attribute.attribute || attribute.slug }}: {{ attribute.label ?? attribute.value }}</p>
                            <p>تعداد: {{ item.quantity }} · قیمت واحد: {{ formatPrice(item.unit_price) }} تومان</p>
                            <p class="font-bold">جمع: {{ formatPrice(item.subtotal) }} تومان</p>
                        </div>
                    </article>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5">
                    <h2 class="text-sm font-bold mb-4">خلاصه مالی</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt>جمع کالاها</dt><dd>{{ formatPrice(order.subtotal) }} تومان</dd></div>
                        <div class="flex justify-between"><dt>تخفیف</dt><dd>{{ formatPrice(order.discount) }} تومان</dd></div>
                        <div class="flex justify-between"><dt>هزینه ارسال</dt><dd>{{ formatPrice(order.shipping_cost) }} تومان</dd></div>
                        <div class="flex justify-between font-bold border-t border-gray-200 pt-3"><dt>مبلغ نهایی</dt><dd>{{ formatPrice(order.total) }} تومان</dd></div>
                    </dl>
                </section>
            </template>
        </main>
        <SiteFooter />
    </div>
</template>
