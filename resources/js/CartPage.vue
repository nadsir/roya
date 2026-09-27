<script setup>
import { computed } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import {
    getCartItems,
    updateQuantity,
    removeFromCart,
    clearCart,
    cartCount,
    cartTotal,
} from './cart-state.js';

const items = computed(() => getCartItems());

function formatPrice(price) {
    return Number(price || 0).toLocaleString('fa-IR');
}

function inc(item) {
    updateQuantity(item.key, item.quantity + 1);
}

function dec(item) {
    updateQuantity(item.key, item.quantity - 1);
}

function remove(key) {
    removeFromCart(key);
}
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased">
        <SiteHeader />

        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-6">
            <h1 class="text-xl sm:text-2xl font-black text-ink mb-6">
                سبد خرید
            </h1>

            <!-- Empty -->
            <div
                v-if="!items.length"
                class="text-center py-16 rounded-xl border border-gray-200 bg-white"
            >
                <i
                    class="fa-solid fa-cart-shopping text-3xl text-slate-400 mb-3 block"
                ></i>
                <p class="text-sm text-slate-500">
                    سبد خرید شما خالی است.
                </p>
                <a
                    href="/store"
                    class="mt-4 inline-block px-5 py-2 rounded-lg bg-brand-accent text-ink text-xs font-bold hover:bg-brand-hover transition-colors"
                >
                    مشاهده محصولات
                </a>
            </div>

            <!-- Items -->
            <div v-else class="space-y-3">
                <div
                    v-for="item in items"
                    :key="item.key"
                    class="flex gap-4 rounded-xl border border-gray-200 bg-white p-4"
                >
                    <!-- Image -->
                    <div
                        class="w-20 h-20 sm:w-24 sm:h-24 rounded-lg bg-sand flex-shrink-0 overflow-hidden"
                    >
                        <img
                            v-if="item.image"
                            :src="item.image"
                            :alt="item.name"
                            class="w-full h-full object-cover"
                        />
                        <div
                            v-else
                            class="w-full h-full flex items-center justify-center"
                        >
                            <i
                                class="fa-solid fa-box text-xl text-slate-400"
                            ></i>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="flex-1 min-w-0">
                        <div
                            class="flex items-start justify-between gap-2"
                        >
                            <div class="min-w-0">
                                <h3
                                    class="text-sm font-bold text-ink line-clamp-2 leading-relaxed"
                                >
                                    {{ item.name }}
                                </h3>
                                <p
                                    v-if="item.sku"
                                    class="text-[10px] text-slate-500 font-mono mt-0.5"
                                >
                                    SKU: {{ item.sku }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 p-1 text-slate-400 hover:text-red-500 transition-colors"
                                @click="remove(item.key)"
                            >
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </div>

                        <!-- Variant attributes -->
                        <div
                            v-if="item.attributes"
                            class="mt-1 flex flex-wrap gap-1"
                        >
                            <span
                                v-for="(vals, slug) in item.attributes"
                                :key="slug"
                                class="inline-block text-[9px] text-slate-500 bg-gray-100 rounded px-1.5 py-0.5"
                            >
                                {{ slug }}:
                                {{
                                    Array.isArray(vals)
                                        ? vals.map((v) => v.label || v).join(', ')
                                        : vals
                                }}
                            </span>
                        </div>

                        <div
                            class="mt-2 flex items-center justify-between"
                        >
<!-- Quantity -->
                            <div
                                class="flex items-center rounded-lg border border-gray-200"
                            >
                                <button
                                    type="button"
                                    :disabled="item.quantity <= 1"
                                    class="px-3 py-2 text-sm text-ink hover:bg-gray-100 transition-colors disabled:opacity-30 min-h-[44px] min-w-[44px] flex items-center justify-center"
                                    @click="dec(item)"
                                >
                                    −
                                </button>
                                <span
                                    class="min-w-[3rem] text-center text-sm font-mono"
                                >{{ item.quantity }}</span
                                >
                                <button
                                    type="button"
                                    :disabled="item.quantity >= item.stock"
                                    class="px-3 py-2 text-sm text-ink hover:bg-gray-100 transition-colors disabled:opacity-30 min-h-[44px] min-w-[44px] flex items-center justify-center"
                                    @click="inc(item)"
                                >
                                    +
                                </button>
                            </div>

                            <!-- Subtotal -->
                            <span
                                class="text-sm font-black text-brand-accent font-mono"
                            >
                                {{ formatPrice(item.price * item.quantity) }}
                                <span
                                    class="text-[10px] font-sans text-slate-400"
                                    >تومان</span
                                >
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 mt-4"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-slate-500"
                            >تعداد کل اقلام</span
                        >
                        <span class="text-sm font-bold text-ink">{{
                            cartCount
                        }}</span>
                    </div>
                    <div
                        class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200"
                    >
                        <span class="text-xs text-slate-500">جمع کل</span>
                        <span
                            class="text-lg font-black text-brand-accent font-mono"
                        >
                            {{ formatPrice(cartTotal) }}
                            <span
                                class="text-xs font-sans text-slate-500"
                                >تومان</span
                            >
                        </span>
                    </div>
                    <div class="flex gap-3">
                        <a
                            href="/store"
                            class="flex-1 text-center rounded-lg border border-gray-200 py-3 text-xs font-bold text-slate-600 hover:border-gray-400 transition-colors min-h-[44px] flex items-center justify-center"
                        >
                            ادامه خرید
                        </a>
                        <a
                            href="/checkout"
                            class="flex-1 text-center rounded-lg bg-brand-accent text-dark-900 py-3 text-xs font-bold hover:bg-brand-hover min-h-[44px] flex items-center justify-center"
                        >
                            تکمیل خرید
                        </a>
                    </div>
                    <button
                        type="button"
                        class="mt-3 w-full text-center text-[11px] text-red-500 hover:text-red-600 font-medium transition-colors"
                        @click="clearCart"
                    >
                        خالی کردن سبد
                    </button>
                </div>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>
