<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { cartCount } from './cart-state.js';
import { hideCartNotification } from './cart-notification-state.js';
import { state as authState, isLoggedIn, logout } from './auth-state.js';
import { wishlistCount, loadWishlist, resetWishlist } from './wishlist-state.js';
import { storefrontDark, toggleStorefrontTheme } from './storefront-theme.js';
import MegaMenu from './components/MegaMenu.vue';
import ShellDialog from './components/ShellDialog.vue';
import CartDrawer from './components/cart/CartDrawer.vue';
import CartNotification from './components/CartNotification.vue';
import SearchOverlay from './components/SearchOverlay.vue';

const searchOpen = ref(false);
const LOGO_SRC = '/images/logo/logo.webp';
const menuOpen = ref(false);
const cartOpen = ref(false);
const accountOpen = ref(false);
const accountRoot = ref(null);
const accountTrigger = ref(null);
const loggingOut = ref(false);
const logoutError = ref('');
const scrolled = ref(false);
const path = location.pathname;
const faNum = (value) => Number(value).toLocaleString('fa-IR');

watch(() => authState.user?.id, (userId) => { accountOpen.value = false; if (userId) loadWishlist(); }, { immediate: true });
function openSearch() { menuOpen.value = false; accountOpen.value = false; nextTick(() => { searchOpen.value = true; }); }
async function toggleAccount() {
    accountOpen.value = !accountOpen.value;
    if (accountOpen.value) { await nextTick(); accountRoot.value?.querySelector('nav a')?.focus(); }
}
function closeAccount(restore = false) { accountOpen.value = false; if (restore) accountTrigger.value?.focus(); }
function outside(event) { if (!accountRoot.value?.contains(event.target)) closeAccount(); }
function focusOut(event) { if (!accountRoot.value?.contains(event.relatedTarget)) closeAccount(); }
function keydown(event) { if (event.key === 'Escape' && accountOpen.value) { event.preventDefault(); closeAccount(true); } }
function onScroll() {
    const y = window.scrollY;
    const next = scrolled.value ? y > 8 : y > 24;
    if (next !== scrolled.value) scrolled.value = next;
}
function viewCart() {
    cartOpen = true;
    hideCartNotification();
}
async function handleLogout() {
    if (loggingOut.value) return;
    loggingOut.value = true; logoutError.value = '';
    try { await logout(); resetWishlist(); closeAccount(); }
    catch { logoutError.value = 'خروج انجام نشد. دوباره تلاش کنید.'; }
    finally { loggingOut.value = false; }
}
onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }); document.addEventListener('pointerdown', outside); document.addEventListener('keydown', keydown); });
onBeforeUnmount(() => { window.removeEventListener('scroll', onScroll); document.removeEventListener('pointerdown', outside); document.removeEventListener('keydown', keydown); });
</script>

<template>
    <header class="sf-header" :class="{ 'is-scrolled': scrolled }" dir="rtl">
        <div class="sf-header-bar">
            <div class="sf-header-brand"><button class="sf-icon-button sf-mobile-only" type="button" aria-label="باز کردن دسته‌بندی‌ها" :aria-expanded="menuOpen" aria-haspopup="dialog" @click="menuOpen = true"><i class="fa-solid fa-bars" aria-hidden="true" /></button><a href="/" class="sf-wordmark" aria-label="گالری؛ صفحه اصلی"><img :src="LOGO_SRC" alt="ROYA" width="68" height="68" /></a></div>
            <nav class="sf-desktop-nav" aria-label="ناوبری اصلی"><MegaMenu /><a href="/store?sort=newest" class="sf-nav-link">تازه‌ها</a><a href="/store" class="sf-nav-link" :aria-current="path === '/store' || path.startsWith('/c/') ? 'page' : undefined">فروشگاه</a><a href="/articles" class="sf-nav-link" :aria-current="path.startsWith('/articles') ? 'page' : undefined">مجله</a></nav>
            <div class="sf-header-actions">
                <button type="button" class="sf-icon-button sf-search-trigger" aria-label="باز کردن جستجو" @click="openSearch"><i class="fa-solid fa-magnifying-glass" aria-hidden="true" /><span>جستجو</span></button>
                <button type="button" class="sf-icon-button sf-theme-trigger" :aria-label="storefrontDark ? 'فعال کردن حالت روشن' : 'فعال کردن حالت تیره'" :aria-pressed="storefrontDark" @click="toggleStorefrontTheme"><i :class="storefrontDark ? 'fa-regular fa-sun' : 'fa-regular fa-moon'" aria-hidden="true" /></button>
                <a href="/wishlist" class="sf-icon-button sf-desktop-action" aria-label="علاقه‌مندی‌ها"><i class="fa-regular fa-heart" aria-hidden="true" /><span v-if="isLoggedIn && wishlistCount" class="sf-count">{{ faNum(wishlistCount) }}</span></a>
                <div v-if="isLoggedIn" ref="accountRoot" class="sf-account sf-desktop-action" @focusout="focusOut">
                    <button ref="accountTrigger" class="sf-icon-button" type="button" aria-label="منوی حساب کاربری" :aria-expanded="accountOpen" aria-controls="sf-account-panel" @click="toggleAccount"><i class="fa-regular fa-user" aria-hidden="true" /></button>
                    <nav v-if="accountOpen" id="sf-account-panel" class="sf-account-panel" aria-label="حساب کاربری"><p class="sf-type-small">{{ authState.user?.name || 'حساب من' }}</p><a href="/account">حساب کاربری</a><a href="/orders">سفارش‌های من</a><a href="/wishlist">علاقه‌مندی‌ها</a><button :disabled="loggingOut" @click="handleLogout">{{ loggingOut ? 'در حال خروج…' : 'خروج از حساب' }}</button><p v-if="logoutError" role="alert">{{ logoutError }}</p></nav>
                </div>
                <a v-else href="/login" class="sf-icon-button sf-desktop-action" aria-label="ورود به حساب کاربری"><i class="fa-regular fa-user" aria-hidden="true" /></a>
                <button type="button" class="sf-icon-button sf-cart-link" aria-haspopup="dialog" :aria-expanded="cartOpen" :aria-label="cartCount ? `باز کردن سبد خرید، ${faNum(cartCount)} کالا` : 'باز کردن سبد خرید'" @click="cartOpen = true"><i class="fa-solid fa-bag-shopping" aria-hidden="true" /><span v-if="cartCount" class="sf-count">{{ faNum(cartCount) }}</span></button>
            </div>
        </div>
        <SearchOverlay :open="searchOpen" @close="searchOpen = false" />
        <ShellDialog :open="menuOpen" label="دسته‌بندی‌های گالری" variant="menu" @close="menuOpen = false">
            <div class="sf-dialog-heading"><h2 class="sf-type-h2">دسته‌بندی‌ها</h2><button class="sf-icon-button" aria-label="بستن دسته‌بندی‌ها" @click="menuOpen = false"><i class="fa-solid fa-xmark" aria-hidden="true" /></button></div>
            <MegaMenu variant="mobile" />
            <div class="sf-mobile-menu-links"><a href="/store?sort=newest">تازه‌ها</a><a href="/articles">مجله</a><a href="/orders">سفارش‌های من</a><button class="sf-text-link" @click="toggleStorefrontTheme">{{ storefrontDark ? 'حالت روشن' : 'حالت تیره' }}</button></div>
        </ShellDialog>
        <CartDrawer :open="cartOpen" @close="cartOpen = false" />
    </header>
    <CartNotification @view-cart="viewCart" />
    <nav class="sf-bottom-nav" aria-label="ناوبری موبایل" dir="rtl">
        <a href="/" :aria-current="path === '/' ? 'page' : undefined"><i class="fa-solid fa-house" aria-hidden="true" /><span>خانه</span></a>
        <button :aria-expanded="menuOpen" @click="menuOpen = true"><i class="fa-solid fa-layer-group" aria-hidden="true" /><span>دسته‌ها</span></button>
        <button :aria-expanded="searchOpen" @click="openSearch"><i class="fa-solid fa-magnifying-glass" aria-hidden="true" /><span>جستجو</span></button>
        <a href="/wishlist" :aria-current="path === '/wishlist' ? 'page' : undefined"><i class="fa-regular fa-heart" aria-hidden="true" /><span>علاقه‌مندی</span></a>
        <a :href="isLoggedIn ? '/account' : '/login'" :aria-current="['/account', '/login'].includes(path) ? 'page' : undefined"><i class="fa-regular fa-user" aria-hidden="true" /><span>حساب من</span></a>
    </nav>
</template>
