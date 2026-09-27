import { reactive, computed, watch } from 'vue';
import axios from 'axios';
import { state as authState, isLoggedIn, getToken, loadUser } from './auth-state.js';

const state = reactive({
    items: [],
    loading: false,
    error: null,
});

let generation = 0;
let queue = Promise.resolve();
let ownerToken = getToken();
let pendingLoad = null;

function resetWishlist() {
    generation += 1;
    queue = Promise.resolve();
    pendingLoad = null;
    ownerToken = getToken();
    state.items = [];
    state.loading = false;
    state.error = null;
}

// Clear immediately on logout or account changes, including pending responses.
watch(() => authState.user?.id ?? null, resetWishlist, { flush: 'sync' });

function hasSession() {
    if (ownerToken !== getToken()) resetWishlist();
    if (!isLoggedIn.value || !getToken()) {
        resetWishlist();
        return false;
    }
    return true;
}

function isInWishlist(productId) {
    if (!hasSession()) return false;
    return state.items.some((item) => String(item.product_id) === String(productId));
}

function upsertItem(item) {
    const productId = item.product?.id ?? item.product_id;
    const existing = state.items.find((entry) => String(entry.product_id) === String(productId));
    if (existing) {
        Object.assign(existing, item, { product_id: productId });
    } else {
        state.items.push({ product: null, ...item, product_id: productId });
    }
}

function forgetProduct(productId) {
    state.items = state.items.filter((item) => String(item.product_id) !== String(productId));
}

// Serialize requests so a slower list/check cannot overwrite a later add/remove.
function runOperation(operation, guestResult = null) {
    if (!hasSession()) return Promise.resolve(guestResult);

    const session = generation;
    const token = getToken();
    const current = () => session === generation
        && isLoggedIn.value && token === getToken();

    const result = queue.then(async () => {
        if (!current()) return guestResult;
        state.loading = true;
        state.error = null;

        try {
            return await operation(current);
        } catch (error) {
            if (!current()) return guestResult;

            if (error.response?.status === 401) {
                // Reuse auth-state's existing token validation and cleanup.
                resetWishlist();
                await loadUser();
                return guestResult;
            }

            state.error = {
                status: error.response?.status ?? null,
                message: error.response?.data?.message || error.message || 'Wishlist request failed.',
                errors: error.response?.data?.errors ?? {},
            };
            return null;
        } finally {
            if (current()) state.loading = false;
        }
    });

    queue = result.catch(() => {});
    return result;
}

async function fetchItems(current) {
    const { data } = await axios.get('/api/customer/wishlist');
    if (!current()) return null;

    state.items = [];
    data.data.forEach(upsertItem);
    return state.items;
}

function loadWishlist() {
    if (!hasSession()) return Promise.resolve(null);
    if (pendingLoad) return pendingLoad;

    // Header and page can request the same initial list in the same render.
    const request = runOperation(fetchItems);
    pendingLoad = request;
    request.finally(() => {
        if (pendingLoad === request) pendingLoad = null;
    });
    return request;
}

function addToWishlist(productId) {
    return runOperation(async (current) => {
        const { data } = await axios.post('/api/customer/wishlist', { product_id: productId });
        if (!current()) return null;

        // POST supplies item/product IDs; GET supplies the complete product.
        upsertItem(data.data);
        await fetchItems(current);
        return current() ? data : null;
    });
}

function removeFromWishlist(productId) {
    return runOperation(async (current) => {
        await axios.delete(`/api/customer/wishlist/${encodeURIComponent(productId)}`);
        if (!current()) return null;

        forgetProduct(productId);
        return true;
    });
}

function checkWishlist(productId) {
    return runOperation(async (current) => {
        const { data } = await axios.get(`/api/customer/wishlist/check/${encodeURIComponent(productId)}`);
        if (!current()) return null;

        if (!data.in_wishlist) {
            forgetProduct(productId);
        } else if (!isInWishlist(productId)) {
            upsertItem({ product_id: productId });
            await fetchItems(current);
        }

        return current() ? data.in_wishlist : null;
    }, false);
}

const wishlistCount = computed(() => state.items.length);

export {
    state,
    wishlistCount,
    loadWishlist,
    checkWishlist,
    addToWishlist,
    removeFromWishlist,
    isInWishlist,
    resetWishlist,
};
