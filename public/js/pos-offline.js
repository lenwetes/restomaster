/**
 * RestoMaster — Resiliencia Offline con IndexedDB y Sincronización Automática
 * Permite tomar comandas en tabletas y celulares en salón aun si la conexión a internet cae.
 */

(function () {
    'use strict';

    if (window.RestoMasterOffline) {
        return;
    }

    const DB_NAME = 'RestoMasterOfflinePOS';
    const DB_VERSION = 2;

    function abrirBaseDatosOffline() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains('comandas_locales')) {
                    const store = db.createObjectStore('comandas_locales', { keyPath: 'uuid' });
                    store.createIndex('estado', 'estado', { unique: false });
                    store.createIndex('mesa_id', 'mesa_id', { unique: false });
                    store.createIndex('created_at', 'created_at', { unique: false });
                }
                if (!db.objectStoreNames.contains('catalogo_cache')) {
                    db.createObjectStore('catalogo_cache', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('mesas_cache')) {
                    db.createObjectStore('mesas_cache', { keyPath: 'id' });
                }
            };

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    window.RestoMasterOffline = {
        async guardarComandaOffline(comanda) {
            const db = await abrirBaseDatosOffline();
            return new Promise((resolve, reject) => {
                const tx = db.transaction('comandas_locales', 'readwrite');
                const store = tx.objectStore('comandas_locales');

                const item = {
                    uuid: comanda.uuid || crypto.randomUUID(),
                    mesa_id: comanda.mesa_id,
                    sucursal_id: comanda.sucursal_id,
                    items: comanda.items || [],
                    subtotal: comanda.subtotal || 0,
                    total: comanda.total || 0,
                    nombre_cliente: comanda.nombre_cliente || '',
                    cliente_id: comanda.cliente_id || null,
                    estado: 'pendiente_sync',
                    created_at: new Date().toISOString()
                };

                const req = store.put(item);
                req.onsuccess = () => resolve(item);
                req.onerror = () => reject(req.error);
            });
        },

        async obtenerComandasPendientes() {
            const db = await abrirBaseDatosOffline();
            return new Promise((resolve, reject) => {
                const tx = db.transaction('comandas_locales', 'readonly');
                const store = tx.objectStore('comandas_locales');
                const index = store.index('estado');
                const req = index.getAll('pendiente_sync');

                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            });
        },

        async marcarSincronizada(uuid, pedidoId) {
            const db = await abrirBaseDatosOffline();
            return new Promise((resolve, reject) => {
                const tx = db.transaction('comandas_locales', 'readwrite');
                const store = tx.objectStore('comandas_locales');
                const getReq = store.get(uuid);

                getReq.onsuccess = () => {
                    const item = getReq.result;
                    if (item) {
                        item.estado = 'sincronizado';
                        item.pedido_id = pedidoId;
                        item.sincronizado_en = new Date().toISOString();
                        store.put(item);
                    }
                    resolve();
                };
                getReq.onerror = () => reject(getReq.error);
            });
        },

        async sincronizarCola() {
            if (!navigator.onLine) {
                return { sincronizados: 0, pendientes: 0 };
            }

            const pendientes = await this.obtenerComandasPendientes();
            if (pendientes.length === 0) {
                return { sincronizados: 0, pendientes: 0 };
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch('/api/pos/sincronizar-offline', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: JSON.stringify({ comandas: pendientes })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}`);
                }

                const data = await response.json();
                if (data.sincronizados && Array.isArray(data.sincronizados)) {
                    for (const item of data.sincronizados) {
                        await this.marcarSincronizada(item.uuid, item.pedido_id);
                    }
                }

                window.dispatchEvent(new CustomEvent('comandas-offline-sincronizadas', { detail: data }));
                return data;
            } catch (error) {
                console.warn('[OfflinePOS] Sincronización diferida: red inestable o servidor no disponible.', error);
                return { error: error.message };
            }
        }
    };

    // Event listeners de auto-sincronización en reconexión
    window.addEventListener('online', () => {
        console.info('[OfflinePOS] Conexión a internet restablecida. Iniciando sincronización de cola...');
        setTimeout(() => {
            window.RestoMasterOffline.sincronizarCola();
        }, 1500);
    });
})();
