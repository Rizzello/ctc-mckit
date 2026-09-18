const VERSION = 'v2';
const STATIC_CACHE = `mckit-static-${VERSION}`;
const METADATA_CACHE = `mckit-pwa-meta-${VERSION}`;
const PRIVATE_CACHE_PREFIX = 'mckit-private-';
const METADATA_KEY = new Request('/__mckit/pwa-snapshot');

const isAuthenticationRequest = (url) => url.pathname === '/login'
    || url.pathname.startsWith('/login/')
    || url.pathname === '/logout'
    || url.searchParams.has('token')
    || url.searchParams.has('signature');

const isEligiblePath = (url) => url.pathname === '/agenda'
    || url.pathname === '/sessions'
    || url.pathname === '/live'
    || /^\/sessions\/[^/]+$/.test(url.pathname);

const isStaticPath = (url) => url.pathname.startsWith('/build/')
    || url.pathname.startsWith('/icons/')
    || url.pathname.startsWith('/livewire/');

const isStaticAsset = (request, url) => request.destination === 'script'
    || request.destination === 'style'
    || request.destination === 'font'
    || request.destination === 'image'
    || isStaticPath(url);

const isLivewireNavigate = (request) => request.headers.get('X-Livewire-Navigate') === '1';

const isCacheablePageRequest = (request, url) => request.method === 'GET'
    && url.origin === self.location.origin
    && !isAuthenticationRequest(url)
    && isEligiblePath(url)
    && (request.mode === 'navigate' || isLivewireNavigate(request));

const canonicalRequest = (url) => {
    const canonicalUrl = new URL(url);
    canonicalUrl.hash = '';

    return new Request(canonicalUrl.toString(), { method: 'GET' });
};

async function snapshotMetadata() {
    const response = await caches.open(METADATA_CACHE).then((cache) => cache.match(METADATA_KEY));

    if (!response) {
        return null;
    }

    try {
        const metadata = await response.json();

        if (typeof metadata?.namespace !== 'string' || typeof metadata?.cacheName !== 'string') {
            return null;
        }

        return metadata;
    } catch {
        return null;
    }
}

async function storeMetadata(metadata) {
    const cache = await caches.open(METADATA_CACHE);
    await cache.put(METADATA_KEY, new Response(JSON.stringify(metadata), {
        headers: { 'Content-Type': 'application/json' },
    }));
}

async function deletePrivateCaches(except = null) {
    const keys = await caches.keys();
    await Promise.all(keys
        .filter((key) => key.startsWith(PRIVATE_CACHE_PREFIX) && key !== except)
        .map((key) => caches.delete(key)));
}

async function setNamespace(namespace) {
    const previous = await snapshotMetadata();

    if (previous?.namespace !== namespace) {
        await deletePrivateCaches();
        await storeMetadata({ namespace, cacheName: null });

        return { ready: false };
    }

    await deletePrivateCaches(previous.cacheName);

    return { ready: previous.cacheName !== null };
}

async function clearPrivateCaches() {
    await deletePrivateCaches();
    await caches.delete(METADATA_CACHE);
}

function isValidPageResponse(request, response) {
    const contentType = response.headers.get('Content-Type') ?? '';
    const responseUrl = new URL(response.url || request.url);

    return response.ok
        && !response.redirected
        && responseUrl.origin === self.location.origin
        && isEligiblePath(responseUrl)
        && !isAuthenticationRequest(responseUrl)
        && contentType.includes('text/html');
}

async function cachePageResponse(request, response) {
    const metadata = await snapshotMetadata();

    if (!metadata?.cacheName || !isValidPageResponse(request, response)) {
        return;
    }

    const cache = await caches.open(metadata.cacheName);
    await cache.put(canonicalRequest(request.url), response.clone());
}

async function warmSnapshot(namespace, urls) {
    const previous = await snapshotMetadata();

    if (previous?.namespace !== namespace || !Array.isArray(urls) || urls.length === 0) {
        throw new Error('Invalid snapshot request.');
    }

    const cacheName = `${PRIVATE_CACHE_PREFIX}${namespace}-snapshot-${Date.now()}-${VERSION}`;
    const cache = await caches.open(cacheName);

    try {
        for (const value of urls) {
            const url = new URL(value, self.location.origin);

            if (url.origin !== self.location.origin || !isEligiblePath(url) || isAuthenticationRequest(url)) {
                throw new Error('Invalid snapshot URL.');
            }

            const request = canonicalRequest(url);
            const response = await fetch(request, { credentials: 'same-origin' });

            if (!isValidPageResponse(request, response)) {
                throw new Error('Snapshot page request failed.');
            }

            await cache.put(request, response.clone());
        }

        await storeMetadata({ namespace, cacheName });
        await deletePrivateCaches(cacheName);

        return true;
    } catch (error) {
        await caches.delete(cacheName);
        throw error;
    }
}

async function warmStaticAssets(assets) {
    if (!Array.isArray(assets)) {
        return;
    }

    const cache = await caches.open(STATIC_CACHE);

    for (const value of assets) {
        const url = new URL(value, self.location.origin);

        if (url.origin !== self.location.origin || !isStaticPath(url)) {
            throw new Error('Invalid static asset URL.');
        }

        const request = canonicalRequest(url);
        const response = await fetch(request, { credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error('Static asset request failed.');
        }

        await cache.put(request, response.clone());
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.add('/offline.html')));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const metadata = await snapshotMetadata();
        const keys = await caches.keys();

        await Promise.all(keys
            .filter((key) => (key.startsWith('mckit-static-') && key !== STATIC_CACHE)
                || (key.startsWith(PRIVATE_CACHE_PREFIX) && key !== metadata?.cacheName))
            .map((key) => caches.delete(key)));

        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    const data = event.data;
    const reply = (message) => event.ports[0]?.postMessage(message);

    if (!data || typeof data !== 'object') {
        return;
    }

    if (data.type === 'SET_PRIVATE_CACHE_NAMESPACE' && typeof data.namespace === 'string' && /^[A-Za-z0-9]{32,}$/.test(data.namespace)) {
        event.waitUntil(setNamespace(data.namespace)
            .then(reply)
            .catch(() => reply({ ready: false })));
    }

    if (data.type === 'WARM_PRIVATE_SNAPSHOT' && typeof data.namespace === 'string' && Array.isArray(data.urls)) {
        event.waitUntil(warmStaticAssets(data.assets)
            .then(() => warmSnapshot(data.namespace, data.urls))
            .then(() => {
                event.source?.postMessage({ type: 'PWA_SNAPSHOT_READY' });
                reply({ ready: true });
            })
            .catch(async () => {
                const metadata = await snapshotMetadata();
                const hasExistingSnapshot = metadata?.namespace === data.namespace && metadata.cacheName !== null;
                event.source?.postMessage({ type: 'PWA_SNAPSHOT_FAILED', hasExistingSnapshot });
                reply({ ready: false, hasExistingSnapshot });
            }));
    }

    if (data.type === 'CLEAR_PRIVATE_CACHES') {
        event.waitUntil(clearPrivateCaches().then(() => reply({ cleared: true })));
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (isStaticAsset(request, url)) {
        event.respondWith(caches.open(STATIC_CACHE).then(async (cache) => {
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);

            if (response.ok) {
                await cache.put(request, response.clone());
            }

            return response;
        }));

        return;
    }

    if (!isCacheablePageRequest(request, url)) {
        return;
    }

    event.respondWith((async () => {
        try {
            const response = await fetch(request);
            await cachePageResponse(request, response);

            return response;
        } catch {
            const metadata = await snapshotMetadata();
            const cached = metadata?.cacheName
                ? await caches.open(metadata.cacheName).then((cache) => cache.match(canonicalRequest(request.url)))
                : null;

            return cached ?? caches.match('/offline.html');
        }
    })());
});
