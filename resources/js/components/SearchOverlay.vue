<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import ShellDialog from './ShellDialog.vue';
import { catalog, categoryHref, isStorefrontProduct, loadCatalog } from '../catalog-state.js';

const props = defineProps({ open: Boolean });
const emit = defineEmits(['close']);
const query = ref('');
const products = ref([]);
const loading = ref(false);
const error = ref('');
const recent = ref([]);
const KEY = 'gallery-recent-searches';
let timer;
let controller;
let sequence = 0;

function readRecent() {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) || '[]');
        recent.value = Array.isArray(saved) ? saved.filter((item) => typeof item === 'string' && item.length <= 120).slice(0, 6) : [];
    } catch { recent.value = []; }
}
function remember() {
    const value = query.value.trim().slice(0, 120);
    if (!value) return;
    recent.value = [value, ...recent.value.filter((item) => item !== value)].slice(0, 6);
    try { localStorage.setItem(KEY, JSON.stringify(recent.value)); } catch {}
}
function clearRecent() { recent.value = []; try { localStorage.removeItem(KEY); } catch {} }
function cancel() { clearTimeout(timer); controller?.abort(); sequence += 1; loading.value = false; }

async function search(value, current) {
    controller = new AbortController();
    const signal = controller.signal;
    try {
        await loadCatalog();
        if (signal.aborted || current !== sequence || !props.open) return;
        const { data } = await axios.get('/api/products', { params: { search: value, per_page: 6, sort: 'newest' }, signal });
        if (current !== sequence || !props.open) return;
        products.value = (data.data || []).filter(isStorefrontProduct);
    } catch (failure) {
        if (current !== sequence || signal.aborted || axios.isCancel(failure)) return;
        error.value = 'جستجو انجام نشد. اتصال را بررسی کنید و دوباره تلاش کنید.';
    } finally { if (current === sequence) loading.value = false; }
}
function schedule() {
    cancel();
    products.value = [];
    error.value = '';
    const value = query.value.trim();
    if (value.length < 2 || !props.open) return;
    loading.value = true;
    const current = sequence;
    timer = setTimeout(() => search(value, current), 350);
}
watch(query, schedule);
watch(() => props.open, (open) => {
    cancel();
    if (open) { query.value = ''; products.value = []; error.value = ''; readRecent(); loadCatalog(); }
});
onBeforeUnmount(cancel);

const suggestions = computed(() => {
    const flatten = (nodes) => nodes.flatMap((node) => [node, ...flatten(node.children || [])]);
    const all = flatten(catalog.categories);
    const value = query.value.trim();
    return (value ? all.filter((node) => node.name.includes(value)) : catalog.categories).slice(0, 6);
});
function viewResults() {
    const value = query.value.trim();
    if (!value) return;
    remember();
    location.href = `/store?search=${encodeURIComponent(value)}`;
}
function productImage(product) {
    const image = product.images?.find((item) => item.is_primary) || product.images?.[0];
    return image?.path ? `/storage/${image.path}` : '';
}
</script>

<template>
    <ShellDialog :open="open" label="جستجو در گالری" @close="emit('close')">
        <div class="sf-dialog-heading"><span class="sf-type-metadata">چیزی که به سبک شما می‌آید</span><button type="button" class="sf-icon-button" aria-label="بستن جستجو" @click="emit('close')"><i class="fa-solid fa-xmark" aria-hidden="true" /></button></div>
        <form class="sf-search-form" role="search" @submit.prevent="viewResults">
            <label class="sf-type-h2" for="sf-search-input">دنبال چه می‌گردید؟</label>
            <div class="sf-search-field"><i class="fa-solid fa-magnifying-glass" aria-hidden="true" /><input id="sf-search-input" v-model="query" autofocus type="search" autocomplete="off" maxlength="120" placeholder="نام محصول، سبک یا دسته‌بندی…" /><button class="sf-button" type="submit" :disabled="!query.trim()">جستجو <span aria-hidden="true">←</span></button></div>
        </form>
        <div class="sf-search-body">
            <aside class="sf-search-aside">
                <div v-if="recent.length" class="sf-search-recents"><div class="sf-section-heading"><h2 class="sf-type-metadata">جستجوهای اخیر</h2><button class="sf-text-link" @click="clearRecent">پاک کردن</button></div><button v-for="item in recent" :key="item" class="sf-recent-item" @click="query = item"><i class="fa-regular fa-clock" aria-hidden="true" />{{ item }}<span aria-hidden="true">↖</span></button></div>
                <h2 class="sf-type-metadata">{{ query.trim() ? 'دسته‌های مرتبط' : 'دسته‌بندی‌ها' }}</h2>
                <div class="sf-search-category-list"><a v-for="category in suggestions" :key="category.slug" :href="categoryHref(category.slug)">{{ category.name }} <span aria-hidden="true">←</span></a></div>
                <p v-if="catalog.status === 'loading'" class="sf-type-small" role="status">در حال دریافت دسته‌بندی‌ها…</p>
                <button v-else-if="catalog.status === 'error'" class="sf-text-link" @click="loadCatalog({ retry: true })">دریافت دوباره دسته‌بندی‌ها</button>
            </aside>
            <section class="sf-search-results" :aria-busy="loading" aria-label="پیشنهادهای محصول">
                <div v-if="loading" class="sf-search-skeletons" role="status" aria-label="در حال جستجو"><div v-for="n in 3" :key="n" class="sf-search-result"><span class="sf-skeleton sf-result-image" /><span class="sf-skeleton sf-result-line" /></div></div>
                <div v-else-if="error" class="sf-shell-state" role="alert"><p>{{ error }}</p><button class="sf-button" @click="schedule">تلاش مجدد</button></div>
                <template v-else-if="products.length">
                    <div class="sf-section-heading"><h2 class="sf-type-metadata">پیشنهادهای محصول</h2><button class="sf-text-link" @click="viewResults">همه نتایج ←</button></div>
                    <a v-for="product in products" :key="product.id" :href="`/products/${product.id}`" class="sf-search-result" @click="remember">
                        <span class="sf-result-image"><img v-if="productImage(product)" :src="productImage(product)" :alt="product.name" loading="lazy" @error="$event.target.style.visibility = 'hidden'" /><span v-else aria-hidden="true">G</span></span>
                        <span class="sf-result-copy"><strong>{{ product.name }}</strong><span class="sf-type-price">{{ Number(product.price || 0).toLocaleString('fa-IR') }} <small>تومان</small></span></span><span aria-hidden="true">←</span>
                    </a>
                </template>
                <div v-else class="sf-shell-state" role="status"><span class="sf-search-monogram" aria-hidden="true">G</span><p>{{ query.trim().length >= 2 ? 'محصولی با این عبارت پیدا نشد.' : 'یک انتخاب تازه، با یک جستجوی ساده.' }}</p><span class="sf-type-small">{{ query.trim().length >= 2 ? 'عبارت دیگری امتحان کنید یا دسته‌بندی‌ها را ببینید.' : 'برای جستجو، دست‌کم دو حرف بنویسید.' }}</span></div>
            </section>
        </div>
    </ShellDialog>
</template>
