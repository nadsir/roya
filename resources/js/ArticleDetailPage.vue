<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { state as authState } from './auth-state.js';
import { createCommentSection, faNum } from './comment-state.js';
import CommentList from './components/CommentList.vue';
import CommentComposer from './components/CommentComposer.vue';

const article = ref(null);
const loading = ref(true);
const error = ref('');
const progress = ref(0);
let scrollCleanup = null;
let metaCleanup = null;

const discussion = createCommentSection('article');

watch([() => article.value?.id, () => authState.user?.id], async ([articleId]) => {
    if (!articleId) return;
    if (!discussion.section.ownerId) {
        discussion.section.ownerId = articleId;
        await discussion.loadFirstPage();
    }
    discussion.restoreIntent();
});

function toPersianNumber(n) {
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

function padNumber(n) {
    return String(n).padStart(2, '0');
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' }).format(d);
}

function articleImage() {
    if (!article.value?.featured_image) return '';
    const p = article.value.featured_image;
    if (/^https?:\/\//i.test(p)) return p;
    return `/storage/${p.replace(/^\/?storage\//, '')}`;
}

function absoluteImageUrl(path) {
    if (!path) return '';
    return /^https?:\/\//i.test(path) ? path : window.location.origin + path;
}

function onImgError(e) {
    e.target.onerror = null;
    e.target.style.display = 'none';
}

function readingTime() {
    const a = article.value;
    if (!a) return 0;
    const text = (a.content || '').replace(/<[^>]*>/g, ' ');
    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
    return Math.max(1, Math.round(words / 160));
}

function triggerScrollTracking() {
    const onScroll = () => {
        const doc = document.documentElement;
        const total = doc.scrollHeight - window.innerHeight;
        if (total <= 0) { progress.value = 0; return; }
        progress.value = Math.min(100, Math.max(0, Math.round((window.scrollY / total) * 100)));
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    scrollCleanup = () => {
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', onScroll);
    };
}

function setMeta(name, content) {
    if (!content) { removeMeta(name); return; }
    const isProperty = name.startsWith('og:') || name === 'twitter:card';
    const attr = isProperty ? 'property' : 'name';
    let el = document.querySelector(`meta[${attr}="${name}"]`);
    if (!el) {
        el = document.createElement('meta');
        el.setAttribute(attr, name);
        document.head.appendChild(el);
    }
    el.content = content;
}

function removeMeta(name) {
    document.querySelectorAll(`meta[name="${name}"], meta[property="${name}"]`).forEach((el) => el.remove());
}

function setCanonical(url) {
    let link = document.querySelector('link[rel="canonical"]');
    if (!link) { link = document.createElement('link'); link.rel = 'canonical'; document.head.appendChild(link); }
    link.href = url;
}

function injectJsonLd(obj) {
    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.textContent = JSON.stringify(obj);
    document.head.appendChild(script);
    return script;
}

function applySeo(a) {
    const title = a.meta_title || `${a.title} | مجله توربوپارت`;
    const description = a.meta_description || (a.excerpt ? a.excerpt.slice(0, 160) : a.title);
    document.title = title;
    setMeta('description', description);
    setMeta('og:type', 'article');
    setMeta('og:title', title);
    setMeta('og:description', description);
    setMeta('og:url', window.location.href);
    setMeta('og:site_name', 'توربوپارت');
    setMeta('og:locale', 'fa_IR');
    if (articleImage()) setMeta('og:image', absoluteImageUrl(articleImage()));
    setCanonical(a.canonical_url || `${window.location.origin}/articles/${a.slug}`);

    const ld = [
        {
            '@context': 'https://schema.org',
            '@type': 'Article',
            headline: a.title,
            description,
            image: articleImage() ? absoluteImageUrl(articleImage()) : undefined,
            datePublished: a.published_at || undefined,
            author: a.author ? { '@type': 'Person', name: a.author.name } : { '@type': 'Organization', name: 'توربوپارت' },
            publisher: { '@type': 'Organization', name: 'توربوپارت', logo: { '@type': 'ImageObject', url: `${window.location.origin}/favicon.svg` } },
            mainEntityOfPage: { '@type': 'WebPage', '@id': window.location.href },
        },
        {
            '@context': 'https://schema.org',
            '@type': 'BreadcrumbList',
            itemListElement: [
                { '@type': 'ListItem', position: 1, name: 'خانه', item: window.location.origin + '/' },
                { '@type': 'ListItem', position: 2, name: 'مجله', item: window.location.origin + '/articles' },
                { '@type': 'ListItem', position: 3, name: a.title, item: window.location.href },
            ],
        },
    ];
    const nodes = ld.map((obj) => injectJsonLd(obj));
    metaCleanup = () => {
        nodes.forEach((n) => n.remove());
        removeMeta('description');
        removeMeta('og:type');
        removeMeta('og:title');
        removeMeta('og:description');
        removeMeta('og:url');
        removeMeta('og:site_name');
        removeMeta('og:locale');
        removeMeta('og:image');
        const link = document.querySelector('link[rel="canonical"]');
        if (link) link.remove();
    };
}

async function fetchArticle() {
    const match = location.pathname.match(/\/articles\/([^/]+)\/?$/);
    if (!match) { error.value = 'مقاله مورد نظر پیدا نشد.'; loading.value = false; return; }
    try {
        const { data } = await axios.get(`/api/articles/${encodeURIComponent(match[1])}`);
        article.value = data.data || data;
    } catch (e) {
        error.value = e.response?.status === 404 ? 'مقاله مورد نظر پیدا نشد.' : 'خطا در دریافت اطلاعات مقاله.';
    } finally {
        loading.value = false;
    }
}

async function onShare() {
    const a = article.value;
    if (!a) return;
    const shareData = {
        title: a.meta_title || a.title,
        text: a.excerpt || a.title,
        url: window.location.href,
    };
    if (navigator.share) {
        try { await navigator.share(shareData); } catch { /* user cancelled */ }
        return;
    }
    try {
        await navigator.clipboard.writeText(window.location.href);
        window.dispatchEvent(new CustomEvent('toast', {
            detail: { message: 'لینک مقاله در حافظه کپی شد.', title: 'اشتراک‌گذاری', type: 'success' },
        }));
    } catch {
        window.dispatchEvent(new CustomEvent('toast', {
            detail: { message: 'امکان کپی لینک وجود ندارد.', title: 'خطا', type: 'error' },
        }));
    }
}

const relatedProducts = computed(() => article.value?.products || []);
const relatedVehicles = computed(() => article.value?.vehicles || []);
const relatedBrands = computed(() => article.value?.brands || []);
const categories = computed(() => article.value?.categories || []);

function goBack() {
    if (window.history.length > 1) window.history.back();
    else window.location.href = '/articles';
}

onMounted(async () => {
    await fetchArticle();
    if (article.value) {
        applySeo(article.value);
        triggerScrollTracking();
    }
});

onUnmounted(() => {
    if (scrollCleanup) scrollCleanup();
    if (metaCleanup) metaCleanup();
});
</script>

<template>
    <div dir="rtl" class="min-h-screen bg-cream text-ink font-sans antialiased overflow-x-hidden">
        <SiteHeader />

        <!-- READING PROGRESS -->
        <div class="fixed top-0 left-0 right-0 z-50 h-[3px] bg-transparent pointer-events-none">
            <div class="h-full bg-brand-accent shadow-[0_0_10px_rgba(255,205,0,0.5)] transition-[width] duration-150 ease-out" :style="{ width: `${progress}%` }"></div>
        </div>

        <main>
            <!-- LOADING -->
            <div v-if="loading" class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
                <div class="max-w-[46rem] mx-auto">
                    <div class="h-3 bg-white border border-gray-200 animate-pulse rounded w-40 mb-6"></div>
                    <div class="h-7 bg-white border border-gray-200 animate-pulse rounded w-3/4 mb-4"></div>
                    <div class="h-4 bg-white border border-gray-200 animate-pulse rounded w-1/2 mb-8"></div>
                    <div class="aspect-[16/9] rounded-xl bg-white border border-gray-200 animate-pulse mb-10"></div>
                    <div class="space-y-3">
                        <div v-for="i in 7" :key="i" class="h-3.5 bg-white border border-gray-200 animate-pulse rounded" :class="i % 3 === 0 ? 'w-2/3' : 'w-full'"></div>
                    </div>
                </div>
            </div>

            <!-- ERROR -->
            <div v-else-if="error" class="max-w-4xl mx-auto px-4 py-24 text-center">
                <i class="fa-solid fa-circle-exclamation text-4xl text-slate-400 mb-4"></i>
                <p class="mb-5 text-sm text-red-600">{{ error }}</p>
                <div class="flex items-center justify-center gap-3">
                    <button type="button" class="px-5 py-2 rounded-lg bg-brand-accent text-ink text-xs font-bold hover:bg-brand-hover transition-colors" @click="goBack">
                        بازگشت به مجله
                    </button>
                    <a href="/articles" class="px-5 py-2 rounded-lg border border-gray-200 bg-white text-xs font-bold text-slate-600 hover:border-gray-400 transition-colors">
                        همه مقالات
                    </a>
                </div>
            </div>

            <!-- ARTICLE -->
            <article v-else-if="article" class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-12">

                <!-- Breadcrumb -->
                <nav class="mb-8 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 max-w-[46rem] mx-auto">
                    <a href="/" class="hover:text-brand-accent transition-colors">خانه</a>
                    <i class="fa-solid fa-chevron-left text-[8px] text-slate-400"></i>
                    <a href="/articles" class="hover:text-brand-accent transition-colors">مجله</a>
                    <i class="fa-solid fa-chevron-left text-[8px] text-slate-400"></i>
                    <span class="text-ink font-semibold max-w-[12rem] truncate">{{ article.title }}</span>
                </nav>

                <!-- Header -->
                <header class="mb-10 max-w-[46rem] mx-auto">
                    <div class="mb-5 flex flex-wrap items-center gap-2">
                        <span class="hidden sm:flex items-center gap-2 pl-2 text-[10px] font-bold text-slate-400">
                            <span class="w-1.5 h-1.5 rotate-45 bg-brand-accent inline-block"></span>
                            مجله توربوپارت
                        </span>
                        <a v-for="cat in categories" :key="cat.id" :href="`/store?category=${encodeURIComponent(cat.slug)}`" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-brand-accent/25 text-brand-accent text-[10px] font-bold hover:bg-brand-accent/10 transition-colors">
                            <span class="w-1 h-1 rotate-45 bg-brand-accent inline-block"></span>
                            {{ cat.name }}
                        </a>
                    </div>

                    <h1 class="text-2xl sm:text-4xl lg:text-[3.25rem] font-black text-ink leading-[1.3] tracking-tight">{{ article.title }}</h1>
                    <p v-if="article.excerpt" class="mt-5 text-sm sm:text-base lg:text-lg text-slate-500 leading-relaxed">{{ article.excerpt }}</p>

                    <!-- Dateline -->
                    <div class="mt-7 pt-5 border-t border-gray-200 flex flex-wrap items-center gap-x-5 gap-y-3 text-xs text-slate-500">
                        <div class="flex items-center gap-2.5">
                            <div v-if="article.author" class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-accent to-amber-300 flex items-center justify-center text-[13px] font-bold text-ink shrink-0 ring-2 ring-brand-accent/30">
                                {{ article.author.name?.charAt(0) || 'م' }}
                            </div>
                            <div>
                                <div class="text-[11px] font-bold text-ink">{{ article.author?.name || 'تیم توربوپارت' }}</div>
                                <div class="text-[10px] text-slate-400">نویسنده</div>
                            </div>
                        </div>
                        <span class="hidden sm:inline-block w-1 h-1 rotate-45 bg-brand-accent"></span>
                        <span class="inline-flex items-center gap-1.5">
                            <i class="fa-regular fa-calendar text-brand-accent"></i> {{ formatDate(article.published_at) }}
                        </span>
                        <span class="hidden sm:inline-block w-1 h-1 rotate-45 bg-brand-accent"></span>
                        <span class="inline-flex items-center gap-1.5">
                            <i class="fa-regular fa-clock text-brand-accent"></i> {{ toPersianNumber(readingTime()) }} دقیقه مطالعه
                        </span>
                        <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-[11px] text-slate-600 hover:border-brand-accent/40 hover:text-brand-accent transition-colors" @click="onShare">
                            <i class="fa-solid fa-share-nodes"></i> اشتراک‌گذاری
                        </button>
                    </div>
                </header>

                <!-- Cover -->
                <figure v-if="articleImage()" class="mb-12 reveal relative">
                    <div class="overflow-hidden rounded-xl ring-1 ring-gray-200 bg-gradient-to-br from-brand-accent/25 via-amber-100 to-sand aspect-[16/9] sm:aspect-[21/9]">
                        <img :src="articleImage()" :alt="article.title" class="w-full h-full object-cover" @error="onImgError($event)" />
                    </div>
                    <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-brand-accent/70 to-transparent"></div>
                </figure>

                <!-- Reading column -->
                <div class="max-w-[46rem] mx-auto">

                    <!-- Body -->
                    <div v-html="article.content" class="article-body" />

                    <!-- End of article signoff -->
                    <div class="mt-12 mb-6 flex items-center gap-4">
                        <span class="h-px flex-1 bg-gray-200"></span>
                        <span class="text-[10px] font-bold text-slate-400 tracking-wide">پایان مقاله</span>
                        <span class="serif text-lg text-brand-accent leading-none px-1">TP</span>
                        <span class="h-px flex-1 bg-gray-200"></span>
                    </div>

                    <!-- Author box -->
                    <div v-if="article.author" class="reveal rounded-xl border border-gray-200 bg-white p-5 sm:p-6 flex flex-col sm:flex-row sm:items-start gap-4 relative overflow-hidden">
                        <div class="absolute right-0 top-0 bottom-0 w-1 bg-brand-accent hidden sm:block"></div>
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-brand-accent to-amber-300 flex items-center justify-center font-black text-ink shrink-0 ring-2 ring-brand-accent/25">
                            {{ article.author.name?.charAt(0) || 'م' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-black text-ink">{{ article.author.name }}</span>
                                <span class="px-2 py-0.5 rounded-md bg-brand-accent/10 text-brand-accent text-[10px] font-bold border border-brand-accent/20">نویسنده متخصص</span>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500 leading-relaxed">راهنمای تخصصی انتخاب قطعات خودرو در مجله توربوپارت؛ این مقاله با دقت فنی و منابع معتبر به قلم تیم توربوپارت تهیه شده است.</p>
                        </div>
                    </div>

                    <!-- Calm end CTA -->
                    <div class="mt-8 reveal rounded-xl border border-gray-200 bg-white p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-lg bg-brand-accent/10 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-road text-brand-accent"></i>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 tracking-wide mb-1">ادامه مسیر در توربوپارت</div>
                                <h2 class="text-base sm:text-lg font-black text-ink">راهنمای دیگری خواندید؟ حالا قطعه مناسب را پیدا کنید.</h2>
                                <p class="text-xs text-slate-500 mt-1">با انتخاب خودروی خود، قطعات سازگار را سریع و مطمئن بیابید.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5 shrink-0 sm:pr-4">
                            <a href="/store" class="px-5 py-2.5 rounded-lg bg-brand-accent text-ink text-xs font-bold hover:bg-brand-hover transition-colors whitespace-nowrap">
                                ورود به فروشگاه
                            </a>
                            <a href="/articles" class="px-4 py-2.5 rounded-lg border border-gray-200 text-xs font-bold text-slate-600 hover:border-brand-accent/40 hover:text-brand-accent transition-colors whitespace-nowrap">
                                مقالات بیشتر
                            </a>
                        </div>
                    </div>

                    <!-- Discussion -->
                    <section class="mt-14 sm:mt-16" aria-labelledby="discussion-title">
                        <div class="mb-7 flex items-start justify-between gap-6">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Discussion</p>
                                <h2 id="discussion-title" class="mt-2 text-xl font-black leading-tight text-ink sm:text-2xl">
                                    گفتگو درباره این مقاله
                                </h2>
                                <p v-if="discussion.section.total" class="mt-1.5 text-xs text-slate-500">
                                    {{ faNum(discussion.section.total) }} دیدگاه ثبت شده است
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-brand-accent px-5 py-3 text-xs font-black text-ink transition hover:bg-brand-hover min-h-[44px]"
                                @click="discussion.openComposer()"
                            >
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                افزودن دیدگاه
                            </button>
                        </div>

                        <CommentList :section="discussion.section" kind="article" />
                    </section>

                    <CommentComposer v-model="discussion.section.composerOpen" kind="article" :section="discussion.section" />
                </div>

                <!-- Related section (wider than reading column) -->
                <div class="max-w-4xl mx-auto mt-16">

                    <!-- Related products -->
                    <div v-if="relatedProducts.length" class="reveal">
                        <div class="mb-6">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="h-px w-6 bg-brand-accent"></span>
                                <span class="text-[10px] font-bold text-slate-400 tracking-wide">مرتبط با این مقاله</span>
                            </div>
                            <h2 class="text-lg sm:text-xl font-black text-ink flex items-center gap-2">
                                <i class="fa-solid fa-boxes-stacked text-brand-accent text-base"></i> قطعات مرتبط با این مقاله
                            </h2>
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                            <a v-for="(p, idx) in relatedProducts" :key="p.id" :href="`/products/${p.id}`" class="group rounded-lg border border-gray-200 bg-white hover:border-brand-accent/50 transition-colors p-4 flex flex-col">
                                <div class="flex items-start justify-between mb-3">
                                    <span class="serif text-brand-accent font-bold text-lg leading-none">{{ padNumber(idx + 1) }}</span>
                                    <i class="fa-solid fa-gear text-slate-300 group-hover:text-brand-accent transition-colors"></i>
                                </div>
                                <span class="block text-xs font-bold text-ink leading-relaxed line-clamp-2 group-hover:text-brand-accent transition-colors">{{ p.name }}</span>
                                <span class="mt-3 pt-3 border-t border-gray-200/70 inline-flex items-center gap-1.5 text-[10px] font-bold text-brand-accent">
                                    مشاهده قطعه <i class="fa-solid fa-arrow-left text-[9px] transition-transform duration-300 group-hover:-translate-x-1"></i>
                                </span>
                            </a>
                        </div>
                    </div>

                    <!-- Related vehicles & brands -->
                    <div v-if="relatedVehicles.length || relatedBrands.length" class="mt-10">
                        <div class="mb-5">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="h-px w-6 bg-brand-accent"></span>
                                <span class="text-[10px] font-bold text-slate-400 tracking-wide">این مقاله درباره</span>
                            </div>
                            <h2 class="text-lg font-black text-ink">خودروها و برندهای پوشش‌داده‌شده</h2>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white p-5">
                            <div v-if="relatedBrands.length" class="flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-[11px] font-bold text-ink">
                                    <i class="fa-solid fa-car text-[10px] text-brand-accent"></i> {{ relatedBrands.map((b) => b.name).join('، ') }}
                                </span>
                            </div>
                            <div v-if="relatedVehicles.length" class="mt-2.5 flex flex-wrap gap-2">
                                <span v-for="v in relatedVehicles" :key="v.id" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-accent/10 border border-brand-accent/20 text-[11px] font-bold text-brand-accent">
                                    <i class="fa-solid fa-engine text-[10px]"></i> {{ v.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </main>

        <SiteFooter />
    </div>
</template>

<style scoped>
.reveal {
    animation: rise 600ms ease-out both;
}
@keyframes rise {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
    .reveal, .animate-pulse {
        animation: none !important;
        transition-duration: 0.01ms !important;
    }
}
select { background-image: none; }
input::placeholder { opacity: 0.75; }
:focus-visible { outline: 2px solid #FFCD00; outline-offset: 2px; }

.article-body {
    color: var(--color-ink);
    font-size: 1.05rem;
    line-height: 2.15;
    word-break: break-word;
}
.article-body > *:first-child {
    margin-top: 0;
}
.article-body > *:last-child {
    margin-bottom: 0;
}
.article-body > p:first-child {
    font-size: 1.15rem;
    line-height: 2.1;
    color: var(--color-ink);
}
.article-body h2 {
    position: relative;
    font-size: 1.4rem;
    font-weight: 900;
    margin: 2.5rem 0 1.1rem;
    line-height: 1.65;
    padding-bottom: 0.65rem;
}
.article-body h2::after {
    content: '';
    position: absolute;
    right: 0;
    bottom: 0;
    width: 2.5rem;
    height: 2px;
    background: #FFCD00;
}
.article-body h3 {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 1.15rem;
    font-weight: 800;
    margin: 2rem 0 0.9rem;
    line-height: 1.7;
}
.article-body h3::before {
    content: '';
    width: 0.9rem;
    height: 2px;
    background: #FFCD00;
    display: inline-block;
    flex-shrink: 0;
}
.article-body p {
    margin: 0 0 1.35rem;
}
.article-body strong {
    font-weight: 700;
    color: var(--color-ink);
}
.article-body a {
    color: #e6b800;
    font-weight: 600;
    text-decoration: none;
    border-bottom: 1px solid rgba(230, 184, 0, 0.45);
    transition: color 200ms ease, border-color 200ms ease;
}
.article-body a:hover {
    color: #FFCD00;
    border-color: #FFCD00;
}
.article-body ul,
.article-body ol {
    margin: 0 0 1.35rem;
    padding-right: 1.6rem;
}
.article-body ul { list-style: disc; }
.article-body ol { list-style: decimal; }
.article-body li {
    margin-bottom: 0.5rem;
    line-height: 2;
}
.article-body li::marker {
    color: #FFCD00;
    font-weight: 700;
}
.article-body blockquote {
    margin: 2rem 0;
    padding: 1.1rem 1.35rem;
    border-right: 3px solid #FFCD00;
    background: var(--color-white);
    border-radius: 0.6rem;
    font-weight: 600;
    font-size: 1.02rem;
    color: var(--color-ink);
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.03);
}
.article-body img {
    max-width: 100%;
    height: auto;
    border-radius: 0.85rem;
    margin: 2rem auto;
    box-shadow: 0 12px 34px -12px rgba(0, 0, 0, 0.22);
    border: 1px solid var(--color-border);
}
.article-body table {
    width: 100%;
    max-width: 100%;
    border-collapse: collapse;
    margin: 2rem 0;
    font-size: 0.9rem;
    display: block;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.article-body th,
.article-body td {
    border: 1px solid var(--color-border);
    padding: 0.65rem 0.9rem;
    text-align: right;
    white-space: nowrap;
}
.article-body th {
    background: var(--color-white);
    color: var(--color-ink);
    font-weight: 800;
}
.article-body td {
    color: var(--color-ink);
}
.article-body code {
    background: var(--color-white);
    border: 1px solid var(--color-border);
    border-radius: 0.4rem;
    padding: 0.12rem 0.45rem;
    font-size: 0.85em;
    direction: ltr;
    unicode-bidi: embed;
    display: inline-block;
}
.article-body hr {
    border: none;
    border-top: 1px solid var(--color-border);
    margin: 2.25rem 0;
}
</style>