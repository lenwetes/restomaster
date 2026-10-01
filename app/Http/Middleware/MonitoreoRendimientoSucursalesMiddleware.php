<?php

namespace App\Http\Middleware;

use App\Models\Sucursal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MonitoreoRendimientoSucursalesMiddleware
{
    /**
     * Clave base para almacenar muestras de latencia por sucursal en caché.
     */
    public const CACHE_PREFIX = 'metricas_latencia_sucursal_';

    /**
     * Cantidad máxima de muestras recientes retenidas por sucursal (ventana rodante).
     */
    public const MAX_MUESTRAS = 150;

    /**
     * Umbral SLA p95 en milisegundos para generar alerta directiva en Coolify.
     */
    public const UMBRAL_SLA_MS = 800.0;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);

        $response = $next($request);

        $duracionMs = round((microtime(true) - $inicio) * 1000, 2);

        // Omitir assets estáticos o rutas internas de healthcheck
        if ($request->is('up', 'livewire/livewire.js', 'build/*')) {
            return $response;
        }

        $this->registrarMuestraSucursal($request, $duracionMs);

        return $response;
    }

    /**
     * Registra la métrica en la ventana rodante y configura Sentry/logs si aplica.
     */
    protected function registrarMuestraSucursal(Request $request, float $duracionMs): void
    {
        $user = Auth::user();
        $sucursalId = (int) ($user?->sucursal_id ?? $request->header('X-Sucursal-Id') ?? 1);

        $sucursalNombre = 'Sucursal #'.$sucursalId;
        if ($user && $user->sucursal) {
            $sucursalNombre = $user->sucursal->nombre;
        }

        // 1. Integración con Sentry APM (si la extensión/SDK está inicializada)
        if (function_exists('\\Sentry\\configureScope')) {
            \Sentry\configureScope(function ($scope) use ($sucursalId, $sucursalNombre, $user, $duracionMs) {
                $scope->setTag('sucursal_id', (string) $sucursalId);
                $scope->setTag('sucursal_nombre', $sucursalNombre);
                if ($user) {
                    $scope->setTag('user_role', $user->role?->slug ?? 'usuario');
                }
                $scope->setTag('sla_alerta', $duracionMs > self::UMBRAL_SLA_MS ? 'true' : 'false');
            });
        }

        // 2. Almacenar muestra en ventana rodante en Cache
        $cacheKey = self::CACHE_PREFIX.$sucursalId;
        $muestras = Cache::get($cacheKey, []);
        $muestras[] = $duracionMs;

        if (count($muestras) > self::MAX_MUESTRAS) {
            array_shift($muestras);
        }

        Cache::put($cacheKey, $muestras, now()->addDays(7));

        // 3. Alerta proactiva en logs de Coolify si supera SLA p95
        if ($duracionMs > self::UMBRAL_SLA_MS) {
            Log::warning("[SLA_P95_BREACH] Sucursal #{$sucursalId} ({$sucursalNombre}): respuesta lenta {$duracionMs}ms en {$request->method()} /{$request->path()}");
        }
    }

    /**
     * Calcula los percentiles reales (p50, p90, p95, p99) para una sucursal dada.
     */
    public static function calcularPercentiles(int $sucursalId): array
    {
        $muestras = Cache::get(self::CACHE_PREFIX.$sucursalId, []);

        if (empty($muestras)) {
            return [
                'total_muestras' => 0,
                'p50' => 0.0,
                'p90' => 0.0,
                'p95' => 0.0,
                'p99' => 0.0,
                'sla_ok' => true,
            ];
        }

        sort($muestras);
        $total = count($muestras);

        $p50Index = (int) floor($total * 0.50);
        $p90Index = (int) floor($total * 0.90);
        $p95Index = (int) floor($total * 0.95);
        $p99Index = (int) floor($total * 0.99);

        $p50 = $muestras[$p50Index] ?? $muestras[0];
        $p90 = $muestras[min($p90Index, $total - 1)];
        $p95 = $muestras[min($p95Index, $total - 1)];
        $p99 = $muestras[min($p99Index, $total - 1)];

        return [
            'total_muestras' => $total,
            'p50' => round($p50, 2),
            'p90' => round($p90, 2),
            'p95' => round($p95, 2),
            'p99' => round($p99, 2),
            'sla_ok' => $p95 <= self::UMBRAL_SLA_MS,
        ];
    }
}
