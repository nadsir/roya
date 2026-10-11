// Shared carousel decisions and autoplay scheduling for the homepage product rails.
export const CAROUSEL_INTERVAL = 4000;
export const INTERACTION_RESUME_DELAY = 6000;
export const FEATURED_MOBILE_QUERY = '(max-width: 760px)';
export const FULL_ROW_CARDS = 4;

// The featured rail slides on mobile for any multi-item selection so every
// curated piece stays reachable; every other rail only slides once the
// selection no longer fits a single desktop row.
export function shouldUseCarousel({ featured = false, mobile = false, total = 0 } = {}) {
    const threshold = featured && mobile ? 1 : FULL_ROW_CARDS;
    return total > threshold;
}

// Recursive-timeout autoplay: `setEnabled` reflects viewport/reduced-motion,
// `pauseForInteraction` suspends while the visitor browses then resumes, and
// `dispose` stops everything on unmount. Timers are injectable for tests.
export function createAutoplayController({ onAdvance, interval = CAROUSEL_INTERVAL, resumeDelay = INTERACTION_RESUME_DELAY, setTimer = globalThis.setTimeout, clearTimer = globalThis.clearTimeout } = {}) {
    let timer = null;
    let resume = null;
    let enabled = false;
    let suspended = false;
    let destroyed = false;

    function clearTick() {
        if (timer !== null) { clearTimer(timer); timer = null; }
    }
    function clearResume() {
        if (resume !== null) { clearTimer(resume); resume = null; }
    }
    function schedule() {
        clearTick();
        if (destroyed || !enabled || suspended) return;
        timer = setTimer(() => { timer = null; onAdvance?.(); schedule(); }, interval);
    }
    function setEnabled(value) {
        enabled = Boolean(value);
        clearResume();
        suspended = false;
        if (enabled) schedule(); else clearTick();
    }
    function pauseForInteraction() {
        if (destroyed) return;
        suspended = true;
        clearTick();
        clearResume();
        resume = setTimer(() => { resume = null; suspended = false; schedule(); }, resumeDelay);
    }
    function dispose() {
        destroyed = true;
        enabled = false;
        clearTick();
        clearResume();
    }
    return { setEnabled, pauseForInteraction, dispose, get scheduled() { return timer !== null; } };
}
