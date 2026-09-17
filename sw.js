const CACHE_NAME = 'rocket-v1.0.10'; // Elevado para forçar os celulares a limparem o cache antigo imediatamente

// Recursos estáticos (App Shell) - Apenas o básico global imutável
const STATIC_ASSETS = [
  'manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap'
];

// 1. Instalação: Salva apenas a estrutura estática essencial global
self.addEventListener('install', (event) => {
  self.skipWaiting(); // Força o SW novo a chutar a versão antiga na hora da instalação
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('PWA Arena Rocket: Cache estático configurado.');
      return cache.addAll(STATIC_ASSETS);
    })
  );
});

// 2. Ativação: Limpa de forma agressiva TODOS os caches antigos do celular
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('PWA Arena Rocket: Limpando cache obsoleto antigo:', cache);
            return caches.delete(cache);
          }
        })
      );
    }).then(() => {
      return self.clients.claim(); // Assume o controle imediato das abas abertas
    })
  );
});

// 3. Interceptação Inteligente (Fetch): Impede o bloqueio de códigos no celular
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // CRÍTICO: Ignorar completamente arquivos de validação e rotas dinâmicas em tempo real
  // Estes arquivos NUNCA podem ir para o cache, devem vir sempre 100% limpos da rede
  if (url.pathname.includes('salvar_assinatura.php') || 
      url.pathname.includes('provas.php') || 
      url.pathname.includes('config.php')) {
    return; // Deixa o navegador buscar direto no servidor PHP de forma limpa
  }

  // Estratégia A: Recursos Estáticos (Imagens, CSS, JS globais) -> Cache-First
  if (STATIC_ASSETS.includes(event.request.url) || url.pathname.match(/\.(?:css|js|woff2?|png|jpg|jpeg|svg|json)$/)) {
    event.respondWith(
      caches.match(event.request).then((cachedResponse) => {
        if (cachedResponse) return cachedResponse;
        
        return fetch(event.request).then((networkResponse) => {
          if (networkResponse.status === 200) {
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
          }
          return networkResponse;
        }).catch(() => new Response('Recurso offline', { status: 503 }));
      })
    );
    return;
  }

  // Estratégia B: Páginas PHP (Estrutura Dinâmica) -> Network-First
  // Garante que o celular busque o painel atualizado na rede, usando o cache apenas se estiver sem internet
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (networkResponse.status === 200) {
          const responseClone = networkResponse.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
        }
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request, { ignoreSearch: true }).then((cachedResponse) => {
          if (cachedResponse) return cachedResponse;
          
          return new Response(
            '<h1>Você está desconectado</h1><p>Conecte-se à internet para acessar a Arena Rocket.</p>', 
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
          );
        });
      })
  );
});

// 4. O BLOCO QUE FALTAVA: Escuta comandos de limpeza enviados pelo dashboard.php
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

// ===================================================
// 5. O BLOCO QUE FALTAVA: INFRAESTRUTURA DE PUSH NOTIFICATIONS
// ===================================================
self.addEventListener('push', (event) => {
  let title = '🚀 Missão Rocket';
  let body = 'Nova atualização na Arena!';
  let targetUrl = 'dashboard.php';

  if (event.data) {
    try {
      const payload = event.data.json();
      title = payload.title || title;
      body = payload.body || body;
      targetUrl = payload.url || targetUrl;
    } catch (e) {
      body = event.data.text();
    }
  }

  const options = {
    body: body,
    icon: 'icon-192x192.png', 
    badge: 'badge-72x72.png', 
    vibrate: [100, 50, 100],
    tag: 'status-evidencia',
    renotify: true,
    data: { url: targetUrl }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      let targetUrl = event.notification.data?.url || 'dashboard.php';
      
      for (let client of windowClients) {
        if (client.url.includes(targetUrl) && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) return clients.openWindow(targetUrl);
    })
  );
});