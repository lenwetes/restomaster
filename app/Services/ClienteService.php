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
        $telefono = ! empty($datos['telefono']) ? trim($datos['telefono']) : null;

        if ($telefono !== null && Cliente::where('telefono', $telefono)->exists()) {
            throw new InvalidArgumentException("Ya existe un comensal registrado con el teléfono {$telefono}.");
        }

        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del comensal es obligatorio.');
        }

        // Si no se suministra teléfono ni tier, se categoriza como ocasional
        $defaultTier = $telefono ? 'regular' : Cliente::TIER_OCASIONAL;
        $tier = ! empty($datos['tier']) ? $datos['tier'] : $defaultTier;

        return DB::transaction(function () use ($datos, $telefono, $nombre, $tier) {
            $cliente = Cliente::create([
                'nombre' => $nombre,
                'telefono' => $telefono,
                'email' => ! empty($datos['email']) ? trim($datos['email']) : null,
                'documento' => ! empty($datos['documento']) ? trim($datos['documento']) : null,
                'tier' => $tier,
                'puntos_fidelidad' => 0,
                'total_gastado' => 0,
                'visitas_count' => $tier === Cliente::TIER_OCASIONAL ? 1 : 0,
                'alergias' => $datos['alergias'] ?? null,
                'preferencias' => $datos['preferencias'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'activo' => $datos['activo'] ?? true,
                'acepta_tratamiento_datos' => (bool) ($datos['acepta_tratamiento_datos'] ?? false),
                'fecha_autorizacion_datos' => ! empty($datos['acepta_tratamiento_datos']) ? now() : null,
                'canal_autorizacion_datos' => $datos['canal_autorizacion_datos'] ?? null,
                'autoriza_whatsapp' => (bool) ($datos['autoriza_whatsapp'] ?? false),
                'autoriza_email' => (bool) ($datos['autoriza_email'] ?? false),
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
     * Busca o crea un cliente ocasional en tiempo de comanda solo con su nombre.
     */
    public function buscarOcrearOcasional(string $nombre): Cliente
    {
        $nombreLimpio = trim($nombre);
        if ($nombreLimpio === '') {
            throw new InvalidArgumentException('El nombre del comensal no puede estar vacío.');
        }

        $existente = Cliente::where('activo', true)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreLimpio)])
            ->first();

        if ($existente) {
            return $existente;
        }

        return Cliente::create([
            'nombre' => $nombreLimpio,
            'telefono' => null,
            'tier' => Cliente::TIER_OCASIONAL,
            'puntos_fidelidad' => 0,
            'total_gastado' => 0,
            'visitas_count' => 1,
            'activo' => true,
        ]);
    }

    /**
     * Búsqueda predictiva activada con 4 o más caracteres.
     */
    public function buscarPredictivo(string $termino, int $limite = 8): Collection
    {
        $termino = trim($termino);
        if (mb_strlen($termino) < 4) {
            return new Collection;
        }

        $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Cliente::with(['direcciones', 'direccionPredeterminada'])
            ->where('activo', true)
            ->where(function ($query) use ($termino, $likeOp) {
                $query->where('nombre', $likeOp, "%{$termino}%")
                    ->orWhere('telefono', $likeOp, "%{$termino}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN lower(tier) in ('vip', 'black', 'imperial', 'gold', 'oro') THEN 1
                    WHEN lower(tier) in ('frecuente', 'regular') THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('nombre')
            ->limit($limite)
            ->get();
    }

    /**
     * Registra consentimiento legal de Habeas Data (Ley 1581) y datos de contacto.
     */
    public function registrarConsentimientoHabeasData(Cliente $cliente, array $datos): Cliente
    {
        $actualizaciones = [
            'acepta_tratamiento_datos' => true,
            'fecha_autorizacion_datos' => now(),
            'canal_autorizacion_datos' => $datos['canal_autorizacion_datos'] ?? $datos['canal'] ?? 'pos',
            'autoriza_whatsapp' => (bool) ($datos['autoriza_whatsapp'] ?? true),
            'autoriza_email' => (bool) ($datos['autoriza_email'] ?? true),
        ];

        if (! empty($datos['telefono'])) {
            $telefono = trim($datos['telefono']);
            $existe = Cliente::where('telefono', $telefono)->where('id', '!=', $cliente->id)->exists();
            if ($existe) {
                throw new InvalidArgumentException("El teléfono {$telefono} ya está asignado a otro cliente.");
            }
            $actualizaciones['telefono'] = $telefono;
        }

        if (! empty($datos['email'])) {
            $actualizaciones['email'] = trim($datos['email']);
        }

        // Si era ocasional y completó sus datos de contacto, evoluciona a frecuente
        if ($cliente->isOcasional()) {
            $actualizaciones['tier'] = Cliente::TIER_FRECUENTE;
        }

        $cliente->update($actualizaciones);

        if (! empty($datos['direccion'])) {
            $this->agregarDireccion($cliente, [
                'etiqueta' => $datos['etiqueta_direccion'] ?? 'Principal',
                'direccion' => trim($datos['direccion']),
                'referencia_apto' => $datos['referencia_apto'] ?? null,
                'barrio_ciudad' => $datos['barrio_ciudad'] ?? 'Medellín',
                'telefono_contacto' => $actualizaciones['telefono'] ?? $cliente->telefono,
                'es_predeterminada' => true,
            ]);
        }

        return $cliente->fresh(['direcciones']);
    }

    /**
     * Actualiza un cliente.
     */
    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        if (isset($datos['telefono']) && $datos['telefono'] !== null && trim($datos['telefono']) !== '') {
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
            'acepta_tratamiento_datos',
            'fecha_autorizacion_datos',
            'canal_autorizacion_datos',
            'autoriza_whatsapp',
            'autoriza_email',
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
