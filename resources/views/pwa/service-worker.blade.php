/*
 * Service worker de Lomé IA School (version {{ $version }}).
 *
 * RÈGLE DE CONFIDENTIALITÉ : seuls les fichiers publics et immuables (CSS/JS compilés, icônes) peuvent
 * être mis en cache. Jamais une page, un message, un fichier de groupe ni une réponse de l'espace membre :
 * ces requêtes ne passent pas par le cache et vont toujours au serveur.
 */
const CACHE_NAME = 'lomeia-static-{{ $version }}';
const CACHEABLE_PREFIXES = ['/build/assets/', '/icons/'];

function isCacheable(request) {
    if (request.method !== 'GET' || request.mode === 'navigate') {
        return false;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || url.search !== '') {
        return false;
    }

    return CACHEABLE_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));
}

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    // Tout le reste (pages, messages, fichiers privés) : aucune intervention, aucun cache.
    if (!isCacheable(event.request)) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(event.request);

            if (cached) {
                return cached;
            }

            const response = await fetch(event.request);
            const cacheControl = response.headers.get('Cache-Control') || '';

            if (response.ok && response.type === 'basic' && !cacheControl.includes('no-store') && !cacheControl.includes('private')) {
                cache.put(event.request, response.clone());
            }

            return response;
        }),
    );
});

// Notification sur téléphone : nouveau message dans un groupe.
self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Lomé IA School', {
            body: data.body || 'Nouveau message dans votre groupe.',
            icon: '/icons/icon-192.png',
            badge: '/icons/badge-96.png',
            tag: data.tag || 'lomeia',
            renotify: true,
            data: { url: data.url || '/mon-espace/groupes' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/mon-espace/groupes', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const existing = windows.find((client) => client.url === target);

            if (existing) {
                return existing.focus();
            }

            return self.clients.openWindow(target);
        }),
    );
});
