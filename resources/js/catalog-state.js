import { reactive } from 'vue';
import axios from 'axios';

export const catalog = reactive({ categories: [], status: 'idle', error: '' });
let pending = null;
const automotiveSlugs = new Set();
const automotiveText = /خودرو|قطعات موتور|لوازم یدکی|تعمیرگاه|توربوپارت|car[- ]parts|automotive|vehicle/i;

function rememberBranch(node) {
    automotiveSlugs.add(node.slug);
    (node.children || []).forEach(rememberBranch);
}

function storefrontCategories(nodes) {
    return nodes.filter((node) => {
        if (automotiveText.test(`${node.name} ${node.slug}`)) {
            rememberBranch(node);
            return false;
        }
        return node.is_active !== false;
    }).map((node) => ({ ...node, children: storefrontCategories(node.children || []) }));
}

export function isStorefrontProduct(product) {
    return !automotiveText.test(`${product.name || ''} ${product.short_description || ''}`)
        && !(product.categories || []).some((category) => automotiveSlugs.has(category.slug)
            || automotiveText.test(`${category.name} ${category.slug}`));
}

export function loadCatalog({ retry = false } = {}) {
    if (pending) return pending;
    if (catalog.status === 'ready' && !retry) return Promise.resolve(catalog.categories);
    catalog.status = 'loading';
    catalog.error = '';
    pending = (async () => {
        try {
            const { data } = await axios.get('/api/categories/tree');
            automotiveSlugs.clear();
            catalog.categories = storefrontCategories(data.data || []);
            catalog.status = 'ready';
        } catch {
            catalog.status = 'error';
            catalog.error = 'دریافت دسته‌بندی‌ها انجام نشد. دوباره تلاش کنید.';
        } finally { pending = null; }
        return catalog.categories;
    })();
    return pending;
}

export function categoryImage(category) {
    const image = category?.image;
    if (!image) return '';
    return /^(https?:\/\/|\/)/i.test(image) ? image : `/storage/${image}`;
}

export function categoryHref(slug) { return `/c/${encodeURIComponent(slug)}`; }
