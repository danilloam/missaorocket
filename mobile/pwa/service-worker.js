const CACHE_NAME = 'arena-v1';

const urlsToCache = [

'/mobile/',

'/mobile/assets/css/theme.css'

];

self.addEventListener('install', event => {

event.waitUntil(

caches.open(CACHE_NAME)
.then(cache => {

return cache.addAll(urlsToCache);

})

);

});

self.addEventListener('fetch', event => {

event.respondWith(

caches.match(event.request)
.then(response => {

return response || fetch(event.request);

})

);

});