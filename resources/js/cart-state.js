import { reactive, computed } from 'vue';

const STORAGE_KEY = 'turbopart-cart';

const state = reactive({
    items: [],
});

function load() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                state.items = parsed.filter(
                    (item) => item && item.key && item.product_id
                );
                return;
            }
        }
    } catch {}
    state.items = [];
}

function persist() {
    try {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(state.items)
        );
    } catch {}
}

function addToCart(item) {
    if (!item || !item.key || !item.product_id) return;

    const existing = state.items.find(
        (i) => i.key === item.key
    );

    if (existing) {
        const maxQty = existing.stock || 999;
        existing.quantity = Math.min(
            existing.quantity + (item.quantity || 1),
            maxQty
        );
    } else {
        state.items.push({
            key: item.key,
            product_id: item.product_id,
            variant_id: item.variant_id || null,
            name: item.name || '',
            price: Number(item.price) || 0,
            quantity: Math.min(
                item.quantity || 1,
                item.stock || 999
            ),
            image: item.image || null,
            attributes: item.attributes || null,
            sku: item.sku || null,
            stock: item.stock || 999,
        });
    }

    persist();
}

function removeFromCart(key) {
    state.items = state.items.filter(
        (i) => i.key !== key
    );
    persist();
}

function updateQuantity(key, quantity) {
    const item = state.items.find(
        (i) => i.key === key
    );
    if (!item) return;

    const qty = Math.max(1, Math.min(quantity, item.stock || 999));
    item.quantity = qty;
    persist();
}

function clearCart() {
    state.items = [];
    persist();
}

function getCartItems() {
    return state.items;
}

const cartCount = computed(() =>
    state.items.reduce((sum, item) => sum + item.quantity, 0)
);

const cartTotal = computed(() =>
    state.items.reduce(
        (sum, item) => sum + item.price * item.quantity,
        0
    )
);

load();

// Sync across tabs
if (typeof window !== 'undefined') {
    window.addEventListener('storage', (e) => {
        if (e.key === STORAGE_KEY) {
            load();
        }
    });
}

export {
    addToCart,
    removeFromCart,
    updateQuantity,
    clearCart,
    getCartItems,
    cartCount,
    cartTotal,
};
