<script setup>
import SiteHeader from '../../SiteHeader.vue';
import SiteFooter from '../../SiteFooter.vue';
import { state as authState } from '../../auth-state.js';
import { wishlistCount } from '../../wishlist-state.js';
import { ACCOUNT_NAV } from '../../account-presentation.js';
import '../../../css/account.css';

// The desktop layout puts the navigation beside the content; on mobile the same
// links become a scrollable rail above it, so every account page shares one shell.
defineProps({
    active: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});

function countFor(key) {
    return key === 'wishlist' && wishlistCount.value ? wishlistCount.value : 0;
}
</script>

<template>
    <div class="ac-page" dir="rtl">
        <SiteHeader />

        <main class="sf-container ac-main">
            <nav class="ac-crumbs" aria-label="مسیر صفحه">
                <a href="/">خانه</a>
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                <span aria-current="page">{{ title }}</span>
            </nav>

            <header class="ac-head">
                <h1 class="sf-type-h1">{{ title }}</h1>
                <p v-if="description" class="sf-type-body">{{ description }}</p>
            </header>

            <div class="ac-layout">
                <div>
                    <div v-if="authState.user" class="ac-member">
                        <span class="ac-member-mark" aria-hidden="true"><i class="fa-regular fa-user" /></span>
                        <span>
                            <strong>{{ authState.user.name }}</strong>
                            <small dir="ltr">{{ authState.user.email }}</small>
                        </span>
                    </div>

                    <nav class="ac-nav" aria-label="بخش‌های حساب کاربری">
                        <a
                            v-for="item in ACCOUNT_NAV"
                            :key="item.key"
                            :href="item.href"
                            class="ac-nav-link"
                            :aria-current="item.key === active ? 'page' : undefined"
                        >
                            <i :class="item.icon" aria-hidden="true"></i>
                            <span>
                                {{ item.label }}
                                <span v-if="countFor(item.key)" class="ac-nav-count">{{ countFor(item.key) }}</span>
                            </span>
                            <small>{{ item.hint }}</small>
                        </a>
                    </nav>
                </div>

                <div class="ac-body">
                    <slot />
                </div>
            </div>
        </main>

        <SiteFooter />
    </div>
</template>
