<script setup>
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { useCustomerOrders, statusLabel, formatDate, formatPrice } from './customer-orders.js';

const { data, loading, error, load, isLoggedIn } = useCustomerOrders('/api/customer/orders');
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased" dir="rtl">
        <SiteHeader />
        <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 min-h-[60vh]">
            <h1 class="text-xl sm:text-2xl font-black mb-6">سفارش‌های من</h1>
            <p v-if="loading || !isLoggedIn" role="status" class="text-center py-16 text-sm text-slate-500">در حال بارگذاری…</p>
            <div v-else-if="error" role="alert" class="rounded-lg border border-red-200 bg-white p-5 text-sm text-red-600">
                <p>{{ error }}</p>
                <button type="button" class="mt-3 underline" @click="load()">تلاش دوباره</button>
            </div>
            <template v-else-if="data?.data.length">
                <div class="space-y-4">
                    <article v-for="order in data.data" :key="order.id" class="rounded-xl border border-gray-200 bg-white p-5 flex flex-wrap items-center justify-between gap-4">
                        <div class="space-y-2">
                            <h2 class="text-sm font-bold">سفارش <span dir="ltr">#{{ order.id }}</span></h2>
                            <p class="text-xs text-slate-500">{{ formatDate(order.created_at) }} · {{ order.items_count }} قلم کالا</p>
                        </div>
                        <p class="text-xs rounded-lg bg-gray-100 px-3 py-2">{{ statusLabel(order.status) }}</p>
                        <p class="text-sm font-bold">{{ formatPrice(order.total) }} تومان</p>
                        <a :href="`/orders/${order.id}`" class="rounded-lg bg-brand-accent text-dark-900 px-4 py-2.5 text-xs font-bold hover:bg-brand-hover">مشاهده جزئیات</a>
                    </article>
                </div>
                <nav v-if="data.last_page > 1" aria-label="صفحه‌بندی سفارش‌ها" class="flex justify-center items-center gap-4 mt-6 text-xs">
                    <button type="button" :disabled="data.current_page <= 1" class="rounded-lg border border-gray-200 px-4 py-2 disabled:opacity-40" @click="load({ page: data.current_page - 1 })">قبلی</button>
                    <span>{{ data.current_page }} / {{ data.last_page }}</span>
                    <button type="button" :disabled="data.current_page >= data.last_page" class="rounded-lg border border-gray-200 px-4 py-2 disabled:opacity-40" @click="load({ page: data.current_page + 1 })">بعدی</button>
                </nav>
            </template>
            <div v-else class="rounded-xl border border-gray-200 bg-white p-10 text-center">
                <p class="text-sm mb-5">هنوز سفارشی ثبت نکرده‌اید.</p>
                <a href="/store" class="inline-block rounded-lg bg-brand-accent text-dark-900 px-5 py-2.5 text-xs font-bold hover:bg-brand-hover">مشاهده فروشگاه</a>
            </div>
        </main>
        <SiteFooter />
    </div>
</template>
