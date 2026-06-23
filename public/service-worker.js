const CACHE_NAME = 'learn-quiz-cache-v1';
const ASSETS_TO_CACHE = [
    '/learner/login',
    '/manifest.json',
    '/icons/icon-512x512.jpg'
];

// Installation du Service Worker : mise en cache des ressources critiques
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[Service Worker] Pré-mise en cache des ressources');
                return cache.addAll(ASSETS_TO_CACHE);
            })
            .then(() => self.skipWaiting())
    );
});

// Activation du Service Worker : nettoyage des anciens caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.map(key => {
                    if (key !== CACHE_NAME) {
                        console.log('[Service Worker] Suppression de l\'ancien cache', key);
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Interception des requêtes
self.addEventListener('fetch', event => {
    const request = event.request;

    // Ne pas intercepter les requêtes non-GET ou de l'administration (cores)
    if (request.method !== 'GET' || request.url.includes('/cores/') || request.url.includes('/admin/')) {
        return;
    }

    event.respondWith(
        caches.match(request).then(cachedResponse => {
            if (cachedResponse) {
                // Pour les assets statiques (CSS, JS, images, polices), servir directement le cache
                if (
                    request.url.includes('/build/') || 
                    request.url.includes('/plugins/') || 
                    request.url.includes('/fonts.googleapis.com/') ||
                    request.url.includes('/icons/')
                ) {
                    return cachedResponse;
                }
            }

            // Pour les autres requêtes (pages HTML, API), tenter le réseau d'abord
            return fetch(request)
                .then(networkResponse => {
                    // Mettre en cache la nouvelle réponse récupérée
                    if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(request, responseToCache);
                        });
                    }
                    return networkResponse;
                })
                .catch(() => {
                    // Hors-ligne : retourner la réponse du cache si disponible
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Si la page demandée est une page HTML de l'apprenant, retourner la page de login ou dashboard du cache
                    if (request.headers.get('accept').includes('text/html')) {
                        return caches.match('/learner/login');
                    }
                });
        })
    );
});
