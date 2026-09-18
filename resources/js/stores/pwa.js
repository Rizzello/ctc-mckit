export function registerPwaStore() {
    let registered = false;

    const register = () => {
        if (registered) {
            return;
        }

        registered = true;

        window.Alpine.store('pwa', {
            online: navigator.onLine,
            warming: false,
            ready: false,
            error: false,

            init() {
                window.addEventListener('online', () => {
                    this.online = true;
                });

                window.addEventListener('offline', () => {
                    this.online = false;
                });
            },

            get offline() {
                return !this.online;
            },

            prepare() {
                this.warming = true;
                this.error = false;
            },

            prepared() {
                this.warming = false;
                this.ready = true;
                this.error = false;
            },

            failed(hasExistingSnapshot = false) {
                this.warming = false;
                this.ready = hasExistingSnapshot;
                this.error = true;
            },
        });

        document.dispatchEvent(new Event('mckit:pwa-store-ready'));
    };

    if (window.Alpine) {
        register();

        return;
    }

    document.addEventListener('alpine:init', register, { once: true });
}
