const PRIVATE_CACHE_PREFIX = 'mckit-private-';
const CONTROL_CACHE_PREFIX = 'mckit-control-';

function sendToServiceWorker(message) {
    if (navigator.serviceWorker?.controller) {
        navigator.serviceWorker.controller.postMessage(message);
    }
}

async function clearPrivateCaches() {
    sendToServiceWorker({ type: 'CLEAR_PRIVATE_CACHES' });

    if (!('caches' in window)) {
        return;
    }

    const keys = await caches.keys();
    await Promise.all(keys
        .filter((key) => key.startsWith(PRIVATE_CACHE_PREFIX) || key.startsWith(CONTROL_CACHE_PREFIX))
        .map((key) => caches.delete(key)));
}

function setPrivateCacheNamespace(namespace) {
    if (namespace !== '') {
        sendToServiceWorker({ type: 'SET_PRIVATE_CACHE_NAMESPACE', namespace });
    }
}

export function registerPrivateCache() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const namespace = document.body.dataset.privateCacheNamespace ?? '';

    navigator.serviceWorker.register('/service-worker.js')
        .then(async (registration) => {
            await navigator.serviceWorker.ready;
            setPrivateCacheNamespace(namespace);

            registration.addEventListener('updatefound', () => {
                registration.installing?.addEventListener('statechange', () => setPrivateCacheNamespace(namespace));
            });
        })
        .catch(() => {});

    navigator.serviceWorker.addEventListener('controllerchange', () => setPrivateCacheNamespace(namespace));

    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-private-cache-logout]') || form.dataset.cacheCleanup === 'running') {
            return;
        }

        event.preventDefault();
        form.dataset.cacheCleanup = 'running';

        try {
            await Promise.race([
                clearPrivateCaches(),
                new Promise((resolve) => window.setTimeout(resolve, 500)),
            ]);
        } catch {
            // The server-side logout must still complete when local cache cleanup fails.
        }

        form.submit();
    });
}
