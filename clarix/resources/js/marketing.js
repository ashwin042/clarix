// Standalone Alpine bundle for the public marketing pages.
//
// This deliberately does NOT live in app.js: authenticated pages load
// @livewireScripts, which already ships its own Alpine instance, and a second
// copy on the same page makes Alpine throw. Marketing pages have no Livewire,
// so they get Alpine from here instead.
import Alpine from 'alpinejs';

/**
 * Draggable polaroid stack.
 *
 * The cards keep a fixed DOM order and are positioned purely from where they
 * sit in `order` — swapping DOM nodes around instead would restart every CSS
 * transition mid-flight. order[0] is the front card and the only draggable
 * one; throwing it past the threshold sends it off-screen, then it silently
 * rejoins the back of the pile while the rest animate forward.
 */
Alpine.data('photoStack', (count = 4) => ({
    order: Array.from({ length: count }, (_, i) => i),

    // Per-depth rotations, so the pile reads as loose rather than as a deck.
    // The front card stays nearly square to the page — it is the one being
    // read, and its caption looks crooked at anything steeper.
    tilt: [-1.2, 3.4, -3.8, 5.2],

    dragX: 0,
    dragging: false,
    startX: 0,
    // Index of the card that has just wrapped to the back and must jump
    // there without animating; everything else keeps its transition.
    silent: null,
    // How far a throw has to travel before it counts as a throw.
    threshold: 88,

    depth(i) {
        return this.order.indexOf(i);
    },

    isFront(i) {
        return this.order[0] === i;
    },

    style(i) {
        const d = this.depth(i);
        const front = this.isFront(i);
        const drag = front ? this.dragX : 0;
        const live = this.silent === i || (this.dragging && front);

        return {
            transform: `translate3d(${d * 15 + drag}px, ${d * -13}px, 0)`
                + ` rotate(${this.tilt[d % this.tilt.length] + drag / 18}deg)`
                + ` scale(${1 - d * 0.045})`,
            zIndex: String(this.order.length - d),
            opacity: String(Math.max(0, 1 - Math.abs(drag) / 620)),
            // Suppressed while a finger is on the card and for the single
            // frame a thrown card takes to reappear at the back.
            transition: live
                ? 'none'
                : 'transform .44s cubic-bezier(.2,.8,.25,1), opacity .44s ease',
        };
    },

    start(event, i) {
        if (!this.isFront(i) || this.silent !== null) return;
        this.dragging = true;
        this.startX = event.clientX;
        this.dragX = 0;
    },

    move(event) {
        if (!this.dragging) return;
        this.dragX = event.clientX - this.startX;
    },

    end() {
        if (!this.dragging) return;
        this.dragging = false;

        if (Math.abs(this.dragX) > this.threshold) this.advance(Math.sign(this.dragX));
        else this.dragX = 0;
    },

    /** Throw the front card out to `dir` (-1 left, 1 right) and cycle. */
    advance(dir = 1) {
        if (this.silent !== null) return;

        const thrown = this.order[0];
        this.dragX = dir * 620;

        setTimeout(() => {
            this.silent = thrown;
            this.order.push(this.order.shift());
            this.dragX = 0;

            // Two frames: one for Alpine to write transition:none and the new
            // position, a second before transitions are allowed back on.
            requestAnimationFrame(() => requestAnimationFrame(() => {
                this.silent = null;
            }));
        }, 360);
    },
}));

window.Alpine = Alpine;

Alpine.start();
