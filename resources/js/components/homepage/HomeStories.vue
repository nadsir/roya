<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { stories } from '../../stories-data.js';

const viewerOpen = ref(false);
const activeStoryIndex = ref(0);
const activeVideoIndex = ref(0);
const progress = ref(0);
const videoFailed = ref(false);
const mediaRatio = ref('9 / 16');
const announcement = ref('');

const dialog = ref(null);
const media = ref(null);

let previousFocus = null;
let previousOverflow = null;
let touchStart = null;
let swipeMoved = false;

const activeStory = computed(() => stories[activeStoryIndex.value]);
const activeVideo = computed(() => activeStory.value.videos[activeVideoIndex.value] ?? activeStory.value.videos[0]);
const videosInStory = computed(() => activeStory.value.videos.length);
const mediaKey = computed(
    () => `${activeStory.value.id}::${activeVideoIndex.value}::${activeVideo.value?.id ?? ''}`
);
const position = computed(() =>
    `${(activeStoryIndex.value + 1).toLocaleString('fa-IR')} از ${stories.length.toLocaleString('fa-IR')}`
);
const liveMessage = computed(() =>
    `داستان ${(activeStoryIndex.value + 1).toLocaleString('fa-IR')} از ${stories.length.toLocaleString('fa-IR')}: ${activeStory.value.title}`
);

function resetProgress() {
    progress.value = 0;
    videoFailed.value = false;
    mediaRatio.value = '9 / 16';
}

function openStory(storyIndex) {
    activeStoryIndex.value = storyIndex;
    activeVideoIndex.value = 0;
    resetProgress();
    viewerOpen.value = true;
    announcement.value = liveMessage.value;
}

function close() {
    viewerOpen.value = false;
}

function next() {
    if (activeVideoIndex.value < videosInStory.value - 1) {
        activeVideoIndex.value++;
        resetProgress();
        announcement.value = liveMessage.value;
        return;
    }
    close();
}

function previous() {
    if (activeVideoIndex.value > 0) {
        activeVideoIndex.value--;
        resetProgress();
        announcement.value = liveMessage.value;
    }
}

function onKey(event) {
    if (event.key === 'ArrowLeft') { event.preventDefault(); next(); }
    else if (event.key === 'ArrowRight') { event.preventDefault(); previous(); }
    else if (event.key === 'Home') {
        event.preventDefault();
        if (activeVideoIndex.value !== 0) {
            activeVideoIndex.value = 0;
            resetProgress();
            announcement.value = liveMessage.value;
        }
    }
    else if (event.key === 'End') {
        event.preventDefault();
        const lastVideoIndex = videosInStory.value - 1;
        if (activeVideoIndex.value !== lastVideoIndex) {
            activeVideoIndex.value = lastVideoIndex;
            resetProgress();
            announcement.value = liveMessage.value;
        }
    }
}

function onBackdrop(event) {
    if (event.target === dialog.value) close();
}

function onTimeUpdate(event) {
    const video = event.target;
    if (video !== media.value) return;
    const duration = video.duration;
    if (!duration || !Number.isFinite(duration)) return;
    const fraction = Math.min(1, Math.max(0, video.currentTime / duration));
    progress.value = fraction * 100;
}

function onLoadedMetadata(event) {
    const video = event.target;
    if (video !== media.value) return;
    if (video.videoWidth && video.videoHeight) mediaRatio.value = `${video.videoWidth} / ${video.videoHeight}`;
}

function onTouchStart(event) {
    if (event.touches.length !== 1) { touchStart = null; return; }
    touchStart = { x: event.touches[0].clientX, y: event.touches[0].clientY };
    swipeMoved = false;
}

function onTouchEnd(event) {
    if (!touchStart || !event.changedTouches.length) { touchStart = null; return; }
    const dx = event.changedTouches[0].clientX - touchStart.x;
    const dy = event.changedTouches[0].clientY - touchStart.y;
    touchStart = null;
    if (Math.abs(dx) < 46 || Math.abs(dx) < Math.abs(dy) * 1.2) return;
    swipeMoved = true;
    if (dx > 0) next(); else previous();
}

function tapZone(zone) {
    if (swipeMoved) { swipeMoved = false; return; }
    if (zone === 'next') next(); else previous();
}

watch(viewerOpen, async (open) => {
    await nextTick();
    const element = dialog.value;
    if (!element) return;
    if (open) {
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        if (!element.open) element.showModal();
        element.querySelector('[data-autofocus]')?.focus();
        const attempt = media.value?.play?.();
        if (attempt?.catch) attempt.catch(() => {});
    } else if (element.open) {
        element.close();
        document.body.style.overflow = previousOverflow ?? '';
        previousOverflow = null;
        if (previousFocus?.isConnected) previousFocus.focus();
        previousFocus = null;
        progress.value = 0;
        touchStart = null;
    }
});

watch([activeStoryIndex, activeVideoIndex], async () => {
    resetProgress();
    if (!viewerOpen.value) return;
    await nextTick();
    const attempt = media.value?.play?.();
    if (attempt?.catch) attempt.catch(() => {});
});

onBeforeUnmount(() => {
    const element = dialog.value;
    if (element?.open) element.close();
    document.body.style.overflow = previousOverflow ?? '';
    previousFocus = null;
    previousOverflow = null;
});
</script>

<template>
    <section class="hp-stories hp-content" aria-labelledby="hp-stories-title">
        <header class="hp-stories-head">
            <h2 id="hp-stories-title" lang="en" dir="ltr"><span>ROYA STORIES</span></h2>
            <p>الهام بگیر، انتخاب کن</p>
        </header>

        <div class="hp-stories-rail" role="group" aria-label="داستان‌های ROYA">
            <ul class="hp-stories-track">
                <li v-for="(story, index) in stories" :key="story.id">
                    <button type="button" class="hp-stories-item" @click="openStory(index)">
                        <span class="hp-stories-orb" aria-hidden="true">
                            <span class="hp-stories-orb-inner">
                                <img :src="story.cover" :alt="story.alt" width="168" height="168"
                                    loading="lazy" decoding="async" />
                            </span>
                        </span>
                        <span class="hp-stories-label">{{ story.title }}</span>
                    </button>
                </li>
            </ul>
        </div>

        <p class="hp-sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</p>

        <dialog ref="dialog" class="hp-stories-dialog" :aria-label="`داستان ${activeStory.title}`"
            @cancel.prevent="close" @click="onBackdrop" @keydown="onKey">
            <div class="hp-stories-shell">
                <div class="hp-stories-top">
                    <div class="hp-stories-progress" aria-hidden="true">
                        <span v-for="(video, index) in activeStory.videos" :key="`${activeStory.id}-${index}`"
                            :class="{ 'is-done': index < activeVideoIndex, 'is-active': index === activeVideoIndex }">
                            <i :style="index === activeVideoIndex ? { width: progress + '%' } : null" />
                        </span>
                    </div>
                    <button type="button" class="hp-stories-close" data-autofocus aria-label="بستن داستان"
                        @click="close"><span aria-hidden="true">✕</span></button>
                </div>

                <div class="hp-stories-stage" @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd"
                    @touchcancel="touchStart = null">
                    <button type="button" class="hp-stories-nav hp-stories-nav--previous"
                        style="right: 0; left: auto;" aria-label="ویدیوی بعدی"
                        @click="next"><span aria-hidden="true">←</span></button>

                    <figure class="hp-stories-figure">
                        <video v-if="viewerOpen && !videoFailed" ref="media" :key="mediaKey"
                            class="hp-stories-media" :src="activeVideo.src" :poster="activeStory.poster"
                            :style="{ aspectRatio: mediaRatio }" controls autoplay playsinline preload="auto"
                            @timeupdate="onTimeUpdate" @loadedmetadata="onLoadedMetadata" @ended="next"
                            @error="videoFailed = true"></video>
                        <img v-else class="hp-stories-media hp-stories-media--cover" :src="activeStory.poster"
                            :alt="activeStory.alt" width="640" height="1138" decoding="async" />
                        <figcaption class="hp-stories-figure-caption">
                            <span class="hp-stories-figure-title">{{ activeStory.title }}</span>
                            <span class="hp-stories-figure-count">{{ position }}</span>
                        </figcaption>
                        <p v-if="videoFailed" class="hp-stories-unavailable">ویدیوی این داستان در دسترس نیست</p>
                    </figure>

                    <button type="button" class="hp-stories-nav hp-stories-nav--next"
                        style="left: 0; right: auto;" aria-label="ویدیوی قبلی"
                        @click="previous"><span aria-hidden="true">→</span></button>

                    <div class="hp-stories-taps" aria-hidden="true">
                        <button type="button" class="hp-stories-tap" tabindex="-1" @click="tapZone('previous')" />
                        <button type="button" class="hp-stories-tap" tabindex="-1" @click="tapZone('next')" />
                    </div>
                </div>

                <p class="hp-stories-credit" lang="en" dir="ltr">{{ activeStory.credit }} <span aria-hidden="true">·</span>
                    {{ activeStory.film }}</p>
            </div>
        </dialog>
    </section>
</template>
