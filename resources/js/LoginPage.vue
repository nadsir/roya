<script setup>
import { computed, onUnmounted, ref } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { login, isLoggedIn, sendOtp, verifyOtp } from './auth-state.js';
import { peekCommentIntentPath } from './comment-state.js';

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');
const mode = ref('email');
const mobile = ref('');
const code = ref('');
const codeSent = ref(false);
const success = ref('');
const resendAt = ref(0);
const clock = ref(Date.now());
const resendSeconds = computed(() => Math.max(0, Math.ceil((resendAt.value - clock.value) / 1000)));
const timer = setInterval(() => { clock.value = Date.now(); }, 1000);
onUnmounted(() => clearInterval(timer));

function changeMode(value) {
    if (loading.value) return;
    mode.value = value;
    error.value = '';
    success.value = '';
}

function otpFailure(failure) {
    const errors = failure.response?.data?.errors;
    error.value = errors ? Object.values(errors)[0]?.[0] : failure.response?.data?.message;
    if (failure.response?.status === 429) {
        error.value = 'تعداد درخواست‌ها بیش از حد مجاز است. کمی صبر کنید.';
        resendAt.value = Date.now() + Number(failure.response.headers['retry-after'] || 60) * 1000;
    }
    if (!error.value) error.value = 'درخواست انجام نشد. لطفاً دوباره تلاش کنید.';
}

async function onSendOtp() {
    if (loading.value || resendSeconds.value > 0) return;
    loading.value = true;
    error.value = '';
    success.value = '';
    try {
        const data = await sendOtp(mobile.value);
        codeSent.value = true;
        code.value = '';
        success.value = data.message;
        resendAt.value = Date.now() + data.retry_after * 1000;
    } catch (failure) {
        otpFailure(failure);
    } finally {
        loading.value = false;
    }
}

async function onVerifyOtp() {
    if (loading.value) return;
    loading.value = true;
    error.value = '';
    try {
        await verifyOtp(mobile.value, code.value);
        window.location.href = destination;
    } catch (failure) {
        otpFailure(failure);
    } finally {
        loading.value = false;
    }
}

function changeMobile() {
    if (loading.value) return;
    codeSent.value = false;
    code.value = '';
    error.value = '';
    success.value = '';
}
const redirectParam = new URLSearchParams(window.location.search).get('redirect');
const commentIntentPath = peekCommentIntentPath();
const destination = redirectParam === '/checkout'
    ? '/checkout'
    : (commentIntentPath || '/account');

if (isLoggedIn.value) {
    window.location.href = destination;
}

async function onSubmit() {
    if (loading.value) return;
    error.value = '';
    loading.value = true;

    try {
        await login(email.value, password.value);
        window.location.href = destination;
    } catch (e) {
        const msg = e.response?.data?.message;
        const errors = e.response?.data?.errors;
        if (errors?.email) {
            error.value = errors.email[0];
        } else if (msg) {
            error.value = msg;
        } else {
            error.value = 'ورود انجام نشد. لطفاً دوباره تلاش کنید.';
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased">
        <SiteHeader />

        <div class="max-w-md mx-auto px-4 py-12">
            <div class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
                <div class="text-center mb-6">
                    <div class="w-12 h-12 rounded-xl bg-brand-accent/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid fa-user text-brand-accent text-lg"></i>
                    </div>
                    <h1 class="text-lg font-black text-ink">ورود به حساب</h1>
                    <p class="text-xs text-slate-500 mt-1">روش ورود به حساب را انتخاب کنید</p>
                </div>

                <div class="flex gap-2 mb-5" aria-label="روش ورود">
                    <button v-for="option in [{ value: 'email', label: 'ورود با ایمیل' }, { value: 'mobile', label: 'ورود با موبایل' }]" :key="option.value" type="button" :disabled="loading" :aria-pressed="mode === option.value" class="flex-1 rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-50" :class="mode === option.value ? 'bg-brand-accent border-brand-accent text-dark-900' : 'border-gray-200 text-slate-500'" @click="changeMode(option.value)">{{ option.label }}</button>
                </div>

                <div v-if="error" role="alert" class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-600">
                    {{ error }}
                </div>

                <form v-if="mode === 'email'" class="space-y-4" @submit.prevent="onSubmit">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5">ایمیل</label>
                        <input
                            v-model="email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="example@email.com"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-ink placeholder-slate-400 focus:border-brand-accent/60 focus:outline-none transition-colors"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5">رمز عبور</label>
                        <input
                            v-model="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="رمز عبور"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-ink placeholder-slate-400 focus:border-brand-accent/60 focus:outline-none transition-colors"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full rounded-lg bg-brand-accent text-dark-900 py-2.5 text-sm font-bold hover:bg-brand-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i v-if="loading" class="fa-solid fa-spinner fa-spin ml-1"></i>
                        {{ loading ? 'در حال ورود...' : 'ورود' }}
                    </button>
                </form>

                <form v-else class="space-y-4" @submit.prevent="codeSent ? onVerifyOtp() : onSendOtp()">
                    <p v-if="success" role="status" class="rounded-lg bg-emerald-50 p-3 text-xs text-emerald-700">{{ success }}</p>
                    <div v-if="!codeSent">
                        <label for="login-mobile" class="block text-xs text-slate-500 mb-2">شماره موبایل</label>
                        <input id="login-mobile" v-model="mobile" type="tel" dir="ltr" autocomplete="tel" required :disabled="loading" maxlength="32" placeholder="09121234567" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none" />
                        <p class="mt-2 text-xs text-slate-500">اگر شماره‌ای در حساب خود ثبت نکرده‌اید، از ورود با ایمیل استفاده کنید.</p>
                    </div>
                    <div v-else>
                        <p class="text-xs text-slate-500 mb-3">شماره موبایل: <span dir="ltr">{{ mobile }}</span></p>
                        <label for="login-code" class="block text-xs text-slate-500 mb-2">کد تأیید شش‌رقمی</label>
                        <input id="login-code" v-model="code" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr" pattern="[0-9]{6}" minlength="6" maxlength="6" required :disabled="loading" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-center tracking-widest text-lg focus:border-brand-accent focus:outline-none" />
                        <p class="text-xs text-slate-500 mt-2">اعتبار کد: ۲ دقیقه</p>
                    </div>
                    <button type="submit" :disabled="loading || (!codeSent && resendSeconds > 0)" class="w-full rounded-lg bg-brand-accent text-dark-900 py-2.5 text-sm font-bold hover:bg-brand-hover disabled:opacity-50 disabled:cursor-not-allowed">{{ loading ? 'در حال انجام…' : codeSent ? 'تأیید و ورود' : resendSeconds > 0 ? `ارسال کد (${resendSeconds} ثانیه)` : 'ارسال کد' }}</button>
                    <div v-if="codeSent" class="flex flex-wrap justify-between gap-3 text-xs">
                        <button type="button" :disabled="loading || resendSeconds > 0" class="text-slate-600 disabled:opacity-50" @click="onSendOtp">{{ resendSeconds > 0 ? `ارسال مجدد تا ${resendSeconds} ثانیه` : 'کد را دریافت نکردید؟ ارسال مجدد' }}</button>
                        <button type="button" :disabled="loading" class="text-slate-600 disabled:opacity-50" @click="changeMobile">تغییر شماره</button>
                    </div>
                </form>

                <p class="mt-5 text-center text-xs text-slate-500">
                    حساب ندارید؟
                    <a href="/register" class="text-brand-accent hover:text-brand-hover font-bold transition-colors">
                        ثبت‌نام کنید
                    </a>
                </p>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>
