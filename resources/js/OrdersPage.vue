<script setup>
import { computed } from 'vue';
import AccountLayout from './components/account/AccountLayout.vue';
import AccountNotice from './components/account/AccountNotice.vue';
import AccountState from './components/account/AccountState.vue';
import OrderCard from './components/account/OrderCard.vue';
import OrderPager from './components/account/OrderPager.vue';
import { useCustomerOrders, formatDate } from './customer-orders.js';

// The existing composable owns authentication, cancellation of stale reads and the
// 401 recovery. It also redirects a signed out visitor to /login, so this page
// never renders account content for a guest.
const { data, loading, error, load } = useCustomerOrders('/api/customer/orders');

const orders = computed(() => data.value?.data || []);
</script>

<template>
    <AccountLayout
        active="orders"
        title="سفارش‌های من"
        description="سفارش‌های ثبت‌شده با این حساب را دنبال کنید و پرداخت‌های ناتمام را تکمیل کنید."
    >
        <section v-if="error" class="ac-card">
            <div class="ac-card-body">
                <AccountNotice tone="error" icon="fa-solid fa-circle-exclamation">{{ error }}</AccountNotice>
                <div class="ac-actions">
                    <button type="button" class="sf-button" :disabled="loading" @click="load()">تلاش دوباره</button>
                </div>
            </div>
        </section>

        <AccountState v-else-if="loading" busy description="سفارش‌های شما در حال دریافت است." />

        <section v-else-if="!orders.length" class="ac-card">
            <AccountState
                icon="fa-regular fa-receipt"
                title="هنوز سفارشی ثبت نکرده‌اید"
                description="پس از اولین خرید، سفارش‌ها و وضعیت پرداخت آن‌ها همین‌جا نمایش داده می‌شود."
            >
                <a class="sf-button" href="/store">مشاهده محصولات</a>
            </AccountState>
        </section>

        <template v-else>
            <div class="ac-orders">
                <OrderCard v-for="order in orders" :key="order.id" :order="order" :format-date="formatDate" />
            </div>

            <OrderPager :meta="data" :busy="loading" @change="load({ page: $event })" />
        </template>
    </AccountLayout>
</template>
