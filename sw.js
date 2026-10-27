/**
 * Service worker de DIAZ Motoservicio.
 *
 * Las páginas llevan datos en vivo y sesión, así que nunca se sirven de caché:
 * se piden siempre a la red. Solo se guardan los recursos estáticos (iconos,
 * tipografías, CDN) para que la app abra rápido y, si se cae la conexión,
 * muestre una pantalla propia en vez del error del navegador.
 */

const VERSION    = 'v3';
const CACHE_APP  = `motoservicio-app-${VERSION}`;
const CACHE_CDN  = `motoservicio-cdn-${VERSION}`;
const SIN_RED    = '/offline.html';

const ESTATICOS = [
    SIN_RED,
    '/manifest.json',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
];

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE_APP)
            .then(c => c.addAll(ESTATICOS))
            .then(() => self.skipWaiting())
            .catch(() => self.skipWaiting())
    );
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys()
            .then(nombres => Promise.all(
                nombres
                    .filter(n => n.startsWith('motoservicio-') && !n.endsWith(VERSION))
                    .map(n => caches.delete(n))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    const req = e.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Recursos de terceros (Tailwind, Font Awesome, Inter): caché primero
    if (url.origin !== location.origin) {
        e.respondWith(
            caches.match(req).then(hit => hit || fetch(req).then(res => {
                if (res.ok || res.type === 'opaque') {
                    const copia = res.clone();
                    caches.open(CACHE_CDN).then(c => c.put(req, copia));
                }
                return res;
            }).catch(() => hit))
        );
        return;
    }

    // Imágenes y estáticos propios: caché primero
    if (/\.(png|jpe?g|svg|webp|ico|woff2?)$/i.test(url.pathname)) {
        e.respondWith(
            caches.match(req).then(hit => hit || fetch(req).then(res => {
                if (res.ok) {
                    const copia = res.clone();
                    caches.open(CACHE_APP).then(c => c.put(req, copia));
                }
                return res;
            }))
        );
        return;
    }

    // Páginas: siempre a la red; sin conexión, pantalla propia
    e.respondWith(
        fetch(req).catch(() =>
            caches.match(SIN_RED).then(hit => hit || new Response(
                'Sin conexión', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } }
            ))
        )
    );
});

// Permite actualizar sin esperar a cerrar todas las pestañas
self.addEventListener('message', e => {
    if (e.data === 'actualizar') self.skipWaiting();
});
