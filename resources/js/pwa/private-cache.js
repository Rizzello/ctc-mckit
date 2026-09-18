function pwaStore() {
    return window.Alpine?.store('pwa');
}

function serviceWorkerTarget(registration) {
    return navigator.serviceWorker.controller ?? registration.active;
}

function messageServiceWorker(registration, message) {
    const target = serviceWorkerTarget(registration);

    if (!target) {
        return Promise.reject(new Error('Service worker is not active.'));
    }

    return new Promise((resolve, reject) => {
        const channel = new MessageChannel();
        const timeout = window.setTimeout(() => reject(new Error('Service worker did not respond.')), 5000);

        channel.port1.onmessage = ({ data }) => {
            window.clearTimeout(timeout);
            resolve(data);
        };

        target.postMessage(message, [channel.port2]);
    });
}

async function manifestUrls() {
    const response = await fetch('/offline/manifest', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('Offline manifest request failed.');
    }

    const manifest = await response.json();

    if (!Array.isArray(manifest?.urls) || !manifest.urls.every((url) => typeof url === 'string')) {
        throw new Error('Offline manifest is invalid.');
    }

    return manifest.urls;
}

function assetUrls() {
    return [...document.querySelectorAll('link[rel="stylesheet"][href], script[src]')]
        .map((element) => new URL(element.getAttribute('href') ?? element.getAttribute('src'), window.location.origin))
        .filter((url) => url.origin === window.location.origin && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/livewire/')))
        .map((url) => url.toString());
}

async function warmSnapshot(registration, namespace) {
    if (namespace === '' || pwaStore()?.online !== true) {
        return;
    }

    const store = pwaStore();
    store?.prepare();

    try {
        const urls = await manifestUrls();
        const result = await messageServiceWorker(registration, {
            type: 'WARM_PRIVATE_SNAPSHOT',
            namespace,
            urls,
            assets: assetUrls(),
        });

        if (result?.ready !== true) {
            throw new Error('Offline snapshot preparation failed.');
        }

        store?.prepared();
    } catch {
        store?.failed(store.ready);
    }
}

async function clearPrivateCaches(registration) {
    try {
        await messageServiceWorker(registration, { type: 'CLEAR_PRIVATE_CACHES' });
    } catch {
        // The server-side logout must still complete when local cache cleanup fails.
    }
}

export function registerPrivateCache() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const initialize = () => {
        const namespace = document.body.dataset.privateCacheNamespace ?? '';

        navigator.serviceWorker.register('/service-worker.js')
            .then(async (registration) => {
                await navigator.serviceWorker.ready;

                if (namespace === '') {
                    return;
                }

                const state = await messageServiceWorker(registration, {
                    type: 'SET_PRIVATE_CACHE_NAMESPACE',
                    namespace,
                });

                const store = pwaStore();
                store.ready = state?.ready === true;

                await warmSnapshot(registration, namespace);
            })
            .catch(() => pwaStore()?.failed());

        navigator.serviceWorker.addEventListener('message', ({ data }) => {
            if (data?.type === 'PWA_SNAPSHOT_READY') {
                pwaStore()?.prepared();
            }

            if (data?.type === 'PWA_SNAPSHOT_FAILED') {
                pwaStore()?.failed(data.hasExistingSnapshot === true);
            }
        });

        document.addEventListener('pwa:retry', () => {
            navigator.serviceWorker.ready.then((registration) => warmSnapshot(registration, namespace));
        });

        document.addEventListener('submit', async (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || !form.matches('[data-private-cache-logout]') || form.dataset.cacheCleanup === 'running') {
                return;
            }

            event.preventDefault();
            form.dataset.cacheCleanup = 'running';

            await Promise.race([
                navigator.serviceWorker.ready.then((registration) => clearPrivateCaches(registration)),
                new Promise((resolve) => window.setTimeout(resolve, 500)),
            ]);

            form.submit();
        });
    };

    if (pwaStore()) {
        initialize();

        return;
    }

    document.addEventListener('mckit:pwa-store-ready', initialize, { once: true });
}
