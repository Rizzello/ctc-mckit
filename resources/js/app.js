window.liveReader = (sessions) => ({
    sessions,
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
        return new Intl.DateTimeFormat(undefined, { hour: '2-digit', hourCycle: 'h23', minute: '2-digit' }).format(new Date(value));
    },

    formatDate(value) {
        return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', hour: '2-digit', hourCycle: 'h23', minute: '2-digit' }).format(new Date(value));
    },
});
