<script setup>
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { getCartItems, clearCart, cartTotal } from './cart-state.js';
import { state as authState, isLoggedIn, loadUser } from './auth-state.js';

const items = computed(() => getCartItems());
const submitting = ref(false);
const error = ref('');
const validationErrors = ref({});
const form = reactive({
    customer_name: '', customer_phone: '', customer_email: '',
    shipping_province: '', shipping_city: '', shipping_address: '',
    shipping_postal_code: '', notes: '',
});
const fields = [
    { key: 'customer_name', label: 'نام و نام خانوادگی', type: 'text', autocomplete: 'name', max: 255 },
    { key: 'customer_phone', label: 'تلفن', type: 'tel', autocomplete: 'tel', max: 32 },
    { key: 'customer_email', label: 'ایمیل', type: 'email', autocomplete: 'email', max: 255 },
    { key: 'shipping_province', label: 'استان', type: 'text', autocomplete: 'address-level1', max: 100 },
    { key: 'shipping_city', label: 'شهر', type: 'text', autocomplete: 'address-level2', max: 100 },
    { key: 'shipping_postal_code', label: 'کد پستی', type: 'text', autocomplete: 'postal-code', max: 20 },
];

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

function formatPrice(value) {
    return Number(value || 0).toLocaleString('fa-IR');
}

function onImgError(event) {
    event.target.onerror = null;
    event.target.src = '/images/placeholder.svg';
}

async function submitOrder() {
    if (submitting.value || !isLoggedIn.value || !items.value.length) return;
    submitting.value = true;
    error.value = '';
    validationErrors.value = {};
    try {
        const { data } = await axios.post('/api/customer/checkout', {
            ...form,
            items: items.value.map((item) => ({
                product_id: item.product_id,
                variant_id: item.variant_id || null,
                quantity: item.quantity,
            })),
        });
        // Retain only a receipt summary for this tab, never the address or phone.
        try {
            sessionStorage.setItem('turbopart-order-receipt', JSON.stringify({
                id: data.order.id, user_id: data.order.user_id, total: data.order.total,
            }));
        } catch {}
        clearCart();
        window.location.replace('/order-success');
    } catch (failure) {
        if (failure.response?.status === 401) {
            await loadUser();
            if (!isLoggedIn.value) goToLogin();
        }
        validationErrors.value = failure.response?.data?.errors || {};
        error.value = failure.response?.data?.message || 'ثبت سفارش انجام نشد. سبد خرید شما حفظ شده است؛ لطفاً اتصال را بررسی کنید.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased" dir="rtl">
        <SiteHeader />
        <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
            <h1 class="text-xl sm:text-2xl font-black mb-6">تکمیل خرید</h1>
            <p v-if="authState.loading || !isLoggedIn" role="status" class="py-16 text-center text-sm text-slate-500">در حال بررسی حساب کاربری…</p>
            <div v-else-if="!items.length" class="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <p class="mb-4 text-sm">سبد خرید شما خالی است.</p>
                <a href="/store" class="text-sm font-bold text-brand-accent">مشاهده محصولات</a>
            </div>
            <form v-else class="grid gap-6 lg:grid-cols-2" @submit.prevent="submitOrder">
                <fieldset :disabled="submitting" class="rounded-xl border border-gray-200 bg-white p-5 min-w-0">
                    <legend class="text-sm font-bold px-2">اطلاعات مشتری و آدرس ارسال</legend>
                    <div v-if="error" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-xs text-red-600">
                        <p>{{ error }}</p>
                        <ul v-if="Object.keys(validationErrors).length" class="mt-2 space-y-1">
                            <li v-for="(messages, key) in validationErrors" :key="key">{{ messages.join(' ') }}</li>
                        </ul>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="field in fields" :key="field.key">
                            <label :for="field.key" class="block text-xs text-slate-600 mb-2">{{ field.label }}</label>
                            <input :id="field.key" v-model="form[field.key]" :type="field.type" :autocomplete="field.autocomplete" :maxlength="field.max" required :aria-invalid="!!validationErrors[field.key]" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none min-h-[44px]" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="shipping_address" class="block text-xs text-slate-600 mb-2">آدرس کامل</label>
                            <textarea id="shipping_address" v-model="form.shipping_address" required maxlength="2000" autocomplete="street-address" rows="3" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm min-h-[44px]"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="notes" class="block text-xs text-slate-600 mb-2">توضیحات (اختیاری)</label>
                            <textarea id="notes" v-model="form.notes" maxlength="2000" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm min-h-[44px]"></textarea>
                        </div>
                    </div>
                </fieldset>
                <section class="rounded-xl border border-gray-200 bg-white p-5">
                    <h2 class="text-sm font-bold mb-4">خلاصه سفارش</h2>
                    <article v-for="item in items" :key="item.key" class="flex gap-3 py-4 border-b border-gray-200">
                        <img :src="item.image || '/images/placeholder.svg'" :alt="item.name" class="w-16 h-16 object-contain rounded-lg shrink-0" @error="onImgError" />
                        <div class="min-w-0 flex-1 text-xs space-y-2">
                            <p class="font-bold">{{ item.name }}</p>
                            <p v-if="item.sku" class="text-slate-500">SKU: {{ item.sku }}</p>
                            <p v-for="(values, slug) in item.attributes || {}" :key="slug" class="text-slate-500">{{ slug }}: {{ Array.isArray(values) ? values.map(v => v.label || v).join('، ') : values }}</p>
                            <p>تعداد: {{ item.quantity }} · قیمت واحد: {{ formatPrice(item.price) }} تومان</p>
                            <p class="font-bold">جمع: {{ formatPrice(item.price * item.quantity) }} تومان</p>
                        </div>
                    </article>
                    <dl class="text-sm space-y-3 py-5">
                        <div class="flex justify-between"><dt>جمع اقلام</dt><dd>{{ formatPrice(cartTotal) }} تومان</dd></div>
                        <div class="flex justify-between"><dt>تخفیف</dt><dd>۰ تومان</dd></div>
                        <div class="flex justify-between"><dt>هزینه ارسال</dt><dd>۰ تومان</dd></div>
                        <div class="flex justify-between font-bold"><dt>جمع کل</dt><dd>{{ formatPrice(cartTotal) }} تومان</dd></div>
                    </dl>
                    <p class="mb-4 text-xs text-slate-500">قیمت و موجودی هنگام ثبت سفارش بررسی می‌شوند؛ مبلغ نهایی در نتیجه سفارش نمایش داده می‌شود.</p>
                    <button type="submit" :disabled="submitting || !items.length" class="w-full rounded-lg bg-brand-accent text-dark-900 py-3.5 text-sm font-bold hover:bg-brand-hover disabled:opacity-50 disabled:cursor-not-allowed min-h-[48px]">{{ submitting ? 'در حال ثبت سفارش…' : 'ثبت سفارش' }}</button>
                    <a href="/cart" class="block mt-3 text-center text-xs text-slate-500">بازگشت به سبد خرید</a>
                </section>
            </form>
        </main>
        <SiteFooter />
    </div>
</template>
