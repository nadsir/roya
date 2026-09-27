import { computed, reactive } from 'vue';
import axios from 'axios';
import { catalog, isStorefrontProduct, loadCatalog } from './catalog-state.js';
import { productImages, imageUrl } from './product-presentation.js';

export function createHomepageData() {
    const state = reactive({ products: [], status: 'idle', error: '' });
    let controller;
    let generation = 0;
    const products = computed(() => state.products.filter(isStorefrontProduct));
    const arrivals = computed(() => products.value.slice(0, 4));
    const featured = computed(() => products.value.filter((product) => product.is_featured).slice(0, 6));
    const heroProduct = computed(() => featured.value.find((product) => product.in_stock) || featured.value[0] || products.value[0] || null);

    async function loadProducts() {
        controller?.abort();
        controller = new AbortController();
        const current = ++generation;
        state.status = 'loading'; state.error = '';
        try {
            const { data } = await axios.get('/api/products', {
                params: { sort: 'newest', per_page: 24 }, signal: controller.signal,
            });
            if (current !== generation) return;
            state.products = data.data || [];
            state.status = 'ready';
        } catch (failure) {
            if (current !== generation || axios.isCancel(failure)) return;
            state.error = 'محصولات دریافت نشدند. دوباره تلاش کنید.';
            state.status = 'error';
        }
    }
    function categoryProduct(category) {
        const slugs = new Set();
        const walk = (node) => { slugs.add(node.slug); (node.children || []).forEach(walk); };
        walk(category);
        return products.value.find((product) => product.categories?.some((item) => slugs.has(item.slug)) && productImages(product).length);
    }
    function categoryProductImage(category) { return imageUrl(productImages(categoryProduct(category))[0]?.path); }
    function dispose() { generation += 1; controller?.abort(); }
    function load() { return Promise.allSettled([loadCatalog(), loadProducts()]); }
    return { state, catalog, products, arrivals, featured, heroProduct, load, loadProducts, dispose, categoryProductImage };
}
