import { ref } from 'vue';

// Keep the existing preference without applying storefront styles to <html>.
const KEY = 'turbopart-theme';
export const storefrontDark = ref(false);

function applyTheme(dark) {
    storefrontDark.value = dark;
    const root = document.getElementById('app');
    if (!root) return;
    root.classList.add('storefront-theme');
    root.classList.toggle('dark', dark);
    root.dataset.theme = dark ? 'dark' : 'light';
}

export function initStorefrontTheme() {
    if (location.pathname.startsWith('/admin')) return;
    document.title = 'گالری | پوشاک و اکسسوری زنانه';
    let dark = false;
    try { dark = localStorage.getItem(KEY) === 'dark'; } catch {}
    applyTheme(dark);
    window.addEventListener('storage', (event) => {
        if (event.key === KEY) applyTheme(event.newValue === 'dark');
    });
}

export function toggleStorefrontTheme() {
    applyTheme(!storefrontDark.value);
    try { localStorage.setItem(KEY, storefrontDark.value ? 'dark' : 'light'); } catch {}
}
