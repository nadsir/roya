import './bootstrap';
import { createApp, defineAsyncComponent } from 'vue';

import Homepage from './Homepage.vue';
import App from './Storefront.vue';
const Admin = defineAsyncComponent(() => import('./Admin.vue'));
import ProductDetail from './ProductDetail.vue';
import CartPage from './CartPage.vue';
import CheckoutPage from './CheckoutPage.vue';
import OrderSuccessPage from './OrderSuccessPage.vue';
import OrdersPage from './OrdersPage.vue';
import OrderDetailPage from './OrderDetailPage.vue';
import LoginPage from './LoginPage.vue';
import RegisterPage from './RegisterPage.vue';
import AccountPage from './AccountPage.vue';
import WishlistPage from './WishlistPage.vue';
import ArticlesPage from './ArticlesPage.vue';
import ArticleDetailPage from './ArticleDetailPage.vue';
import { initStorefrontTheme } from './storefront-theme.js';

function resolveApp() {
    const path = location.pathname;

    if (path.startsWith('/admin')) return Admin;
    if (path === '/login') return LoginPage;
    if (path === '/register') return RegisterPage;
    if (path === '/account') return AccountPage;
    if (path === '/cart') return CartPage;
    if (path === '/checkout' || path === '/checkout/') return CheckoutPage;
    if (path === '/order-success' || path === '/order-success/') return OrderSuccessPage;
    if (path === '/orders' || path === '/orders/') return OrdersPage;
    if (/^\/orders\/\d+\/?$/.test(path)) return OrderDetailPage;
    if (path === '/wishlist' || path === '/wishlist/') return WishlistPage;
    if (/^\/products\/\d+/.test(path)) return ProductDetail;
    if (/^\/articles\/[^/]+\/?$/.test(path)) return ArticleDetailPage;
    if (path === '/articles' || path === '/articles/') return ArticlesPage;
    if (path === '/' || path === '') return Homepage;
    if (/^\/c\/[^/]+\/?$/.test(path)) return App;

    return App;
}

if (!location.pathname.startsWith('/admin')) initStorefrontTheme();

createApp(resolveApp()).mount('#app');
