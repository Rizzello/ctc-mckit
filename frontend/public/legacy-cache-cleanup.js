self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) =>
        Promise.all(
          cacheNames
            .filter(
              (cacheName) =>
                cacheName.startsWith('mckit-private-') ||
                cacheName.startsWith('mckit-pwa-meta-') ||
                cacheName.startsWith('mckit-static-'),
            )
            .map((cacheName) => caches.delete(cacheName)),
        ),
      ),
  );
});
