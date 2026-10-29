<script setup>
import SocialIcon from '../social/SocialIcon.vue';
import { SOCIAL_NETWORKS } from '../../social-links.js';

// Networks without a real configured URL render as a clearly-disabled
// «به‌زودی» card instead of pointing visitors at a fake destination.
const networks = SOCIAL_NETWORKS;
</script>

<template>
    <section class="hp-social" aria-labelledby="hp-social-title">
        <div class="hp-social-inner">
            <header class="hp-social-head">
                <p class="hp-social-kicker" lang="en" dir="ltr">STAY CONNECTED</p>
                <h2 id="hp-social-title" class="hp-social-heading">دنیای رویا، یک قدم نزدیک‌تر</h2>
                <p class="hp-social-intro">برای دیدن جدیدترین استایل‌ها، آشنایی با محصولات تازه و ارتباط آسان‌تر با رویا گالری، در شبکه‌های اجتماعی همراه ما باشید.</p>
            </header>

            <ul class="hp-social-grid" role="list">
                <li v-for="network in networks" :key="network.key">
                    <a
                        v-if="network.url"
                        :href="network.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="hp-social-card"
                        :aria-label="`${network.action} — ${network.label} رویا گالری`"
                    >
                        <span class="hp-social-icon"><SocialIcon :network="network.key" :size="24" /></span>
                        <span class="hp-social-body">
                            <span class="hp-social-name">{{ network.label }}</span>
                            <span class="hp-social-desc">{{ network.description }}</span>
                        </span>
                        <span class="hp-social-action">
                            {{ network.action }}
                            <span class="hp-social-arrow" aria-hidden="true">←</span>
                        </span>
                    </a>
                    <article v-else class="hp-social-card hp-social-card--soon" :aria-label="`${network.label} رویا گالری — به‌زودی`">
                        <span class="hp-social-icon"><SocialIcon :network="network.key" :size="24" /></span>
                        <span class="hp-social-body">
                            <span class="hp-social-name">{{ network.label }}</span>
                            <span class="hp-social-desc">{{ network.description }}</span>
                        </span>
                        <span class="hp-social-action hp-social-action--soon">به‌زودی</span>
                    </article>
                </li>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.hp-social {
    background: var(--sf-surface-soft, #f5f4f2);
    padding-block: clamp(48px, 6vw, 88px);
}

.hp-social-inner {
    width: min(1400px, calc(100% - 2 * clamp(16px, 3.2vw, 48px)));
    margin-inline: auto;
}

.hp-social-head {
    max-width: 640px;
    margin-bottom: clamp(24px, 3vw, 40px);
}

.hp-social-kicker {
    margin: 0 0 10px;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    color: var(--sf-champagne, #8a8272);
}

.hp-social-heading {
    margin: 0 0 12px;
    font-size: clamp(24px, 3vw, 40px);
    font-weight: 300;
    line-height: 1.4;
    color: var(--sf-text, #111111);
}

.hp-social-intro {
    margin: 0;
    font-size: 0.9375rem;
    line-height: 2;
    color: var(--sf-text-secondary, #5a5a5a);
    max-width: 62ch;
}

.hp-social-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: clamp(14px, 1.8vw, 24px);
    margin: 0;
    padding: 0;
    list-style: none;
}

.hp-social-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 44px;
    padding: clamp(16px, 2vw, 22px);
    background: var(--sf-surface, #ffffff);
    border: 1px solid var(--sf-border, #e4e3e0);
    color: var(--sf-text, #111111);
    text-decoration: none;
    transition: border-color 200ms, background 200ms, transform 200ms;
}

.hp-social-card:hover {
    border-color: var(--sf-border-strong, #c9c8c4);
    background: var(--sf-surface, #ffffff);
    transform: translateY(-2px);
}

.hp-social-card:focus-visible {
    outline: 2px solid var(--sf-focus, #111111);
    outline-offset: 2px;
}

.hp-social-card--soon {
    cursor: default;
    color: var(--sf-text-muted, #737373);
}

.hp-social-card--soon:hover {
    border-color: var(--sf-border, #e4e3e0);
    transform: none;
}

.hp-social-icon {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border: 1px solid var(--sf-border, #e4e3e0);
    background: var(--sf-surface-soft, #f5f4f2);
    color: var(--sf-text, #111111);
    transition: color 200ms, border-color 200ms, background 200ms;
}

.hp-social-card:not(.hp-social-card--soon):hover .hp-social-icon {
    border-color: var(--sf-text, #111111);
    background: var(--sf-text, #111111);
    color: var(--sf-text-inverse, #ffffff);
}

.hp-social-body {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    flex: 1;
}

.hp-social-name {
    font-size: 0.9375rem;
    font-weight: 500;
    line-height: 1.5;
}

.hp-social-desc {
    font-size: 0.75rem;
    line-height: 1.8;
    color: var(--sf-text-muted, #737373);
}

.hp-social-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--sf-text, #111111);
    border-bottom: 1px solid var(--sf-border-strong, #c9c8c4);
    padding-bottom: 2px;
    transition: border-color 200ms, gap 200ms;
}

.hp-social-card:hover .hp-social-action {
    border-color: var(--sf-text, #111111);
    gap: 12px;
}

.hp-social-action--soon {
    color: var(--sf-text-muted, #737373);
    border-bottom-style: dashed;
}

.hp-social-card--soon:hover .hp-social-action {
    border-color: var(--sf-border-strong, #c9c8c4);
    gap: 8px;
}

@media (max-width: 960px) {
    .hp-social-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .hp-social-action {
        padding: 8px 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hp-social-card,
    .hp-social-icon,
    .hp-social-action {
        transition: none;
        transform: none;
    }
}
</style>
