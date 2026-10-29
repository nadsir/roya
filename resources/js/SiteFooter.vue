<script setup>
import { onMounted } from 'vue';
import { catalog, categoryHref, loadCatalog } from './catalog-state.js';
import SocialIcon from './components/social/SocialIcon.vue';
import { ACTIVE_SOCIAL_NETWORKS } from './social-links.js';
onMounted(() => loadCatalog());
const year = new Intl.DateTimeFormat('fa-IR', { year: 'numeric' }).format(new Date());
const LOGO_SRC = '/images/logo/logo.webp';
const socialNetworks = ACTIVE_SOCIAL_NETWORKS;
</script>

<template>
    <footer class="sf-footer" dir="rtl">
        <div class="sf-container">
            <div class="sf-footer-invitation"><div><h2>انتخاب‌های شما، کنار هم.</h2><p>حساب گالری؛ برای نگه‌داشتن علاقه‌مندی‌ها و پیگیری سفارش‌ها.</p></div><a href="/account" class="sf-button">ورود به حساب گالری ←</a></div>
            <div class="sf-footer-grid">
                <div class="sf-footer-brand">
                    <a href="/" class="sf-wordmark" aria-label="گالری؛ صفحه اصلی"><img :src="LOGO_SRC" alt="ROYA" width="42" height="42" /></a>
                    <p class="sf-type-body">سبک شما، روایت شما.</p>
                    <p class="sf-type-small">انتخابی از پوشاک، کیف، کفش و اکسسوری زنانه؛ برای جزئیاتی که تفاوت می‌سازند.</p>
                    <nav v-if="socialNetworks.length" class="sf-footer-social" aria-label="شبکه‌های اجتماعی">
                        <h2 class="sf-type-metadata">شبکه‌های اجتماعی</h2>
                        <ul>
                            <li v-for="network in socialNetworks" :key="network.key">
                                <a
                                    :href="network.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="sf-footer-social-link"
                                    :aria-label="`${network.label} رویا گالری`"
                                >
                                    <span class="sf-footer-social-icon" aria-hidden="true"><SocialIcon :network="network.key" :size="18" /></span>
                                    <span>{{ network.label }}</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <nav aria-label="فروشگاه"><h2 class="sf-type-metadata">فروشگاه</h2><ul>
                    <li><a href="/store">همه محصولات</a></li><li><a href="/store?sort=newest">تازه‌ها</a></li>
                    <li v-for="category in catalog.categories.slice(0, 4)" :key="category.slug"><a :href="categoryHref(category.slug)">{{ category.name }}</a></li>
                </ul></nav>
                <nav aria-label="خدمات مشتریان"><h2 class="sf-type-metadata">همراه شما</h2><ul>
                    <li><a href="/account">حساب کاربری</a></li><li><a href="/orders">پیگیری سفارش‌ها</a></li><li><a href="/wishlist">علاقه‌مندی‌ها</a></li><li><a href="/cart">سبد خرید</a></li>
                </ul></nav>
                <nav aria-label="مطالعه و عضویت"><h2 class="sf-type-metadata">دنیای گالری</h2><ul>
                    <li><a href="/articles">مجله</a></li><li><a href="/login">ورود به گالری</a></li><li><a href="/register">ساخت حساب کاربری</a></li>
                </ul></nav>
            </div>
            <div class="sf-footer-bottom"><p>© {{ year }} گالری. تمامی حقوق محفوظ است.</p><span class="sf-type-caption" lang="en" dir="ltr">A considered wardrobe.</span></div>
        </div>
    </footer>
</template>
