// Behavioral checks for the homepage carousel decisions and autoplay scheduler.
import assert from 'node:assert/strict';
import { CAROUSEL_INTERVAL, INTERACTION_RESUME_DELAY, FEATURED_MOBILE_QUERY, shouldUseCarousel, createAutoplayController } from '../../resources/js/homepage-carousel.js';

assert.equal(FEATURED_MOBILE_QUERY, '(max-width: 760px)');
assert.ok(CAROUSEL_INTERVAL > 0 && INTERACTION_RESUME_DELAY > CAROUSEL_INTERVAL);
console.log('PASS carousel constants expose the mobile query and a pause longer than the interval');

// A full desktop row still shapes every rail; the featured rail also slides on
// mobile the moment there is more than one piece to browse.
for (const total of [0, 1, 2, 4, 5, 9]) {
    assert.equal(shouldUseCarousel({ featured: false, mobile: false, total }), total > 4, `arrivals desktop ${total}`);
    assert.equal(shouldUseCarousel({ featured: false, mobile: true, total }), total > 4, `arrivals mobile ${total}`);
    assert.equal(shouldUseCarousel({ featured: true, mobile: false, total }), total > 4, `featured desktop ${total}`);
    assert.equal(shouldUseCarousel({ featured: true, mobile: true, total }), total > 1, `featured mobile ${total}`);
}
assert.equal(shouldUseCarousel(), false);
assert.equal(shouldUseCarousel({ featured: true, mobile: true, total: 1 }), false);
assert.equal(shouldUseCarousel({ featured: true, mobile: true, total: 2 }), true);
console.log('PASS carousel decision matrix for arrivals, desktop featured and mobile featured');

// Deterministic fake clock so autoplay scheduling can be observed directly.
function createClock() {
    let time = 0;
    let seq = 0;
    const jobs = new Map();
    const setTimer = (fn, delay = 0) => { const id = ++seq; jobs.set(id, { fn, at: time + delay }); return id; };
    const clearTimer = (id) => { jobs.delete(id); };
    const advance = (ms) => {
        const target = time + ms;
        for (;;) {
            let due = null;
            for (const [id, job] of jobs) if (job.at <= target && (!due || job.at < due.job.at || (job.at === due.job.at && id < due.id))) due = { id, job };
            if (!due) break;
            jobs.delete(due.id);
            time = due.job.at;
            due.job.fn();
        }
        time = target;
    };
    return { setTimer, clearTimer, advance, get pending() { return jobs.size; } };
}
function controller(clock, onAdvance) {
    return createAutoplayController({ onAdvance, interval: 4000, resumeDelay: 6000, setTimer: clock.setTimer, clearTimer: clock.clearTimer });
}

const idleClock = createClock();
let idle = 0;
const idleCtl = controller(idleClock, () => { idle++; });
idleClock.advance(60000);
assert.equal(idle, 0, 'a disabled controller never advances');
assert.equal(idleCtl.scheduled, false);

const runClock = createClock();
let ticks = 0;
const runner = controller(runClock, () => { ticks++; });
runner.setEnabled(true);
assert.equal(runner.scheduled, true);
runClock.advance(3999); assert.equal(ticks, 0);
runClock.advance(1); assert.equal(ticks, 1);
runClock.advance(4000); assert.equal(ticks, 2);
runClock.advance(12000); assert.equal(ticks, 5);
runner.setEnabled(false);
assert.equal(runner.scheduled, false);
runClock.advance(20000); assert.equal(ticks, 5, 'disabling stops the loop');

const pauseClock = createClock();
let paused = 0;
const pauser = controller(pauseClock, () => { paused++; });
pauser.setEnabled(true);
pauseClock.advance(4000); assert.equal(paused, 1);
pauser.pauseForInteraction();
assert.equal(pauser.scheduled, false, 'interaction clears the pending tick');
pauseClock.advance(5999); assert.equal(paused, 1); assert.equal(pauser.scheduled, false);
pauseClock.advance(1); assert.equal(pauser.scheduled, true, 'the loop resumes after the delay');
assert.equal(paused, 1);
pauseClock.advance(4000); assert.equal(paused, 2);

const repeatClock = createClock();
let repeated = 0;
const repeater = controller(repeatClock, () => { repeated++; });
repeater.setEnabled(true);
repeater.pauseForInteraction();
repeatClock.advance(3000);
repeater.pauseForInteraction();
repeatClock.advance(5000); assert.equal(repeater.scheduled, false, 'each interaction restarts the delay');
repeatClock.advance(1000); assert.equal(repeater.scheduled, true);
repeatClock.advance(4000); assert.equal(repeated, 1);

const disposeClock = createClock();
let disposed = 0;
const disposable = controller(disposeClock, () => { disposed++; });
disposable.setEnabled(true);
disposeClock.advance(4000); assert.equal(disposed, 1);
disposable.pauseForInteraction();
disposable.dispose();
assert.equal(disposable.scheduled, false);
disposeClock.advance(60000); assert.equal(disposed, 1, 'a disposed controller stays stopped');
console.log('PASS autoplay starts, repeats, pauses on interaction, resumes, restarts the delay and disposes');
console.log('3 verification groups passed (live scroll behavior remains verified in the browser).');
