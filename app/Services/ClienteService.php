<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\DireccionCliente;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClienteService
{
    /**
     * Crea un cliente y opcionalmente su dirección principal.
     */
    public function crear(array $datos): Cliente
    {
        $telefono = trim($datos['telefono'] ?? '');
        if ($telefono === '') {
            throw new InvalidArgumentException('El teléfono del cliente es obligatorio.');
        }

        if (Cliente::where('telefono', $telefono)->exists()) {
            throw new InvalidArgumentException("Ya existe un comensal registrado con el teléfono {$telefono}.");
        }

        return DB::transaction(function () use ($datos, $telefono) {
            $cliente = Cliente::create([
                'nombre' => trim($datos['nombre'] ?? ''),
                'telefono' => $telefono,
                'email' => ! empty($datos['email']) ? trim($datos['email']) : null,
                'documento' => ! empty($datos['documento']) ? trim($datos['documento']) : null,
                'tier' => $datos['tier'] ?? 'regular',
                'puntos_fidelidad' => 0,
                'total_gastado' => 0,
                'visitas_totales' => 0,
                'alergias' => $datos['alergias'] ?? null,
                'preferencias' => $datos['preferencias'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'activo' => $datos['activo'] ?? true,
            ]);

            // Si se envió una dirección inicial
            if (! empty($datos['direccion'])) {
                $cliente->direcciones()->create([
                    'etiqueta' => $datos['etiqueta_direccion'] ?? 'Principal',
                    'direccion' => trim($datos['direccion']),
                    'referencia_apto' => $datos['referencia_apto'] ?? null,
                    'barrio_ciudad' => $datos['barrio_ciudad'] ?? 'Medellín',
                    'telefono_contacto' => $datos['telefono_contacto'] ?? $telefono,
                    'notas_entrega' => $datos['notas_entrega'] ?? null,
                    'es_predeterminada' => true,
                ]);
            }

            return $cliente->fresh(['direcciones']);
        });
    }

    /**
     * Actualiza un cliente.
     */
    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        if (isset($datos['telefono'])) {
            $telefono = trim($datos['telefono']);
            $existe = Cliente::where('telefono', $telefono)
                ->where('id', '!=', $cliente->id)
                ->exists();

            if ($existe) {
                throw new InvalidArgumentException("El teléfono {$telefono} ya está asignado a otro cliente.");
            }

            $datos['telefono'] = $telefono;
        }

        $camposPermitidos = [
            'nombre',
            'telefono',
            'email',
            'documento',
            'tier',
            'alergias',
            'preferencias',
            'notas',
            'activo',
        ];

        $actualizaciones = array_intersect_key($datos, array_flip($camposPermitidos));
        $cliente->update($actualizaciones);

        return $cliente->fresh(['direcciones']);
    }

    /**
     * Agrega una nueva dirección a un cliente.
     */
    public function agregarDireccion(Cliente $cliente, array $datos): DireccionCliente
    {
        $direccion = trim($datos['direccion'] ?? '');
        if ($direccion === '') {
            throw new InvalidArgumentException('La dirección de entrega es obligatoria.');
        }

        return DB::transaction(function () use ($cliente, $datos, $direccion) {
            $esPredeterminada = (bool) ($datos['es_predeterminada'] ?? false);

            if ($esPredeterminada || $cliente->direcciones()->count() === 0) {
                $cliente->direcciones()->update(['es_predeterminada' => false]);
                $esPredeterminada = true;
            }

            return $cliente->direcciones()->create([
                'etiqueta' => $datos['etiqueta'] ?? 'Entrega',
                'direccion' => $direccion,
                'referencia_apto' => $datos['referencia_apto'] ?? null,
                'barrio_ciudad' => $datos['barrio_ciudad'] ?? 'Medellín',
                'telefono_contacto' => $datos['telefono_contacto'] ?? $cliente->telefono,
                'notas_entrega' => $datos['notas_entrega'] ?? null,
                'es_predeterminada' => $esPredeterminada,
            ]);
        });
    }

    /**
     * Búsqueda rápida por teléfono, nombre o documento.
     */
    public function buscar(string $termino, int $limite = 10): Collection
    {
        $termino = trim($termino);
        if ($termino === '') {
            return new Collection;
        }

        return Cliente::with(['direcciones', 'direccionPredeterminada'])
            ->where(function ($query) use ($termino) {
                $query->where('telefono', 'like', "%{$termino}%")
                    ->orWhere('nombre', 'like', "%{$termino}%")
                    ->orWhere('documento', 'like', "%{$termino}%");
            })
            ->where('activo', true)
            ->limit($limite)
            ->get();
    }

    /**
     * Desactiva un cliente manteniendo su historial.
     */
    public function desactivar(Cliente $cliente): Cliente
    {
        $cliente->update(['activo' => false]);

        return $cliente;
    }

    /**
     * Reactiva un comensal.
     */
    public function reactivar(Cliente $cliente): Cliente
    {
        $cliente->update(['activo' => true]);

        return $cliente;
    }
}
