<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { addToCart } from './cart-state.js';

const categories = ref([]);
const filters = ref([]);
const products = ref([]);
const loading = ref(false);
const error = ref('');
const mobileFiltersOpen = ref(false);
const categoryDetail = ref(null);
const categoryNotFound = ref(false);

const pagination = reactive({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 12,
});

const state = reactive({
    category: '',
    values: {},
    priceMin: '',
    priceMax: '',
    inStock: false,
    search: '',
    sort: 'newest',
    page: 1,
    vehicleBrandId: '',
    vehicleModelId: '',
    vehicleGenerationId: '',
    vehicleTrimId: '',
    vehicleEngineId: '',
});

const categorySearchQuery = ref('');
const expandedSlugs = ref(new Set());

const vehicleBrands = ref([]);
const vehicleModels = ref([]);
const vehicleGenerations = ref([]);
const vehicleTrims = ref([]);
const vehicleEngines = ref([]);

async function loadVehicleBrands() {
    const { data } = await axios.get('/api/vehicles/brands');
    vehicleBrands.value = data.data || [];
}
async function loadVehicleModels(brandId) {
    if (!brandId) { vehicleModels.value = []; return; }
    const { data } = await axios.get(`/api/vehicles/brands/${brandId}/models`);
    vehicleModels.value = data.data || [];
}
async function loadVehicleGenerations(modelId) {
    if (!modelId) { vehicleGenerations.value = []; return; }
    const { data } = await axios.get(`/api/vehicles/models/${modelId}/generations`);
    vehicleGenerations.value = data.data || [];
}
async function loadVehicleTrims(generationId) {
    if (!generationId) { vehicleTrims.value = []; return; }
    const { data } = await axios.get(`/api/vehicles/generations/${generationId}/trims`);
    vehicleTrims.value = data.data || [];
}
async function loadVehicleEngines(trimId) {
    if (!trimId) { vehicleEngines.value = []; return; }
    const { data } = await axios.get(`/api/vehicles/trims/${trimId}/engines`);
    vehicleEngines.value = data.data || [];
}

async function onBrandChange() {
    state.vehicleModelId = ''; state.vehicleGenerationId = ''; state.vehicleTrimId = ''; state.vehicleEngineId = '';
    vehicleModels.value = []; vehicleGenerations.value = []; vehicleTrims.value = []; vehicleEngines.value = [];
    if (state.vehicleBrandId) await loadVehicleModels(state.vehicleBrandId);
    triggerFetch();
}
async function onModelChange() {
    state.vehicleGenerationId = ''; state.vehicleTrimId = ''; state.vehicleEngineId = '';
    vehicleGenerations.value = []; vehicleTrims.value = []; vehicleEngines.value = [];
    if (state.vehicleModelId) await loadVehicleGenerations(state.vehicleModelId);
    triggerFetch();
}
async function onGenerationChange() {
    state.vehicleTrimId = ''; state.vehicleEngineId = '';
    vehicleTrims.value = []; vehicleEngines.value = [];
    if (state.vehicleGenerationId) await loadVehicleTrims(state.vehicleGenerationId);
    triggerFetch();
}
async function onTrimChange() {
    state.vehicleEngineId = '';
    vehicleEngines.value = [];
    if (state.vehicleTrimId) await loadVehicleEngines(state.vehicleTrimId);
    triggerFetch();
}
function onEngineChange() { triggerFetch(); }

const RESERVED_KEYS = [
    'category', 'categories', 'page', 'per_page', 'sort', 'search',
    'price_min', 'price_max', 'in_stock',
    'vehicle_brand_id', 'vehicle_model_id', 'vehicle_generation_id',
    'vehicle_trim_id', 'vehicle_engine_id',
];

function categoryPathSlug() {
    const match = window.location.pathname.match(/^\/c\/([^/]+)\/?$/);
    if (!match) return null;
    try { return decodeURIComponent(match[1]); } catch { return match[1]; }
}

function stateToParams() {
    const params = new URLSearchParams();
    if (state.category && !categoryPathSlug()) params.set('category', state.category);
    for (const [slug, values] of Object.entries(state.values)) { if (values.length) params.set(slug, values.join(',')); }
    if (state.priceMin !== '') params.set('price_min', state.priceMin);
    if (state.priceMax !== '') params.set('price_max', state.priceMax);
    if (state.inStock) params.set('in_stock', '1');
    if (state.search) params.set('search', state.search);
    if (state.sort && state.sort !== 'newest') params.set('sort', state.sort);
    if (state.page > 1) params.set('page', String(state.page));
    if (state.vehicleBrandId) params.set('vehicle_brand_id', state.vehicleBrandId);
    if (state.vehicleModelId) params.set('vehicle_model_id', state.vehicleModelId);
    if (state.vehicleGenerationId) params.set('vehicle_generation_id', state.vehicleGenerationId);
    if (state.vehicleTrimId) params.set('vehicle_trim_id', state.vehicleTrimId);
    if (state.vehicleEngineId) params.set('vehicle_engine_id', state.vehicleEngineId);
    return params;
}

function pushUrl() {
    const params = stateToParams();
    const qs = params.toString();
    const pathSlug = categoryPathSlug();
    let path = window.location.pathname;
    if (pathSlug) {
        path = '/c/' + encodeURIComponent(state.category || pathSlug);
    }
    history.pushState({}, '', qs ? `${path}?${qs}` : path);
}

function restoreFromUrl() {
    const params = new URLSearchParams(window.location.search);
    state.category = categoryPathSlug() ?? (params.get('category') || '');
    categorySearchQuery.value = '';
    state.priceMin = params.get('price_min') || '';
    state.priceMax = params.get('price_max') || '';
    state.inStock = params.get('in_stock') === '1';
    state.search = params.get('search') || '';
    state.sort = params.get('sort') || 'newest';
    state.page = parseInt(params.get('page') || '1', 10);
    state.vehicleBrandId = params.get('vehicle_brand_id') || '';
    state.vehicleModelId = params.get('vehicle_model_id') || '';
    state.vehicleGenerationId = params.get('vehicle_generation_id') || '';
    state.vehicleTrimId = params.get('vehicle_trim_id') || '';
    state.vehicleEngineId = params.get('vehicle_engine_id') || '';
    state.values = {};
    for (const [key, val] of params.entries()) {
        if (!RESERVED_KEYS.includes(key)) state.values[key] = val.split(',').filter(Boolean);
    }
}

async function onPopState() {
    restoreFromUrl();
    await syncCategoryDetailForPath();
    if (state.category) expandAncestorsOf(state.category);
    await refreshAfterRestore();
}

function getCategoryPath(items, slug, path = []) {
    for (const item of items) {
        const currentPath = [...path, { name: item.name, slug: item.slug }];
        if (item.slug === slug) return currentPath;
        const found = getCategoryPath(item.children || [], slug, currentPath);
        if (found) return found;
    }
    return null;
}

const selectedCategoryBreadcrumb = computed(() => {
    if (!state.category) return [];
    if (categoryPathSlug() && categoryDetail.value?.ancestors?.length) {
        const crumbs = categoryDetail.value.ancestors.map((a) => ({ name: a.name, slug: a.slug }));
        if (!crumbs.some((c) => c.slug === state.category)) {
            crumbs.push({ name: categoryDetail.value.name, slug: categoryDetail.value.slug });
        }
        return crumbs;
    }
    return getCategoryPath(categories.value, state.category) || [];
});

const flatCategorySearchResults = computed(() => {
    const query = categorySearchQuery.value.trim().toLowerCase();
    if (!query) return [];
    const results = [];
    function walk(items, ancestors) {
        for (const item of items) {
            const currentPath = [...ancestors, item.name];
            if (item.name.toLowerCase().includes(query)) results.push({ slug: item.slug, name: item.name, path: currentPath.join(' / ') });
            walk(item.children || [], currentPath);
        }
    }
    walk(categories.value, []);
    return results;
});

const filteredCategories = computed(() => {
    const query = categorySearchQuery.value.trim().toLowerCase();
    if (!query) return categories.value;
    function filterTree(items) {
        return items.reduce((acc, item) => {
            const childResults = filterTree(item.children || []);
            if (item.name.toLowerCase().includes(query) || childResults.length > 0) acc.push({ ...item, children: childResults });
            return acc;
        }, []);
    }
    return filterTree(categories.value);
});

function selectedValues(slug) { return state.values[slug] || []; }
function isSelected(slug, value) { return selectedValues(slug).includes(value); }

async function selectCategorySlug(slug) {
    if (state.category === slug) { await clearCategorySelection(); return; }
    state.category = slug; state.values = {}; state.page = 1; filters.value = [];
    categorySearchQuery.value = '';
    expandAncestorsOf(slug);
    if (categoryPathSlug()) { await loadCategoryDetail(slug); } else { categoryDetail.value = null; }
    await loadFilters(); pushUrl(); await loadProducts();
}

async function clearCategorySelection() {
    if (categoryPathSlug()) {
        state.category = ''; state.values = {}; state.page = 1; filters.value = [];
        categorySearchQuery.value = ''; categoryDetail.value = null; categoryNotFound.value = false;
        history.pushState({}, '', '/store');
        await loadProducts();
        return;
    }
    state.category = ''; state.values = {}; state.page = 1; filters.value = [];
    categorySearchQuery.value = '';
    pushUrl(); await loadProducts();
}

async function loadCategories() {
    const { data } = await axios.get('/api/categories/tree');
    categories.value = data.data || [];
}
async function loadFilters() {
    if (!state.category) { filters.value = []; return; }
    const params = stateToParams();
    params.delete('category');
    params.delete('per_page');
    params.delete('page');
    params.delete('sort');
    const config = params.toString() ? { params } : undefined;
    const { data } = await axios.get(`/api/categories/${state.category}/filters`, config);
    filters.value = data.data || [];
}

async function loadCategoryDetail(slug) {
    try {
        const { data } = await axios.get(`/api/categories/${encodeURIComponent(slug)}`);
        categoryDetail.value = data.data || null;
        categoryNotFound.value = false;
        return true;
    } catch (e) {
        if (e.response?.status === 404) {
            categoryDetail.value = null;
            categoryNotFound.value = true;
            return false;
        }
        throw e;
    }
}

async function syncCategoryDetailForPath() {
    const slug = categoryPathSlug();
    if (slug) {
        await loadCategoryDetail(slug);
    } else {
        categoryDetail.value = null;
        categoryNotFound.value = false;
    }
}

function expandAncestorsOf(slug) {
    const path = getCategoryPath(categories.value, slug);
    if (!path) return;
    const newSet = new Set(expandedSlugs.value);
    for (const node of path) { if (node.slug !== slug) newSet.add(node.slug); }
    expandedSlugs.value = newSet;
}
function toggleCategoryExpand(slug) {
    const newSet = new Set(expandedSlugs.value);
    if (newSet.has(slug)) newSet.delete(slug); else newSet.add(slug);
    expandedSlugs.value = newSet;
}

async function toggleValue(filter, value) {
    const current = [...selectedValues(filter.slug)];
    const idx = current.indexOf(value);
    if (idx >= 0) current.splice(idx, 1); else current.push(value);
    if (current.length) state.values[filter.slug] = current; else delete state.values[filter.slug];
    state.page = 1; pushUrl(); await loadProducts();
}
async function setScalarValue(filter, value) {
    if (value === '') delete state.values[filter.slug]; else state.values[filter.slug] = [value];
    state.page = 1; pushUrl(); await loadProducts();
}

const activeFilters = computed(() => {
    const selected = [];
    if (state.category) {
        const path = getCategoryPath(categories.value, state.category);
        const label = path ? path.map((n) => n.name).join(' › ') : state.category;
        selected.push({ type: 'category', slug: 'category', value: state.category, label: `دسته: ${label}` });
    }
    for (const filter of filters.value) {
        for (const value of selectedValues(filter.slug)) {
            const option = filter.values.find((item) => item.value === value);
            selected.push({ type: 'attribute', slug: filter.slug, value, label: `${filter.name}: ${option?.label ?? value}` });
        }
    }
    if (state.priceMin !== '' || state.priceMax !== '') {
        const min = state.priceMin !== '' ? formatPrice(state.priceMin) : '۰';
        const max = state.priceMax !== '' ? formatPrice(state.priceMax) : '∞';
        selected.push({ type: 'price', slug: 'price', value: 'price', label: `قیمت: ${min} — ${max}` });
    }
    if (state.inStock) selected.push({ type: 'stock', slug: 'in_stock', value: '1', label: 'فقط موجود' });
    if (state.search) selected.push({ type: 'search', slug: 'search', value: state.search, label: `جستجو: ${state.search}` });
    if (state.vehicleEngineId) { const e = vehicleEngines.value.find((v) => String(v.id) === String(state.vehicleEngineId)); if (e) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'engine', label: `خودرو: ${e.name}` }); }
    else if (state.vehicleTrimId) { const t = vehicleTrims.value.find((v) => String(v.id) === String(state.vehicleTrimId)); if (t) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'trim', label: `خودرو: ${t.name}` }); }
    else if (state.vehicleGenerationId) { const g = vehicleGenerations.value.find((v) => String(v.id) === String(state.vehicleGenerationId)); if (g) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'generation', label: `خودرو: ${g.name}` }); }
    else if (state.vehicleModelId) { const m = vehicleModels.value.find((v) => String(v.id) === String(state.vehicleModelId)); if (m) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'model', label: `خودرو: ${m.name}` }); }
    else if (state.vehicleBrandId) { const b = vehicleBrands.value.find((v) => String(v.id) === String(state.vehicleBrandId)); if (b) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'brand', label: `خودرو: ${b.name}` }); }
    return selected;
});

async function removeChip(chip) {
    if (chip.type === 'category') { await clearCategorySelection(); return; }
    else if (chip.type === 'attribute') { const current = selectedValues(chip.slug).filter((v) => v !== chip.value); if (current.length) state.values[chip.slug] = current; else delete state.values[chip.slug]; }
    else if (chip.type === 'price') { state.priceMin = ''; state.priceMax = ''; }
    else if (chip.type === 'stock') { state.inStock = false; }
    else if (chip.type === 'search') { state.search = ''; }
    else if (chip.type === 'vehicle') { state.vehicleBrandId = ''; state.vehicleModelId = ''; state.vehicleGenerationId = ''; state.vehicleTrimId = ''; state.vehicleEngineId = ''; vehicleModels.value = []; vehicleGenerations.value = []; vehicleTrims.value = []; vehicleEngines.value = []; }
    state.page = 1; pushUrl(); await loadProducts();
}

let searchTimer = null;
function onSearchInput(value) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { state.search = value; state.page = 1; pushUrl(); loadProducts(); }, 400);
}

let priceTimer = null;
async function onSortChange() { state.page = 1; pushUrl(); await loadProducts(); }
function onPriceInput(which, value) {
    clearTimeout(priceTimer);
    priceTimer = setTimeout(() => { if (which === 'min') state.priceMin = value; else state.priceMax = value; state.page = 1; pushUrl(); loadProducts(); }, 500);
}
async function onStockToggle() { state.page = 1; pushUrl(); await loadProducts(); }
async function triggerFetch() { state.page = 1; pushUrl(); await loadProducts(); }

async function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) return;
    state.page = page; pushUrl(); await loadProducts();
    await nextTick(); window.scrollTo({ top: 0, behavior: 'smooth' });
}
const pageNumbers = computed(() => {
    const pages = []; const total = pagination.lastPage; const current = pagination.currentPage; const delta = 2;
    for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) pages.push(i);
    return pages;
});

async function clearAllFilters() {
    if (categoryPathSlug()) { await clearCategorySelection(); return; }
    state.category = ''; state.values = {}; state.priceMin = ''; state.priceMax = '';
    state.inStock = false; state.search = ''; state.sort = 'newest'; state.page = 1;
    state.vehicleBrandId = ''; state.vehicleModelId = ''; state.vehicleGenerationId = '';
    state.vehicleTrimId = ''; state.vehicleEngineId = '';
    categorySearchQuery.value = ''; filters.value = [];
    vehicleModels.value = []; vehicleGenerations.value = []; vehicleTrims.value = []; vehicleEngines.value = [];
    pushUrl(); await loadProducts();
}

async function loadProducts() {
    loading.value = true; error.value = '';
    try {
        const params = {};
        if (state.category) params.category = state.category;
        for (const [slug, values] of Object.entries(state.values)) { if (values.length) params[slug] = values.join(','); }
        if (state.priceMin !== '') params.price_min = state.priceMin;
        if (state.priceMax !== '') params.price_max = state.priceMax;
        if (state.inStock) params.in_stock = '1';
        if (state.search) params.search = state.search;
        if (state.sort) params.sort = state.sort;
        params.page = state.page; params.per_page = 12;
        if (state.vehicleEngineId) params.vehicle_engine_id = state.vehicleEngineId;
        else if (state.vehicleTrimId) params.vehicle_trim_id = state.vehicleTrimId;
        else if (state.vehicleGenerationId) params.vehicle_generation_id = state.vehicleGenerationId;
        else if (state.vehicleModelId) params.vehicle_model_id = state.vehicleModelId;
        else if (state.vehicleBrandId) params.vehicle_brand_id = state.vehicleBrandId;
        const { data } = await axios.get('/api/products', { params });
        products.value = data.data || [];
        const meta = data.meta || {};
        pagination.currentPage = meta.current_page || 1;
        pagination.lastPage = meta.last_page || 1;
        pagination.total = meta.total || products.value.length;
        pagination.perPage = meta.per_page || 12;
    } catch (e) {
        products.value = []; error.value = e.response?.data?.message || 'دریافت محصولات ناموفق بود.';
        pagination.currentPage = 1; pagination.lastPage = 1; pagination.total = 0;
    } finally { loading.value = false; }
}

async function refreshAfterRestore() {
    if (categoryNotFound.value) { filters.value = []; return; }
    if (state.category) { expandAncestorsOf(state.category); await loadFilters(); } else { filters.value = []; }
    if (state.vehicleBrandId) await loadVehicleModels(state.vehicleBrandId); else { vehicleModels.value = []; }
    if (state.vehicleModelId) await loadVehicleGenerations(state.vehicleModelId); else { vehicleGenerations.value = []; }
    if (state.vehicleGenerationId) await loadVehicleTrims(state.vehicleGenerationId); else { vehicleTrims.value = []; }
    if (state.vehicleTrimId) await loadVehicleEngines(state.vehicleTrimId); else { vehicleEngines.value = []; }
    await loadProducts();
}

function productImage(product) {
    const image = product.images?.find((item) => item.is_primary) || product.images?.[0];
    return image?.path ? `/storage/${image.path}` : '';
}
function formatPrice(price) { return Number(price || 0).toLocaleString('fa-IR'); }
const hasActiveFilters = computed(() => activeFilters.value.length > 0 || state.category !== '');

function addToCartFromStorefront(product) {
    if (product.has_variants) {
        window.location.href = `/products/${product.id}`;
        return;
    }
    if (!product.in_stock) return;

    const image =
        product.images?.find((img) => img.is_primary)?.path ||
        product.images?.[0]?.path ||
        null;

    addToCart({
        key: `product_${product.id}`,
        product_id: product.id,
        variant_id: null,
        name: product.name,
        price: product.price,
        quantity: 1,
        image: image ? `/storage/${image}` : null,
        attributes: null,
        sku: product.sku || null,
        stock: product.stock ?? 999,
    });

    window.dispatchEvent(
        new CustomEvent('toast', {
            detail: {
                message: `${product.name} به سبد خرید اضافه شد.`,
                title: 'افزودن به سبد',
                type: 'success',
            },
        })
    );
}

function onImgError(e) {
    e.target.onerror = null;
    e.target.src = '/images/placeholder.svg';
}

onMounted(async () => {
    restoreFromUrl();
    window.addEventListener('popstate', onPopState);
    try { await loadCategories(); await loadVehicleBrands(); await syncCategoryDetailForPath(); await refreshAfterRestore(); }
    catch { error.value = 'دریافت اطلاعات فروشگاه ناموفق بود.'; }
});
onUnmounted(() => {
    window.removeEventListener('popstate', onPopState);
    clearTimeout(searchTimer); clearTimeout(priceTimer);
});
</script>

<template>
    <div dir="rtl" class="min-h-screen bg-cream text-ink font-sans antialiased">
        <SiteHeader />

        <!-- PAGE HERO -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-ink">فروشگاه قطعات خودرو</h1>
                        <p class="text-xs text-slate-500 mt-1">{{ pagination.total }} محصول یافت شد</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="lg:hidden px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs text-slate-600 hover:border-brand-accent/40 transition-colors" @click="mobileFiltersOpen = true">
                            <i class="fa-solid fa-sliders ml-1.5 text-[10px]"></i> فیلترها
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Active filter chips -->
            <div v-if="activeFilters.length" class="mb-4 flex flex-wrap items-center gap-1.5">
                <button v-for="chip in activeFilters" :key="`${chip.type}-${chip.slug}-${chip.value}`" type="button" class="inline-flex items-center gap-1 rounded-lg bg-gray-100 border border-gray-200 px-2.5 py-1 text-[11px] text-slate-600 hover:border-gray-400 transition-colors" @click="removeChip(chip)">
                    {{ chip.label }}
                    <i class="fa-solid fa-xmark text-[9px] text-slate-400"></i>
                </button>
                <button type="button" class="text-[11px] text-brand-accent hover:text-brand-hover font-medium px-2 py-1 transition-colors" @click="clearAllFilters">
                    حذف همه
                </button>
            </div>

            <!-- Mobile overlay -->
            <Teleport to="body">
                <div v-if="mobileFiltersOpen" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" @click="mobileFiltersOpen = false" />
            </Teleport>

            <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <!-- SIDEBAR -->
                <aside :class="[
                    'bg-white border border-gray-200 rounded-xl p-4',
                    'lg:block',
                    mobileFiltersOpen ? 'fixed inset-y-0 right-0 z-50 w-80 overflow-y-auto shadow-2xl lg:relative lg:inset-auto lg:w-auto' : 'hidden',
                ]" @click.stop>
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
                        <h2 class="font-bold text-sm text-ink">فیلترها</h2>
                        <div class="flex items-center gap-2">
                            <button v-if="hasActiveFilters" type="button" class="text-[11px] text-brand-accent hover:text-brand-hover transition-colors" @click="clearAllFilters">پاک کردن</button>
                            <button type="button" class="text-slate-400 hover:text-ink lg:hidden" @click="mobileFiltersOpen = false"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>

                    <!-- Category tree -->
                    <section class="pb-4 border-b border-gray-200/50">
                        <h3 class="mb-2 text-[11px] font-bold text-slate-500 uppercase tracking-wider">دسته‌بندی</h3>
                        <div v-if="selectedCategoryBreadcrumb.length" class="mb-2 flex flex-wrap items-center gap-1 text-[10px] text-slate-500">
                            <template v-for="(crumb, idx) in selectedCategoryBreadcrumb" :key="crumb.slug">
                                <button type="button" class="hover:text-brand-accent transition-colors" @click="selectCategorySlug(crumb.slug)">{{ crumb.name }}</button>
                                <span v-if="idx < selectedCategoryBreadcrumb.length - 1" class="text-slate-600">/</span>
                            </template>
                        </div>
                        <div class="relative mb-2">
                            <input v-model="categorySearchQuery" type="text" placeholder="جستجوی دسته..." class="w-full rounded-lg bg-white border border-gray-200 px-3 py-1.5 text-[11px] text-ink placeholder-slate-400 focus:border-brand-accent/40 focus:outline-none" />
                            <button v-if="categorySearchQuery" type="button" class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 hover:text-ink" @click="categorySearchQuery = ''"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div v-if="categorySearchQuery.trim() && flatCategorySearchResults.length" class="mb-2 max-h-40 overflow-y-auto rounded-lg border border-gray-200/50">
                            <button v-for="result in flatCategorySearchResults" :key="result.slug" type="button" class="block w-full px-3 py-1.5 text-right text-[11px] hover:bg-gray-100 transition-colors border-b border-gray-200/30 last:border-0" :class="state.category === result.slug ? 'bg-brand-accent/10 text-brand-accent' : 'text-ink'" @click="selectCategorySlug(result.slug)">
                                <span class="block font-medium">{{ result.name }}</span>
                                <span class="block text-[9px] text-slate-500 mt-0.5">{{ result.path }}</span>
                            </button>
                        </div>
                        <p v-else-if="categorySearchQuery.trim() && !flatCategorySearchResults.length" class="text-[10px] text-slate-500 mb-2">پیدا نشد</p>

                        <div v-if="!categorySearchQuery.trim()" class="max-h-52 overflow-y-auto space-y-0.5">
                            <template v-for="category in filteredCategories" :key="category.slug">
                                <div class="flex items-center gap-1">
                                    <button v-if="category.children?.length" type="button" class="w-4 h-4 shrink-0 flex items-center justify-center rounded text-[9px] text-slate-500 hover:bg-gray-100 transition-colors" @click.stop="toggleCategoryExpand(category.slug)">
                                        {{ expandedSlugs.has(category.slug) ? '−' : '+' }}
                                    </button>
                                    <span v-else class="w-4 shrink-0" />
                                    <button type="button" class="flex-1 truncate rounded px-2 py-1 text-right text-[11px] hover:bg-gray-100 transition-colors" :class="state.category === category.slug ? 'bg-brand-accent/10 font-bold text-brand-accent' : 'text-ink'" @click="selectCategorySlug(category.slug)">
                                        {{ category.name }}
                                    </button>
                                </div>
                                <div v-if="expandedSlugs.has(category.slug) && category.children?.length" class="mr-3 border-r border-gray-200/50 space-y-0.5">
                                    <template v-for="child1 in category.children" :key="child1.slug">
                                        <div class="flex items-center gap-1">
                                            <button v-if="child1.children?.length" type="button" class="w-4 h-4 shrink-0 flex items-center justify-center rounded text-[9px] text-slate-500 hover:bg-gray-100" @click.stop="toggleCategoryExpand(child1.slug)">
                                                {{ expandedSlugs.has(child1.slug) ? '−' : '+' }}
                                            </button>
                                            <span v-else class="w-4 shrink-0" />
                                            <button type="button" class="flex-1 truncate rounded px-2 py-1 text-right text-[11px] hover:bg-gray-100 transition-colors" :class="state.category === child1.slug ? 'bg-brand-accent/10 font-bold text-brand-accent' : 'text-ink'" @click="selectCategorySlug(child1.slug)">
                                                {{ child1.name }}
                                            </button>
                                        </div>
                                        <div v-if="expandedSlugs.has(child1.slug) && child1.children?.length" class="mr-3 border-r border-gray-200/50 space-y-0.5">
                                            <template v-for="child2 in child1.children" :key="child2.slug">
                                                <div class="flex items-center gap-1">
                                                    <span class="w-4 shrink-0" />
                                                    <button type="button" class="flex-1 truncate rounded px-2 py-1 text-right text-[11px] hover:bg-gray-100 transition-colors" :class="state.category === child2.slug ? 'bg-brand-accent/10 font-bold text-brand-accent' : 'text-ink'" @click="selectCategorySlug(child2.slug)">
                                                        {{ child2.name }}
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </section>

                    <!-- Attribute filters -->
                    <section v-for="filter in filters" :key="filter.id" class="py-3 border-b border-gray-200/50">
                        <h3 class="mb-2 text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ filter.name }}</h3>
                        <div v-if="filter.values.length && filter.type === 'color'" class="flex flex-wrap gap-2">
                            <button v-for="value in filter.values" :key="value.id" type="button" :title="value.label" class="w-9 h-9 rounded-full border-2 border-gray-200 hover:scale-110 transition-transform min-h-[36px] min-w-[36px]" :class="{ 'ring-2 ring-brand-accent': isSelected(filter.slug, value.value) }" :style="{ backgroundColor: value.hex_color || '#333' }" @click="toggleValue(filter, value.value)" />
                        </div>
                        <div v-else-if="filter.values.length" class="flex flex-wrap gap-2">
                            <button v-for="value in filter.values" :key="value.id" type="button" class="rounded-lg border px-3 py-1.5 text-sm transition-colors min-h-[40px] flex items-center" :class="isSelected(filter.slug, value.value) ? 'bg-brand-accent text-ink border-brand-accent font-bold' : 'border-gray-200 text-ink hover:border-slate-600'" @click="toggleValue(filter, value.value)">
                                {{ value.label }}
                            </button>
                        </div>
                        <div v-else-if="filter.type === 'boolean'" class="flex gap-2">
                            <button type="button" class="rounded-lg border px-3 py-1.5 text-sm min-h-[40px]" :class="isSelected(filter.slug, 'true') ? 'bg-brand-accent text-ink border-brand-accent font-bold' : 'border-gray-200 text-ink hover:border-slate-600'" @click="toggleValue(filter, 'true')">بله</button>
                            <button type="button" class="rounded-lg border px-3 py-1.5 text-sm min-h-[40px]" :class="isSelected(filter.slug, 'false') ? 'bg-brand-accent text-ink border-brand-accent font-bold' : 'border-gray-200 text-ink hover:border-slate-600'" @click="toggleValue(filter, 'false')">خیر</button>
                        </div>
                        <input v-else-if="filter.type === 'number' || filter.type === 'text'" :type="filter.type === 'number' ? 'number' : 'text'" :value="selectedValues(filter.slug)[0] || ''" class="w-full rounded-lg bg-sand border border-gray-200 px-3 py-2.5 text-sm text-ink focus:border-brand-accent/40 focus:outline-none min-h-[44px]" @change="setScalarValue(filter, $event.target.value)" />
                    </section>

                    <!-- Price -->
                    <section class="py-3 border-b border-gray-200/50">
                        <h3 class="mb-2 text-[11px] font-bold text-slate-400 uppercase tracking-wider">قیمت</h3>
                        <div class="flex gap-2">
                            <input type="number" placeholder="حداقل" :value="state.priceMin" class="w-1/2 rounded-lg bg-sand border border-gray-200 px-3 py-2.5 text-sm text-ink focus:border-brand-accent/40 focus:outline-none min-h-[44px]" @input="onPriceInput('min', $event.target.value)" />
                            <input type="number" placeholder="حداکثر" :value="state.priceMax" class="w-1/2 rounded-lg bg-sand border border-gray-200 px-3 py-2.5 text-sm text-ink focus:border-brand-accent/40 focus:outline-none min-h-[44px]" @input="onPriceInput('max', $event.target.value)" />
                        </div>
                    </section>

                    <!-- Stock -->
                    <section class="py-3 border-b border-gray-200/50">
                        <label class="flex items-center justify-between text-[11px] text-ink">
                            فقط موجود
                            <input :checked="state.inStock" type="checkbox" class="check" @change="state.inStock = $event.target.checked; onStockToggle()" />
                        </label>
                    </section>

                    <!-- Vehicle -->
                    <section class="py-3">
                        <h3 class="mb-2 text-[11px] font-bold text-slate-400 uppercase tracking-wider">خودرو</h3>
                        <div class="flex flex-col gap-2">
                            <select :value="state.vehicleBrandId" class="rounded-lg bg-white border border-gray-200 px-3 py-2.5 text-sm text-ink focus:border-brand-accent/40 focus:outline-none min-h-[44px]" @change="state.vehicleBrandId = $event.target.value; onBrandChange()">
                                <option value="">انتخاب برند</option>
                                <option v-for="b in vehicleBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                            <select :value="state.vehicleModelId" :disabled="!state.vehicleBrandId" class="rounded-lg bg-white border border-gray-200 px-3 py-2.5 text-sm text-ink focus:border-brand-accent/40 focus:outline-none disabled:opacity-40 min-h-[44px]" @change="state.vehicleModelId = $event.target.value; onModelChange()">
                                <option value="">انتخاب مدل</option>
                                <option v-for="m in vehicleModels" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                            <select :value="state.vehicleGenerationId" :disabled="!state.vehicleModelId" class="rounded-lg bg-white border border-gray-200 px-3 py-2.5 text-sm text-ink focus:border-brand-accent/40 focus:outline-none disabled:opacity-40 min-h-[44px]" @change="state.vehicleGenerationId = $event.target.value; onGenerationChange()">
                                <option value="">انتخاب نسل</option>
                                <option v-for="g in vehicleGenerations" :key="g.id" :value="g.id">{{ g.name }} ({{ g.year_start }})</option>
                            </select>
                            <select :value="state.vehicleTrimId" :disabled="!state.vehicleGenerationId" class="rounded-lg bg-white border border-gray-200 px-3 py-2.5 text-sm text-ink focus:border-brand-accent/40 focus:outline-none disabled:opacity-40 min-h-[44px]" @change="state.vehicleTrimId = $event.target.value; onTrimChange()">
                                <option value="">انتخاب تیپ</option>
                                <option v-for="t in vehicleTrims" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                            <select :value="state.vehicleEngineId" :disabled="!state.vehicleTrimId" class="rounded-lg bg-white border border-gray-200 px-3 py-2.5 text-sm text-ink focus:border-brand-accent/40 focus:outline-none disabled:opacity-40 min-h-[44px]" @change="state.vehicleEngineId = $event.target.value; onEngineChange()">
                                <option value="">انتخاب موتور</option>
                                <option v-for="e in vehicleEngines" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                        </div>
                    </section>
                </aside>

                <!-- PRODUCT CONTENT -->
                <section>
                    <!-- Search -->
                    <div class="mb-4">
                        <input type="text" :value="state.search" placeholder="جستجوی محصول..." class="w-full rounded-xl bg-white border border-gray-200 px-4 py-2.5 text-sm text-ink placeholder-slate-400 focus:border-brand-accent/40 focus:outline-none" @input="onSearchInput($event.target.value)" />
                    </div>

                    <!-- Sort -->
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-xs text-slate-500">{{ pagination.total }} محصول</p>
                        <select v-model="state.sort" class="rounded-lg bg-white border border-gray-200 px-3 py-2.5 text-sm text-ink focus:border-brand-accent/40 focus:outline-none min-h-[44px]" @change="onSortChange">
                            <option value="newest">جدیدترین</option>
                            <option value="oldest">قدیمی‌ترین</option>
                            <option value="price_asc">ارزان‌ترین</option>
                            <option value="price_desc">گران‌ترین</option>
                            <option value="name_asc">نام: صعودی</option>
                            <option value="name_desc">نام: نزولی</option>
                        </select>
                    </div>

                    <!-- Error -->
                    <div v-if="error" class="mb-4 rounded-xl bg-red-50 border border-red-200 p-3 text-xs text-red-600">
                        {{ error }}
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                        <div v-for="i in 6" :key="i" class="rounded-xl bg-white border border-gray-200 animate-pulse">
                            <div class="aspect-square bg-sand rounded-t-xl"></div>
                            <div class="p-3 space-y-2"><div class="h-2 bg-sand rounded w-3/4"></div><div class="h-3 bg-sand rounded w-1/2"></div></div>
                        </div>
                    </div>

                    <!-- Category not found -->
                    <div v-else-if="categoryNotFound" class="text-center py-16 rounded-xl border border-gray-200 bg-white">
                        <i class="fa-solid fa-folder-open text-3xl text-slate-400 mb-3 block"></i>
                        <p class="text-sm text-slate-500">دسته‌بندی مورد نظر یافت نشد.</p>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="!products.length" class="text-center py-16 rounded-xl border border-gray-200 bg-white">
                        <i class="fa-solid fa-box-open text-3xl text-slate-400 mb-3 block"></i>
                        <p class="text-sm text-slate-500">محصولی با این شرایط پیدا نشد.</p>
                        <button v-if="hasActiveFilters" type="button" class="mt-3 px-4 py-1.5 rounded-lg bg-brand-accent text-ink text-xs font-bold hover:bg-brand-hover transition-colors" @click="clearAllFilters">
                            حذف فیلترها
                        </button>
                    </div>

                    <!-- Product grid -->
                    <div v-else class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                        <article v-for="product in products" :key="product.id" class="rounded-xl bg-white border border-gray-200 hover:border-brand-accent/30 transition-all group overflow-hidden">
                            <a :href="`/products/${product.id}`" class="block">
                                <div class="aspect-square bg-sand relative">
                                    <img v-if="productImage(product)" :src="productImage(product)" :alt="product.name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" @error="onImgError($event)" />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <i class="fa-solid fa-box text-3xl text-slate-400"></i>
                                    </div>
                                    <span v-if="product.in_stock === false" class="absolute top-2 left-2 px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-600/80 text-white">ناموجود</span>
                                </div>
                                <div class="p-3">
                                    <h2 class="font-bold text-xs text-ink group-hover:text-brand-accent transition-colors line-clamp-2 leading-relaxed">{{ product.name }}</h2>
                                    <div class="mt-2 flex items-end justify-between">
                                        <div>
                                            <p class="text-sm font-black text-brand-accent font-mono">{{ formatPrice(product.price) }} <span class="text-[10px] font-sans text-slate-400">تومان</span></p>
                                            <p v-if="product.compare_at_price" class="text-[10px] text-slate-500 line-through font-mono">{{ formatPrice(product.compare_at_price) }}</p>
                                        </div>
                                    </div>
                                    <dl v-if="product.attributes && Object.keys(product.attributes).length" class="mt-2 space-y-0.5">
                                        <div v-for="(values, slug) in product.attributes" :key="slug" class="flex gap-1 text-[9px] text-slate-500">
                                            <dt class="shrink-0">{{ slug }}:</dt>
                                            <dd class="truncate">{{ values.map((v) => v.label).join('، ') }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </a>
                            <div class="px-3 pb-3">
                                <button
                                    type="button"
                                    :disabled="!product.in_stock"
                                    class="w-full rounded-lg bg-brand-accent text-dark-900 py-2 text-[11px] font-bold transition-colors"
                                    :class="!product.in_stock ? 'opacity-40 cursor-not-allowed' : 'hover:bg-brand-hover'"
                                    @click.prevent="addToCartFromStorefront(product)"
                                >
                                    <i class="fa-solid fa-cart-plus ml-1 text-[9px]"></i>
                                    {{ product.in_stock ? 'افزودن به سبد' : 'ناموجود' }}
                                </button>
                            </div>
                        </article>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pagination.lastPage > 1 && !loading" class="mt-6 flex items-center justify-center gap-1.5">
                        <button type="button" :disabled="pagination.currentPage <= 1" class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-slate-600 hover:border-gray-400 disabled:opacity-30 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center" @click="goToPage(pagination.currentPage - 1)">
                            <i class="fa-solid fa-chevron-right text-sm"></i>
                        </button>
                        <button v-for="p in pageNumbers" :key="p" type="button" class="min-w-[2.5rem] rounded-lg px-3 py-2 text-sm font-medium transition-colors min-h-[44px] flex items-center justify-center" :class="p === pagination.currentPage ? 'bg-brand-accent text-ink font-bold' : 'border border-gray-200 text-slate-600 hover:border-gray-400'" @click="goToPage(p)">
                            {{ p }}
                        </button>
                        <button type="button" :disabled="pagination.currentPage >= pagination.lastPage" class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-slate-600 hover:border-gray-400 disabled:opacity-30 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center" @click="goToPage(pagination.currentPage + 1)">
                            <i class="fa-solid fa-chevron-left text-sm"></i>
                        </button>
                    </div>
                </section>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>

<style>
.no-scrollbar { scrollbar-width: none; -ms-overflow-style: none; }
.no-scrollbar::-webkit-scrollbar { display: none; }
select { background-image: none; }
input::placeholder { opacity: 0.7; }
:focus-visible { outline: 2px solid #FFCD00; outline-offset: 2px; }

.product-image { max-width: 100%; }
</style>
