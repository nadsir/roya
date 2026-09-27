import { reactive, computed } from 'vue';
import axios from 'axios';

const TOKEN_KEY = 'turbopart-auth-token';

const state = reactive({
    user: null,
    loading: false,
});

function getToken() {
    try {
        return localStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

function setToken(token) {
    try {
        if (token) {
            localStorage.setItem(TOKEN_KEY, token);
        } else {
            localStorage.removeItem(TOKEN_KEY);
        }
    } catch {}
}

function applyToken(token) {
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
        delete axios.defaults.headers.common['Authorization'];
    }
}

async function loadUser() {
    const token = getToken();
    if (!token) {
        state.user = null;
        return;
    }

    applyToken(token);
    state.loading = true;

    try {
        const { data } = await axios.get('/api/customer/me');
        state.user = data.user || null;
    } catch {
        state.user = null;
        setToken(null);
        applyToken(null);
    } finally {
        state.loading = false;
    }
}

async function login(email, password) {
    const { data } = await axios.post('/api/customer/login', {
        email,
        password,
    });

    setToken(data.token);
    applyToken(data.token);
    state.user = data.user;

    return data;
}

async function sendOtp(mobile) {
    const { data } = await axios.post('/api/customer/send-otp', { mobile });
    return data;
}

async function verifyOtp(mobile, code) {
    const { data } = await axios.post('/api/customer/verify-otp', { mobile, code });
    setToken(data.token);
    applyToken(data.token);
    state.user = data.user;
    return data;
}

async function register(name, email, password, passwordConfirmation, mobile) {
    const { data } = await axios.post('/api/customer/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        mobile,
    });

    setToken(data.token);
    applyToken(data.token);
    state.user = data.user;

    return data;
}

async function logout() {
    try {
        await axios.post('/api/customer/logout');
    } catch {}

    state.user = null;
    setToken(null);
    applyToken(null);
}

async function updateProfile(name, email) {
    const { data } = await axios.put('/api/customer/profile', {
        name,
        email,
    });

    state.user = data.user;

    return data;
}

const isLoggedIn = computed(() => state.user !== null);

// Initialize from stored token
const initialToken = getToken();
if (initialToken) {
    applyToken(initialToken);
    loadUser();
}

export {
    state,
    isLoggedIn,
    loadUser,
    login,
    sendOtp,
    verifyOtp,
    register,
    logout,
    updateProfile,
    getToken,
};
