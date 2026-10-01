<script setup>
import { computed } from 'vue';
import ShellDialog from '../ShellDialog.vue';
import CartItemRow from './CartItemRow.vue';
import { cartCount, cartTotal, getCartItems } from '../../cart-state.js';
import { formatPrice } from '../../product-presentation.js';
import '../../../css/cart.css';

defineProps({ open: Boolean });
const emit = defineEmits(['close']);

const items = computed(() => getCartItems());
</script>

<template>
    <ShellDialog variant="cart" label="سبد خرید" :open="open" @close="emit('close')">
        <div class="cd-layout">
            <header class="cd-header">
                <div class="cd-heading">
                    <p class="cd-kicker sf-type-caption">سبد خرید شما</p>
                    <h2 class="cd-title sf-type-h3">{{ cartCount.toLocaleString('fa-IR') }} کالا در سبد</h2>
                </div>
                <button type="button" class="sf-icon-button cd-close" aria-label="بستن سبد خرید" @click="emit('close')">
                    <i class="fa-solid fa-xmark" aria-hidden="true" />
                </button>
            </header>

            <ul v-if="items.length" class="cd-items">
                <CartItemRow v-for="item in items" :key="item.key" :item="item" compact />
            </ul>

            <div v-else class="cd-empty sf-shell-state">
                <span class="cd-empty-mark" aria-hidden="true"><i class="fa-regular fa-bag" /></span>
                <p class="sf-type-body">سبد خرید شما خالی است</p>
                <p class="sf-type-caption">محصولات دلخواهتان را به سبد اضافه کنید تا اینجا نگه دارید.</p>
                <a class="sf-button cd-empty-action" href="/store" @click="emit('close')">مشاهده محصولات</a>
            </div>

            <footer v-if="items.length" class="cd-footer">
                <div class="cd-total">
                    <span class="sf-type-small">جمع سبد خرید</span>
                    <strong class="sf-type-price">{{ formatPrice(cartTotal) }} <small>تومان</small></strong>
                    <p class="sf-type-caption">مبلغ نهایی در مرحله بعد محاسبه می‌شود.</p>
                </div>
                <a class="sf-button cd-cta" href="/cart" @click="emit('close')">مشاهده سبد خرید</a>
                <a class="sf-text-link cd-continue" href="/store" @click="emit('close')">ادامه خرید</a>
            </footer>
        </div>
    </ShellDialog>
</template>
