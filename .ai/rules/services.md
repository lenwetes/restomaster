---
paths:
  - 'app/Services/**'
  - app/Services/PedidoService.php
---

# Services

## Never Cache::remember Eloquent collections/models
CACHE_STORE=database serializes cache values with serialize(). Caching Eloquent collections/models (e.g. NotificacionService pre-fix) makes unserialize produce __PHP_Incomplete_Class on read → "call to a method on an incomplete object" 500 in Livewire views. Only cache plain scalars/arrays; query Eloquent fresh per request (or cache IDs + rehydrate).

## Never cache Eloquent models/collections (Cache::remember)
CACHE_STORE=database/file + serializable_classes=false breaks ALL objects on cache read (__PHP_Incomplete_Class). Cache::remember must always return/stores plain nested arrays (scalars only), never Eloquent models, Eloquent collections, or Carbon instances. Rehydrate into models/collections AFTER reading. Covers pos.terminal.categorias/mesas and menu.publico.v1.

## cobrarPedido exige turno abierto; acumulación con lockForUpdate
cobrarPedido lanza DomainException si no hay turno_caja abierto (por sucursal del pedido si pedido.sucursal_id no es null). CajaService::vincularCobroPedido debe re-seleccionar el turno con lockForUpdate antes de sumar totales para evitar lost-updates.
