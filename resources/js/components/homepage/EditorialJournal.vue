<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const articles = ref([]);
const loading = ref(true);
const error = ref('');

function toPersianNumber(n) {
    return String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

function pad(n) {
    return String(n).padStart(2, '0');
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' }).format(d);
}

function articleImage(a) {
    if (!a || !a.featured_image) return '';
    if (/^https?:\/\//i.test(a.featured_image)) return a.featured_image;
    return `/storage/${a.featured_image.replace(/^\/?storage\//, '')}`;
}

function onImgError(e) {
    e.target.onerror = null;
    e.target.style.display = 'none';
}

function readingTime(a) {
    const text = (a.content || '').replace(/<[^>]*>/g, ' ');
    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
    return Math.max(1, Math.round(words / 160));
}

function primaryCategory(a) {
    return a?.categories?.[0] || null;
}

const featuredArticle = computed(() => {
    if (!articles.value.length) return null;
    return articles.value.find((a) => a.is_featured) || articles.value[0];
});

const supportingArticles = computed(() => {
    if (!articles.value.length) return [];
    const featured = featuredArticle.value;
    return articles.value.filter((a) => a.id !== featured.id).slice(0, 3);
});

async function loadArticles() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/articles');
        articles.value = data.data || [];
    } catch (e) {
        articles.value = [];
        error.value = e.response?.data?.message || 'دریافت مقالات ناموفق بود.';
    } finally {
        loading.value = false;
    }
}

onMounted(loadArticles);
</script>

<template>
    <section class="hp-journal" aria-labelledby="hp-journal-title">
        <!-- Header -->
        <header class="hp-journal-head">
            <div class="hp-journal-head-copy">
                <p class="hp-journal-eyebrow" lang="en" dir="ltr">ROYA STYLE JOURNAL</p>
                <h2 id="hp-journal-title">مجله مد رویا</h2>
                <p class="hp-journal-subtitle">ایده‌هایی برای استایل امروز شما</p>
            </div>
            <a href="/articles" class="hp-journal-all">
                مشاهده همه مقالات
                <span aria-hidden="true">←</span>
            </a>
        </header>

        <!-- Loading -->
        <div v-if="loading" class="hp-journal-state" aria-busy="true" aria-live="polite">
            <div class="hp-journal-skeleton-featured"></div>
            <div class="hp-journal-skeleton-row">
                <div v-for="i in 3" :key="i" class="hp-journal-skeleton-card"></div>
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="hp-journal-state">
            <p class="hp-journal-state-text">{{ error }}</p>
            <button type="button" class="hp-journal-retry" @click="loadArticles">تلاش مجدد</button>
        </div>

        <!-- Empty -->
        <div v-else-if="!articles.length" class="hp-journal-state">
            <p class="hp-journal-state-text">هنوز مقاله‌ای منتشر نشده است.</p>
            <a href="/articles" class="hp-journal-all hp-journal-all--solid">ورود به مجله</a>
        </div>

        <!-- Content -->
        <template v-else>
            <!-- Featured article -->
            <a v-if="featuredArticle" :href="`/articles/${featuredArticle.slug}`" class="hp-journal-featured">
                <div class="hp-journal-featured-media">
                    <img
                        v-if="articleImage(featuredArticle)"
                        :src="articleImage(featuredArticle)"
                        :alt="featuredArticle.title"
                        width="960"
                        height="1200"
                        loading="eager"
                        fetchpriority="high"
                        decoding="async"
                        @error="onImgError($event)"
                    />
                    <div v-else class="hp-journal-placeholder" aria-hidden="true">
                        <span>G.</span>
                    </div>
                </div>

                <div class="hp-journal-featured-copy">
                    <div class="hp-journal-meta">
                        <span v-if="primaryCategory(featuredArticle)" class="hp-journal-category">
                            {{ primaryCategory(featuredArticle).name }}
                        </span>
                        <span v-if="featuredArticle.published_at" class="hp-journal-date">
                            {{ formatDate(featuredArticle.published_at) }}
                        </span>
                    </div>

                    <h3 class="hp-journal-featured-title">{{ featuredArticle.title }}</h3>

                    <p v-if="featuredArticle.excerpt" class="hp-journal-excerpt">
                        {{ featuredArticle.excerpt }}
                    </p>

                    <div class="hp-journal-featured-foot">
                        <div class="hp-journal-featured-meta">
                            <span v-if="featuredArticle.author" class="hp-journal-author">
                                {{ featuredArticle.author.name }}
                            </span>
                            <span class="hp-journal-reading">
                                {{ toPersianNumber(readingTime(featuredArticle)) }} دقیقه مطالعه
                            </span>
                        </div>
                        <span class="hp-journal-read">
                            مطالعه مقاله
                            <span aria-hidden="true">←</span>
                        </span>
                    </div>
                </div>
            </a>

            <!-- Supporting articles -->
            <div v-if="supportingArticles.length" class="hp-journal-support">
                <a
                    v-for="(article, idx) in supportingArticles"
                    :key="article.id"
                    :href="`/articles/${article.slug}`"
                    class="hp-journal-card"
                >
                    <div class="hp-journal-card-media">
                        <img
                            v-if="articleImage(article)"
                            :src="articleImage(article)"
                            :alt="article.title"
                            width="640"
                            height="800"
                            loading="lazy"
                            decoding="async"
                            @error="onImgError($event)"
                        />
                        <div v-else class="hp-journal-placeholder hp-journal-placeholder--small" aria-hidden="true">
                            <span>G.</span>
                        </div>
                        <span class="hp-journal-card-number" aria-hidden="true">{{ pad(idx + 2) }}</span>
                    </div>

                    <div class="hp-journal-card-copy">
                        <div class="hp-journal-meta">
                            <span v-if="primaryCategory(article)" class="hp-journal-category">
                                {{ primaryCategory(article).name }}
                            </span>
                            <span v-if="article.published_at" class="hp-journal-date">
                                {{ formatDate(article.published_at) }}
                            </span>
                        </div>

                        <h3 class="hp-journal-card-title">{{ article.title }}</h3>

                        <p v-if="article.excerpt" class="hp-journal-card-excerpt">
                            {{ article.excerpt }}
                        </p>

                        <span class="hp-journal-card-read">
                            مطالعه مقاله
                            <span aria-hidden="true">←</span>
                        </span>
                    </div>
                </a>
            </div>
        </template>
    </section>
</template>

<style scoped>
.hp-journal {
    padding-block: clamp(48px, 6vw, 88px);
    border-top: 1px solid var(--sf-border);
}

.hp-journal-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: clamp(16px, 3vw, 40px);
    margin-bottom: clamp(24px, 3vw, 40px);
}

.hp-journal-head-copy {
    min-width: 0;
}

.hp-journal-eyebrow {
    font-size: var(--sf-type-eyebrow);
    font-weight: 700;
    letter-spacing: var(--sf-tracking-wide);
    text-transform: uppercase;
    color: var(--sf-text-muted);
    margin: 0 0 10px;
}

.hp-journal-head h2 {
    margin: 0 0 6px;
    font-size: clamp(24px, 3vw, 40px);
    font-weight: 300;
    line-height: 1.3;
    letter-spacing: -0.01em;
}

.hp-journal-subtitle {
    margin: 0;
    font-size: var(--sf-type-small);
    color: var(--sf-text-secondary);
    line-height: 1.9;
}

.hp-journal-all {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 8px 0;
    border-bottom: 1px solid var(--sf-border-strong);
    font-size: var(--sf-type-small);
    font-weight: 500;
    color: var(--sf-text);
    white-space: nowrap;
    transition: border-color var(--sf-duration-fast), gap var(--sf-duration-fast);
}

.hp-journal-all:hover {
    border-color: var(--sf-text);
    gap: 16px;
}

.hp-journal-all--solid {
    padding: 10px 18px;
    border: 1px solid var(--sf-accent);
    background: var(--sf-accent);
    color: var(--sf-on-accent);
    border-bottom: 1px solid var(--sf-accent);
}

.hp-journal-all--solid:hover {
    background: var(--sf-accent-hover);
    border-color: var(--sf-accent-hover);
    gap: 10px;
}

/* Featured */
.hp-journal-featured {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
    gap: clamp(20px, 3vw, 48px);
    align-items: stretch;
    margin-bottom: clamp(32px, 4vw, 56px);
    color: inherit;
    text-decoration: none;
}

.hp-journal-featured-media {
    position: relative;
    aspect-ratio: 4 / 5;
    overflow: hidden;
    background: var(--sf-surface-soft);
}

.hp-journal-featured-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 900ms var(--sf-ease);
}

.hp-journal-featured:hover .hp-journal-featured-media img {
    transform: scale(1.04);
}

.hp-journal-featured-copy {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 14px;
    padding-block: clamp(8px, 2vw, 24px);
}

.hp-journal-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px 18px;
}

.hp-journal-category {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: var(--sf-type-eyebrow);
    font-weight: 700;
    letter-spacing: var(--sf-tracking-wide);
    text-transform: uppercase;
    color: var(--sf-text);
}

.hp-journal-category::before {
    content: '';
    width: 18px;
    height: 1px;
    background: var(--sf-text);
}

.hp-journal-date {
    font-size: var(--sf-type-caption);
    color: var(--sf-text-muted);
}

.hp-journal-featured-title {
    margin: 0;
    font-size: clamp(22px, 2.6vw, 34px);
    font-weight: 400;
    line-height: 1.4;
    letter-spacing: -0.01em;
    transition: color var(--sf-duration-fast);
}

.hp-journal-featured:hover .hp-journal-featured-title {
    color: var(--sf-text-secondary);
}

.hp-journal-excerpt {
    margin: 0;
    font-size: var(--sf-type-body);
    line-height: 2;
    color: var(--sf-text-secondary);
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hp-journal-featured-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 6px;
    padding-top: 16px;
    border-top: 1px solid var(--sf-border);
    flex-wrap: wrap;
}

.hp-journal-featured-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 18px;
    font-size: var(--sf-type-caption);
    color: var(--sf-text-muted);
}

.hp-journal-author {
    font-weight: 500;
    color: var(--sf-text-secondary);
}

.hp-journal-reading {
    color: var(--sf-text-muted);
}

.hp-journal-read {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: var(--sf-type-small);
    font-weight: 500;
    color: var(--sf-text);
    transition: gap var(--sf-duration-fast);
}

.hp-journal-featured:hover .hp-journal-read {
    gap: 14px;
}

.hp-journal-read span {
    transition: transform var(--sf-duration-fast);
}

.hp-journal-featured:hover .hp-journal-read span {
    transform: translateX(-4px);
}

/* Supporting */
.hp-journal-support {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: clamp(20px, 2.4vw, 32px);
}

.hp-journal-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
    color: inherit;
    text-decoration: none;
    min-width: 0;
}

.hp-journal-card-media {
    position: relative;
    aspect-ratio: 3 / 4;
    overflow: hidden;
    background: var(--sf-surface-soft);
}

.hp-journal-card-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 800ms var(--sf-ease);
}

.hp-journal-card:hover .hp-journal-card-media img {
    transform: scale(1.05);
}

.hp-journal-card-number {
    position: absolute;
    top: 12px;
    inset-inline-start: 12px;
    z-index: 2;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    color: var(--sf-text);
    background: var(--sf-surface);
    padding: 4px 8px;
    border: 1px solid var(--sf-border);
}

.hp-journal-card-copy {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}

.hp-journal-card-title {
    margin: 0;
    font-size: var(--sf-type-h3);
    font-weight: 500;
    line-height: 1.6;
    color: var(--sf-text);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color var(--sf-duration-fast);
}

.hp-journal-card:hover .hp-journal-card-title {
    color: var(--sf-text-secondary);
}

.hp-journal-card-excerpt {
    margin: 0;
    font-size: var(--sf-type-small);
    line-height: 1.85;
    color: var(--sf-text-secondary);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hp-journal-card-read {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    font-size: var(--sf-type-caption);
    font-weight: 500;
    color: var(--sf-text);
    transition: gap var(--sf-duration-fast);
}

.hp-journal-card:hover .hp-journal-card-read {
    gap: 10px;
}

.hp-journal-card-read span {
    transition: transform var(--sf-duration-fast);
}

.hp-journal-card:hover .hp-journal-card-read span {
    transform: translateX(-3px);
}

/* Placeholder */
.hp-journal-placeholder {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    background: var(--sf-surface-soft);
    color: var(--sf-text-muted);
    font-family: Georgia, serif;
    font-size: clamp(80px, 10vw, 140px);
    font-style: italic;
}

.hp-journal-placeholder--small {
    font-size: clamp(48px, 6vw, 80px);
}

/* State */
.hp-journal-state {
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    text-align: center;
    color: var(--sf-text-secondary);
    font-size: var(--sf-type-body);
    border: 1px solid var(--sf-border);
    padding: clamp(28px, 4vw, 48px);
}

.hp-journal-state-text {
    margin: 0;
}

.hp-journal-retry {
    min-height: 44px;
    padding: 10px 18px;
    border: 1px solid var(--sf-border-strong);
    background: transparent;
    color: var(--sf-text);
    font-size: var(--sf-type-small);
    transition: border-color var(--sf-duration-fast), background var(--sf-duration-fast);
}

.hp-journal-retry:hover {
    border-color: var(--sf-text);
    background: var(--sf-surface-soft);
}

/* Skeleton */
.hp-journal-skeleton-featured {
    aspect-ratio: 4 / 5;
    max-height: 420px;
    background: var(--sf-surface-soft);
    animation: hpj-pulse 1.4s ease-in-out infinite;
    margin-bottom: clamp(24px, 3vw, 40px);
}

.hp-journal-skeleton-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: clamp(20px, 2.4vw, 32px);
}

.hp-journal-skeleton-card {
    aspect-ratio: 3 / 4;
    background: var(--sf-surface-soft);
    animation: hpj-pulse 1.4s ease-in-out infinite;
}

@keyframes hpj-pulse {
    50% { opacity: 0.55; }
}

@keyframes hpj-rise {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive */
@media (max-width: 900px) {
    .hp-journal-featured {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .hp-journal-featured-media {
        aspect-ratio: 16 / 10;
    }

    .hp-journal-support {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hp-journal-skeleton-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .hp-journal-head {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .hp-journal-featured-media {
        aspect-ratio: 4 / 3;
    }

    .hp-journal-excerpt {
        -webkit-line-clamp: 3;
    }

    .hp-journal-support {
        display: flex;
        gap: 14px;
        overflow-x: auto;
        scroll-snap-type: x proximity;
        overscroll-behavior-x: contain;
        padding-bottom: 8px;
        scrollbar-width: none;
        margin-inline: -18px;
        padding-inline: 18px;
    }

    .hp-journal-support > * {
        flex: 0 0 78%;
        scroll-snap-align: start;
    }

    .hp-journal-skeleton-row {
        display: flex;
        gap: 14px;
        overflow-x: auto;
        scrollbar-width: none;
        margin-inline: -18px;
        padding-inline: 18px;
    }

    .hp-journal-skeleton-row > * {
        flex: 0 0 78%;
    }

    .hp-journal-support::-webkit-scrollbar,
    .hp-journal-skeleton-row::-webkit-scrollbar {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hp-journal-featured-media img,
    .hp-journal-card-media img {
        transition: none;
        transform: none;
    }

    .hp-journal-skeleton-featured,
    .hp-journal-skeleton-card {
        animation: none;
    }
}
</style>