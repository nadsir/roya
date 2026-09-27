<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';

const articles = ref([]);
const loading = ref(true);
const error = ref('');
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0, perPage: 12 });

function toPersianNumber(n) {
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
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
    if (!a.featured_image) return '';
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
    return a.categories?.[0] || null;
}

const featuredArticle = computed(() => {
    if (!articles.value.length) return null;
    return articles.value.find((a) => a.is_featured) || articles.value[0];
});

const gridArticles = computed(() => {
    if (!articles.value.length) return [];
    const featured = featuredArticle.value;
    return articles.value.filter((a) => a.id !== featured.id);
});

const leadArticle = computed(() => gridArticles.value[0] || null);
const stackedArticles = computed(() => gridArticles.value.slice(1, 3));
const standardArticles = computed(() => gridArticles.value.slice(3));

async function loadArticles() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/articles', {
            params: { page: pagination.value.currentPage },
        });
        articles.value = data.data || [];
        pagination.value = {
            currentPage: data.current_page || 1,
            lastPage: data.last_page || 1,
            total: data.total || 0,
            perPage: data.per_page || 12,
        };
    } catch (e) {
        articles.value = [];
        error.value = e.response?.data?.message || 'دریافت مقالات ناموفق بود.';
        pagination.value = { currentPage: 1, lastPage: 1, total: 0, perPage: 12 };
    } finally {
        loading.value = false;
    }
}

async function goToPage(page) {
    if (page < 1 || page > pagination.value.lastPage || page === pagination.value.currentPage) return;
    pagination.value.currentPage = page;
    history.pushState({}, '', page > 1 ? `/articles?page=${page}` : '/articles');
    await loadArticles();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function onPopState() {
    const page = parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);
    if (!isNaN(page) && page >= 1) pagination.value.currentPage = page;
    loadArticles();
}

const pageNumbers = computed(() => {
    const pages = [];
    const total = pagination.value.lastPage;
    const current = pagination.value.currentPage;
    const delta = 2;
    for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) pages.push(i);
    return pages;
});

function setMeta() {
    document.title = 'مجله توربوپارت | مقالات تخصصی قطعات خودرو';
    let desc = document.querySelector('meta[name="description"]');
    if (!desc) { desc = document.createElement('meta'); desc.setAttribute('name', 'description'); document.head.appendChild(desc); }
    desc.setAttribute('content', 'مقالات تخصصی و راهنمای انتخاب قطعات خودرو؛ از شناخت قطعات موتوری و سیستم ترمز تا نکات نگهداری خودروهای داخلی، چینی و وارداتی.');
}

onMounted(() => {
    const page = parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);
    if (!isNaN(page) && page >= 1) pagination.value.currentPage = page;
    window.addEventListener('popstate', onPopState);
    setMeta();
    loadArticles();
});

onUnmounted(() => {
    window.removeEventListener('popstate', onPopState);
});
</script>

<template>
    <div dir="rtl" class="min-h-screen bg-cream text-ink font-sans antialiased overflow-x-hidden">
        <SiteHeader />

        <!-- MASTHEAD -->
        <section class="relative bg-white border-b border-gray-200 overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand-accent/70 to-transparent"></div>
            <div class="absolute -top-24 -left-24 w-80 h-80 rounded-full bg-brand-accent/8 blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 -right-24 w-72 h-72 rounded-full bg-cyan-400/8 blur-3xl pointer-events-none"></div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-8">
                    <div class="space-y-5 max-w-3xl">
                        <!-- Micro breadcrumb -->
                        <nav class="flex items-center gap-2 text-[11px] text-slate-500">
                            <a href="/" class="hover:text-brand-accent transition-colors">خانه</a>
                            <i class="fa-solid fa-chevron-left text-[8px] text-slate-400"></i>
                            <span class="text-ink font-semibold">مجله توربوپارت</span>
                        </nav>

                        <!-- Kicker -->
                        <div class="flex items-center gap-3">
                            <span class="w-1.5 h-1.5 rotate-45 bg-brand-accent shrink-0"></span>
                            <span class="text-[11px] font-bold text-slate-400 tracking-wide">راهنمای تخصصی انتخاب قطعات خودرو</span>
                            <span class="hidden sm:block h-px flex-1 max-w-[5rem] bg-gray-200"></span>
                        </div>

                        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-ink leading-[1.25] tracking-tight">
                            مقالات تخصصی،<br class="hidden sm:block">
                            راهنمای انتخاب <span class="text-brand-accent">قطعه درست</span>
                        </h1>

                        <p class="text-sm sm:text-base text-slate-500 leading-relaxed max-w-2xl">
                            از شناخت فنی قطعات موتوری، ترمز و جلوبندی تا نکات نگهداری خودروهای داخلی، چینی و وارداتی؛
                            همه‌چیز را با زبان ساده اما دقیق بیاموزید.
                        </p>
                    </div>

                    <!-- Editorial stats -->
                    <div v-if="!loading && pagination.total > 0" class="shrink-0">
                        <div class="flex items-center gap-5 px-5 py-4 border border-gray-200 bg-white/60 rounded-xl">
                            <span>
                                <span class="serif text-3xl font-bold text-brand-accent leading-none">{{ toPersianNumber(pagination.total) }}</span>
                                <span class="text-[11px] font-bold text-slate-500 leading-tight"> مقاله منتشر شده</span>
                            </span>
                            <span class="w-px h-9 bg-gray-200"></span>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-gauge-high text-brand-accent text-sm"></i>
                                <span class="text-[11px] font-bold text-slate-500">به‌روزرسانی مداوم</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 sm:pb-20">

            <!-- LOADING -->
            <div v-if="loading" class="space-y-12 pt-8 sm:pt-10">
                <div class="rounded-2xl bg-gradient-to-t from-ink/90 via-ink/60 to-ink/40 animate-pulse overflow-hidden">
                    <div class="min-h-[380px] sm:min-h-[460px]"></div>
                </div>
                <div class="grid lg:grid-cols-12 gap-8">
                    <div class="lg:col-span-7 rounded-xl bg-white border border-gray-200 animate-pulse overflow-hidden">
                        <div class="aspect-[16/9] bg-sand"></div>
                        <div class="p-6 space-y-3">
                            <div class="h-4 bg-sand rounded w-24"></div>
                            <div class="h-6 bg-sand rounded w-3/4"></div>
                            <div class="h-3 bg-sand rounded w-full"></div>
                            <div class="h-3 bg-sand rounded w-2/3"></div>
                        </div>
                    </div>
                    <div class="lg:col-span-5 space-y-8">
                        <div v-for="i in 2" :key="i" class="grid grid-cols-[4.5rem_1fr] gap-4 sm:gap-5 items-start">
                            <div class="aspect-square rounded-lg bg-white border border-gray-200 animate-pulse"></div>
                            <div class="space-y-2 pt-1">
                                <div class="h-3 bg-white border border-gray-200 animate-pulse rounded w-1/3"></div>
                                <div class="h-4 bg-white border border-gray-200 animate-pulse rounded w-4/5"></div>
                                <div class="h-3 bg-white border border-gray-200 animate-pulse rounded w-2/3"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
                    <div v-for="i in 3" :key="i" class="rounded-xl bg-white border border-gray-200 animate-pulse overflow-hidden">
                        <div class="aspect-[3/2] bg-sand"></div>
                        <div class="p-4 space-y-2">
                            <div class="h-3 bg-sand rounded w-1/3"></div>
                            <div class="h-4 bg-sand rounded w-4/5"></div>
                            <div class="h-2.5 bg-sand rounded w-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ERROR -->
            <div v-else-if="error" class="text-center py-24 rounded-2xl bg-white border border-gray-200 mt-8">
                <i class="fa-solid fa-circle-exclamation text-4xl text-slate-400 mb-4"></i>
                <p class="text-sm text-slate-600 mb-5">{{ error }}</p>
                <button type="button" class="px-5 py-2 rounded-lg bg-brand-accent text-ink text-xs font-bold hover:bg-brand-hover transition-colors" @click="loadArticles">
                    تلاش مجدد
                </button>
            </div>

            <!-- EMPTY -->
            <div v-else-if="!articles.length" class="text-center py-24 rounded-2xl bg-white border border-gray-200 mt-8">
                <i class="fa-solid fa-book-open text-4xl text-slate-400 mb-4"></i>
                <p class="text-sm text-slate-600">هنوز مقاله‌ای منتشر نشده است.</p>
                <a href="/store" class="inline-block mt-5 px-5 py-2 rounded-lg bg-brand-accent text-ink text-xs font-bold hover:bg-brand-hover transition-colors">
                    بازگشت به فروشگاه
                </a>
            </div>

            <!-- CONTENT -->
            <template v-else>

                <!-- FEATURED COVER -->
                <a
                    v-if="featuredArticle"
                    :href="`/articles/${featuredArticle.slug}`"
                    class="group relative block overflow-hidden rounded-2xl -mt-2 sm:-mt-4 min-h-[440px] sm:min-h-[520px] focus-visible:ring-2 ring-brand-accent"
                >
                    <!-- Cover image -->
                    <div class="absolute inset-0 bg-[#141414] overflow-hidden">
                        <div v-if="featuredArticle" class="w-full h-full">
                            <img v-if="articleImage(featuredArticle)" :src="articleImage(featuredArticle)" :alt="featuredArticle.title" class="w-full h-full object-cover transition-transform duration-[900ms] ease-out group-hover:scale-[1.035]" @error="onImgError($event)" />
                            <div v-else class="w-full h-full bg-[radial-gradient(circle_at_15%_20%,rgba(255,205,0,0.18),transparent_55%),radial-gradient(circle_at_85%_80%,rgba(255,205,0,0.10),transparent_45%)]">
                                <span class="serif absolute bottom-6 left-8 text-[7rem] sm:text-[10rem] leading-none text-white/[0.06] select-none pointer-events-none">TP</span>
                            </div>
                        </div>
                    </div>

                    <!-- Legibility overlays -->
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-black/10"></div>
                    <div class="absolute inset-0 bg-gradient-to-l from-transparent via-transparent to-black/25"></div>
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand-accent/80 to-transparent"></div>

                    <!-- Content -->
                    <div class="relative z-10 flex flex-col justify-end min-h-[440px] sm:min-h-[520px] p-6 sm:p-10">
                        <div class="mb-4 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-brand-accent text-ink text-[10px] font-bold shadow">
                                <i class="fa-solid fa-star text-[9px]"></i> مقاله ویژه
                            </span>
                            <span v-if="primaryCategory(featuredArticle)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/10 backdrop-blur-sm text-white text-[10px] font-bold border border-white/20">
                                <span class="w-1 h-1 rotate-45 bg-brand-accent"></span>
                                {{ primaryCategory(featuredArticle).name }}
                            </span>
                        </div>

                        <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-white leading-[1.3] tracking-tight max-w-4xl">
                            {{ featuredArticle.title }}
                        </h2>

                        <p v-if="featuredArticle.excerpt" class="mt-4 text-sm sm:text-base text-white/75 leading-relaxed line-clamp-3 max-w-2xl">
                            {{ featuredArticle.excerpt }}
                        </p>

                        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-3 text-[11px] text-white/70">
                            <div v-if="featuredArticle.author" class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-brand-accent flex items-center justify-center text-[12px] font-black text-ink shrink-0">
                                    {{ featuredArticle.author.name?.charAt(0) || 'م' }}
                                </div>
                                <span class="font-bold text-white">{{ featuredArticle.author.name }}</span>
                            </div>
                            <span class="hidden sm:inline-block w-px h-4 bg-white/20"></span>
                            <span class="inline-flex items-center gap-1.5">
                                <i class="fa-regular fa-calendar text-brand-accent"></i> {{ formatDate(featuredArticle.published_at) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <i class="fa-regular fa-clock text-brand-accent"></i> {{ toPersianNumber(readingTime(featuredArticle)) }} دقیقه مطالعه
                            </span>
                        </div>

                        <span class="mt-6 inline-flex items-center gap-2 text-brand-accent font-bold text-sm w-fit">
                            <span class="border-b border-brand-accent/60 pb-0.5 group-hover:border-brand-accent transition-colors">ادامه مطلب</span>
                            <i class="fa-solid fa-arrow-left text-xs transition-transform duration-300 group-hover:-translate-x-1"></i>
                        </span>
                    </div>
                </a>

                <!-- ZONE A: lead story + stacked stories -->
                <div v-if="leadArticle" class="reveal grid lg:grid-cols-12 gap-x-8 gap-y-10 mt-12 sm:mt-14">

                    <!--Lead editorial story-->
                    <a :href="`/articles/${leadArticle.slug}`" class="group lg:col-span-7 block">
                        <div class="rounded-lg overflow-hidden border border-gray-200 ring-1 ring-gray-200/60 bg-white">
                            <div class="aspect-[16/9] bg-gradient-to-br from-brand-accent/15 via-amber-100 to-sand overflow-hidden">
                                <img v-if="articleImage(leadArticle)" :src="articleImage(leadArticle)" :alt="leadArticle.title" class="w-full h-full object-cover transition-transform duration-[700ms] ease-out group-hover:scale-[1.028]" @error="onImgError($event)" loading="lazy" />
                            </div>
                            <div class="p-5 sm:p-7">
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="serif text-3xl text-brand-accent font-bold leading-none">01</span>
                                    <span class="h-px flex-1 bg-gray-200 max-w-[3rem]"></span>
                                    <span v-if="primaryCategory(leadArticle)" class="text-[11px] font-bold text-slate-500 flex items-center gap-1.5">
                                        <span class="w-1 h-1 rotate-45 bg-brand-accent inline-block"></span>
                                        {{ primaryCategory(leadArticle).name }}
                                        <span class="text-slate-300">/</span>
                                        {{ formatDate(leadArticle.published_at) }}
                                    </span>
                                </div>
                                <h3 class="text-xl sm:text-2xl font-black text-ink leading-[1.45] transition-colors duration-300 group-hover:text-brand-hover">{{ leadArticle.title }}</h3>
                                <p v-if="leadArticle.excerpt" class="mt-3 text-sm text-slate-500 leading-relaxed line-clamp-2">{{ leadArticle.excerpt }}</p>
                                <div class="mt-5 pt-4 border-t border-gray-200/70 flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-[11px] text-slate-500">
                                        <span v-if="leadArticle.author">{{ leadArticle.author.name }}</span>
                                        <span v-else>تیم توربوپارت</span>
                                        <span class="w-px h-3 bg-gray-200"></span>
                                        <span class="inline-flex items-center gap-1">{{ toPersianNumber(readingTime(leadArticle)) }} دقیقه</span>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 text-brand-accent font-bold text-xs">
                                        ادامه مطلب <i class="fa-solid fa-arrow-left text-[10px] transition-transform duration-300 group-hover:-translate-x-1"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>

                    <!-- Stacked secondary stories -->
                    <div class="lg:col-span-5 flex flex-col lg:pt-2">
                        <div v-for="(article, idx) in stackedArticles" :key="article.id" class="flex-1 py-6 first:pt-0 last:pb-0 border-b border-gray-200/70 last:border-0">
                            <a :href="`/articles/${article.slug}`" class="group grid grid-cols-[4.5rem_1fr] sm:grid-cols-[5.5rem_1fr] gap-4 sm:gap-5 items-start">
                                <div class="aspect-square rounded-lg border border-gray-200 ring-1 ring-gray-200/50 bg-gradient-to-br from-brand-accent/15 via-amber-100 to-sand overflow-hidden min-w-0">
                                    <img v-if="articleImage(article)" :src="articleImage(article)" :alt="article.title" class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.05]" @error="onImgError($event)" loading="lazy" />
                                </div>
                                <div class="min-w-0 pt-0.5">
                                    <div class="flex items-center gap-2 mb-2 text-[10px]">
                                        <span class="serif text-sm text-brand-accent font-bold leading-none">{{ pad(idx + 2) }}</span>
                                        <span class="w-px h-3 bg-gray-200"></span>
                                        <span v-if="primaryCategory(article)" class="font-bold text-slate-500 truncate">{{ primaryCategory(article).name }}</span>
                                    </div>
                                    <h3 class="font-black text-[15px] text-ink leading-[1.6] line-clamp-2 transition-colors duration-300 group-hover:text-brand-hover">{{ article.title }}</h3>
                                    <div class="mt-2.5 flex items-center gap-2 text-[10px] text-slate-400">
                                        <span>{{ formatDate(article.published_at) }}</span>
                                        <span class="w-px h-2.5 bg-gray-200"></span>
                                        <span>{{ toPersianNumber(readingTime(article)) }} دقیقه</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- SECTION DIVIDER -->
                <div v-if="standardArticles.length" class="my-12 sm:my-16 flex items-center gap-4">
                    <span class="h-px flex-1 bg-gray-200"></span>
                    <span class="serif text-lg text-brand-accent leading-none px-1">TP</span>
                    <span class="text-[10px] font-bold text-slate-400 tracking-wide">مقالات بیشتر</span>
                    <span class="h-px flex-1 bg-gray-200"></span>
                </div>

                <!-- ZONE B: standard grid -->
                <div v-if="standardArticles.length" class="reveal-stagger grid sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
                    <a
                        v-for="(article, idx) in standardArticles"
                        :key="article.id"
                        :href="`/articles/${article.slug}`"
                        class="group block"
                    >
                        <div class="mb-4 flex items-center gap-2 text-[10px]">
                            <span class="serif text-brand-accent font-bold leading-none">{{ pad(idx + 4) }}</span>
                            <span class="w-px h-3 bg-gray-200"></span>
                            <span v-if="primaryCategory(article)" class="font-bold text-slate-500 truncate">{{ primaryCategory(article).name }}</span>
                            <span class="mr-auto text-slate-400">{{ formatDate(article.published_at) }}</span>
                        </div>

                        <div class="rounded-lg overflow-hidden border border-gray-200 ring-1 ring-gray-200/50 bg-white mb-4">
                            <div class="aspect-[3/2] bg-gradient-to-br from-brand-accent/15 via-amber-100 to-sand overflow-hidden">
                                <img v-if="articleImage(article)" :src="articleImage(article)" :alt="article.title" class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.03]" @error="onImgError($event)" loading="lazy" />
                            </div>
                        </div>

                        <h3 class="font-black text-base text-ink leading-[1.6] line-clamp-2 transition-colors duration-300 group-hover:text-brand-hover">{{ article.title }}</h3>
                        <p v-if="article.excerpt" class="mt-2 text-xs text-slate-500 leading-relaxed line-clamp-2">{{ article.excerpt }}</p>

                        <div class="mt-4 flex items-center justify-between border-t border-gray-200/70 pt-3">
                            <span class="flex items-center gap-2 text-[10px] text-slate-400">
                                <i class="fa-regular fa-clock"></i> {{ toPersianNumber(readingTime(article)) }} دقیقه مطالعه
                            </span>
                            <span v-if="article.author" class="flex items-center gap-1.5 text-[10px] text-slate-400 min-w-0">
                                <span class="w-5 h-5 rounded-full bg-brand-accent/15 text-brand-accent flex items-center justify-center text-[9px] font-black shrink-0">
                                    {{ article.author.name?.charAt(0) || 'م' }}
                                </span>
                                <span class="truncate">{{ article.author.name }}</span>
                            </span>
                        </div>
                    </a>
                </div>

                <!-- PAGINATION -->
                <nav v-if="pagination.lastPage > 1" class="mt-14 sm:mt-16 border-t border-gray-200 pt-8">
                    <div class="flex flex-col items-center gap-5">
                        <p class="text-[10px] font-bold text-slate-400 tracking-wide">
                            صفحه <span class="text-brand-accent">{{ toPersianNumber(pagination.currentPage) }}</span> از {{ toPersianNumber(pagination.lastPage) }}
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="button" :disabled="pagination.currentPage <= 1" class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-slate-600 hover:border-brand-accent/50 hover:text-brand-accent disabled:opacity-30 disabled:hover:border-gray-200 disabled:hover:text-slate-600 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center" @click="goToPage(pagination.currentPage - 1)" aria-label="صفحه قبل">
                                <i class="fa-solid fa-chevron-right text-sm"></i>
                            </button>
                            <button v-for="p in pageNumbers" :key="p" type="button" class="min-w-[2.5rem] rounded-lg px-3 py-2 text-sm font-bold transition-colors min-h-[44px] flex items-center justify-center" :class="p === pagination.currentPage ? 'bg-brand-accent text-ink' : 'border border-gray-200 text-slate-600 hover:border-brand-accent/50 hover:text-brand-accent'" @click="goToPage(p)">
                                {{ toPersianNumber(p) }}
                            </button>
                            <button type="button" :disabled="pagination.currentPage >= pagination.lastPage" class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-slate-600 hover:border-brand-accent/50 hover:text-brand-accent disabled:opacity-30 disabled:hover:border-gray-200 disabled:hover:text-slate-600 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center" @click="goToPage(pagination.currentPage + 1)" aria-label="صفحه بعد">
                                <i class="fa-solid fa-chevron-left text-sm"></i>
                            </button>
                        </div>
                    </div>
                </nav>
            </template>
        </main>

        <SiteFooter />
    </div>
</template>

<style scoped>
.reveal {
    animation: rise 600ms ease-out both;
}
.reveal-stagger > * {
    animation: rise 600ms ease-out both;
}
.reveal-stagger > *:nth-child(2) { animation-delay: 60ms; }
.reveal-stagger > *:nth-child(3) { animation-delay: 120ms; }
.reveal-stagger > *:nth-child(4) { animation-delay: 180ms; }
@keyframes rise {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
    .reveal, .reveal-stagger > *, .animate-pulse {
        animation: none !important;
        transition-duration: 0.01ms !important;
    }
}
select { background-image: none; }
input::placeholder { opacity: 0.75; }
:focus-visible { outline: 2px solid #FFCD00; outline-offset: 2px; }
</style>