<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuService
{
    public const CACHE_KEY_PUBLICO = 'menu.publico.v1';

    public const CACHE_TTL_PUBLICO = 300;

    /**
     * Obtiene el menú público con categorías y productos activos en cache-aside (300 segundos).
     *
     * La caché persiste únicamente datos planos (arrays anidados). Con CACHE_STORE=database/file,
     * cachear modelos o colecciones Eloquent produce __PHP_Incomplete_Class al hidratar.
     */
    public function obtenerMenuPublico(): Collection
    {
        $menu = Cache::remember(self::CACHE_KEY_PUBLICO, self::CACHE_TTL_PUBLICO, function (): array {
            return Categoria::where('activo', true)
                ->orderBy('orden')
                ->with(['productos' => function ($query) {
                    $query->where('activo', true)->orderBy('nombre');
                }])
                ->get()
                ->map(fn (Categoria $categoria) => [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'slug' => $categoria->slug,
                    'icono' => $categoria->icono,
                    'productos' => $categoria->productos
                        ->map(fn (Producto $producto) => [
                            'id' => $producto->id,
                            'nombre' => $producto->nombre,
                            'descripcion' => $producto->descripcion,
                            'precio' => (float) $producto->precio,
                            'area_cocina' => $producto->area_cocina,
                            'imagen' => $producto->imagen,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all();
        });

        return collect($menu);
    }

    /**
     * Invalida la caché del menú público.
     */
    public function invalidarCacheMenu(): void
    {
        Cache::forget(self::CACHE_KEY_PUBLICO);
        Cache::forget('pos.terminal.categorias');
    }

    /**
     * Crea una categoría de menú con slug único.
     */
    public function crearCategoria(array $datos): Categoria
    {
        $nombre = trim($datos['nombre'] ?? '');
        $slug = Str::slug($nombre);

        if (Categoria::where('slug', $slug)->exists()) {
            throw new InvalidArgumentException("Ya existe una categoría con el nombre {$nombre}.");
        }

        $categoria = Categoria::create([
            'nombre' => $nombre,
            'slug' => $slug,
            'icono' => $datos['icono'] ?? '🍣',
            'orden' => (int) ($datos['orden'] ?? 0),
            'activo' => $datos['activo'] ?? true,
        ]);

        $this->invalidarCacheMenu();

        app(AuditoriaService::class)->registrar(
            accion: 'categoria.creada',
            entidad: 'categoria',
            entidadId: $categoria->id,
            descripcion: "Se creó la categoría {$nombre}",
            datos: ['nombre' => $nombre, 'icono' => $categoria->icono, 'orden' => $categoria->orden],
        );

        return $categoria;
    }

    /**
     * Actualiza una categoría y recalcula su slug si cambió el nombre.
     */
    public function actualizarCategoria(Categoria $categoria, array $datos): Categoria
    {
        if (isset($datos['nombre'])) {
            $nombre = trim($datos['nombre']);
            $slug = Str::slug($nombre);

            $duplicado = Categoria::where('slug', $slug)
                ->where('id', '!=', $categoria->id)
                ->exists();

            if ($duplicado) {
                throw new InvalidArgumentException("Ya existe otra categoría con el nombre {$nombre}.");
            }

            $categoria->nombre = $nombre;
            $categoria->slug = $slug;
        }

        foreach (['icono', 'orden', 'activo'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $categoria->{$campo} = $datos[$campo];
            }
        }

        $categoria->save();
        $this->invalidarCacheMenu();

        app(AuditoriaService::class)->registrar(
            accion: 'categoria.actualizada',
            entidad: 'categoria',
            entidadId: $categoria->id,
            descripcion: "Se actualizó la categoría {$categoria->nombre}",
            datos: $datos,
        );

        return $categoria;
    }

    /**
     * Desactiva una categoría sin eliminarla (los productos conservan su historial).
     */
    public function desactivarCategoria(Categoria $categoria): Categoria
    {
        $categoria->update(['activo' => false]);
        $this->invalidarCacheMenu();

        app(AuditoriaService::class)->registrar(
            accion: 'categoria.desactivada',
            entidad: 'categoria',
            entidadId: $categoria->id,
            descripcion: "Se desactivó la categoría {$categoria->nombre}",
        );

        return $categoria;
    }

    /**
     * Crea un producto de la carta con slug y área de cocina.
     */
    public function crearProducto(array $datos): Producto
    {
        $nombre = trim($datos['nombre'] ?? '');
        $precio = (float) ($datos['precio'] ?? 0);

        if (! isset($datos['categoria_id']) || ! Categoria::where('id', $datos['categoria_id'])->exists()) {
            throw new InvalidArgumentException('La categoría seleccionada no existe.');
        }

        if ($precio <= 0) {
            throw new InvalidArgumentException('El precio de venta debe ser mayor a cero.');
        }

        $producto = Producto::create([
            'categoria_id' => $datos['categoria_id'],
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'descripcion' => $datos['descripcion'] ?? null,
            'precio' => $precio,
            'costo' => (float) ($datos['costo'] ?? 0),
            'area_cocina' => $datos['area_cocina'] ?? 'sushi',
            'activo' => $datos['activo'] ?? true,
            'imagen' => $datos['imagen'] ?? null,
        ]);

        $this->invalidarCacheMenu();

        app(AuditoriaService::class)->registrar(
            accion: 'producto.creado',
            entidad: 'producto',
            entidadId: $producto->id,
            descripcion: "Se creó el producto {$nombre}",
            datos: ['nombre' => $nombre, 'precio' => $precio, 'area_cocina' => $producto->area_cocina],
        );

        return $producto;
    }

    /**
     * Actualiza datos de un producto y recalcula el slug si cambió el nombre.
     */
    public function actualizarProducto(Producto $producto, array $datos): Producto
    {
        if (isset($datos['nombre'])) {
            $nombre = trim($datos['nombre']);
            $producto->nombre = $nombre;
            $producto->slug = Str::slug($nombre);

            $duplicado = Producto::where('slug', $producto->slug)
                ->where('id', '!=', $producto->id)
                ->exists();

            if ($duplicado) {
                throw new InvalidArgumentException("Ya existe otro producto con el nombre {$nombre}.");
            }
        }

        if (isset($datos['precio']) && (float) $datos['precio'] <= 0) {
            throw new InvalidArgumentException('El precio de venta debe ser mayor a cero.');
        }

        foreach (['categoria_id', 'descripcion', 'precio', 'costo', 'area_cocina', 'activo', 'imagen'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $producto->{$campo} = $datos[$campo];
            }
        }

        $producto->save();
        $this->invalidarCacheMenu();

        app(AuditoriaService::class)->registrar(
            accion: 'producto.actualizado',
            entidad: 'producto',
            entidadId: $producto->id,
            descripcion: "Se actualizó el producto {$producto->nombre}",
            datos: $datos,
        );

        return $producto;
    }

    /**
     * Desactiva un producto sin eliminarlo (historial de pedidos intacto).
     */
    public function desactivarProducto(Producto $producto): Producto
    {
        $producto->update(['activo' => false]);
        $this->invalidarCacheMenu();

        app(AuditoriaService::class)->registrar(
            accion: 'producto.desactivado',
            entidad: 'producto',
            entidadId: $producto->id,
            descripcion: "Se desactivó el producto {$producto->nombre}",
        );

        return $producto;
    }

    /**
     * Reactiva un producto previamente desactivado.
     */
    public function activarProducto(Producto $producto): Producto
    {
        $producto->update(['activo' => true]);
        $this->invalidarCacheMenu();

        app(AuditoriaService::class)->registrar(
            accion: 'producto.activado',
            entidad: 'producto',
            entidadId: $producto->id,
            descripcion: "Se activó el producto {$producto->nombre}",
        );

        return $producto;
    }

    /**
     * Reactiva una categoría previamente desactivada.
     */
    public function activarCategoria(Categoria $categoria): Categoria
    {
        $categoria->update(['activo' => true]);
        $this->invalidarCacheMenu();

        app(AuditoriaService::class)->registrar(
            accion: 'categoria.activada',
            entidad: 'categoria',
            entidadId: $categoria->id,
            descripcion: "Se activó la categoría {$categoria->nombre}",
        );

        return $categoria;
    }
}
