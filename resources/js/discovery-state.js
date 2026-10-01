import { computed, reactive } from 'vue';
import axios from 'axios';
import { catalog, loadCatalog, isStorefrontProduct, categoryHref } from './catalog-state.js';

export const discoverySorts = [
    { value: 'newest', label: 'جدیدترین' }, { value: 'oldest', label: 'قدیمی‌ترین' },
    { value: 'price_asc', label: 'قیمت: کم به زیاد' }, { value: 'price_desc', label: 'قیمت: زیاد به کم' },
    { value: 'name_asc', label: 'نام: صعودی' }, { value: 'name_desc', label: 'نام: نزولی' },
];
const reserved = new Set(['category', 'categories', 'page', 'per_page', 'sort', 'search', 'price_min', 'price_max', 'in_stock', 'vehicle_brand_id', 'vehicle_model_id', 'vehicle_generation_id', 'vehicle_trim_id', 'vehicle_engine_id']);
export function categoryPath(items, slug, parents = []) {
    for (const item of items) {
        const path = [...parents, item];
        if (item.slug === slug) return path;
        const found = categoryPath(item.children || [], slug, path);
        if (found.length) return found;
    }
    return [];
}

// Replaces the listing state formerly held inside Storefront.vue. No new global store.
export function createDiscoveryState(browser = window) {
    const state = reactive({ category: '', values: {}, priceMin: '', priceMax: '', inStock: false, search: '', sort: 'newest', page: 1 });
    const result = reactive({ products: [], loading: false, error: '', filters: [], filtersLoading: false, filtersError: '', detail: null, detailLoading: false, detailError: '', notFound: false });
    const pagination = reactive({ currentPage: 1, lastPage: 1, total: 0 });
    const counters = { products: 0, filters: 0, detail: 0 };
    const controllers = {};
    let active = true;
    function start(kind) { controllers[kind]?.abort(); controllers[kind] = new AbortController(); return { version: ++counters[kind], signal: controllers[kind].signal }; }
    function current(kind, version) { return active && counters[kind] === version; }
    function pathSlug() { const match = browser.location.pathname.match(/^\/c\/([^/]+)\/?$/); if (!match) return ''; try { return decodeURIComponent(match[1]); } catch { return match[1]; } }
    const crumbs = computed(() => categoryPath(catalog.categories, state.category));
    const category = computed(() => result.detail || crumbs.value.at(-1) || null);
    const subcategories = computed(() => crumbs.value.at(-1)?.children || []);
    const title = computed(() => category.value?.name || (state.category ? 'مجموعه گالری' : 'همه انتخاب‌های گالری'));
    const products = computed(() => result.products.filter(isStorefrontProduct));
    function restore() {
        const query = new URLSearchParams(browser.location.search);
        state.category = pathSlug() || query.get('category') || '';
        state.search = query.get('search') || ''; state.priceMin = query.get('price_min') || ''; state.priceMax = query.get('price_max') || '';
        state.inStock = query.get('in_stock') === '1'; state.sort = discoverySorts.some((item) => item.value === query.get('sort')) ? query.get('sort') : 'newest';
        state.page = Math.max(1, parseInt(query.get('page'), 10) || 1); state.values = {};
        if (state.category) for (const [slug, value] of query) if (!reserved.has(slug) && value) state.values[slug] = value.split(',').filter(Boolean);
    }
    function params({ filter = false, url = false } = {}) {
        const query = new URLSearchParams();
        if (state.category && !filter && (!url || !pathSlug())) query.set('category', state.category);
        for (const [slug, values] of Object.entries(state.values)) if (values.length) query.set(slug, values.join(','));
        if (state.priceMin !== '') query.set('price_min', state.priceMin);
        if (state.priceMax !== '') query.set('price_max', state.priceMax);
        if (state.inStock) query.set('in_stock', '1'); if (state.search) query.set('search', state.search);
        if (!filter) {
            if (!url || state.sort !== 'newest') query.set('sort', state.sort);
            if (!url || state.page > 1) query.set('page', String(state.page));
            if (!url) query.set('per_page', '12');
        }
        return query;
    }
    function pushUrl() {
        const path = pathSlug() ? (state.category ? categoryHref(state.category) : '/store') : '/store';
        const query = params({ url: true }).toString(); const next = path + (query ? `?${query}` : '');
        if (next !== browser.location.pathname + browser.location.search) browser.history.pushState({}, '', next);
    }
    async function loadDetail() {
        const request = start('detail'); result.detail = null; result.notFound = false; result.detailError = ''; result.detailLoading = Boolean(state.category);
        if (!state.category) return;
        try {
            const { data } = await axios.get(`/api/categories/${encodeURIComponent(state.category)}`, { signal: request.signal });
            if (current('detail', request.version)) {
                const detail = data.data || null;
                if (detail && !isStorefrontProduct({ name: detail.name, categories: detail.ancestors || [] })) result.notFound = true;
                else result.detail = detail;
            }
        } catch (error) {
            if (!current('detail', request.version) || axios.isCancel(error)) return;
            if (error.response?.status === 404) result.notFound = true;
            else result.detailError = 'اطلاعات مجموعه دریافت نشد.';
        } finally { if (current('detail', request.version)) result.detailLoading = false; }
    }
    async function loadFilters() {
        const request = start('filters'); result.filtersError = ''; result.filtersLoading = Boolean(state.category);
        if (!state.category) { result.filters = []; return; }
        try {
            const { data } = await axios.get(`/api/categories/${encodeURIComponent(state.category)}/filters`, { params: params({ filter: true }), signal: request.signal });
            if (current('filters', request.version)) result.filters = data.data || [];
        } catch (error) {
            if (!current('filters', request.version) || axios.isCancel(error)) return;
            result.filtersError = 'فیلترهای این مجموعه دریافت نشدند. دوباره تلاش کنید.';
        } finally { if (current('filters', request.version)) result.filtersLoading = false; }
    }
    async function loadProducts() {
        const request = start('products'); result.loading = true; result.error = '';
        try {
            const { data } = await axios.get('/api/products', { params: params(), signal: request.signal });
            if (!current('products', request.version)) return;
            result.products = data.data || [];
            const meta = data.meta || {};
            Object.assign(pagination, { currentPage: meta.current_page ?? state.page, lastPage: meta.last_page ?? 1, total: meta.total ?? result.products.length });
        } catch (error) {
            if (!current('products', request.version) || axios.isCancel(error)) return;
            result.products = []; result.error = 'محصولات دریافت نشدند. لطفاً دوباره تلاش کنید.';
        } finally { if (current('products', request.version)) result.loading = false; }
    }
    function refresh() { return Promise.allSettled([loadFilters(), loadProducts()]); }
    function change({ facets = true } = {}) { state.page = 1; pushUrl(); return facets ? refresh() : loadProducts(); }
    function toggleValue(slug, value) { const selected = state.values[slug] || []; const next = selected.includes(String(value)) ? selected.filter((item) => item !== String(value)) : [...selected, String(value)]; if (next.length) state.values[slug] = next; else delete state.values[slug]; return change(); }
    function setScalar(slug, value) { if (value === '') delete state.values[slug]; else state.values[slug] = [String(value)]; return change(); }
    function clearFilters() { state.values = {}; state.priceMin = ''; state.priceMax = ''; state.inStock = false; state.search = ''; return change(); }
    async function selectCategory(slug) {
        if (slug === state.category) return;
        state.category = slug; state.values = {}; state.page = 1; result.filters = []; result.products = [];
        pushUrl(); await Promise.allSettled([loadDetail(), refresh()]);
    }
    function goToPage(page) { if (page < 1 || page > pagination.lastPage || page === state.page) return; state.page = page; pushUrl(); return loadProducts(); }
    const chips = computed(() => {
        const items = [];
        for (const [slug, values] of Object.entries(state.values)) {
            const filter = result.filters.find((item) => item.slug === slug);
            for (const value of values) items.push({ key: `${slug}:${value}`, slug, value, label: `${filter?.name || slug}: ${filter?.values?.find((item) => String(item.value) === value)?.label || value}` });
        }
        if (state.priceMin !== '' || state.priceMax !== '') items.push({ key: 'price', label: 'محدوده قیمت' });
        if (state.inStock) items.push({ key: 'stock', label: 'فقط موجود' });
        if (state.search) items.push({ key: 'search', label: `جستجو: ${state.search}` });
        return items;
    });
    function removeChip(chip) {
        if (chip.slug) return toggleValue(chip.slug, chip.value);
        if (chip.key === 'price') { state.priceMin = ''; state.priceMax = ''; }
        if (chip.key === 'stock') state.inStock = false;
        if (chip.key === 'search') state.search = '';
        return change();
    }
    function restoreAndLoad() { restore(); result.filters = []; return Promise.allSettled([loadDetail(), refresh()]); }
    async function init() { restore(); browser.addEventListener('popstate', restoreAndLoad); await Promise.allSettled([loadCatalog(), loadDetail(), refresh()]); }
    function dispose() { active = false; browser.removeEventListener('popstate', restoreAndLoad); Object.values(controllers).forEach((controller) => controller.abort()); }
    return { state, result, catalog, pagination, crumbs, category, subcategories, title, products, chips, init, dispose, restore, params, pushUrl, loadProducts, loadFilters, loadDetail, refresh, change, selectCategory, toggleValue, setScalar, clearFilters, removeChip, goToPage, restoreAndLoad };
}
