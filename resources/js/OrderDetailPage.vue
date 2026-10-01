<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import AccountLayout from './components/account/AccountLayout.vue';
import AccountNotice from './components/account/AccountNotice.vue';
import AccountState from './components/account/AccountState.vue';
import OrderStatusChip from './components/account/OrderStatusChip.vue';
import OrderSteps from './components/account/OrderSteps.vue';
import OrderLineRow from './components/account/OrderLineRow.vue';
import PaymentResultBanner from './components/checkout/PaymentResultBanner.vue';
import { paymentReturnState, canRetryPayment } from './checkout-presentation.js';
import { useCustomerOrders, statusLabel, formatDate, formatPrice, cancelOrder } from './customer-orders.js';
import { orderCanCancel, orderIsPaid } from './account-presentation.js';

// The composable keeps the existing auth gate, 401 recovery and ownership scoping.
// The gateway callback redirects here with ?payment=; the stored order fields decide
// the outcome, and a retry always goes through POST /api/customer/orders/{id}/pay.
const id = window.location.pathname.match(/^\/orders\/(\d+)\/?$/)?.[1];
const { data, loading, error, load } = useCustomerOrders(`/api/customer/orders/${id}`);
const order = computed(() => data.value?.order);
const paymentResult = computed(() => paymentReturnState(window.location.search, order.value));
const showBanner = computed(() => paymentResult.value.tone !== 'none');
const retryingPay = ref(false);
const payError = ref('');
const cancelling = ref(false);
const cancelError = ref('');

async function retryPayment() {
    if (retryingPay.value) return;
    retryingPay.value = true;
    payError.value = '';
    try {
        const { data: result } = await axios.post(`/api/customer/orders/${id}/pay`);
        if (result?.payment_url) {
            window.location.href = result.payment_url;
            return;
        }
        payError.value = 'آدرس پرداخت دریافت نشد. لطفاً مجدداً تلاش کنید.';
    } catch (failure) {
        payError.value = failure.response?.data?.message || 'اتصال به درگاه پرداخت انجام نشد. لطفاً مجدداً تلاش کنید.';
    } finally {
        retryingPay.value = false;
    }
}

async function handleCancel() {
    if (cancelling.value) return;
    cancelling.value = true;
    cancelError.value = '';
    try {
        const result = await cancelOrder(id);
        data.value = { order: result.order };
    } catch (failure) {
        cancelError.value = failure.response?.status === 409
            ? failure.response?.data?.message
            || 'این سفارش در وضعیت فعلی قابل لغو نیست.'
            : failure.response?.data?.message
                || 'لغو سفارش انجام نشد. لطفاً دوباره تلاش کنید.';
    } finally {
        cancelling.value = false;
    }
}
</script>

<template>
    <AccountLayout
        active="orders"
        :title="`سفارش #${id}`"
        description="جزئیات اقلام، وضعیت پرداخت و آدرس ارسال همین سفارش."
    >
        <AccountState v-if="loading" busy description="اطلاعات سفارش در حال دریافت است." />

        <section v-else-if="error" class="ac-card">
            <div class="ac-card-body">
                <AccountNotice tone="error" icon="fa-solid fa-circle-exclamation">{{ error }}</AccountNotice>
                <div class="ac-actions">
                    <button type="button" class="sf-button" :disabled="loading" @click="load()">تلاش دوباره</button>
                    <a class="sf-text-link" href="/orders">بازگشت به سفارش‌ها</a>
                </div>
            </div>
        </section>

        <template v-else-if="order">
            <section v-if="showBanner" class="ac-card">
                <div class="ac-card-body">
                    <PaymentResultBanner :state="paymentResult" :order-id="order.id" :retrying="retryingPay" @retry="retryPayment" />
                    <AccountNotice v-if="payError" tone="error" icon="fa-solid fa-circle-exclamation">{{ payError }}</AccountNotice>
                </div>
            </section>

            <section class="ac-card">
                <div class="ac-card-head">
                    <h2>وضعیت سفارش</h2>
                    <OrderStatusChip :order="order" />
                </div>
                <div class="ac-card-body">
                    <OrderSteps :order="order" :format-date="formatDate" />

                    <dl class="ac-facts">
                        <div class="ac-fact">
                            <dt>تاریخ ثبت</dt>
                            <dd>{{ formatDate(order.created_at) }}</dd>
                        </div>
                        <div class="ac-fact">
                            <dt>وضعیت ثبت‌شده</dt>
                            <dd>{{ statusLabel(order.status) }}</dd>
                        </div>
                        <div v-if="orderIsPaid(order)" class="ac-fact">
                            <dt>تاریخ پرداخت</dt>
                            <dd>{{ formatDate(order.paid_at) }}</dd>
                        </div>
                        <div v-if="order.payment_ref" class="ac-fact">
                            <dt>کد رهگیری پرداخت</dt>
                            <dd class="is-ltr" dir="ltr">{{ order.payment_ref }}</dd>
                        </div>
                    </dl>

                    <AccountNotice
                        v-if="!orderIsPaid(order) && canRetryPayment(order)"
                        tone="info"
                        icon="fa-solid fa-credit-card"
                    >
                        پرداخت این سفارش هنوز تأیید نشده است. می‌توانید پرداخت را دوباره از طریق درگاه بانکی انجام دهید.
                    </AccountNotice>

                    <AccountNotice v-if="cancelError" tone="error" icon="fa-solid fa-circle-exclamation">{{ cancelError }}</AccountNotice>

                    <div v-if="orderCanCancel(order)" class="ac-actions">
                        <button type="button" class="ac-action-danger" :disabled="cancelling" @click="handleCancel">
                            <i class="fa-regular fa-circle-xmark" aria-hidden="true"></i>
                            {{ cancelling ? 'در حال لغو…' : 'لغو سفارش' }}
                        </button>
                    </div>
                </div>
            </section>

            <section class="ac-card">
                <div class="ac-card-head">
                    <h2>اقلام سفارش</h2>
                    <p>{{ order.items?.length || 0 }} قلم کالا</p>
                </div>
                <div class="ac-card-body">
                    <div v-if="order.items?.length" class="ac-lines">
                        <OrderLineRow v-for="item in order.items" :key="item.id" :item="item" />
                    </div>
                    <p v-else class="ac-card-note">جزئیات اقلام این سفارش در دسترس نیست.</p>
                </div>
            </section>

            <section class="ac-card">
                <div class="ac-card-head">
                    <h2>اطلاعات ارسال</h2>
                    <p>اطلاعات ثبت‌شده هنگام ثبت سفارش</p>
                </div>
                <div class="ac-card-body">
                    <dl class="ac-facts">
                        <div class="ac-fact">
                            <dt>نام گیرنده</dt>
                            <dd>{{ order.customer_name }}</dd>
                        </div>
                        <div class="ac-fact">
                            <dt>شماره تماس</dt>
                            <dd class="is-ltr" dir="ltr">{{ order.customer_phone }}</dd>
                        </div>
                        <div class="ac-fact">
                            <dt>ایمیل</dt>
                            <dd class="is-ltr" dir="ltr">{{ order.customer_email }}</dd>
                        </div>
                        <div class="ac-fact">
                            <dt>استان و شهر</dt>
                            <dd>{{ [order.shipping_province, order.shipping_city].filter(Boolean).join('، ') || '—' }}</dd>
                        </div>
                        <div class="ac-fact">
                            <dt>کد پستی</dt>
                            <dd class="is-ltr" dir="ltr">{{ order.shipping_postal_code || '—' }}</dd>
                        </div>
                        <div v-if="order.notes" class="ac-fact">
                            <dt>توضیحات سفارش</dt>
                            <dd>{{ order.notes }}</dd>
                        </div>
                    </dl>
                    <div class="ac-fact">
                        <dt class="sf-type-caption">نشانی</dt>
                        <p class="sf-type-small ac-address">{{ order.shipping_address || '—' }}</p>
                    </div>
                </div>
            </section>

            <section class="ac-card">
                <div class="ac-card-head">
                    <h2>خلاصه مالی</h2>
                    <p>مبالغ ثبت‌شده در زمان سفارش</p>
                </div>
                <div class="ac-card-body">
                    <dl class="ac-totals">
                        <div class="ac-total-row">
                            <dt>جمع کالاها</dt>
                            <dd>{{ formatPrice(order.subtotal) }} تومان</dd>
                        </div>
                        <div class="ac-total-row">
                            <dt>تخفیف</dt>
                            <dd>{{ formatPrice(order.discount) }} تومان</dd>
                        </div>
                        <div class="ac-total-row">
                            <dt>هزینه ارسال</dt>
                            <dd>{{ formatPrice(order.shipping_cost) }} تومان</dd>
                        </div>
                        <div class="ac-total-row ac-total-row--grand">
                            <dt>مبلغ نهایی</dt>
                            <dd>{{ formatPrice(order.total) }} تومان</dd>
                        </div>
                    </dl>
                    <p class="ac-card-note">مبلغ نهایی توسط سرور محاسبه و ذخیره شده است.</p>
                </div>
            </section>
        </template>
    </AccountLayout>
</template>
