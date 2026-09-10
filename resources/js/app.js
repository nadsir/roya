import './bootstrap';
import { createApp } from 'vue';

import App from './App.vue';
import Admin from './Admin.vue';

createApp(location.pathname.startsWith('/admin') ? Admin : App).mount('#app');
