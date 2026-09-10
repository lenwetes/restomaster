---
paths:
  - 'resources/views/livewire/**'
---

# Livewire

## Volt: wire:* no funciona dentro de x-slot name="header"
Nunca poner directivas wire:* (wire:click, wire:model, etc.) dentro de <x-slot name="header"> de un componente Volt: Livewire renderiza ese slot como parte del LAYOUT, fuera del div wire:id root, y el delegador de eventos no resuelve el componente => click/input silenciosamente no hace nada. Las acciones interactivas deben vivir dentro del root del componente (primer elemento del template), como en mesas/index.blade.php. Botón roto así se detecta con test que compara posiciones en el HTML servido (ver Fase0TrabajadoresTest::test_boton_nuevo_trabajador_queda_dentro_del_root_livewire).
