<script setup>
import { cartNotification, hideCartNotification } from '../cart-notification-state.js';

const emit = defineEmits(['view-cart']);

function viewCart() {
    hideCartNotification();
    emit('view-cart');
}
</script>

<template>
    <Transition name="sf-cart-toast">
        <div v-if="cartNotification.visible" class="sf-cart-toast-wrap" dir="rtl">
            <div class="sf-cart-toast" role="status">
                <img v-if="cartNotification.image" :src="cartNotification.image" alt="" width="48" height="48" />
                <span v-else class="sf-cart-toast-ph" aria-hidden="true">G</span>
                <span class="sf-cart-toast-icon" aria-hidden="true"><i class="fa-solid fa-check" /></span>
                <div class="sf-cart-toast-copy">
                    <p class="sf-cart-toast-title">محصول به سبد خرید اضافه شد</p>
                    <p v-if="cartNotification.name" class="sf-cart-toast-name">{{ cartNotification.name }}</p>
                </div>
                <button type="button" class="sf-cart-toast-action" @click="viewCart">مشاهده سبد خرید</button>
                <button type="button" class="sf-cart-toast-close" aria-label="بستن اعلان" @click="hideCartNotification"><i class="fa-solid fa-xmark" aria-hidden="true" /></button>
            </div>
        </div>
    </Transition>
</template>
