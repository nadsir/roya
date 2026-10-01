<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import ShellDialog from './components/ShellDialog.vue';
import ProductCard from './components/ProductCard.vue';
import DiscoveryFilters from './components/discovery/DiscoveryFilters.vue';
import { createDiscoveryState, discoverySorts } from './discovery-state.js';
import { categoryHref, categoryImage } from './catalog-state.js';
import '../css/homepage.css';
import '../css/discovery.css';

const model = createDiscoveryState();
const { state, result, pagination, crumbs, category, subcategories, title, products, chips } = model;
const mobileOpen = ref(false);
const failedImages = ref([]);
const pages = computed(() => Array.from({ length: Math.min(5, pagination.lastPage) }, (_, index) => Math.min(Math.max(1, pagination.currentPage - 2), Math.max(1, pagination.lastPage - 4)) + index));
let searchTimer;
function cancelSearch() { clearTimeout(searchTimer); }
function onSearch(value) { state.search = value; cancelSearch(); searchTimer = setTimeout(() => model.change(), 350); }
function clear() { cancelSearch(); return model.clearFilters(); }
function select(slug) { cancelSearch(); return model.selectCategory(slug); }
function sort() { cancelSearch(); return model.change({ facets: false }); }
function closeFilters() { mobileOpen.value = false; }
async function page(value) { cancelSearch(); await model.goToPage(value); document.getElementById('dc-results')?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }); }
onMounted(() => { model.init(); window.addEventListener('popstate', cancelSearch); });
onBeforeUnmount(() => { model.dispose(); cancelSearch(); window.removeEventListener('popstate', cancelSearch); });
</script>
<template>
    <SiteHeader />
    <main class="dc-page" dir="rtl">
        <header class="dc-category-header dc-container">
            <nav aria-label="مسیر مجموعه" class="dc-breadcrumb"><a href="/">خانه</a><span aria-hidden="true">/</span><a href="/store" @click.prevent="select('')">گالری</a><template v-for="crumb in crumbs" :key="crumb.slug"><span aria-hidden="true">/</span><a :href="categoryHref(crumb.slug)" :aria-current="crumb.slug === state.category ? 'page' : undefined" @click.prevent="select(crumb.slug)">{{ crumb.name }}</a></template></nav>
            <div class="dc-header-composition"><div><p class="dc-eyebrow" lang="en" dir="ltr">THE GALLERY EDIT</p><h1>{{ result.notFound ? 'مجموعه پیدا نشد' : title }}</h1><p v-if="category?.description" class="dc-category-description">{{ category.description }}</p><p class="dc-count" :aria-busy="result.loading">{{ result.loading ? 'در حال یافتن انتخاب‌های شما…' : result.error || result.notFound ? 'مجموعه گالری' : `${pagination.total.toLocaleString('fa-IR')} محصول برای کشف` }}</p></div><div v-if="categoryImage(category) && !failedImages.includes(categoryImage(category))" class="dc-category-visual"><img :src="categoryImage(category)" :alt="category?.name" width="640" height="480" decoding="async" @error="failedImages.push($event.target.getAttribute('src'))" /></div><span v-else class="dc-header-mark" aria-hidden="true" lang="en">G.</span></div>
            <p v-if="result.detailError" class="dc-notice" role="alert">{{ result.detailError }} <button type="button" class="dc-text-button" @click="model.loadDetail">تلاش مجدد</button></p>
            <nav v-if="subcategories.length" class="dc-subcategories" aria-label="زیرمجموعه‌ها"><a v-for="child in subcategories" :key="child.slug" :href="categoryHref(child.slug)" @click.prevent="select(child.slug)">{{ child.name }} <span aria-hidden="true">↗</span></a></nav>
        </header>
        <div class="dc-container dc-layout">
            <aside class="dc-desktop-filters" aria-label="فیلترهای محصولات"><DiscoveryFilters :model="model" /></aside>
            <section id="dc-results" class="dc-results" aria-label="محصولات مجموعه" :aria-busy="result.loading">
                <h2 class="dc-sr-only">محصولات {{ title }}</h2>
                <div class="dc-toolbar"><label class="dc-search"><span class="dc-sr-only">جستجوی محصول در مجموعه</span><input type="search" :value="state.search" placeholder="جستجو در این مجموعه…" @input="onSearch($event.target.value)" @keydown.enter="cancelSearch(); model.change()" /></label><div class="dc-sort-row"><button type="button" class="dc-mobile-filter-trigger" aria-haspopup="dialog" :aria-expanded="mobileOpen" @click="mobileOpen = true"><i class="fa-solid fa-sliders" aria-hidden="true" /> فیلترها <span v-if="chips.length">({{ chips.length.toLocaleString('fa-IR') }})</span></button><label class="dc-sort"><span>چیدمان</span><select v-model="state.sort" @change="sort"><option v-for="item in discoverySorts" :key="item.value" :value="item.value">{{ item.label }}</option></select></label></div></div>
                <div v-if="chips.length" class="dc-chips"><button v-for="chip in chips" :key="chip.key" type="button" :aria-label="`حذف ${chip.label}`" @click="cancelSearch(); model.removeChip(chip)">{{ chip.label }} <span aria-hidden="true">×</span></button><button type="button" class="dc-text-button" @click="clear">پاک‌کردن همه</button></div>
                <div v-if="result.loading" class="dc-product-grid" role="status" aria-label="در حال دریافت محصولات"><div v-for="n in 12" :key="n" class="hp-product-skeleton"><div class="sf-skeleton" /><span class="sf-skeleton" /></div></div>
                <div v-else-if="result.notFound" class="dc-state"><p class="dc-eyebrow" lang="en">A DIFFERENT DIRECTION</p><h2>این مجموعه در دسترس نیست.</h2><a href="/store" class="sf-button" @click.prevent="select('')">دیدن همه محصولات</a></div>
                <div v-else-if="result.error" class="dc-state" role="alert"><h2>کمی بعد دوباره ببینیم.</h2><p>{{ result.error }}</p><button type="button" class="sf-button" @click="model.loadProducts">تلاش مجدد</button></div>
                <div v-else-if="!products.length" class="dc-state"><p class="dc-eyebrow" lang="en">ROOM FOR ANOTHER CHOICE</p><h2>محصولی با این انتخاب‌ها پیدا نشد.</h2><p>فیلترها را تغییر دهید یا پاک کنید.</p><button v-if="chips.length" type="button" class="sf-button" @click="clear">پاک‌کردن فیلترها</button><a v-else href="/store" class="dc-text-button" @click.prevent="select('')">مشاهده همه محصولات ←</a></div>
                <div v-else class="dc-product-grid"><ProductCard v-for="product in products" :key="product.id" :product="product" /></div>
                <nav v-if="pagination.lastPage > 1 && !result.loading && !result.error && !result.notFound" class="dc-pagination" aria-label="صفحه‌بندی محصولات"><button type="button" aria-label="صفحه قبلی" :disabled="pagination.currentPage <= 1" @click="page(pagination.currentPage - 1)">→</button><button v-if="pages[0] > 1" type="button" @click="page(1)">۱</button><span v-if="pages[0] > 2" aria-hidden="true">…</span><button v-for="number in pages" :key="number" type="button" :aria-current="number === pagination.currentPage ? 'page' : undefined" :aria-label="`صفحه ${number.toLocaleString('fa-IR')}`" @click="page(number)">{{ number.toLocaleString('fa-IR') }}</button><span v-if="pages.at(-1) < pagination.lastPage - 1" aria-hidden="true">…</span><button v-if="pages.at(-1) < pagination.lastPage" type="button" @click="page(pagination.lastPage)">{{ pagination.lastPage.toLocaleString('fa-IR') }}</button><button type="button" aria-label="صفحه بعدی" :disabled="pagination.currentPage >= pagination.lastPage" @click="page(pagination.currentPage + 1)">←</button></nav>
            </section>
        </div>
    </main>
    <ShellDialog :open="mobileOpen" label="فیلترهای مجموعه" variant="filters" @close="closeFilters"><div class="dc-drawer-heading"><h2>انتخاب‌های شما</h2><button type="button" class="sf-icon-button" aria-label="بستن فیلترها" autofocus @click="closeFilters">×</button></div><div class="dc-drawer-body"><DiscoveryFilters v-if="mobileOpen" :model="model" prefix="mobile" /></div><div class="dc-drawer-actions"><button type="button" class="dc-text-button" @click="clear">پاک‌کردن همه</button><button type="button" class="sf-button" @click="closeFilters">مشاهده نتایج</button></div></ShellDialog>
    <SiteFooter />
</template>
