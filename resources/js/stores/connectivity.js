export function registerConnectivityStore() {
    let registered = false;

    const register = () => {
        if (registered) {
            return;
        }

        registered = true;

        window.Alpine.store('connectivity', {
            online: navigator.onLine,

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
        });
    };

    if (window.Alpine) {
        register();

        return;
    }

    document.addEventListener('alpine:init', register, { once: true });
}
