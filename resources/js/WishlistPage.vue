<script setup>
import { onMounted, watch } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { state as authState, isLoggedIn } from './auth-state.js';
import { state as wishlistState, loadWishlist, removeFromWishlist } from './wishlist-state.js';

onMounted(() => {
    if (isLoggedIn.value) loadWishlist();
});

watch(() => authState.user?.id, (userId) => {
    if (userId) loadWishlist();
});

function productImage(product) {
    const image = product?.images?.find((item) => item.is_primary) || product?.images?.[0];
    return image?.path ? `/storage/${image.path}` : '/images/placeholder.svg';
}

function onImgError(event) {
    event.target.onerror = null;
    event.target.src = '/images/placeholder.svg';
}

function formatPrice(price) {
    return Number(price || 0).toLocaleString('fa-IR');
}

function removeItem(productId) {
    if (!isLoggedIn.value || wishlistState.loading) return;
    return removeFromWishlist(productId);
}

function retryLoad() {
    if (isLoggedIn.value && !wishlistState.loading) return loadWishlist();
}
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased" dir="rtl">
        <SiteHeader />
        <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 min-h-[60vh]">
            <h1 class="text-xl sm:text-2xl font-black mb-6">علاقه‌مندی‌های من</h1>

            <div v-if="authState.loading" role="status" class="text-center py-16 text-sm text-slate-500">
                <i class="fa-solid fa-spinner fa-spin ml-2" aria-hidden="true"></i> در حال بررسی حساب کاربری…
            </div>
            <div v-else-if="!isLoggedIn" class="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <p class="text-sm text-slate-600 mb-5">برای مشاهده علاقه‌مندی‌ها وارد حساب کاربری خود شوید.</p>
                <a href="/login" class="inline-block rounded-lg bg-brand-accent px-5 py-2.5 text-xs font-bold text-dark-900 hover:bg-brand-hover">ورود به حساب کاربری</a>
            </div>
            <template v-else>
                <div v-if="wishlistState.error" role="alert" class="mb-5 rounded-lg border border-red-200 bg-white p-4 text-sm text-red-600">
                    <p>{{ wishlistState.error.message }}</p>
                    <button type="button" :disabled="wishlistState.loading" class="mt-2 text-xs font-bold underline disabled:opacity-50" @click="retryLoad">تلاش دوباره</button>
                </div>

                <p v-if="wishlistState.loading" role="status" class="py-5 text-center text-sm text-slate-500">
                    <i class="fa-solid fa-spinner fa-spin ml-2" aria-hidden="true"></i> در حال به‌روزرسانی علاقه‌مندی‌ها…
                </p>

                <div v-if="wishlistState.items.length" :aria-busy="wishlistState.loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="item in wishlistState.items" :key="item.product_id" class="rounded-xl border border-gray-200 bg-white overflow-hidden flex flex-col">
                        <a :href="`/products/${item.product_id}`" class="block">
                            <img :src="productImage(item.product)" :alt="item.product?.name || 'تصویر محصول'" class="aspect-square w-full object-contain" loading="lazy" @error="onImgError" />
                        </a>
                        <div class="p-4 flex flex-col gap-3 flex-1">
                            <a :href="`/products/${item.product_id}`" class="text-sm font-bold hover:text-brand-accent">{{ item.product?.name || 'مشاهده محصول' }}</a>
                            <p v-if="item.product" class="text-sm font-bold text-brand-accent">{{ formatPrice(item.product.price) }} <span class="text-xs font-normal text-slate-500">تومان</span></p>
                            <p v-if="item.product && typeof item.product.in_stock === 'boolean'" class="text-xs" :class="item.product.in_stock ? 'text-emerald-600' : 'text-red-600'">{{ item.product.in_stock ? 'موجود' : 'ناموجود' }}</p>
                            <button type="button" :disabled="wishlistState.loading" :aria-label="`حذف ${item.product?.name || 'محصول'} از علاقه‌مندی‌ها`" class="mt-auto rounded-lg border border-gray-200 px-3 py-2 text-xs text-red-600 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed" @click="removeItem(item.product_id)">
                                <i class="fa-regular fa-trash-can ml-1" aria-hidden="true"></i> حذف از علاقه‌مندی‌ها
                            </button>
                        </div>
                    </article>
                </div>

                <div v-else-if="!wishlistState.loading && !wishlistState.error" class="rounded-xl border border-gray-200 bg-white p-8 sm:p-12 text-center">
                    <i class="fa-regular fa-heart text-4xl text-slate-400 mb-4" aria-hidden="true"></i>
                    <p class="text-sm font-bold mb-2">لیست علاقه‌مندی‌های شما خالی است.</p>
                    <p class="text-xs text-slate-500 mb-6">محصولات دلخواهتان را ذخیره کنید تا بعداً به‌راحتی آن‌ها را پیدا کنید.</p>
                    <a href="/store" class="inline-block rounded-lg bg-brand-accent px-5 py-2.5 text-xs font-bold text-dark-900 hover:bg-brand-hover">مشاهده محصولات</a>
                </div>
            </template>
        </main>
        <SiteFooter />
    </div>
</template>
