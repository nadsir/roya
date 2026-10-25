<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const loading = ref(true);
const settings = ref(null);

const defaults = {
    enabled: true,
    eyebrow: 'YOUR NEXT CHAPTER',
    heading: 'انتخاب بعدی، به سلیقه شما.',
    intro: 'استایل جدید را با اطمینان کشف کنید؛ رویا گالری در کنار شماست تا انتخاب‌هایتان را در سراسر ایران دریافت کنید و برای خرید عمده، همکاری تجاری آغاز کنید.',
    delivery_enabled: true,
    delivery_title: 'استایل شما، در سراسر ایران',
    delivery_description: 'انتخابتان را با خیال راحت سفارش دهید؛ رویا گالری در کنار شماست تا محصولاتتان را در سراسر ایران دریافت کنید.',
    delivery_image: null,
    delivery_cta_label: 'مشاهده محصولات',
    delivery_cta_url: '/store',
    wholesale_enabled: true,
    wholesale_title: 'همکاری عمده با رویا گالری',
    wholesale_description: 'برای خرید عمده و آغاز یک همکاری تجاری، با ما در ارتباط باشید.',
    wholesale_image: null,
    wholesale_cta_label: 'درخواست همکاری عمده',
    whatsapp_number: '09126612898',
    whatsapp_message: 'سلام، برای دریافت اطلاعات خرید عمده محصولات رویا گالری و شرایط همکاری پیام می‌دهم.',
    whatsapp_url: 'https://wa.me/989126612898?text=' + encodeURIComponent('سلام، برای دریافت اطلاعات خرید عمده محصولات رویا گالری و شرایط همکاری پیام می‌دهم.'),
};

const s = computed(() => ({
    ...defaults,
    ...(settings.value || {}),
    intro: settings.value?.intro || defaults.intro,
}));

const visible = computed(() => s.value.enabled !== false);
const deliveryVisible = computed(() => visible.value && s.value.delivery_enabled !== false);
const wholesaleVisible = computed(() => visible.value && s.value.wholesale_enabled !== false);
const hasAny = computed(() => deliveryVisible.value || wholesaleVisible.value);

async function load() {
    try {
        const { data } = await axios.get('/api/next-chapter');
        settings.value = data;
    } catch (e) {
        settings.value = null;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section
        v-if="!loading && visible && hasAny"
        class="hp-nextchapter"
        aria-labelledby="hp-nextchapter-title"
    >
        <div class="hp-nextchapter-inner">
            <header class="hp-nextchapter-head">
                <p class="hp-nextchapter-eyebrow" lang="en" dir="ltr">{{ s.eyebrow }}</p>
                <h2 id="hp-nextchapter-title" class="hp-nextchapter-heading">{{ s.heading }}</h2>
                <p v-if="s.intro" class="hp-nextchapter-intro">{{ s.intro }}</p>
                <span class="hp-nextchapter-rule" aria-hidden="true"></span>
            </header>

            <div class="hp-nextchapter-grid">
                <!-- Nationwide delivery -->
                <article v-if="deliveryVisible" class="hp-nextchapter-panel hp-nextchapter-panel--delivery">
                    <div class="hp-nextchapter-media">
                        <img
                            v-if="s.delivery_image"
                            :src="s.delivery_image"
                            :alt="s.delivery_title"
                            width="960"
                            height="720"
                            loading="lazy"
                            decoding="async"
                        />
                        <div v-else class="hp-nextchapter-fallback hp-nextchapter-fallback--delivery" aria-hidden="true">
                            <span class="hp-nextchapter-glyph">IR</span>
                        </div>
                        <div class="hp-nextchapter-overlay hp-nextchapter-overlay--delivery"></div>
                    </div>
                    <div class="hp-nextchapter-copy">
                        <span class="hp-nextchapter-kicker">NATIONWIDE DELIVERY</span>
                        <h3 class="hp-nextchapter-title">{{ s.delivery_title }}</h3>
                        <p class="hp-nextchapter-desc">{{ s.delivery_description }}</p>
                        <a :href="s.delivery_cta_url || '/store'" class="hp-nextchapter-cta hp-nextchapter-cta--delivery">
                            <span>{{ s.delivery_cta_label }}</span>
                            <span aria-hidden="true">←</span>
                        </a>
                    </div>
                </article>

                <!-- Wholesale -->
                <article v-if="wholesaleVisible" class="hp-nextchapter-panel hp-nextchapter-panel--wholesale">
                    <div class="hp-nextchapter-media">
                        <img
                            v-if="s.wholesale_image"
                            :src="s.wholesale_image"
                            :alt="s.wholesale_title"
                            width="960"
                            height="720"
                            loading="lazy"
                            decoding="async"
                        />
                        <div v-else class="hp-nextchapter-fallback hp-nextchapter-fallback--wholesale" aria-hidden="true">
                            <span class="hp-nextchapter-glyph">G</span>
                        </div>
                        <div class="hp-nextchapter-overlay hp-nextchapter-overlay--wholesale"></div>
                    </div>
                    <div class="hp-nextchapter-copy">
                        <span class="hp-nextchapter-kicker hp-nextchapter-kicker--gold">WHOLESALE PARTNERSHIP</span>
                        <h3 class="hp-nextchapter-title">{{ s.wholesale_title }}</h3>
                        <p class="hp-nextchapter-desc">{{ s.wholesale_description }}</p>
                        <a
                            :href="s.whatsapp_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hp-nextchapter-cta hp-nextchapter-cta--wholesale"
                            :aria-label="s.wholesale_cta_label + ' از طریق واتساپ'"
                        >
                            <span class="hp-nextchapter-wa-icon" aria-hidden="true">WA</span>
                            <span>{{ s.wholesale_cta_label }}</span>
                            <span aria-hidden="true">←</span>
                        </a>
                        <p class="hp-nextchapter-contact">
                            {{ s.whatsapp_number }}
                        </p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hp-nextchapter {
    position: relative;
    background: #0d0d0d;
    color: #f4f4f4;
    padding-block: clamp(64px, 8vw, 112px);
    overflow: hidden;
    isolation: isolate;
}

.hp-nextchapter::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 15% 20%, rgba(255, 205, 0, 0.08), transparent 45%),
        radial-gradient(circle at 85% 80%, rgba(255, 255, 255, 0.04), transparent 40%);
    pointer-events: none;
    z-index: 0;
}

.hp-nextchapter-inner {
    position: relative;
    z-index: 1;
    width: min(1400px, calc(100% - 2 * clamp(16px, 3.2vw, 48px)));
    margin-inline: auto;
}

.hp-nextchapter-head {
    max-width: 760px;
    margin-bottom: clamp(32px, 4vw, 56px);
}

.hp-nextchapter-eyebrow {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.55);
    margin: 0 0 12px;
}

.hp-nextchapter-heading {
    margin: 0 0 14px;
    font-size: clamp(28px, 4vw, 52px);
    font-weight: 300;
    line-height: 1.3;
    letter-spacing: -0.01em;
    color: #f4f4f4;
}

.hp-nextchapter-intro {
    margin: 0 0 18px;
    font-size: 0.9375rem;
    line-height: 2;
    color: rgba(255, 255, 255, 0.7);
    max-width: 62ch;
}

.hp-nextchapter-rule {
    display: block;
    width: 72px;
    height: 1px;
    background: linear-gradient(90deg, #FFCD00, transparent);
}

.hp-nextchapter-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: clamp(20px, 2.4vw, 32px);
}

.hp-nextchapter-panel {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: #161616;
    display: flex;
    flex-direction: column;
    min-height: clamp(360px, 42vw, 520px);
    transition: border-color var(--sf-duration-normal, 260ms), transform var(--sf-duration-normal, 260ms) var(--sf-ease, cubic-bezier(.22,1,.36,1));
}

.hp-nextchapter-panel:hover {
    border-color: rgba(255, 205, 0, 0.45);
    transform: translateY(-4px);
}

.hp-nextchapter-media {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: #1f1f1f;
}

.hp-nextchapter-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 900ms var(--sf-ease, cubic-bezier(.22,1,.36,1));
}

.hp-nextchapter-panel:hover .hp-nextchapter-media img {
    transform: scale(1.05);
}

.hp-nextchapter-fallback {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    font-family: Georgia, serif;
    font-size: clamp(80px, 12vw, 160px);
    font-style: italic;
    color: rgba(255, 205, 0, 0.25);
    background: linear-gradient(135deg, #1f1f1f, #0d0d0d);
}

.hp-nextchapter-fallback--wholesale {
    color: rgba(255, 255, 255, 0.12);
}

.hp-nextchapter-overlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.hp-nextchapter-overlay--delivery {
    background: linear-gradient(180deg, transparent 40%, rgba(13, 13, 13, 0.85) 100%);
}

.hp-nextchapter-overlay--wholesale {
    background: linear-gradient(180deg, transparent 30%, rgba(13, 13, 13, 0.9) 100%);
}

.hp-nextchapter-copy {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: clamp(20px, 2.4vw, 32px);
    flex: 1;
}

.hp-nextchapter-kicker {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.5);
}

.hp-nextchapter-kicker--gold {
    color: #FFCD00;
}

.hp-nextchapter-title {
    margin: 0;
    font-size: clamp(20px, 2.2vw, 28px);
    font-weight: 400;
    line-height: 1.4;
    color: #f4f4f4;
}

.hp-nextchapter-desc {
    margin: 0;
    font-size: 0.875rem;
    line-height: 2;
    color: rgba(255, 255, 255, 0.7);
}

.hp-nextchapter-cta {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 48px;
    padding: 10px 20px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: transparent;
    color: #f4f4f4;
    font-size: 0.8125rem;
    font-weight: 500;
    width: fit-content;
    transition: background var(--sf-duration-fast, 160ms), border-color var(--sf-duration-fast, 160ms), gap var(--sf-duration-fast, 160ms);
}

.hp-nextchapter-cta:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: #f4f4f4;
    gap: 16px;
}

.hp-nextchapter-cta--wholesale {
    border-color: #FFCD00;
    background: #FFCD00;
    color: #0d0d0d;
}

.hp-nextchapter-cta--wholesale:hover {
    background: #e6b800;
    border-color: #e6b800;
    color: #0d0d0d;
}

.hp-nextchapter-wa-icon {
    display: inline-grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(13, 13, 13, 0.15);
    font-size: 0.625rem;
    font-weight: 700;
}

.hp-nextchapter-contact {
    margin: 4px 0 0;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.45);
    letter-spacing: 0.06em;
    direction: ltr;
    text-align: right;
}

/* Responsive */
@media (max-width: 900px) {
    .hp-nextchapter-grid {
        grid-template-columns: 1fr;
    }

    .hp-nextchapter-panel {
        min-height: auto;
    }

    .hp-nextchapter-media {
        aspect-ratio: 16 / 10;
    }
}

@media (max-width: 600px) {
    .hp-nextchapter-heading {
        font-size: clamp(24px, 7vw, 34px);
    }

    .hp-nextchapter-cta {
        width: 100%;
        justify-content: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hp-nextchapter-panel,
    .hp-nextchapter-media img {
        transition: none;
        transform: none;
    }
}
</style>