import { registerConnectivityStore } from './stores/connectivity';
import { registerPrivateCache } from './pwa/private-cache';

registerConnectivityStore();
registerPrivateCache();

window.liveReader = (sessions, timezone = 'UTC') => ({
    sessions,
    timezone,
    index: 0,
    now: Date.now(),
    timer: null,

    start() {
        this.destroy();
        this.index = this.initialIndex();
        this.timer = window.setInterval(() => {
            this.now = Date.now();
        }, 30000);
    },

    destroy() {
        if (this.timer !== null) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    },

    get session() {
        return this.sessions[this.index] ?? null;
    },

    get stateLabel() {
        if (!this.session) {
            return '';
        }

        const startsAt = Date.parse(this.session.startsAt);
        const endsAt = Date.parse(this.session.endsAt);

        if (this.now < startsAt) {
            return 'Upcoming';
        }

        return this.now >= endsAt ? 'Past' : 'Current';
    },

    get timeRange() {
        if (!this.session) {
            return '';
        }

        return `${this.formatTime(this.session.startsAt)}–${this.formatTime(this.session.endsAt)}`;
    },

    initialIndex() {
        const currentIndex = this.sessions.findIndex((session) => this.now >= Date.parse(session.startsAt) && this.now < Date.parse(session.endsAt));

        if (currentIndex !== -1) {
            return currentIndex;
        }

        const upcomingIndex = this.sessions.findIndex((session) => this.now < Date.parse(session.startsAt));

        return upcomingIndex === -1 ? Math.max(this.sessions.length - 1, 0) : upcomingIndex;
    },

    previous() {
        this.index = Math.max(this.index - 1, 0);
    },

    next() {
        this.index = Math.min(this.index + 1, this.sessions.length - 1);
    },

    formatTime(value) {
        return new Intl.DateTimeFormat(undefined, { hour: '2-digit', hourCycle: 'h23', minute: '2-digit', timeZone: this.timezone }).format(new Date(value));
    },

    formatDate(value) {
        return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', hour: '2-digit', hourCycle: 'h23', minute: '2-digit', timeZone: this.timezone }).format(new Date(value));
    },
});

window.toastCenter = (initialToasts = []) => ({
    toasts: initialToasts.map((toast, index) => ({ ...toast, id: toast.id ?? `flash-${index}` })),
    nextId: 0,
    timers: {},
    pointerStart: null,

    init() {
        this.toasts.forEach((toast) => this.scheduleDismissal(toast));
    },

    push(detail) {
        const type = ['success', 'error', 'info'].includes(detail?.type) ? detail.type : 'info';
        const message = typeof detail?.message === 'string' ? detail.message : '';

        if (message === '') {
            return;
        }

        const toast = { id: `toast-${this.nextId++}`, type, message };
        this.toasts.unshift(toast);
        this.toasts = this.toasts.slice(0, 5);
        this.scheduleDismissal(toast);
    },

    scheduleDismissal(toast) {
        this.timers[toast.id] = window.setTimeout(() => this.dismiss(toast.id), toast.type === 'error' ? 6000 : 4000);
    },

    dismiss(id) {
        if (this.timers[id]) {
            window.clearTimeout(this.timers[id]);
            delete this.timers[id];
        }

        this.toasts = this.toasts.filter((toast) => toast.id !== id);
    },

    startPointer(event) {
        this.pointerStart = { x: event.clientX, y: event.clientY };
    },

    endPointer(event, id) {
        if (this.pointerStart === null) {
            return;
        }

        const deltaX = event.clientX - this.pointerStart.x;
        const deltaY = event.clientY - this.pointerStart.y;
        this.pointerStart = null;

        if (Math.abs(deltaX) >= 50 && Math.abs(deltaX) > Math.abs(deltaY)) {
            this.dismiss(id);
        }
    },
});
