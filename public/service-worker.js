const VERSION = 'v1';
const STATIC_CACHE = `mckit-static-${VERSION}`;
const CONTROL_CACHE = `mckit-control-${VERSION}`;
const PRIVATE_PREFIX = 'mckit-private-';
const NAMESPACE_KEY = new Request('/__mckit/private-cache-namespace');

let privateNamespace = null;

const isAuthenticationRequest = (url) => url.pathname === '/login'
    || url.pathname.startsWith('/login/')
    || url.searchParams.has('token')
    || url.searchParams.has('signature');

const privateCacheName = () => privateNamespace === null ? null : `${PRIVATE_PREFIX}${privateNamespace}-${VERSION}`;

async function restoreNamespace() {
    if (privateNamespace !== null) {
        return privateNamespace;
    }

    const response = await caches.open(CONTROL_CACHE).then((cache) => cache.match(NAMESPACE_KEY));
    privateNamespace = response ? await response.text() : null;

    return privateNamespace;
}

async function setNamespace(namespace) {
    privateNamespace = namespace;
    const control = await caches.open(CONTROL_CACHE);
    await control.put(NAMESPACE_KEY, new Response(namespace));

    const keys = await caches.keys();
    await Promise.all(keys
        .filter((key) => key.startsWith(PRIVATE_PREFIX) && key !== privateCacheName())
        .map((key) => caches.delete(key)));
}

async function clearPrivateCaches() {
    privateNamespace = null;
    const keys = await caches.keys();
    await Promise.all(keys
        .filter((key) => key.startsWith(PRIVATE_PREFIX) || key === CONTROL_CACHE)
        .map((key) => caches.delete(key)));
}

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.add('/offline.html')));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys
        .filter((key) => key.startsWith('mckit-static-') && key !== STATIC_CACHE)
        .map((key) => caches.delete(key)))));
    self.clients.claim();
});

self.addEventListener('message', (event) => {
    const data = event.data;

    if (!data || typeof data !== 'object') {
        return;
    }

    if (data.type === 'SET_PRIVATE_CACHE_NAMESPACE' && typeof data.namespace === 'string' && /^[A-Za-z0-9]{32,}$/.test(data.namespace)) {
        event.waitUntil(setNamespace(data.namespace));
    }

    if (data.type === 'CLEAR_PRIVATE_CACHES') {
        event.waitUntil(clearPrivateCaches());
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/livewire')) {
        return;
    }

    if (request.destination === 'script' || request.destination === 'style' || request.destination === 'font' || url.pathname.startsWith('/build/')) {
        event.respondWith(caches.open(STATIC_CACHE).then(async (cache) => {
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);

            if (response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        }));

        return;
    }

    if (request.mode !== 'navigate' || isAuthenticationRequest(url)) {
        return;
    }

    event.respondWith((async () => {
        const namespace = await restoreNamespace();

        if (namespace === null) {
            return fetch(request);
        }

        const cache = await caches.open(privateCacheName());

        try {
            const response = await fetch(request);

            if (response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        } catch {
            return (await cache.match(request)) ?? (await caches.match('/offline.html'));
        }
    })());
});
