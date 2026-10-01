<script setup>
import { onMounted, ref, watch } from 'vue';
import AccountLayout from './components/account/AccountLayout.vue';
import AccountNotice from './components/account/AccountNotice.vue';
import AccountState from './components/account/AccountState.vue';
import WishlistCard from './components/account/WishlistCard.vue';
import { state as authState, isLoggedIn, loadUser } from './auth-state.js';
import { state as wishlistState, loadWishlist, removeFromWishlist } from './wishlist-state.js';

// wishlist-state is the single source of truth: it clears itself on logout or an
// account change, serialises requests and handles 401 by revalidating the token.
onMounted(async () => {
    if (!isLoggedIn.value && !authState.loading) await loadUser();
    if (isLoggedIn.value) loadWishlist();
});

watch(() => authState.user?.id, (userId) => { if (userId) loadWishlist(); });

const removing = ref(null);

function friendlyError(status) {
    if (status === 401) return 'نشست شما معتبر نیست. دوباره وارد حساب کاربری شوید.';
    if (status === 404) return 'این فهرست دیگر وجود ندارد.';
    if (status === 429) return 'تعداد درخواست‌ها بیش از حد مجاز است. کمی صبر کنید.';
    return 'دریافت علاقه‌مندی‌ها انجام نشد. لطفاً دوباره تلاش کنید.';
}

async function removeItem(productId) {
    if (removing.value !== null || wishlistState.loading) return;
    removing.value = productId;
    try {
        await removeFromWishlist(productId);
    } finally {
        removing.value = null;
    }
}
</script>

<template>
    <AccountLayout
        active="wishlist"
        title="علاقه‌مندی‌ها"
        description="محصولاتی که برای بررسی بعدی ذخیره کرده‌اید."
    >
        <AccountState v-if="authState.loading" busy description="در حال بررسی حساب کاربری…" />

        <section v-else-if="!isLoggedIn" class="ac-card">
            <AccountState
                icon="fa-regular fa-heart"
                title="برای دیدن علاقه‌مندی‌ها وارد شوید"
                description="علاقه‌مندی‌ها به حساب کاربری شما متصل است و روی همه دستگاه‌ها در دسترس می‌ماند."
            >
                <a class="sf-button" href="/login">ورود به حساب کاربری</a>
            </AccountState>
        </section>

        <template v-else>
            <section v-if="wishlistState.error" class="ac-card">
                <div class="ac-card-body">
                    <AccountNotice tone="error" icon="fa-solid fa-circle-exclamation">
                        {{ friendlyError(wishlistState.error.status) }}
                    </AccountNotice>
                    <div class="ac-actions">
                        <button type="button" class="sf-button" :disabled="wishlistState.loading" @click="loadWishlist()">تلاش دوباره</button>
                    </div>
                </div>
            </section>

            <AccountState v-else-if="wishlistState.loading" busy description="علاقه‌مندی‌های شما در حال دریافت است." />

            <section v-else-if="!wishlistState.items.length" class="ac-card">
                <AccountState
                    icon="fa-regular fa-heart"
                    title="فهرست علاقه‌مندی‌ها خالی است"
                    description="محصولات دلخواه‌تان را ذخیره کنید تا بعداً سریع‌تر پیدایشان کنید."
                >
                    <a class="sf-button" href="/store">مشاهده محصولات</a>
                </AccountState>
            </section>

            <div v-else :aria-busy="wishlistState.loading" class="ac-items">
                <WishlistCard
                    v-for="item in wishlistState.items"
                    :key="item.product_id"
                    :item="item"
                    :busy="removing === item.product_id || wishlistState.loading"
                    @remove="removeItem"
                />
            </div>
        </template>
    </AccountLayout>
</template>
