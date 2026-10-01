<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import AccountNotice from './components/account/AccountNotice.vue';
import { login, isLoggedIn, sendOtp, verifyOtp } from './auth-state.js';
import { peekCommentIntentPath } from './comment-state.js';
import { firstError, errorSummary } from './account-presentation.js';
import '../css/account.css';

// Both existing login modes stay: email and password, or a one time code sent to
// the mobile number. The OTP endpoints, throttle copy and redirect target are
// untouched; only the presentation is rebuilt.
const mode = ref('email');
const email = ref('');
const password = ref('');
const mobile = ref('');
const code = ref('');
const codeSent = ref(false);
const loading = ref(false);
const error = ref('');
const fieldErrors = ref({});
const success = ref('');
const expiresIn = ref(0);
const resendAt = ref(0);
const clock = ref(Date.now());

const resendSeconds = computed(() => Math.max(0, Math.ceil((resendAt.value - clock.value) / 1000)));
// The resend countdown only ticks once the page is actually on screen, so nothing
// runs while the component is being rendered ahead of time.
let timer = null;
onMounted(() => {
    timer = setInterval(() => { clock.value = Date.now(); }, 1000);
});
onUnmounted(() => {
    if (timer !== null) clearInterval(timer);
    timer = null;
});

const redirectParam = new URLSearchParams(window.location.search).get('redirect');
const commentIntentPath = peekCommentIntentPath();
const destination = redirectParam === '/checkout'
    ? '/checkout'
    : (commentIntentPath || '/account');

if (isLoggedIn.value) {
    window.location.href = destination;
}

function changeMode(value) {
    if (loading.value) return;
    mode.value = value;
    error.value = '';
    success.value = '';
    fieldErrors.value = {};
}

function changeMobile() {
    if (loading.value) return;
    codeSent.value = false;
    code.value = '';
    error.value = '';
    success.value = '';
    fieldErrors.value = {};
    expiresIn.value = 0;
}

function failure(failed) {
    const errors = failed.response?.data?.errors;
    fieldErrors.value = errors || {};
    error.value = firstError(errors, 'email') || firstError(errors, 'code')
        || failed.response?.data?.message
        || '';
    if (failed.response?.status === 429) {
        error.value = 'تعداد درخواست‌ها بیش از حد مجاز است. کمی صبر کنید.';
        resendAt.value = Date.now() + Number(failed.response.headers['retry-after'] || 60) * 1000;
    }
    if (!error.value) error.value = 'درخواست انجام نشد. لطفاً دوباره تلاش کنید.';
    return errorSummary(errors);
}

async function onSendOtp() {
    if (loading.value || resendSeconds.value > 0) return;
    loading.value = true;
    error.value = '';
    success.value = '';
    fieldErrors.value = {};
    try {
        const data = await sendOtp(mobile.value);
        codeSent.value = true;
        code.value = '';
        success.value = data.message;
        expiresIn.value = Number(data.expires_in) || 0;
        resendAt.value = Date.now() + data.retry_after * 1000;
    } catch (failed) {
        failure(failed);
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
    } catch (failed) {
        failure(failed);
    } finally {
        loading.value = false;
    }
}

async function onSubmit() {
    if (loading.value) return;
    error.value = '';
    fieldErrors.value = {};
    loading.value = true;
    try {
        await login(email.value, password.value);
        window.location.href = destination;
    } catch (failed) {
        failure(failed);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="ac-auth" dir="rtl">
        <SiteHeader />

        <main class="sf-container ac-auth-main">
            <section class="ac-auth-card">
                <div class="ac-auth-head">
                    <span class="ac-auth-mark" aria-hidden="true"><i class="fa-regular fa-user" /></span>
                    <h1 class="sf-type-h2">ورود به حساب</h1>
                    <p>روش ورود به حساب کاربری را انتخاب کنید.</p>
                </div>

                <div class="ac-auth-tabs" role="group" aria-label="روش ورود">
                    <button
                        v-for="option in [{ value: 'email', label: 'ورود با ایمیل' }, { value: 'mobile', label: 'ورود با موبایل' }]"
                        :key="option.value"
                        type="button"
                        class="ac-auth-tab"
                        :aria-pressed="mode === option.value"
                        :disabled="loading"
                        @click="changeMode(option.value)"
                    >{{ option.label }}</button>
                </div>

                <AccountNotice v-if="error" tone="error" icon="fa-solid fa-circle-exclamation">{{ error }}</AccountNotice>
                <AccountNotice v-if="success" tone="success" icon="fa-solid fa-circle-check">{{ success }}</AccountNotice>

                <form v-if="mode === 'email'" class="ac-auth-form" novalidate @submit.prevent="onSubmit">
                    <div class="ac-field">
                        <label for="login-email">ایمیل</label>
                        <input
                            id="login-email"
                            v-model="email"
                            type="email"
                            dir="ltr"
                            required
                            autocomplete="email"
                            placeholder="example@email.com"
                            :disabled="loading"
                            :aria-invalid="fieldErrors.email ? 'true' : undefined"
                            :aria-describedby="fieldErrors.email ? 'login-email-error' : undefined"
                        />
                        <p v-if="fieldErrors.email" id="login-email-error" class="ac-field-error" role="alert">{{ fieldErrors.email[0] }}</p>
                    </div>

                    <div class="ac-field">
                        <label for="login-password">رمز عبور</label>
                        <input
                            id="login-password"
                            v-model="password"
                            type="password"
                            dir="ltr"
                            required
                            autocomplete="current-password"
                            placeholder="رمز عبور"
                            :disabled="loading"
                        />
                    </div>

                    <button type="submit" class="sf-button" :disabled="loading">
                        {{ loading ? 'در حال ورود…' : 'ورود به حساب' }}
                    </button>
                </form>

                <form v-else class="ac-auth-form" novalidate @submit.prevent="codeSent ? onVerifyOtp() : onSendOtp()">
                    <div v-if="!codeSent" class="ac-field">
                        <label for="login-mobile">شماره موبایل</label>
                        <input
                            id="login-mobile"
                            v-model="mobile"
                            type="tel"
                            dir="ltr"
                            inputmode="tel"
                            maxlength="32"
                            required
                            autocomplete="tel"
                            placeholder="09121234567"
                            :disabled="loading"
                            :aria-invalid="fieldErrors.mobile ? 'true' : undefined"
                            :aria-describedby="fieldErrors.mobile ? 'login-mobile-error' : undefined"
                        />
                        <p id="login-mobile-hint" class="ac-field-hint">اگر شماره‌ای در حساب خود ثبت نکرده‌اید، از ورود با ایمیل استفاده کنید.</p>
                        <p v-if="fieldErrors.mobile" id="login-mobile-error" class="ac-field-error" role="alert">{{ fieldErrors.mobile[0] }}</p>
                    </div>

                    <template v-else>
                        <p class="ac-field-hint">کد تأیید به شماره <span dir="ltr">{{ mobile }}</span> ارسال شد.</p>
                        <div class="ac-field">
                            <label for="login-code">کد تأیید شش‌رقمی</label>
                            <input
                                id="login-code"
                                v-model="code"
                                type="text"
                                dir="ltr"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                minlength="6"
                                maxlength="6"
                                required
                                autocomplete="one-time-code"
                                :disabled="loading"
                                :aria-invalid="fieldErrors.code ? 'true' : undefined"
                                :aria-describedby="fieldErrors.code ? 'login-code-error' : undefined"
                            />
                            <p v-if="fieldErrors.code" id="login-code-error" class="ac-field-error" role="alert">{{ fieldErrors.code[0] }}</p>
                            <p v-if="expiresIn" class="ac-field-hint">اعتبار کد: {{ expiresIn.toLocaleString('fa-IR') }} دقیقه</p>
                        </div>
                    </template>

                    <button type="submit" class="sf-button" :disabled="loading || (!codeSent && resendSeconds > 0)">
                        <template v-if="loading">در حال انجام…</template>
                        <template v-else-if="codeSent">تأیید و ورود</template>
                        <template v-else-if="resendSeconds > 0">ارسال کد ({{ resendSeconds }} ثانیه)</template>
                        <template v-else>ارسال کد تأیید</template>
                    </button>

                    <div v-if="codeSent" class="ac-auth-actions">
                        <button type="button" :disabled="loading || resendSeconds > 0" @click="onSendOtp">
                            {{ resendSeconds > 0 ? `ارسال مجدد تا ${resendSeconds} ثانیه` : 'ارسال مجدد کد' }}
                        </button>
                        <button type="button" :disabled="loading" @click="changeMobile">تغییر شماره</button>
                    </div>
                </form>

                <p class="ac-auth-foot">
                    حساب ندارید؟
                    <a href="/register">ثبت‌نام کنید</a>
                </p>
            </section>
        </main>

        <SiteFooter />
    </div>
</template>
