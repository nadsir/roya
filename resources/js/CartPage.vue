<script setup>
import { computed } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import CartItemRow from './components/cart/CartItemRow.vue';
import { cartCount, cartTotal, clearCart, getCartItems } from './cart-state.js';
import { formatPrice } from './product-presentation.js';
import '../css/cart.css';

const items = computed(() => getCartItems());
</script>

<template>
    <div class="ct-page" dir="rtl">
        <SiteHeader />

        <main class="sf-container ct-main">
            <nav class="ct-breadcrumb sf-type-caption" aria-label="مسیر صفحه">
                <a href="/store">فروشگاه</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">سبد خرید</span>
            </nav>

            <header class="ct-header">
                <div>
                    <p class="ct-eyebrow" lang="en" dir="ltr">YOUR BAG</p>
                    <h1 class="sf-type-h1">سبد خرید شما</h1>
                </div>
                <p v-if="items.length" class="ct-header-count sf-type-small">
                    {{ cartCount.toLocaleString('fa-IR') }} کالا در سبد
                </p>
            </header>

            <div v-if="!items.length" class="ct-empty sf-shell-state">
                <span class="ct-empty-mark" aria-hidden="true"><i class="fa-regular fa-bag" /></span>
                <h2 class="sf-type-h3">سبد خرید شما خالی است</h2>
                <p class="sf-type-body">هنوز کالایی انتخاب نکرده‌اید. از میان مجموعه‌های ما انتخاب کنید.</p>
                <a class="sf-button ct-empty-action" href="/store">مشاهده محصولات</a>
                <a class="sf-text-link" href="/wishlist">مشاهده علاقه‌مندی‌ها</a>
            </div>

            <div v-else class="ct-layout">
                <section class="ct-panel" aria-labelledby="ct-items-title">
                    <h2 id="ct-items-title" class="sf-type-h3 ct-panel-title">کالاهای سبد خرید</h2>
                    <ul class="ct-items">
                        <CartItemRow v-for="item in items" :key="item.key" :item="item" />
                    </ul>
                    <a class="sf-text-link ct-continue" href="/store"><span aria-hidden="true">←</span> ادامه خرید</a>
                </section>

                <aside class="ct-summary" aria-labelledby="ct-summary-title">
                    <h2 id="ct-summary-title" class="sf-type-h3 ct-panel-title">خلاصه سبد خرید</h2>
                    <dl class="ct-summary-rows">
                        <div class="ct-summary-row">
                            <dt class="sf-type-small">تعداد کل کالاها</dt>
                            <dd class="sf-type-price">{{ cartCount.toLocaleString('fa-IR') }}</dd>
                        </div>
                        <div class="ct-summary-row ct-summary-row--total">
                            <dt class="sf-type-small">جمع کل</dt>
                            <dd class="sf-type-price">{{ formatPrice(cartTotal) }} <small>تومان</small></dd>
                        </div>
                    </dl>
                    <p class="sf-type-caption ct-summary-note">هزینه ارسال و مبلغ نهایی در مرحله بعد محاسبه می‌شود.</p>
                    <a class="sf-button ct-checkout" href="/checkout">تکمیل خرید</a>
                    <button type="button" class="ct-clear" @click="clearCart">خالی کردن سبد خرید</button>
                </aside>
            </div>
        </main>

        <div v-if="items.length" class="ct-sticky">
            <div class="ct-sticky-inner">
                <div class="ct-sticky-total">
                    <span class="sf-type-caption">جمع سبد خرید</span>
                    <strong class="sf-type-price">{{ formatPrice(cartTotal) }} <small>تومان</small></strong>
                </div>
                <a class="sf-button ct-sticky-cta" href="/checkout">تکمیل خرید</a>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>
