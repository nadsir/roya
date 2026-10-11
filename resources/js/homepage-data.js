import { computed, reactive } from 'vue';
import axios from 'axios';
import { catalog, isStorefrontProduct, loadCatalog } from './catalog-state.js';
import { productImages, imageUrl } from './product-presentation.js';

const PRODUCT_PAGE_SIZE = 24;

function listProducts(params, signal) {
    return axios.get('/api/products', { params, signal }).then(({ data }) => data.data || []);
}

export function createHomepageData() {
    const state = reactive({ products: [], arrivals: [], featured: [], status: 'idle', error: '' });
    let controller;
    let generation = 0;
    const products = computed(() => state.products.filter(isStorefrontProduct));
    const arrivals = computed(() => state.arrivals.filter(isStorefrontProduct));
    const featured = computed(() => state.featured.filter(isStorefrontProduct));
    const heroProduct = computed(() => featured.value.find((product) => product.in_stock) || featured.value[0] || products.value[0] || null);

    async function loadProducts() {
        controller?.abort();
        controller = new AbortController();
        const signal = controller.signal;
        const current = ++generation;
        state.status = 'loading'; state.error = '';
        try {
            const [latest, galleryNew, curated] = await Promise.all([
                listProducts({ sort: 'newest', per_page: PRODUCT_PAGE_SIZE }, signal),
                listProducts({ gallery_new: 1, per_page: PRODUCT_PAGE_SIZE }, signal),
                listProducts({ featured: 1, per_page: PRODUCT_PAGE_SIZE }, signal),
            ]);
            if (current !== generation) return;
            state.products = latest;
            state.arrivals = galleryNew;
            state.featured = curated;
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
