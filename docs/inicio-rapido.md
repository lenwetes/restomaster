# Inicio Rápido

## Estructura del repositorio

```
Default Project/
├── README.md                     → Vista general + flujo del sistema
├── docs/
│   ├── inicio-rapido.md          → Este archivo
│   ├── arquitectura/
│   │   └── arquitectura.md       → Stack, capas, flujo de datos
│   ├── modelo-datos/
│   │   └── modelo-datos.md       → Entidades y relaciones (PostgreSQL)
│   ├── fases/
│   │   └── fases.md              → Fases de desarrollo (control de avance)
│   ├── pantallas/
│   │   └── pantallas.md          → Catálogo único de pantallas e interacciones (para Stitch)
│   ├── requerimientos.md         → Requerimientos de preparación del proyecto
│   └── runbook-setup.md          → Runbook de instalación/configuración (para Antigravity)
│   └── modulos/                  → Lógica funcional de cada módulo
│       ├── pedidos.md
│       ├── mesas.md
│       ├── cocina.md
│       ├── inventario.md
│       ├── clientes.md
│       ├── trabajadores.md
│       ├── pos.md
│       ├── impresion.md
│       ├── caja.md               → Control de caja (apertura, arqueo, cortes)
│       ├── reservas.md
│       ├── reportes.md
│       └── contabilidad.md
└── (aquí se creará el proyecto Laravel en la Fase 0)
```

## Cómo usar esta documentación
1. **Empieza por [fases.md](fases/fases.md)** → define cuál fase estás haciendo.
2. Antes de codificar un módulo, revisa su [documentación funcional](modulos/).
3. Usa [modelo-datos.md](modelo-datos/modelo-datos.md) como referencia de tablas al crear migraciones.

## Recomendación de orden
- Seguir las fases **en orden** (F0 → F6). Cada fase es utilizable por sí sola.
- Al terminar cada fase, marcar los checkboxes y validar contra su "criterio de salida".