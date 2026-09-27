// Read-only checks against the running local Laravel server; no DB mutations.
import assert from 'node:assert/strict';
const origin = process.env.STOREFRONT_QA_URL || 'http://127.0.0.1:8000';
async function get(path) { const response = await fetch(origin + path); assert.equal(response.status, 200, `${path}: ${response.status}`); return response; }
const [categoriesResponse, productsResponse] = await Promise.all([get('/api/categories/tree'), get('/api/products?sort=newest&per_page=24')]);
const categories = (await categoriesResponse.json()).data;
const products = (await productsResponse.json()).data;
assert.ok(Array.isArray(categories)); assert.ok(Array.isArray(products));
const paths = ['/', '/store', '/store?sort=newest', '/cart', '/wishlist', '/images/editorial/placeholder.svg', ...categories.map((category) => `/c/${encodeURIComponent(category.slug)}`), ...products.map((product) => `/products/${product.id}`)];
for (const path of paths) await get(path);
console.log(`PASS ${paths.length} page/asset links; ${categories.length} categories and ${products.length} products from real API.`);
const wishlist = await fetch(origin + '/api/customer/wishlist', { headers: { Accept: 'application/json' } });
assert.equal(wishlist.status, 401); console.log('PASS guest wishlist requires authentication.');
const imagePaths = [...new Set(products.flatMap((product) => (product.images || []).map((image) => image.path)))];
const unavailable = [];
for (const path of imagePaths) { const response = await fetch(origin + '/storage/' + path, { method: 'HEAD' }); if (!response.ok) unavailable.push(`${response.status} ${path}`); }
console.log(`Product images: ${imagePaths.length - unavailable.length}/${imagePaths.length} available.`);
if (unavailable.length) console.log('KNOWN ISSUE product image responses: ' + [...new Set(unavailable.map((item) => item.split(' ')[0]))].join(', '));
