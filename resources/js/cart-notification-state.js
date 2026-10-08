import { reactive } from 'vue';

const AUTO_DISMISS_MS = 3500;

const state = reactive({
    visible: false,
    name: '',
    image: null,
});

let timer = null;

function showCartAdded(item) {
    if (!item) return;
    state.name = item.name || '';
    state.image = item.image || null;
    state.visible = true;
    clearTimeout(timer);
    timer = setTimeout(hideCartNotification, AUTO_DISMISS_MS);
}

function hideCartNotification() {
    clearTimeout(timer);
    state.visible = false;
}

export { state as cartNotification, showCartAdded, hideCartNotification };
