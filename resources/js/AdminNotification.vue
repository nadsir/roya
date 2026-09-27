<script setup>
import { onMounted, onUnmounted, watch, computed, ref } from 'vue';

const props = defineProps({
    notification: {
        type: Object,
        default: () => ({
            visible: false,
            type: 'success',
            title: '',
            message: '',
        }),
    },
});

const emit = defineEmits(['close']);

let autoCloseTimer = null;
let exitTimer = null;

const isExiting = ref(false);

const icon = computed(() => {
    switch (props.notification.type) {
        case 'success':
            return 'fa-solid fa-check-circle';
        case 'error':
            return 'fa-solid fa-times-circle';
        case 'warning':
            return 'fa-solid fa-triangle-exclamation';
        default:
            return 'fa-solid fa-info-circle';
    }
});

const iconBg = computed(() => {
    switch (props.notification.type) {
        case 'success':
            return 'bg-emerald-500/15 text-emerald-600';
        case 'error':
            return 'bg-red-500/15 text-red-600';
        case 'warning':
            return 'bg-amber-500/15 text-amber-600';
        default:
            return 'bg-blue-500/15 text-blue-600';
    }
});

const borderColor = computed(() => {
    switch (props.notification.type) {
        case 'success':
            return 'border-emerald-500/30';
        case 'error':
            return 'border-red-500/30';
        case 'warning':
            return 'border-amber-500/30';
        default:
            return 'border-blue-500/30';
    }
});

function startAutoClose() {
    clearTimeout(autoCloseTimer);
    clearTimeout(exitTimer);
    isExiting.value = false;

    const delay = props.notification.type === 'error' ? 8000 : 5000;

    autoCloseTimer = setTimeout(() => {
        close();
    }, delay);
}

function close() {
    if (isExiting.value) return;
    isExiting.value = true;

    exitTimer = setTimeout(() => {
        emit('close');
    }, 250);
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    if (props.notification.visible) {
        startAutoClose();
        document.addEventListener('keydown', onKeydown);
    }
});

onUnmounted(() => {
    clearTimeout(autoCloseTimer);
    clearTimeout(exitTimer);
    document.removeEventListener('keydown', onKeydown);
});

watch(
    () => props.notification.visible,
    (val) => {
        if (val) {
            startAutoClose();
            document.addEventListener('keydown', onKeydown);
        } else {
            clearTimeout(autoCloseTimer);
            clearTimeout(exitTimer);
            isExiting.value = false;
            document.removeEventListener('keydown', onKeydown);
        }
    }
);
</script>

<template>
    <Teleport to="body">
        <Transition
            name="admin-notification"
            appear
        >
            <div
                v-if="notification.visible && !isExiting"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 pointer-events-none"
            >
                <div
                    class="fixed inset-0 bg-black/20 backdrop-blur-sm pointer-events-auto"
                    @click="close"
                    aria-hidden="true"
                />

                <div
                    class="relative pointer-events-auto w-full max-w-md transform transition-all duration-300"
                    role="alert"
                    aria-live="polite"
                >
                    <div
                        class="rounded-2xl bg-white border shadow-2xl overflow-hidden"
                        :class="borderColor"
                    >
                        <div class="flex items-start gap-3 p-4">
                            <div
                                class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center"
                                :class="iconBg"
                            >
                                <i :class="icon" class="text-xl" aria-hidden="true"></i>
                            </div>

                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-bold text-ink">
                                    {{ notification.title }}
                                </h3>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    {{ notification.message }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="flex-shrink-0 p-1.5 text-slate-400 hover:text-slate-600 hover:bg-gray-100 rounded-lg transition-colors"
                                @click="close"
                                aria-label="بستن اعلان"
                            >
                                <i class="fa-solid fa-xmark text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style>
@keyframes admin-notification-enter {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes admin-notification-leave {
    from {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
    to {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
}

.admin-notification-enter-active,
.admin-notification-leave-active {
    animation-duration: 250ms;
    animation-timing-function: cubic-bezier(0.22, 1, 0.36, 1);
    animation-fill-mode: both;
}

.admin-notification-enter-from {
    animation-name: admin-notification-enter;
}

.admin-notification-leave-to {
    animation-name: admin-notification-leave;
}
</style>