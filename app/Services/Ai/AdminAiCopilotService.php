<?php

namespace App\Services\Ai;

use App\Models\Caja;
use App\Models\CrmConfiguracion;
use App\Models\Encuesta;
use App\Models\Insumo;
use App\Models\ItemPedido;
use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\Promocion;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ReportesComparativosService;
use App\Services\ReporteService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminAiCopilotService
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Procesa la consulta de lenguaje natural del administrador/gerente con entendimiento
     * semántico avanzado, analítica profunda en base de datos, generación de gráficos y síntesis conversacional con Gemini.
     */
    public function procesarConsulta(string $mensaje, User $usuario): array
    {
        @set_time_limit(120);
        @ini_set('max_execution_time', '120');

        // 1. Verificación estricta de autorización de back-office
        if (! $usuario->isAdmin() && ! $usuario->isGerente()) {
            return [
                'tipo' => 'error_autorizacion',
                'mensaje' => 'Acceso denegado: El Copiloto Ejecutivo IA está restringido exclusivamente a Administradores y Gerentes de RestoMaster.',
            ];
        }

        $mensajeNormalizado = Str::lower(trim($mensaje));

        // 1b. Arquitectura agéntica (Fase 2): intentar Function Calling real con Gemini.
        //     Si hay API configurada y el LLM selecciona una tool, se usan datos SQL reales.
        //     Fallback determinista offline si no hay API, en testing o si la API falla.
        $agente = $this->procesarConFunctionCalling($mensaje, $usuario);
        if ($agente !== null) {
            return $agente;
        }

        // 2. Intención: Inventario e Insumos Críticos
        if ($this->esIntencionInventario($mensajeNormalizado)) {
            return $this->ejecutarAuditoriaInventario();
        }

        // 3. Intención: Diseñar o Crear Encuesta
        if ($this->esIntencionEncuesta($mensajeNormalizado)) {
            return $this->ejecutarGeneracionEncuesta($mensaje);
        }

        // 4. Intención: Crear Promoción
        if ($this->esIntencionPromocion($mensajeNormalizado)) {
            return $this->ejecutarGeneracionPromocion($mensaje);
        }

        // 5. Intención: Generar Imagen / Infografía de Estadísticas
        if ($this->esConsultaImagenOInfografia($mensajeNormalizado)) {
            return $this->ejecutarGeneracionInfografia($usuario, $mensaje);
        }

        // 6. Extracción de Entidades Semánticas (Caja y Rango Temporal)
        $caja = $this->resolverCaja($mensajeNormalizado, $usuario->sucursal_id);
        $rangoFecha = $this->resolverRangoFecha($mensajeNormalizado);

        // 7. Consulta específica de Caja (ej. "ventas en caja 1", "caja barra ayer", "estado de caja 2")
        if ($caja !== null) {
            return $this->ejecutarConsultaVentasCaja($usuario, $caja, $rangoFecha, $mensaje);
        }

        // 8. Consulta de Métodos de Pago (ej. "cuánto en efectivo vs tarjeta", "desglose de pagos")
        if ($this->esConsultaMetodosPago($mensajeNormalizado)) {
            return $this->ejecutarConsultaMetodosPago($usuario, $rangoFecha, $mensaje);
        }

        // 9. Consulta de Rendimiento de Meseros / Personal (ej. "ventas por mesero", "atención")
        if ($this->esConsultaMeseros($mensajeNormalizado)) {
            return $this->ejecutarConsultaMeseros($usuario, $rangoFecha, $mensaje);
        }

        // 10. Consulta de Platos / Top Productos (ej. "platos más vendidos", "top 5 sushi")
        if ($this->esConsultaTopProductos($mensajeNormalizado)) {
            return $this->ejecutarConsultaTopProductos($usuario, $rangoFecha, 5, $mensaje);
        }

        // 11. Consulta de Egresos / Retiros / Salidas de Base de Caja (ANTES de rangoFecha
        //     para que "retiros de caja de hoy" no se intercepte como "ventas de hoy")
        if ($this->esIntencionEgresos($mensajeNormalizado)) {
            return $this->ejecutarConsultaEgresosBase($usuario, $rangoFecha, $mensaje);
        }

        // 12. Consulta con Período Temporal Específico (ej. "ventas del martes de esta semana", "ayer", "este mes")
        if ($rangoFecha !== null) {
            if ($rangoFecha['periodo'] === 'hoy') {
                return $this->ejecutarResumenVentas($usuario);
            }

            return $this->ejecutarConsultaVentasPeriodo($usuario, $rangoFecha, $mensaje);
        }

        // 13. Petición explícita de Gráficos (ej. "gráfico de ventas", "comparativa de facturación")
        if ($this->esConsultaGrafico($mensajeNormalizado)) {
            return $this->ejecutarConsultaGraficoGeneral($usuario, $rangoFecha, $mensaje);
        }

        // 14. Resumen general de ventas si menciona ventas/facturación/kpis sin filtro específico
        if ($this->esIntencionVentas($mensajeNormalizado)) {
            return $this->ejecutarResumenVentas($usuario);
        }

        // 15. Consulta Conversacional a Gemini con Conocimiento Completo en Vivo del Restaurante
        $respuestaConversacional = $this->ejecutarConsultaConversacionalLlm($mensaje, $usuario);
        if ($respuestaConversacional !== null) {
            return $respuestaConversacional;
        }

        // 16. Mensaje analítico de orientación si no encaja con ninguna herramienta ni LLM
        return $this->generarOrientacionPersuasiva();
    }

    /**
     * Obtiene la configuración de IA activa con fallback garantizado a la clave global del sistema.
     */
    public function obtenerConfiguracionIa(?int $sucursalId = null): ?CrmConfiguracion
    {
        $config = CrmConfiguracion::activa($sucursalId);
        if ($config->iaActiva() && ! empty($config->obtenerApiKeyIa())) {
            return $config;
        }

        // Fallback a configuración global si existe
        $global = CrmConfiguracion::whereNull('sucursal_id')->first();
        if ($global && $global->iaActiva() && ! empty($global->obtenerApiKeyIa())) {
            return $global;
        }

        return $config->iaActiva() ? $config : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FASE 2 — Arquitectura Agéntica: catálogo de herramientas (Function Calling)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Catálogo de herramientas reales del copiloto (declaraciones Gemini Function Calling).
     *
     * @return array<int, array{name: string, description: string, parameters: array}>
     */
    public static function definicionesHerramientas(): array
    {
        return [
            [
                'name' => 'consultar_ventas',
                'description' => 'Consulta ventas reales de pedidos pagados con totales, ticket promedio, desglose y serie temporal (diaria u horaria). Soporta periodos fijos y dinamicos ultimos_N_dias con N=1..365. La app genera el gráfico automáticamente con estos datos.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => [
                            'type' => 'string',
                            'description' => 'Periodo: hoy, ayer, esta_semana, semana_pasada, este_mes, mes_pasado, ultimos_7_dias, ultimos_30_dias o ultimos_N_dias con N=1..365 (ej. ultimos_15_dias, ultimos_45_dias, ultimos_90_dias). Convierte semanas/meses a dias (2 semanas=ultimos_14_dias, 3 meses=ultimos_90_dias)',
                        ],
                        'agrupacion' => [
                            'type' => 'string',
                            'description' => 'Agrupación y gráfico: dia (evolución diaria con barras), hora (curva horaria con barras), metodo_pago (dona de participación), caja (comparativa por terminal con barras)',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'consultar_movimientos_caja',
                'description' => 'Consulta movimientos reales de caja (ingresos, egresos, retiros) con totales y detalle.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tipo' => [
                            'type' => 'string',
                            'description' => 'Tipo: ingreso, egreso, retiro, todos',
                        ],
                        'caja_id' => [
                            'type' => 'integer',
                            'description' => 'ID de la caja registradora (opcional)',
                        ],
                        'fecha' => [
                            'type' => 'string',
                            'description' => 'Fecha Y-m-d para filtrar movimientos del día (opcional)',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'consultar_inventario',
                'description' => 'Consulta insumos reales con stock, alertas críticas y proveedor.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'insumo' => [
                            'type' => 'string',
                            'description' => 'Nombre parcial del insumo a buscar (opcional)',
                        ],
                        'solo_alertas' => [
                            'type' => 'boolean',
                            'description' => 'true para solo stock crítico/agotado',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'consultar_rendimiento_meseros',
                'description' => 'Ranking real de ventas, comandas y propinas por mesero. Soporta periodos fijos y ultimos_N_dias dinamico.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'periodo' => [
                            'type' => 'string',
                            'description' => 'Periodo: hoy, ayer, esta_semana, semana_pasada, este_mes, mes_pasado, ultimos_7_dias, ultimos_30_dias o ultimos_N_dias con N=1..365 (ej. ultimos_15_dias)',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'consultar_platos_estrella',
                'description' => 'Top real de platos más vendidos por facturación y unidades.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limite' => [
                            'type' => 'integer',
                            'description' => 'Número de platos (1-20, por defecto 5)',
                        ],
                        'categoria' => [
                            'type' => 'string',
                            'description' => 'Nombre o slug de categoría para filtrar (opcional)',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'ejecutar_sql_analytics',
                'description' => 'Ejecuta consultas SQL directas de solo lectura (SELECT) en PostgreSQL sobre cualquiera de las tablas de la base de datos (pedidos, items_pedido, productos, categorias, mesas, zonas, turnos_caja, cajas, movimientos_caja, clientes, reservas, insumos, recetas, promociones, users, etc.). Úsala para responder cualquier pregunta analítica, comparativas, rankings, reportes avanzados o cuando ninguna otra herramienta cubra los requerimientos del usuario. Devuelve filas estructuradas y permite generar gráficos automáticamente.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sql' => [
                            'type' => 'string',
                            'description' => 'Consulta SQL PostgreSQL SELECT válida. No uses DML/DDL. Para fechas usa DATE_TRUNC(\'day\', pagado_en), NOW(), INTERVAL. Si aplica a la sucursal actual, puedes incluir WHERE sucursal_id = :sucursal_id.',
                        ],
                        'titulo' => [
                            'type' => 'string',
                            'description' => 'Título descriptivo y ejecutivo de la consulta para mostrar en la interfaz.',
                        ],
                        'tipo_grafico' => [
                            'type' => 'string',
                            'description' => 'Tipo de gráfico opcional si los datos son graficables: bar (barras), doughnut (distribución/porcentajes), ranking (tabla de posiciones). Si no aplica gráfico, dejar null.',
                        ],
                        'columna_etiqueta' => [
                            'type' => 'string',
                            'description' => 'Nombre exacto de la columna del SELECT que contiene las etiquetas del eje X o nombres para el gráfico.',
                        ],
                        'columna_valor' => [
                            'type' => 'string',
                            'description' => 'Nombre exacto de la columna del SELECT que contiene el valor numérico para el gráfico.',
                        ],
                    ],
                    'required' => ['sql', 'titulo'],
                ],
            ],
        ];
    }

    /**
     * Ejecuta una herramienta del catálogo con validación estricta (sin excepciones hacia el LLM).
     */
    public function ejecutarTool(string $nombre, array $args, User $usuario): array
    {
        if (! $usuario->isAdmin() && ! $usuario->isGerente()) {
            return ['ok' => false, 'tool' => $nombre, 'error' => 'Acceso denegado.'];
        }

        $permitidas = [
            'consultar_ventas',
            'consultar_movimientos_caja',
            'consultar_inventario',
            'consultar_rendimiento_meseros',
            'consultar_platos_estrella',
            'ejecutar_sql_analytics',
        ];

        if (! in_array($nombre, $permitidas, true)) {
            return ['ok' => false, 'tool' => $nombre, 'error' => 'Herramienta no reconocida.'];
        }

        try {
            return match ($nombre) {
                'consultar_ventas' => $this->toolConsultarVentas($args, $usuario),
                'consultar_movimientos_caja' => $this->toolConsultarMovimientosCaja($args, $usuario),
                'consultar_inventario' => $this->toolConsultarInventario($args, $usuario),
                'consultar_rendimiento_meseros' => $this->toolConsultarRendimientoMeseros($args, $usuario),
                'consultar_platos_estrella' => $this->toolConsultarPlatosEstrella($args, $usuario),
                'ejecutar_sql_analytics' => $this->toolEjecutarSqlAnalytics($args, $usuario),
            };
        } catch (\Throwable $e) {
            Log::warning('Error ejecutando tool del copiloto: '.$e->getMessage());

            return ['ok' => false, 'tool' => $nombre, 'error' => 'No se pudo ejecutar la consulta.'];
        }
    }

    /**
     * Selección determinista local de herramienta (fallback offline sin LLM).
     *
     * @return array{tool: string, args: array}
     */
    public function seleccionarToolLocal(string $mensaje): array
    {
        $t = Str::lower(trim($mensaje));

        if ($this->esIntencionEgresos($t) || Str::contains($t, ['movimiento', 'retiro', 'egreso', 'salida de caja', 'base de caja'])) {
            $tipo = 'todos';
            if (Str::contains($t, ['retiro', 'retiros'])) {
                $tipo = 'retiro';
            } elseif (Str::contains($t, ['egreso', 'egresos'])) {
                $tipo = 'egreso';
            } elseif (Str::contains($t, ['ingreso', 'ingresos'])) {
                $tipo = 'ingreso';
            }

            return ['tool' => 'consultar_movimientos_caja', 'args' => ['tipo' => $tipo]];
        }

        if ($this->esConsultaMeseros($t)) {
            return ['tool' => 'consultar_rendimiento_meseros', 'args' => ['periodo' => $this->detectarPeriodoLocal($t)]];
        }

        if ($this->esConsultaTopProductos($t)) {
            $limite = 5;
            if (preg_match('/top\s+(\d{1,2})\b/iu', $t, $m)) {
                $limite = max(1, min(20, (int) $m[1]));
            }

            return ['tool' => 'consultar_platos_estrella', 'args' => ['limite' => $limite]];
        }

        if ($this->esIntencionInventario($t)) {
            $soloAlertas = (bool) preg_match('/\b(agotad|cr[ií]tico|alerta|falta|m[ií]nimo)\b/iu', $t);

            return ['tool' => 'consultar_inventario', 'args' => ['solo_alertas' => $soloAlertas]];
        }

        return ['tool' => 'consultar_ventas', 'args' => [
            'periodo' => $this->detectarPeriodoLocal($t),
            'agrupacion' => Str::contains($t, ['hora']) ? 'hora' : 'dia',
        ]];
    }

    /**
     * Flujo Gemini Function Calling: pregunta → tool → SQL real → respuesta ejecutiva.
     * Retorna null si no hay API configurada, en testing o si la API falla (fallback offline).
     */
    /**
     * Construye la instrucción de sistema con el esquema completo de tablas PostgreSQL,
     * directivas agénticas y reglas de ejecución para el Copiloto Ejecutivo.
     */
    public function construirSystemInstructionCopiloto(User $usuario): string
    {
        $fechaActual = Carbon::now()->format('Y-m-d H:i:s');
        $sucursalId = $usuario->sucursal_id ?? 1;

        return <<<PROMPT
Eres el Copiloto Ejecutivo IA de RestoMaster (sistema gastronómico integral de restaurante).
Actúas como Director de Operaciones y Analista de Negocio Senior para el Administrador/Gerente ({$usuario->name}).
Momento actual del sistema: {$fechaActual}. Sucursal activa ID: {$sucursalId}.

ACCESO A DATOS:
TIENES ACCESO COMPLETO DE LECTURA A LA BASE DE DATOS POSTGRESQL DE RESTOMASTER mediante herramientas de Function Calling.
NUNCA digas "no tengo acceso a la base de datos", "no puedo generar gráficos", "no puedo ver las mesas" o excusas similares.
TIENES HERRAMIENTAS Y DEBES USARLAS OBLIGATORIAMENTE PARA OBTENER DATOS REALES.

HERRAMIENTAS DISPONIBLES:
1. 'consultar_ventas': Totales, ticket promedio, desglose y serie temporal de ventas pagadas.
   - periodo: hoy, ayer, esta_semana, semana_pasada, este_mes, mes_pasado, ultimos_N_dias (ej. ultimos_7_dias, ultimos_15_dias, ultimos_30_dias, ultimos_45_dias, ultimos_90_dias).
   - agrupacion: dia, hora, metodo_pago, caja.
2. 'consultar_movimientos_caja': Ingresos, egresos y retiros de caja (tipo: todos, egreso, retiro, ingreso).
3. 'consultar_inventario': Stock de insumos, costo y alertas de stock crítico (solo_alertas: true/false).
4. 'consultar_rendimiento_meseros': Ventas, comandas y propinas por mesero en un periodo.
5. 'consultar_platos_estrella': Ranking de productos más vendidos y facturación generada (limite: 1-20).
6. 'ejecutar_sql_analytics': CONSULTAS SQL SELECT DIRECTAS EN POSTGRESQL sobre cualquiera de las tablas.
   - Úsala SIEMPRE que el usuario pregunte por mesas, reservas, clientes, compras, promociones, pedidos detallados, comandas, turnos de caja, o cualquier consulta analítica que no cubran las 5 herramientas anteriores.
   - Incluye parámetros para gráficos (tipo_grafico: 'bar', 'doughnut', 'ranking', columna_etiqueta, columna_valor) siempre que los datos representen series, rankings o distribuciones.

ESQUEMA PRINCIPAL DE TABLAS (PostgreSQL 18):
- pedidos (id, sucursal_id, mesa_id, mesero_id, cliente_id, codigo, tipo, estado ['pendiente','en_preparacion','servido','pagado','anulado'], subtotal, descuento, propina, total, metodo_pago ['efectivo','tarjeta','transferencia','mixto'], pagado_en, created_at)
- items_pedido (id, pedido_id, producto_id, cantidad, precio_unitario, subtotal, notas, estado)
- productos (id, categoria_id, nombre, descripcion, precio, costo, activo)
- categorias (id, nombre, slug, activo)
- mesas (id, sucursal_id, zona_id, numero, capacidad, estado ['libre','ocupada','reservada','mantenimiento'], codigo_qr)
- zonas (id, sucursal_id, nombre, slug, activa)
- turnos_caja (id, caja_id, user_id, apertura_en, cierre_en, monto_inicial, total_ventas_efectivo, total_ventas_tarjeta, total_ventas_transferencia, total_egresos, total_retiros, estado ['abierto','cerrado'])
- cajas (id, sucursal_id, nombre, estado ['abierta','cerrada'])
- movimientos_caja (id, turno_caja_id, user_id, tipo ['ingreso','egreso','retiro'], concepto, monto, created_at)
- clientes (id, nombre, telefono, email, total_visitas, total_gastado, ultima_visita)
- reservas (id, sucursal_id, cliente_id, nombre_contacto, telefono_contacto, fecha, hora_llegada, personas, estado ['pendiente','confirmada','cancelada','completada'])
- insumos (id, nombre, unidad_medida, stock_actual, stock_minimo, costo_unitario)
- recetas (id, producto_id, insumo_id, cantidad, merma_esperada_pct)
- promociones (id, sucursal_id, titulo, slug, tipo_beneficio, descuento_porcentaje, precio_promocional, precio_original, fecha_inicio, fecha_fin, activo)
- users (id, name, email, role_id, sucursal_id, activo)

REGLAS PARA SQL EN 'ejecutar_sql_analytics':
- Solo consultas SELECT de solo lectura.
- Para filtros de sucursal: WHERE sucursal_id = :sucursal_id (o tabla.sucursal_id = :sucursal_id).
- Para ventas efectivas: WHERE pedidos.estado = 'pagado'.
- Fechas en PostgreSQL: DATE_TRUNC('day', pagado_en), NOW(), CURRENT_DATE, INTERVAL 'X days'.
- Siempre limita o agrupa lógicamente para evitar respuestas masivas innecesarias.

ESTILO DE RESPUESTA:
- Cuando recibas el resultado de una herramienta, responde en español con tono ejecutivo, directo y profesional.
- Destaca cifras clave en **negrita**, usando formato de moneda cuando corresponda (\$X.XXX COP).
- La interfaz dibuja y exporta automáticamente los gráficos a partir de los datos retornados por la herramienta; menciona brevemente los insights que refleja el gráfico.
PROMPT;
    }

    /**
     * Flujo Gemini Function Calling: pregunta → tool → SQL real → respuesta ejecutiva.
     * Retorna null si no hay API configurada, en testing o si la API falla (fallback offline).
     */
    public function procesarConFunctionCalling(string $mensaje, User $usuario, int $maxTurnos = 2): ?array
    {
        if (app()->environment('testing')) {
            return null;
        }

        @set_time_limit(120);
        @ini_set('max_execution_time', '120');

        $config = $this->obtenerConfiguracionIa($usuario->sucursal_id);
        if (! $config) {
            return null;
        }

        $apiKey = $config->obtenerApiKeyIa();
        if (empty($apiKey) || str_starts_with($apiKey, 'dummy')) {
            return null;
        }

        $modelo = $config->ia_modelo ?: 'gemini-flash-lite-latest';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

        $tools = [['function_declarations' => self::definicionesHerramientas()]];
        $systemInstruction = $this->construirSystemInstructionCopiloto($usuario);

        $esSaludoOConversacionPura = (bool) preg_match('/^(hola|buenas|buenos d[ií]as|buenas tardes|buenas noches|qui[eé]n eres|qu[eé] eres|qu[eé] puedes hacer|saludos|chao|adi[oó]s|gracias)\b/iu', trim($mensaje));

        try {
            $contents = [['role' => 'user', 'parts' => [['text' => $mensaje]]]];
            $turno = 0;
            $ultimaTool = null;
            $ultimoResultado = null;

            while ($turno < $maxTurnos) {
                $turno++;

                $toolConfig = null;
                if ($turno === 1) {
                    $toolConfig = $esSaludoOConversacionPura
                        ? ['function_calling_config' => ['mode' => 'AUTO']]
                        : ['function_calling_config' => ['mode' => 'ANY']];
                } else {
                    $toolConfig = ['function_calling_config' => ['mode' => 'AUTO']];
                }

                $genConfig = [
                    'temperature' => 0.15,
                    'maxOutputTokens' => 1200,
                ];
                if (str_contains($modelo, '2.5')) {
                    $genConfig['thinkingConfig'] = ['thinkingBudget' => 0];
                }

                $body = [
                    'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => $contents,
                    'tools' => $tools,
                    'generationConfig' => $genConfig,
                ];
                if ($toolConfig !== null) {
                    $body['tool_config'] = $toolConfig;
                }

                $response = Http::withoutVerifying()
                    ->timeout(15)
                    ->post($url, $body);

                // Fallback automático si el modelo activo agota cuota (429), está ocupado (503) o no existe (404)
                if (! $response->successful() && $modelo !== 'gemini-flash-lite-latest') {
                    $urlFallback = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key={$apiKey}";
                    $body['generationConfig'] = ['temperature' => 0.15, 'maxOutputTokens' => 1200];
                    $response = Http::withoutVerifying()
                        ->timeout(15)
                        ->post($urlFallback, $body);
                }

                if (! $response->successful()) {
                    Log::warning('Gemini Function Calling HTTP '.$response->status().': '.$response->body());

                    return null;
                }

                $parts = $response->json('candidates.0.content.parts', []);
                if (! is_array($parts) || empty($parts)) {
                    return null;
                }

                $functionCall = null;
                $texto = null;
                foreach ($parts as $part) {
                    if (isset($part['functionCall'])) {
                        $functionCall = $part['functionCall'];
                        break;
                    }
                    if (isset($part['text'])) {
                        $texto = ($texto ?? '').$part['text'];
                    }
                }

                if ($functionCall === null) {
                    if (! empty($texto)) {
                        $respuesta = [
                            'tipo' => 'texto',
                            'mensaje' => trim($texto),
                            'accion_rapida' => ['etiqueta' => 'Abrir Módulo de Reportes', 'url' => '/reportes', 'icono' => 'analytics'],
                        ];

                        // Adjuntar datos + gráfico de la última herramienta para que el
                        // drawer dibuje la visualización bajo el texto del modelo.
                        if ($ultimaTool !== null && ($ultimoResultado['ok'] ?? false)) {
                            $grafico = $this->construirGraficoTool($ultimaTool, $ultimoResultado['datos'] ?? [], $usuario);
                            $respuesta['datos'] = [
                                'tool' => $ultimaTool,
                                'resultado_tool' => $ultimoResultado['datos'] ?? [],
                            ];
                            if ($grafico !== null) {
                                $respuesta['datos']['grafico'] = $grafico;
                            }
                        }

                        return $respuesta;
                    }

                    return null;
                }

                $toolNombre = (string) ($functionCall['name'] ?? '');
                $toolArgs = is_array($functionCall['args'] ?? null) ? $functionCall['args'] : [];
                $resultadoTool = $this->ejecutarTool($toolNombre, $toolArgs, $usuario);
                $ultimaTool = $toolNombre;
                $ultimoResultado = $resultadoTool;

                $modelContent = $response->json('candidates.0.content');
                $contents[] = $modelContent;
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $toolNombre,
                                'response' => ['name' => $toolNombre, 'content' => $resultadoTool],
                            ],
                        ],
                    ],
                ];

                if ($turno >= $maxTurnos) {
                    return $this->formatearRespuestaTool($toolNombre, $resultadoTool, $mensaje, $usuario);
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Error en Function Calling del copiloto: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Prueba la conexión con Gemini midiendo latencia sin exponer la clave en logs.
     *
     * @return array{ok: bool, latencia_ms: ?int, modelo: string, error: ?string}
     */
    public function probarConexionIa(?int $sucursalId = null): array
    {
        $config = $this->obtenerConfiguracionIa($sucursalId);
        if (! $config) {
            return ['ok' => false, 'latencia_ms' => null, 'modelo' => '', 'error' => 'IA no configurada.'];
        }

        $apiKey = $config->obtenerApiKeyIa();
        if (empty($apiKey) || str_starts_with($apiKey, 'dummy')) {
            return ['ok' => false, 'latencia_ms' => null, 'modelo' => '', 'error' => 'Sin API Key válida.'];
        }

        if (app()->environment('testing')) {
            return ['ok' => true, 'latencia_ms' => 12, 'modelo' => $config->ia_modelo ?: 'gemini-2.5-flash', 'error' => null];
        }

        $modelo = $config->ia_modelo ?: 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";
        $inicio = microtime(true);

        try {
            $response = Http::withoutVerifying()->timeout(8)->post($url, [
                'contents' => [['role' => 'user', 'parts' => [['text' => 'Responde solo: OK']]]],
                'generationConfig' => ['maxOutputTokens' => 8, 'temperature' => 0],
            ]);
            $latencia = (int) round((microtime(true) - $inicio) * 1000);

            if ($response->successful() && ! empty($response->json('candidates.0.content.parts.0.text'))) {
                return ['ok' => true, 'latencia_ms' => $latencia, 'modelo' => $modelo, 'error' => null];
            }

            return ['ok' => false, 'latencia_ms' => $latencia, 'modelo' => $modelo, 'error' => 'La API respondió con error HTTP '.$response->status().'.'];
        } catch (\Throwable $e) {
            $latencia = (int) round((microtime(true) - $inicio) * 1000);

            return ['ok' => false, 'latencia_ms' => $latencia, 'modelo' => $modelo, 'error' => 'No se pudo conectar con Gemini.'];
        }
    }

    protected function detectarPeriodoLocal(string $textoNormalizado): string
    {
        $t = $textoNormalizado;
        if (preg_match('/\bayer\b/iu', $t)) {
            return 'ayer';
        }
        if (preg_match('/\b(esta semana|semana actual)\b/iu', $t)) {
            return 'esta_semana';
        }
        if (preg_match('/\b(semana pasada|semana anterior)\b/iu', $t)) {
            return 'semana_pasada';
        }
        if (preg_match('/\b(este mes|mes actual)\b/iu', $t)) {
            return 'este_mes';
        }
        if (preg_match('/\b(mes pasado|mes anterior)\b/iu', $t)) {
            return 'mes_pasado';
        }
        // Dinamico: ultimos/ultimas N dias | semanas | meses -> normalizado a ultimos_N_dias
        if (preg_match('/[uú]ltimas?\s+(\d{1,3})\s+semanas?/iu', $t, $m)) {
            $dias = max(1, min(365, ((int) $m[1]) * 7));

            return "ultimos_{$dias}_dias";
        }
        if (preg_match('/[uú]ltimos?\s+(\d{1,3})\s+mes(?:es)?\b/iu', $t, $m)) {
            $dias = max(1, min(365, ((int) $m[1]) * 30));

            return "ultimos_{$dias}_dias";
        }
        if (preg_match('/[uú]ltimos?\s+(\d{1,3})\s+d[ií]as?/iu', $t, $m)) {
            $dias = max(1, min(365, (int) $m[1]));

            return "ultimos_{$dias}_dias";
        }
        // "hace N dias" -> día único de hace N días (periodo puntual, no rango)
        if (preg_match('/\bhace\s+(\d{1,3})\s+d[ií]as?\b/iu', $t, $m)) {
            $dias = max(1, min(365, (int) $m[1]));

            return "hace_{$dias}_dias";
        }

        return 'hoy';
    }

    protected function resolverRangoTool(string $periodo): array
    {
        $p = Str::lower(trim($periodo));

        // Patrones predefinidos
        $predefinidos = [
            'ayer' => ['inicio' => Carbon::yesterday()->startOfDay(), 'fin' => Carbon::yesterday()->endOfDay(), 'etiqueta' => 'Ayer'],
            'esta_semana' => ['inicio' => Carbon::now()->startOfWeek()->startOfDay(), 'fin' => Carbon::now()->endOfWeek()->endOfDay(), 'etiqueta' => 'Esta semana'],
            'semana_pasada' => ['inicio' => Carbon::now()->subWeek()->startOfWeek()->startOfDay(), 'fin' => Carbon::now()->subWeek()->endOfWeek()->endOfDay(), 'etiqueta' => 'Semana pasada'],
            'este_mes' => ['inicio' => Carbon::now()->startOfMonth()->startOfDay(), 'fin' => Carbon::now()->endOfMonth()->endOfDay(), 'etiqueta' => 'Este mes'],
            'mes_pasado' => ['inicio' => Carbon::now()->subMonth()->startOfMonth()->startOfDay(), 'fin' => Carbon::now()->subMonth()->endOfMonth()->endOfDay(), 'etiqueta' => 'Mes pasado'],
            'ultimos_7_dias' => ['inicio' => Carbon::now()->subDays(7)->startOfDay(), 'fin' => Carbon::now()->endOfDay(), 'etiqueta' => 'Últimos 7 días'],
            'ultimos_30_dias' => ['inicio' => Carbon::now()->subDays(30)->startOfDay(), 'fin' => Carbon::now()->endOfDay(), 'etiqueta' => 'Últimos 30 días'],
            'hoy' => ['inicio' => Carbon::today()->startOfDay(), 'fin' => Carbon::today()->endOfDay(), 'etiqueta' => 'Hoy'],
        ];

        if (isset($predefinidos[$p])) {
            return $predefinidos[$p];
        }

        // Patrón dinámico: ultimos_N_dias (rango N días, 1..365)
        if (preg_match('/^ultimos_(\d{1,3})_dias$/', $p, $m)) {
            $dias = max(1, min(365, (int) $m[1]));

            return [
                'inicio' => Carbon::now()->subDays($dias)->startOfDay(),
                'fin' => Carbon::now()->endOfDay(),
                'etiqueta' => "Últimos {$dias} días",
            ];
        }

        // Patrón puntual: hace_N_dias (día único de hace N días, 1..365)
        if (preg_match('/^hace_(\d{1,3})_dias$/', $p, $m)) {
            $dias = max(1, min(365, (int) $m[1]));
            $fecha = Carbon::now()->subDays($dias);

            return [
                'inicio' => $fecha->copy()->startOfDay(),
                'fin' => $fecha->copy()->endOfDay(),
                'etiqueta' => 'Hace '.$dias.($dias === 1 ? ' día' : ' días').' ('.$fecha->format('d/m/Y').')',
            ];
        }

        // Compatibilidad: periodo genérico legacy 'ultimos_dias' -> 7 días
        if ($p === 'ultimos_dias') {
            return [
                'inicio' => Carbon::now()->subDays(7)->startOfDay(),
                'fin' => Carbon::now()->endOfDay(),
                'etiqueta' => 'Últimos 7 días',
            ];
        }

        // Fallback: hoy
        return $predefinidos['hoy'];
    }

    protected function toolConsultarVentas(array $args, User $usuario): array
    {
        $periodo = Str::lower(trim((string) ($args['periodo'] ?? 'hoy')));

        // Aceptar periodos fijos + dinamicos ultimos_N_dias y puntuales hace_N_dias
        $periodosEstaticos = ['hoy', 'ayer', 'esta_semana', 'semana_pasada', 'este_mes', 'mes_pasado', 'ultimos_7_dias', 'ultimos_30_dias'];
        $esValido = in_array($periodo, $periodosEstaticos, true)
            || preg_match('/^(ultimos|hace)_\d{1,3}_dias$/', $periodo);
        if (! $esValido) {
            $periodo = 'hoy';
        }

        $agrupacionesValidas = ['hora', 'dia', 'metodo_pago', 'caja'];
        $agrupacion = Str::lower((string) ($args['agrupacion'] ?? 'dia'));
        if (! in_array($agrupacion, $agrupacionesValidas, true)) {
            $agrupacion = 'dia';
        }

        $rango = $this->resolverRangoTool($periodo);
        $query = Pedido::query()
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$rango['inicio'], $rango['fin']])
            ->when($usuario->sucursal_id, fn ($q) => $q->where('sucursal_id', $usuario->sucursal_id));

        $pedidos = $query->get(['id', 'total', 'metodo_pago', 'pagado_en', 'turno_caja_id']);
        $totalVentas = (float) $pedidos->sum('total');
        $totalPedidos = $pedidos->count();
        $ticketPromedio = $totalPedidos > 0 ? round($totalVentas / $totalPedidos, 2) : 0.0;
        $desglosePagos = $pedidos->groupBy('metodo_pago')->map(fn ($g) => (float) $g->sum('total'))->all();

        // Serie temporal para graficar: por hora en días puntuales, por día en rangos.
        $serie = [];
        $serieTipo = 'dia';
        if ($agrupacion === 'hora' || in_array($periodo, ['hoy', 'ayer'], true) || str_starts_with($periodo, 'hace_')) {
            $porHora = array_fill(0, 24, 0.0);
            foreach ($pedidos as $p) {
                if ($p->pagado_en) {
                    $h = (int) Carbon::parse($p->pagado_en)->format('H');
                    if (isset($porHora[$h])) {
                        $porHora[$h] += (float) $p->total;
                    }
                }
            }
            foreach ($porHora as $h => $v) {
                $serie[] = ['etiqueta' => sprintf('%02d:00', $h), 'valor' => $v, 'valor_fmt' => '$'.number_format($v, 0, ',', '.')];
            }
            $serieTipo = 'hora';
        } else {
            $inicioDia = $rango['inicio']->copy()->startOfDay();
            $finDia = $rango['fin']->copy()->startOfDay();
            $mapa = [];
            $cursor = $inicioDia->copy();
            while ($cursor->lte($finDia) && count($mapa) < 366) {
                $mapa[$cursor->toDateString()] = 0.0;
                $cursor->addDay();
            }
            foreach ($pedidos as $p) {
                if ($p->pagado_en) {
                    $k = Carbon::parse($p->pagado_en)->toDateString();
                    if (array_key_exists($k, $mapa)) {
                        $mapa[$k] += (float) $p->total;
                    }
                }
            }
            // Más de 31 días: agregar por semanas para no saturar el gráfico
            if (count($mapa) > 31) {
                $chunksEtiq = array_chunk(array_keys($mapa), 7);
                $chunksVal = array_chunk(array_values($mapa), 7);
                foreach ($chunksVal as $idx => $chunk) {
                    $ini = Carbon::parse($chunksEtiq[$idx][0])->format('d/m');
                    $finE = Carbon::parse(end($chunksEtiq[$idx]))->format('d/m');
                    $v = array_sum($chunk);
                    $serie[] = ['etiqueta' => "{$ini}–{$finE}", 'valor' => $v, 'valor_fmt' => '$'.number_format($v, 0, ',', '.')];
                }
                $serieTipo = 'semana';
            } else {
                foreach ($mapa as $fecha => $v) {
                    $serie[] = ['etiqueta' => Carbon::parse($fecha)->format('d/m'), 'valor' => $v, 'valor_fmt' => '$'.number_format($v, 0, ',', '.')];
                }
            }
        }

        return ['ok' => true, 'tool' => 'consultar_ventas', 'datos' => [
            'periodo' => $periodo,
            'etiqueta' => $rango['etiqueta'],
            'agrupacion' => $agrupacion,
            'total_ventas' => $totalVentas,
            'total_pedidos' => $totalPedidos,
            'ticket_promedio' => $ticketPromedio,
            'desglose_metodo_pago' => $desglosePagos,
            'serie' => $serie,
            'serie_tipo' => $serieTipo,
        ]];
    }

    protected function toolConsultarMovimientosCaja(array $args, User $usuario): array
    {
        $tiposValidos = ['ingreso', 'egreso', 'retiro', 'todos'];
        $tipo = Str::lower((string) ($args['tipo'] ?? 'todos'));
        if (! in_array($tipo, $tiposValidos, true)) {
            $tipo = 'todos';
        }

        $cajaId = isset($args['caja_id']) ? (int) $args['caja_id'] : null;
        if ($cajaId !== null && $cajaId <= 0) {
            $cajaId = null;
        }

        $fecha = null;
        if (! empty($args['fecha'])) {
            try {
                $fecha = Carbon::createFromFormat('Y-m-d', (string) $args['fecha'])->startOfDay();
            } catch (\Throwable) {
                $fecha = null;
            }
        }

        $query = MovimientoCaja::query()
            ->whereHas('turno.caja', fn ($q) => $q->when($usuario->sucursal_id, fn ($sq) => $sq->where('sucursal_id', $usuario->sucursal_id)))
            ->when($tipo !== 'todos', fn ($q) => $q->where('tipo', $tipo))
            ->when($cajaId, fn ($q) => $q->whereHas('turno', fn ($t) => $t->where('caja_id', $cajaId)))
            ->when($fecha, fn ($q) => $q->whereDate('created_at', $fecha->toDateString()))
            ->orderBy('id', 'desc')
            ->limit(20);

        $movimientos = $query->get(['id', 'turno_caja_id', 'tipo', 'concepto', 'monto', 'created_at']);

        return ['ok' => true, 'tool' => 'consultar_movimientos_caja', 'datos' => [
            'tipo' => $tipo,
            'caja_id' => $cajaId,
            'fecha' => $fecha?->toDateString(),
            'total_monto' => (float) $movimientos->sum('monto'),
            'total_movimientos' => $movimientos->count(),
            'movimientos' => $movimientos->toArray(),
        ]];
    }

    protected function toolConsultarInventario(array $args, User $usuario): array
    {
        $insumo = mb_substr(trim((string) ($args['insumo'] ?? '')), 0, 80);
        $soloAlertas = filter_var($args['solo_alertas'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = Insumo::query()
            ->where('activo', true)
            ->when($insumo !== '', fn ($q) => $q->where('nombre', 'like', '%'.$insumo.'%'))
            ->when($soloAlertas, fn ($q) => $q->where(function ($w) {
                $w->where('stock_actual', '<=', 0)->orWhereColumn('stock_actual', '<=', 'stock_minimo');
            }))
            ->orderBy('stock_actual')
            ->limit(20);

        $insumos = $query->get(['id', 'nombre', 'stock_actual', 'stock_minimo', 'unidad_medida', 'costo_unitario', 'proveedor_nombre']);

        $totalAlertas = Insumo::query()
            ->where('activo', true)
            ->where(function ($w) {
                $w->where('stock_actual', '<=', 0)->orWhereColumn('stock_actual', '<=', 'stock_minimo');
            })
            ->count();

        return ['ok' => true, 'tool' => 'consultar_inventario', 'datos' => [
            'insumo' => $insumo,
            'solo_alertas' => $soloAlertas,
            'total_alertas' => $soloAlertas ? $insumos->count() : $totalAlertas,
            'insumos' => $insumos->toArray(),
        ]];
    }

    protected function toolConsultarRendimientoMeseros(array $args, User $usuario): array
    {
        $periodo = Str::lower(trim((string) ($args['periodo'] ?? 'hoy')));
        $periodosEstaticos = ['hoy', 'ayer', 'esta_semana', 'semana_pasada', 'este_mes', 'mes_pasado', 'ultimos_7_dias', 'ultimos_30_dias'];
        $esValido = in_array($periodo, $periodosEstaticos, true)
            || preg_match('/^(ultimos|hace)_\d{1,3}_dias$/', $periodo);
        if (! $esValido) {
            $periodo = 'hoy';
        }

        $rango = $this->resolverRangoTool($periodo);
        $ranking = Pedido::query()
            ->join('users', 'pedidos.mesero_id', '=', 'users.id')
            ->where('pedidos.estado', 'pagado')
            ->whereBetween('pedidos.pagado_en', [$rango['inicio'], $rango['fin']])
            ->when($usuario->sucursal_id, fn ($q) => $q->where('pedidos.sucursal_id', $usuario->sucursal_id))
            ->select('users.id', 'users.name as nombre')
            ->selectRaw('SUM(pedidos.total) as total_ventas, COUNT(pedidos.id) as total_pedidos, SUM(COALESCE(pedidos.propina, 0)) as total_propinas')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_ventas')
            ->limit(20)
            ->get();

        return ['ok' => true, 'tool' => 'consultar_rendimiento_meseros', 'datos' => [
            'periodo' => $periodo,
            'etiqueta' => $rango['etiqueta'],
            'total_meseros' => $ranking->count(),
            'ranking' => $ranking->toArray(),
        ]];
    }

    protected function toolConsultarPlatosEstrella(array $args, User $usuario): array
    {
        $limite = isset($args['limite']) ? (int) $args['limite'] : 5;
        $limite = max(1, min(20, $limite));
        $categoria = mb_substr(trim((string) ($args['categoria'] ?? '')), 0, 60);

        $query = ItemPedido::query()
            ->join('pedidos', 'items_pedido.pedido_id', '=', 'pedidos.id')
            ->join('productos', 'items_pedido.producto_id', '=', 'productos.id')
            ->where('pedidos.estado', 'pagado')
            ->when($usuario->sucursal_id, fn ($q) => $q->where('pedidos.sucursal_id', $usuario->sucursal_id))
            ->when($categoria !== '', function ($q) use ($categoria) {
                $q->whereHas('producto.categoria', function ($cq) use ($categoria) {
                    $cq->where('nombre', 'like', '%'.$categoria.'%')->orWhere('slug', 'like', '%'.$categoria.'%');
                });
            })
            ->select('productos.nombre as producto')
            ->selectRaw('SUM(items_pedido.cantidad) as cantidad, SUM(items_pedido.subtotal) as total_ventas')
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('total_ventas')
            ->limit($limite);

        $platos = $query->get();

        return ['ok' => true, 'tool' => 'consultar_platos_estrella', 'datos' => [
            'limite' => $limite,
            'categoria' => $categoria,
            'platos' => $platos->toArray(),
        ]];
    }

    /**
     * Herramienta universal: ejecuta cualquier SELECT SQL generado por Gemini.
     * Guardas de seguridad estrictas: solo SELECT, auto-inject sucursal_id, límite 200 filas.
     */
    protected function toolEjecutarSqlAnalytics(array $args, User $usuario): array
    {
        $sql = trim((string) ($args['sql'] ?? ''));
        $titulo = mb_substr(trim((string) ($args['titulo'] ?? 'Resultado SQL')), 0, 120);
        $tipoGrafico = $args['tipo_grafico'] ?? null;
        $columnaEtiqueta = $args['columna_etiqueta'] ?? null;
        $columnaValor = $args['columna_valor'] ?? null;

        // ── Guardia 1: no vacío ─────────────────────────────────────────────
        if (empty($sql)) {
            return ['ok' => false, 'tool' => 'ejecutar_sql_analytics', 'error' => 'SQL vacío.'];
        }

        // ── Guardia 2: solo SELECT (normalizar quitando comentarios) ─────────
        $sqlNorm = preg_replace('/--[^\n]*/', '', $sql);                 // -- comentarios
        $sqlNorm = preg_replace('/\/\*.*?\*\//s', '', $sqlNorm ?? '');  // /* */ comentarios
        $sqlNorm = trim(preg_replace('/\s+/', ' ', $sqlNorm ?? ''));
        if (! preg_match('/^\s*SELECT\b/i', $sqlNorm)) {
            return ['ok' => false, 'tool' => 'ejecutar_sql_analytics', 'error' => 'Solo se permiten consultas SELECT.'];
        }

        // ── Guardia 3: prohibir palabras DML/DDL ─────────────────────────────
        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|TRUNCATE|ALTER|CREATE|GRANT|REVOKE|EXEC|EXECUTE|COPY|pg_read_file|pg_ls_dir)\b/i', $sqlNorm)) {
            return ['ok' => false, 'tool' => 'ejecutar_sql_analytics', 'error' => 'Operación no permitida detectada en SQL.'];
        }

        // ── Guardia 4: limitar filas si no tiene LIMIT ───────────────────────
        $sql = rtrim(trim($sql), "; \t\n\r");
        if (! preg_match('/\bLIMIT\b/i', $sqlNorm)) {
            $sql .= ' LIMIT 200';
        }

        // ── Ejecutar con binding seguro de sucursal_id ───────────────────────
        try {
            $bindings = [];
            if (str_contains($sql, ':sucursal_id')) {
                $bindings['sucursal_id'] = $usuario->sucursal_id;
            }
            $filas = DB::select($sql, $bindings);
        } catch (\Throwable $e) {
            Log::warning('toolEjecutarSqlAnalytics error SQL: '.$e->getMessage(), ['sql' => $sql]);

            return ['ok' => false, 'tool' => 'ejecutar_sql_analytics', 'error' => 'Error al ejecutar la consulta: '.$e->getMessage()];
        }

        // Convertir a array para serialización
        $filas = array_map(fn ($row) => (array) $row, $filas);

        // ── Construir gráfico si se indicó ───────────────────────────────────
        $grafico = null;
        if ($tipoGrafico && $columnaEtiqueta && $columnaValor && ! empty($filas)) {
            $colores = ['#e0442e', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
            $etiquetas = array_column($filas, $columnaEtiqueta);
            $valores = array_column($filas, $columnaValor);
            $maxVal = max(1, max(array_map('floatval', $valores)));

            $esMoneda = (bool) preg_match('/\b(total|monto|venta|precio|costo|ingreso|egreso|propina|subtotal|descuento|recaudo)\b/i', $columnaValor);

            $grafColores = [];
            $grafValoresFmt = [];
            $grafPorcentajes = [];
            $totalVal = array_sum(array_map('floatval', $valores));

            foreach ($valores as $idx => $v) {
                $grafColores[] = $colores[$idx % count($colores)];
                $grafValoresFmt[] = $esMoneda
                    ? '$'.number_format((float) $v, 0, ',', '.')
                    : (is_numeric($v) ? number_format((float) $v, 0, ',', '.') : (string) $v);
                $grafPorcentajes[] = $totalVal > 0 ? round(((float) $v / $totalVal) * 100, 1) : 0;
            }

            $grafico = [
                'tipo' => $tipoGrafico,
                'titulo' => $titulo,
                'etiquetas' => array_map('strval', $etiquetas),
                'valores' => array_map('floatval', $valores),
                'valores_formateados' => $grafValoresFmt,
                'porcentajes' => $grafPorcentajes,
                'colores' => $grafColores,
                'unidad' => 'COP',
            ];
        }

        return [
            'ok' => true,
            'tool' => 'ejecutar_sql_analytics',
            'datos' => [
                'titulo' => $titulo,
                'total_filas' => count($filas),
                'filas' => $filas,
                'columnas' => ! empty($filas) ? array_keys($filas[0]) : [],
                'grafico' => $grafico,
                'tipo_grafico' => $tipoGrafico,
                'columna_etiqueta' => $columnaEtiqueta,
                'columna_valor' => $columnaValor,
            ],
        ];
    }

    /**
     * Construye el payload de gráfico (bar/doughnut/ranking) que el drawer dibuja
     * y exporta a PNG, a partir del resultado de cualquier herramienta.
     * Retorna null cuando no hay datos graficables.
     */
    public function construirGraficoTool(string $toolNombre, array $datos, User $usuario): ?array
    {
        if ($toolNombre === 'ejecutar_sql_analytics') {
            return $datos['grafico'] ?? null;
        }

        $fmt = fn ($v) => '$'.number_format((float) $v, 0, ',', '.');

        if ($toolNombre === 'consultar_ventas') {
            $agrupacion = Str::lower((string) ($datos['agrupacion'] ?? 'dia'));
            $etiqueta = (string) ($datos['etiqueta'] ?? 'Ventas');

            if ($agrupacion === 'metodo_pago') {
                $colores = ['efectivo' => '#10b981', 'tarjeta' => '#3b82f6', 'transferencia' => '#8b5cf6', 'mixto' => '#f59e0b'];
                $labels = [];
                $valores = [];
                $pcts = [];
                $cols = [];
                $fmts = [];
                $total = array_sum(array_map('floatval', $datos['desglose_metodo_pago'] ?? []));
                foreach (($datos['desglose_metodo_pago'] ?? []) as $metodo => $monto) {
                    $labels[] = Str::headline((string) $metodo);
                    $valores[] = (float) $monto;
                    $pcts[] = $total > 0 ? round(((float) $monto / $total) * 100, 1) : 0.0;
                    $cols[] = $colores[Str::lower((string) $metodo)] ?? '#6b7280';
                    $fmts[] = $fmt($monto);
                }
                if ($labels === []) {
                    return null;
                }

                return ['tipo' => 'doughnut', 'titulo' => "Métodos de Pago — {$etiqueta}", 'subtitulo' => 'Distribución de recaudos', 'etiquetas' => $labels, 'valores' => $valores, 'porcentajes' => $pcts, 'valores_formateados' => $fmts, 'colores' => $cols, 'unidad' => 'COP'];
            }

            if ($agrupacion === 'caja') {
                $rango = $this->resolverRangoTool((string) ($datos['periodo'] ?? 'hoy'));
                $labels = [];
                $valores = [];
                $cajas = Caja::where('sucursal_id', $usuario->sucursal_id)->where('activa', true)->get();
                foreach ($cajas as $caja) {
                    $total = (float) Pedido::where('sucursal_id', $usuario->sucursal_id)
                        ->where('estado', 'pagado')
                        ->whereBetween('pagado_en', [$rango['inicio'], $rango['fin']])
                        ->whereHas('turnoCaja', fn ($q) => $q->where('caja_id', $caja->id))
                        ->sum('total');
                    $labels[] = $caja->nombre;
                    $valores[] = $total;
                }
                if ($labels === []) {
                    return null;
                }

                return ['tipo' => 'bar', 'titulo' => "Ventas por Caja — {$etiqueta}", 'subtitulo' => 'Comparativa por terminal de cobro', 'etiquetas' => $labels, 'valores' => $valores, 'valores_formateados' => array_map($fmt, $valores), 'colores' => ['#e0442e', '#f59e0b', '#10b981', '#3b82f6'], 'unidad' => 'COP'];
            }

            $serie = $datos['serie'] ?? [];
            if ($serie === []) {
                return null;
            }
            $sub = ($datos['serie_tipo'] ?? 'dia') === 'hora' ? 'Evolución por hora' : (($datos['serie_tipo'] ?? '') === 'semana' ? 'Agregado semanal del período' : 'Evolución diaria de facturación');

            return [
                'tipo' => 'bar',
                'titulo' => "Evolución de Ventas — {$etiqueta}",
                'subtitulo' => $sub,
                'etiquetas' => array_column($serie, 'etiqueta'),
                'valores' => array_map('floatval', array_column($serie, 'valor')),
                'valores_formateados' => array_column($serie, 'valor_fmt'),
                'colores' => array_map(fn ($s) => (float) ($s['valor'] ?? 0) > 0 ? '#e0442e' : '#6b7280', $serie),
                'unidad' => 'COP',
            ];
        }

        if ($toolNombre === 'consultar_movimientos_caja') {
            $porTipo = ['ingreso' => 0.0, 'egreso' => 0.0, 'retiro' => 0.0];
            foreach (($datos['movimientos'] ?? []) as $m) {
                $t = Str::lower((string) ($m['tipo'] ?? ''));
                if (isset($porTipo[$t])) {
                    $porTipo[$t] += (float) ($m['monto'] ?? 0);
                }
            }
            if (array_sum($porTipo) <= 0) {
                return null;
            }
            $total = array_sum($porTipo);
            $meta = ['ingreso' => ['Ingresos', '#10b981'], 'egreso' => ['Egresos', '#ef4444'], 'retiro' => ['Retiros', '#3b82f6']];
            $labels = [];
            $valores = [];
            $pcts = [];
            $cols = [];
            $fmts = [];
            foreach ($meta as $tipo => [$nombre, $color]) {
                $labels[] = $nombre;
                $valores[] = $porTipo[$tipo];
                $pcts[] = round(($porTipo[$tipo] / $total) * 100, 1);
                $cols[] = $color;
                $fmts[] = $fmt($porTipo[$tipo]);
            }

            return ['tipo' => 'doughnut', 'titulo' => 'Movimientos de Caja ('.($datos['tipo'] ?? 'todos').')', 'subtitulo' => 'Composición de salidas e ingresos', 'etiquetas' => $labels, 'valores' => $valores, 'porcentajes' => $pcts, 'valores_formateados' => $fmts, 'colores' => $cols, 'unidad' => 'COP'];
        }

        if ($toolNombre === 'consultar_rendimiento_meseros') {
            $ranking = array_slice($datos['ranking'] ?? [], 0, 10);
            if ($ranking === []) {
                return null;
            }

            return $this->graficoRanking(
                array_map(fn ($r) => (string) ($r['nombre'] ?? 'Mesero'), $ranking),
                array_map(fn ($r) => (float) ($r['total_ventas'] ?? 0), $ranking),
                'Ventas por Mesero — '.($datos['etiqueta'] ?? ''),
                'Facturación generada en salón'
            );
        }

        if ($toolNombre === 'consultar_platos_estrella') {
            $platos = array_slice($datos['platos'] ?? [], 0, 10);
            if ($platos === []) {
                return null;
            }

            return $this->graficoRanking(
                array_map(fn ($p) => (string) ($p['producto'] ?? 'Plato'), $platos),
                array_map(fn ($p) => (float) ($p['total_ventas'] ?? 0), $platos),
                'Platos Estrella por Facturación',
                'Ranking de productos con mayor contribución'
            );
        }

        return null;
    }

    protected function graficoRanking(array $etiquetas, array $valores, string $titulo, string $subtitulo): array
    {
        $fmt = fn ($v) => '$'.number_format((float) $v, 0, ',', '.');

        return [
            'tipo' => 'ranking',
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'etiquetas' => $etiquetas,
            'valores' => $valores,
            'valores_formateados' => array_map($fmt, $valores),
            'colores' => ['#e0442e', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6'],
            'unidad' => 'COP',
        ];
    }

    protected function formatearRespuestaTool(string $toolNombre, array $resultadoTool, string $mensajeOriginal, User $usuario): array
    {
        if (! ($resultadoTool['ok'] ?? false)) {
            return $this->generarOrientacionPersuasiva();
        }

        $datos = $resultadoTool['datos'] ?? [];
        $grafico = $this->construirGraficoTool($toolNombre, $datos, $usuario);

        // ── Resultado SQL Analytics: tabla + gráfico dinámico ────────────────
        if ($toolNombre === 'ejecutar_sql_analytics') {
            $titulo = $datos['titulo'] ?? 'Resultado';
            $filas = $datos['filas'] ?? [];
            $columnas = $datos['columnas'] ?? [];
            $grafico = $datos['grafico'] ?? null;
            $totalFilas = $datos['total_filas'] ?? count($filas);

            if (empty($filas)) {
                $mensaje = "🔍 **{$titulo}**\n\nNo se encontraron registros para esta consulta.";
            } else {
                // Construir tabla Markdown con máximo 15 filas visibles
                $visible = array_slice($filas, 0, 15);
                $cabecera = '| '.implode(' | ', array_map('ucwords', $columnas)).' |';
                $separador = '| '.implode(' | ', array_fill(0, count($columnas), '---')).' |';
                $filasMd = array_map(function ($fila) use ($columnas) {
                    $celdas = array_map(function ($col) use ($fila) {
                        $v = $fila[$col] ?? '';

                        return is_numeric($v) && $v > 1000 ? number_format((float) $v, 0, ',', '.') : htmlspecialchars((string) $v, ENT_QUOTES);
                    }, $columnas);

                    return '| '.implode(' | ', $celdas).' |';
                }, $visible);

                $tabla = implode("\n", array_merge([$cabecera, $separador], $filasMd));
                $extra = $totalFilas > 15 ? "\n\n*...y {$totalFilas} registros en total.*" : '';
                $mensaje = "📊 **{$titulo}**\n\n{$tabla}{$extra}";
            }

            $respuesta = [
                'tipo' => 'ventas',
                'mensaje' => $mensaje,
                'datos' => ['tool' => $toolNombre, 'resultado_tool' => $datos],
                'accion_rapida' => ['etiqueta' => 'Ver Reportes Completos', 'url' => '/reportes', 'icono' => 'analytics'],
            ];

            // Inyectar gráfico si el LLM lo pidió
            if ($grafico !== null) {
                $respuesta['datos']['grafico'] = $grafico;
            }

            return $respuesta;
        }

        $respuesta = match ($toolNombre) {
            'consultar_ventas' => [
                'tipo' => 'ventas',
                'mensaje' => '📊 **Ventas '.($datos['etiqueta'] ?? '').':** $'.number_format((float) ($datos['total_ventas'] ?? 0), 0, ',', '.').' COP ('.($datos['total_pedidos'] ?? 0).' pedidos, ticket $'.number_format((float) ($datos['ticket_promedio'] ?? 0), 0, ',', '.').'). Incluye gráfico evolutivo descargable.',
                'datos' => ['tool' => $toolNombre, 'resultado_tool' => $datos],
                'accion_rapida' => ['etiqueta' => 'Abrir Módulo de Reportes', 'url' => '/reportes', 'icono' => 'analytics'],
            ],
            'consultar_movimientos_caja' => [
                'tipo' => 'ventas',
                'mensaje' => '💸 **Movimientos de caja ('.($datos['tipo'] ?? 'todos').'):** $'.number_format((float) ($datos['total_monto'] ?? 0), 0, ',', '.').' COP en '.($datos['total_movimientos'] ?? 0).' movimientos. Incluye gráfico de composición.',
                'datos' => ['tool' => $toolNombre, 'resultado_tool' => $datos],
                'accion_rapida' => ['etiqueta' => 'Ver Módulo de Caja', 'url' => '/caja', 'icono' => 'point_of_sale'],
            ],
            'consultar_inventario' => [
                'tipo' => 'inventario',
                'mensaje' => '📦 **Inventario:** '.($datos['total_alertas'] ?? 0).' insumos en alerta.',
                'datos' => ['tool' => $toolNombre, 'resultado_tool' => $datos],
                'accion_rapida' => ['etiqueta' => 'Ir al Módulo de Inventario', 'url' => '/inventario', 'icono' => 'inventory_2'],
            ],
            'consultar_rendimiento_meseros' => [
                'tipo' => 'ventas',
                'mensaje' => '👨‍🍳 **Rendimiento de meseros ('.($datos['etiqueta'] ?? '').'):** '.($datos['total_meseros'] ?? 0).' meseros con ventas registradas. Incluye ranking gráfico descargable.',
                'datos' => ['tool' => $toolNombre, 'resultado_tool' => $datos],
                'accion_rapida' => ['etiqueta' => 'Abrir Módulo de Salón', 'url' => '/pos', 'icono' => 'table_bar'],
            ],
            default => [
                'tipo' => 'ventas',
                'mensaje' => '🍣 **Top platos:** '.count($datos['platos'] ?? []).' productos destacados. Incluye ranking gráfico descargable.',
                'datos' => ['tool' => $toolNombre, 'resultado_tool' => $datos],
                'accion_rapida' => ['etiqueta' => 'Abrir Menú y Carta', 'url' => '/menu', 'icono' => 'restaurant_menu'],
            ],
        };

        if ($grafico !== null) {
            $respuesta['datos']['grafico'] = $grafico;
        }

        return $respuesta;
    }

    /**
     * Resuelve menciones a Cajas registradoras (ej. "caja 1", "caja #01", "caja principal", "caja barra").
     */
    public function resolverCaja(string $texto, ?int $sucursalId = null): ?Caja
    {
        $query = Caja::query()->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId));

        // Patrón numérico: "caja 1", "caja #1", "caja 01", "caja 2"
        if (preg_match('/\bcajas?\s*(?:#|n[uú]mero|num\.?)?\s*0*([1-9]\d*)\b/iu', $texto, $matches)) {
            $num = (int) $matches[1];

            // Buscar por ID exacto
            $cajaPorId = (clone $query)->where('id', $num)->first();
            if ($cajaPorId) {
                return $cajaPorId;
            }

            // Buscar por código o nombre que contenga el número
            $codigoBuscado = sprintf('CAJ-%02d', $num);
            $cajaPorCodigo = (clone $query)->where('codigo', $codigoBuscado)
                ->orWhere('nombre', 'like', "%{$num}%")
                ->first();

            if ($cajaPorCodigo) {
                return $cajaPorCodigo;
            }
        }

        // Patrón semántico de tipo o nombre: "caja principal", "caja barra", "caja salon", "caja delivery"
        if (preg_match('/\bcajas?\s+(principal|barra|sal[oó]n|delivery|mostrador)\b/iu', $texto, $matches)) {
            $termino = Str::lower($matches[1]);
            $terminoSinTilde = Str::ascii($termino);

            return (clone $query)
                ->where(function ($q) use ($termino, $terminoSinTilde) {
                    $q->whereRaw('LOWER(nombre) LIKE ?', ["%{$termino}%"])
                        ->orWhereRaw('LOWER(nombre) LIKE ?', ["%{$terminoSinTilde}%"])
                        ->orWhereRaw('LOWER(tipo) LIKE ?', ["%{$termino}%"]);
                })
                ->first();
        }

        return null;
    }

    /**
     * Resuelve fechas y períodos temporales expresados en lenguaje natural humano en español.
     *
     * @return array{inicio: Carbon, fin: Carbon, etiqueta: string, periodo: string}|null
     */
    public function resolverRangoFecha(string $texto): ?array
    {
        $t = Str::lower($texto);

        // 1. "hoy"
        if (preg_match('/\b(hoy|el d[ií]a de hoy|al d[ií]a de hoy)\b/iu', $t)) {
            return [
                'inicio' => Carbon::today()->startOfDay(),
                'fin' => Carbon::today()->endOfDay(),
                'etiqueta' => 'Hoy ('.Carbon::today()->format('d/m/Y').')',
                'periodo' => 'hoy',
            ];
        }

        // 2. "ayer"
        if (preg_match('/\b(ayer|el d[ií]a de ayer|en el d[ií]a de ayer)\b/iu', $t)) {
            $ayer = Carbon::yesterday();

            return [
                'inicio' => $ayer->copy()->startOfDay(),
                'fin' => $ayer->copy()->endOfDay(),
                'etiqueta' => 'Ayer ('.$ayer->format('d/m/Y').')',
                'periodo' => 'ayer',
            ];
        }

        // 3. "anteayer" o "antier"
        if (preg_match('/\b(anteayer|antier)\b/iu', $t)) {
            $anteayer = Carbon::today()->subDays(2);

            return [
                'inicio' => $anteayer->copy()->startOfDay(),
                'fin' => $anteayer->copy()->endOfDay(),
                'etiqueta' => 'Anteayer ('.$anteayer->format('d/m/Y').')',
                'periodo' => 'anteayer',
            ];
        }

        // 4. Días específicos de la semana: "martes de esta semana", "dia martes", "el miercoles pasado", "viernes"
        $mapaDias = [
            'lunes' => 0,
            'martes' => 1,
            'miercoles' => 2,
            'miércoles' => 2,
            'jueves' => 3,
            'viernes' => 4,
            'sabado' => 5,
            'sábado' => 5,
            'domingo' => 6,
        ];

        if (preg_match('/(?:del?\s+)?(?:d[ií]a\s+)?(lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo)(?:\s+(?:de\s+)?(esta\s+semana|pasad[oa]|anterior))?/iu', $t, $m)) {
            $diaStr = Str::lower($m[1]);
            $offset = $mapaDias[$diaStr] ?? 0;
            $modificador = Str::lower($m[2] ?? '');

            $baseSemana = Str::contains($modificador, ['pasad', 'anterior'])
                ? Carbon::now()->subWeek()->startOfWeek()
                : Carbon::now()->startOfWeek();

            $fechaDestino = $baseSemana->copy()->addDays($offset);

            return [
                'inicio' => $fechaDestino->copy()->startOfDay(),
                'fin' => $fechaDestino->copy()->endOfDay(),
                'etiqueta' => ucfirst($diaStr).' ('.$fechaDestino->format('d/m/Y').')',
                'periodo' => 'dia_semana',
            ];
        }

        // 5. "esta semana"
        if (preg_match('/\b(esta semana|semana actual)\b/iu', $t)) {
            return [
                'inicio' => Carbon::now()->startOfWeek()->startOfDay(),
                'fin' => Carbon::now()->endOfWeek()->endOfDay(),
                'etiqueta' => 'Esta Semana ('.Carbon::now()->startOfWeek()->format('d/m').' — '.Carbon::now()->endOfWeek()->format('d/m').')',
                'periodo' => 'esta_semana',
            ];
        }

        // 6. "la semana pasada" / "semana anterior"
        if (preg_match('/\b(semana pasada|semana anterior|la semana pasada)\b/iu', $t)) {
            $inicio = Carbon::now()->subWeek()->startOfWeek()->startOfDay();
            $fin = Carbon::now()->subWeek()->endOfWeek()->endOfDay();

            return [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => 'Semana Pasada ('.$inicio->format('d/m').' — '.$fin->format('d/m').')',
                'periodo' => 'semana_pasada',
            ];
        }

        // 7. "este mes"
        if (preg_match('/\b(este mes|mes actual)\b/iu', $t)) {
            return [
                'inicio' => Carbon::now()->startOfMonth()->startOfDay(),
                'fin' => Carbon::now()->endOfMonth()->endOfDay(),
                'etiqueta' => 'Este Mes ('.Carbon::now()->format('m/Y').')',
                'periodo' => 'este_mes',
            ];
        }

        // 8. "el mes pasado"
        if (preg_match('/\b(mes pasado|mes anterior)\b/iu', $t)) {
            $inicio = Carbon::now()->subMonth()->startOfMonth()->startOfDay();
            $fin = Carbon::now()->subMonth()->endOfMonth()->endOfDay();

            return [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => 'Mes Pasado ('.$inicio->format('m/Y').')',
                'periodo' => 'mes_pasado',
            ];
        }

        // 9. "últimos/ultimas N días | semanas | meses" -> normalizado a ultimos_N_dias (1..365)
        if (preg_match('/[uú]ltimas?\s+(\d{1,3})\s+semanas?/iu', $t, $m)) {
            $dias = max(1, min(365, ((int) $m[1]) * 7));
            $inicio = Carbon::now()->subDays($dias)->startOfDay();
            $fin = Carbon::now()->endOfDay();

            return [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => "Últimos {$dias} días",
                'periodo' => "ultimos_{$dias}_dias",
            ];
        }
        if (preg_match('/[uú]ltimos?\s+(\d{1,3})\s+mes(?:es)?\b/iu', $t, $m)) {
            $dias = max(1, min(365, ((int) $m[1]) * 30));
            $inicio = Carbon::now()->subDays($dias)->startOfDay();
            $fin = Carbon::now()->endOfDay();

            return [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => "Últimos {$dias} días",
                'periodo' => "ultimos_{$dias}_dias",
            ];
        }
        if (preg_match('/[uú]ltimos?\s+(\d{1,3})\s+d[ií]as?/iu', $t, $m)) {
            $dias = max(1, min(365, (int) $m[1]));
            $inicio = Carbon::now()->subDays($dias)->startOfDay();
            $fin = Carbon::now()->endOfDay();

            return [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => "Últimos {$dias} días",
                'periodo' => "ultimos_{$dias}_dias",
            ];
        }

        // 9b. "hace N días" -> día único de hace N días
        if (preg_match('/\bhace\s+(\d{1,3})\s+d[ií]as?\b/iu', $t, $m)) {
            $dias = max(1, min(365, (int) $m[1]));
            $fecha = Carbon::now()->subDays($dias);

            return [
                'inicio' => $fecha->copy()->startOfDay(),
                'fin' => $fecha->copy()->endOfDay(),
                'etiqueta' => 'Hace '.$dias.($dias === 1 ? ' día' : ' días').' ('.$fecha->format('d/m/Y').')',
                'periodo' => "hace_{$dias}_dias",
            ];
        }

        return null;
    }

    /**
     * Consulta profunda de una Caja Registradora específica con desglose de turnos, pedidos y métodos de pago.
     */
    public function ejecutarConsultaVentasCaja(User $usuario, Caja $caja, ?array $rangoFecha = null, ?string $mensajeOriginal = null): array
    {
        // Determinar pedidos asociados a esta caja
        $pedidosQuery = Pedido::query()
            ->where('sucursal_id', $usuario->sucursal_id)
            ->whereHas('turnoCaja', fn ($q) => $q->where('caja_id', $caja->id))
            ->where('estado', 'pagado');

        if ($rangoFecha) {
            $pedidosQuery->whereBetween('pagado_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            $etiquetaFiltro = $rangoFecha['etiqueta'];
        } else {
            $etiquetaFiltro = 'Histórico Reciente / Último Turno';
        }

        $pedidos = $pedidosQuery->orderBy('pagado_en', 'asc')->get();

        $totalVentas = (float) $pedidos->sum('total');
        $totalComandas = $pedidos->count();
        $ticketPromedio = $totalComandas > 0 ? round($totalVentas / $totalComandas, 2) : 0.0;

        // Desglose de métodos de pago
        $porEfectivo = (float) $pedidos->where('metodo_pago', 'efectivo')->sum('total');
        $porTarjeta = (float) $pedidos->where('metodo_pago', 'tarjeta')->sum('total');
        $porTransferencia = (float) $pedidos->where('metodo_pago', 'transferencia')->sum('total');

        // Último turno de la caja
        $ultimoTurno = TurnoCaja::where('caja_id', $caja->id)->latest('id')->first();

        $ventasFmt = number_format($totalVentas, 0, ',', '.');
        $ticketFmt = number_format($ticketPromedio, 0, ',', '.');

        $mensajeBase = "📊 **Auditoría de Ventas — {$caja->nombre} ({$caja->codigo}):**\n\n"
            ."• **Período Consultado:** {$etiquetaFiltro}\n"
            ."• **Ventas Totales:** \${$ventasFmt} COP\n"
            ."• **Comandas / Tickets:** {$totalComandas} pedidos cerrados\n"
            ."• **Ticket Promedio:** \${$ticketFmt} COP\n\n"
            ."💳 **Desglose de Formas de Pago:**\n"
            .'• Efectivo: $'.number_format($porEfectivo, 0, ',', '.').' COP'.($totalVentas > 0 ? ' ('.round(($porEfectivo / $totalVentas) * 100).'%)' : '')."\n"
            .'• Tarjetas (Débito/Crédito): $'.number_format($porTarjeta, 0, ',', '.').' COP'.($totalVentas > 0 ? ' ('.round(($porTarjeta / $totalVentas) * 100).'%)' : '')."\n"
            .'• Transferencias / Digital: $'.number_format($porTransferencia, 0, ',', '.').' COP'.($totalVentas > 0 ? ' ('.round(($porTransferencia / $totalVentas) * 100).'%)' : '')."\n\n";

        if ($ultimoTurno) {
            $estadoTurno = $ultimoTurno->estado === 'abierto' ? '🟢 Turno Abierto Actualmente' : '🔴 Turno Cerrado';
            $fechaApertura = $ultimoTurno->apertura_en ? $ultimoTurno->apertura_en->format('d/m H:i') : 'N/A';
            $cajeroNombre = $ultimoTurno->cajero?->name ?? 'Asignado';
            $mensajeBase .= "🔐 **Estado del Turno en Caja:** {$estadoTurno}\n"
                ."• Cajero: {$cajeroNombre} | Apertura: {$fechaApertura} | Fondo: \$".number_format((float) $ultimoTurno->monto_inicial, 0, ',', '.')." COP\n";
        }

        // Construir datos de gráfico interactivo para la caja
        $grafico = [
            'tipo' => 'bar',
            'titulo' => "Facturación por Comanda — {$caja->nombre}",
            'subtitulo' => "Detalle de pedidos pagados ({$totalComandas} comandas)",
            'etiquetas' => $pedidos->take(8)->map(fn ($p) => $p->codigo)->all() ?: ['Sin pedidos'],
            'valores' => $pedidos->take(8)->map(fn ($p) => (float) $p->total)->all() ?: [0],
            'valores_formateados' => $pedidos->take(8)->map(fn ($p) => '$'.number_format((float) $p->total, 0, ',', '.'))->all() ?: ['$0'],
            'colores' => ['#e0442e', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'],
            'unidad' => 'COP',
        ];

        // Consultar a Gemini si está activo para enriquecer con comentarios estratégicos
        $contexto = [
            'caja_nombre' => $caja->nombre,
            'caja_codigo' => $caja->codigo,
            'periodo' => $etiquetaFiltro,
            'ventas_totales' => $totalVentas,
            'comandas' => $totalComandas,
            'ticket_promedio' => $ticketPromedio,
            'desglose_pagos' => [
                'efectivo' => $porEfectivo,
                'tarjeta' => $porTarjeta,
                'transferencia' => $porTransferencia,
            ],
            'ultimo_turno' => $ultimoTurno ? [
                'estado' => $ultimoTurno->estado,
                'cajero' => $ultimoTurno->cajero?->name,
                'fondo_inicial' => (float) $ultimoTurno->monto_inicial,
            ] : null,
        ];

        $mensajeFinal = $this->consultarLlmSiDisponible($mensajeOriginal ?: 'Ventas en '.$caja->nombre, $contexto, $mensajeBase, $usuario->sucursal_id) ?? $mensajeBase;

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeFinal,
            'datos' => [
                'kpis' => [
                    'ventas' => $totalVentas,
                    'transacciones' => $totalComandas,
                    'ticket_promedio' => $ticketPromedio,
                    'variacion_ventas_pct' => 0.0,
                ],
                'grafico' => $grafico,
                'caja' => [
                    'id' => $caja->id,
                    'nombre' => $caja->nombre,
                    'codigo' => $caja->codigo,
                ],
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Módulo de Caja',
                'url' => '/caja',
                'icono' => 'point_of_sale',
            ],
        ];
    }

    /**
     * Consulta de ventas por período o día específico (ej. "ventas del martes de esta semana", "ayer", "ultimos 15 dias").
     */
    public function ejecutarConsultaVentasPeriodo(User $usuario, array $rangoFecha, ?string $mensajeOriginal = null): array
    {
        $inicio = $rangoFecha['inicio'];
        $fin = $rangoFecha['fin'];
        $etiqueta = $rangoFecha['etiqueta'];
        $periodoKey = Str::lower((string) ($rangoFecha['periodo'] ?? ''));

        // Consultar pedidos pagados en el rango
        $pedidos = Pedido::query()
            ->where('sucursal_id', $usuario->sucursal_id)
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$inicio, $fin])
            ->get();

        $totalVentas = (float) $pedidos->sum('total');
        $transacciones = $pedidos->count();
        $ticketPromedio = $transacciones > 0 ? round($totalVentas / $transacciones, 2) : 0.0;

        $ventasFmt = number_format($totalVentas, 0, ',', '.');
        $ticketFmt = number_format($ticketPromedio, 0, ',', '.');

        // ── Rango dinámico ultimos_N_dias: gráfico de evolución diaria del propio período ──
        $diasDinamicos = null;
        if (preg_match('/^ultimos_(\d{1,3})_dias$/', $periodoKey, $m)) {
            $diasDinamicos = max(1, min(365, (int) $m[1]));
        } elseif ($periodoKey === 'ultimos_dias') {
            $diasDinamicos = max(1, (int) Carbon::parse($inicio)->diffInDays(Carbon::parse($fin)) + 1);
            $diasDinamicos = min(365, $diasDinamicos);
        }

        if ($diasDinamicos !== null) {
            $etiquetas = [];
            $valores = [];
            $mapa = [];
            for ($i = $diasDinamicos - 1; $i >= 0; $i--) {
                $fecha = Carbon::now()->subDays($i);
                $key = $fecha->toDateString();
                $mapa[$key] = 0.0;
                $etiquetas[] = $fecha->format('d/m');
            }
            foreach ($pedidos as $p) {
                if ($p->pagado_en) {
                    $k = Carbon::parse($p->pagado_en)->toDateString();
                    if (array_key_exists($k, $mapa)) {
                        $mapa[$k] += (float) $p->total;
                    }
                }
            }
            $valores = array_values($mapa);
            // Si N > 31, agregar por semanas para no saturar el gráfico
            if ($diasDinamicos > 31) {
                $etiquetasSem = [];
                $valoresSem = [];
                $chunkEtiquetas = array_chunk($etiquetas, 7);
                $chunkValores = array_chunk($valores, 7);
                foreach ($chunkValores as $idx => $chunk) {
                    $valoresSem[] = array_sum($chunk);
                    $etiquetasSem[] = ($chunkEtiquetas[$idx][0] ?? '').'–'.end($chunkEtiquetas[$idx]);
                }
                $etiquetas = $etiquetasSem;
                $valores = $valoresSem;
                $subtitulo = 'Agregado semanal dentro del período';
            } else {
                $subtitulo = 'Evolución diaria de facturación';
            }

            $promedioDiario = $diasDinamicos > 0 ? round($totalVentas / $diasDinamicos, 2) : 0.0;
            $promedioFmt = number_format($promedioDiario, 0, ',', '.');
            $maxVal = $valores !== [] ? max($valores) : 0;
            $idxPico = $valores !== [] ? array_search($maxVal, $valores, true) : false;
            $diaPico = ($idxPico !== false && isset($etiquetas[$idxPico])) ? $etiquetas[$idxPico] : 'N/A';

            $mensajeBase = "📅 **Reporte de Ventas — {$etiqueta}:**\n\n"
                ."• **Facturación:** \${$ventasFmt} COP\n"
                ."• **Comandas / Tickets:** {$transacciones} pedidos cerrados\n"
                ."• **Ticket Promedio:** \${$ticketFmt} COP\n"
                ."• **Promedio diario:** \${$promedioFmt} COP\n\n";

            if ($totalVentas === 0.0) {
                $mensajeBase .= "💡 **Observación:** No se registraron pedidos cerrados en este período de {$diasDinamicos} días.\n\n";
            } else {
                $mensajeBase .= "🎉 **Operación activa:** {$transacciones} servicios completados en los últimos {$diasDinamicos} días (pico el **{$diaPico}**).\n\n";
            }

            $grafico = [
                'tipo' => 'bar',
                'titulo' => "Evolución de Ventas — {$etiqueta}",
                'subtitulo' => $subtitulo,
                'etiquetas' => $etiquetas,
                'valores' => $valores,
                'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valores),
                'colores' => array_map(fn ($v) => $v > 0 ? '#e0442e' : '#6b7280', $valores),
                'unidad' => 'COP',
            ];

            $contexto = [
                'periodo_consultado' => $etiqueta,
                'dias' => $diasDinamicos,
                'ventas_periodo' => $totalVentas,
                'pedidos_periodo' => $transacciones,
                'ticket_promedio' => $ticketPromedio,
                'promedio_diario' => $promedioDiario,
                'dia_pico' => $diaPico,
            ];

            $mensajeFinal = $this->consultarLlmSiDisponible($mensajeOriginal ?: 'Ventas de '.$etiqueta, $contexto, $mensajeBase, $usuario->sucursal_id) ?? $mensajeBase;

            return [
                'tipo' => 'ventas',
                'mensaje' => $mensajeFinal,
                'datos' => [
                    'kpis' => [
                        'ventas' => $totalVentas,
                        'transacciones' => $transacciones,
                        'ticket_promedio' => $ticketPromedio,
                        'variacion_ventas_pct' => 0.0,
                    ],
                    'grafico' => $grafico,
                    'periodo' => $etiqueta,
                ],
                'accion_rapida' => [
                    'etiqueta' => 'Abrir Módulo de Reportes',
                    'url' => '/reportes',
                    'icono' => 'analytics',
                ],
            ];
        }

        // Contexto semanal complementario
        $inicioSemana = Carbon::now()->startOfWeek()->startOfDay();
        $finSemana = Carbon::now()->endOfWeek()->endOfDay();

        $pedidosSemana = Pedido::query()
            ->where('sucursal_id', $usuario->sucursal_id)
            ->where('estado', 'pagado')
            ->whereBetween('pagado_en', [$inicioSemana, $finSemana])
            ->get(['total', 'pagado_en']);

        $diasSemanaLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $valoresDiasSemana = array_fill(0, 7, 0.0);

        foreach ($pedidosSemana as $p) {
            if ($p->pagado_en) {
                $idx = Carbon::parse($p->pagado_en)->dayOfWeekIso - 1;
                if (isset($valoresDiasSemana[$idx])) {
                    $valoresDiasSemana[$idx] += (float) $p->total;
                }
            }
        }

        $totalSemana = array_sum($valoresDiasSemana);
        $totalSemanaFmt = number_format($totalSemana, 0, ',', '.');

        $mensajeBase = "📅 **Reporte de Ventas — {$etiqueta}:**\n\n"
            ."• **Facturación:** \${$ventasFmt} COP\n"
            ."• **Comandas / Tickets:** {$transacciones} pedidos cerrados\n"
            ."• **Ticket Promedio:** \${$ticketFmt} COP\n\n";

        if ($totalVentas === 0.0) {
            $mensajeBase .= '💡 **Observación:** No se registraron pedidos cerrados en esa fecha específica. '
                ."Durante esta semana en curso se registra un acumulado total de **\${$totalSemanaFmt} COP** en el restaurante.\n\n";
        } else {
            $mensajeBase .= "🎉 **Operación activa:** Se registraron {$transacciones} servicios completados con éxito durante este período.\n\n";
        }

        // Gráfico semanal comparativo
        $grafico = [
            'tipo' => 'bar',
            'titulo' => "Flujo de Ventas de la Semana ({$inicioSemana->format('d/m')} — {$finSemana->format('d/m')})",
            'subtitulo' => 'Comparativa diaria de facturación semanal',
            'etiquetas' => $diasSemanaLabels,
            'valores' => $valoresDiasSemana,
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valoresDiasSemana),
            'colores' => array_map(function ($idx, $val) {
                return $val > 0 ? '#10b981' : '#6b7280';
            }, array_keys($valoresDiasSemana), $valoresDiasSemana),
            'unidad' => 'COP',
        ];

        $contexto = [
            'periodo_consultado' => $etiqueta,
            'ventas_periodo' => $totalVentas,
            'pedidos_periodo' => $transacciones,
            'ticket_promedio' => $ticketPromedio,
            'resumen_semanal' => [
                'lunes' => $valoresDiasSemana[0],
                'martes' => $valoresDiasSemana[1],
                'miercoles' => $valoresDiasSemana[2],
                'jueves' => $valoresDiasSemana[3],
                'viernes' => $valoresDiasSemana[4],
                'sabado' => $valoresDiasSemana[5],
                'domingo' => $valoresDiasSemana[6],
            ],
            'total_semana' => $totalSemana,
        ];

        $mensajeFinal = $this->consultarLlmSiDisponible($mensajeOriginal ?: 'Ventas de '.$etiqueta, $contexto, $mensajeBase, $usuario->sucursal_id) ?? $mensajeBase;

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeFinal,
            'datos' => [
                'kpis' => [
                    'ventas' => $totalVentas,
                    'transacciones' => $transacciones,
                    'ticket_promedio' => $ticketPromedio,
                    'variacion_ventas_pct' => 0.0,
                ],
                'grafico' => $grafico,
                'periodo' => $etiqueta,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Módulo de Reportes',
                'url' => '/reportes',
                'icono' => 'analytics',
            ],
        ];
    }

    /**
     * Consulta de desglose por métodos de pago con gráfico doughnut/barras.
     */
    public function ejecutarConsultaMetodosPago(User $usuario, ?array $rangoFecha = null, ?string $mensajeOriginal = null): array
    {
        $query = Pedido::query()
            ->where('sucursal_id', $usuario->sucursal_id)
            ->where('estado', 'pagado');

        if ($rangoFecha) {
            $query->whereBetween('pagado_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            $etiqueta = $rangoFecha['etiqueta'];
        } else {
            $etiqueta = 'Semana Actual';
            $query->whereBetween('pagado_en', [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfWeek()->endOfDay()]);
        }

        $desglose = (clone $query)
            ->select('metodo_pago', DB::raw('SUM(total) as total_monto'), DB::raw('COUNT(*) as total_pedidos'))
            ->groupBy('metodo_pago')
            ->get();

        $totalGeneral = (float) $desglose->sum('total_monto');
        $pedidosTotal = (int) $desglose->sum('total_pedidos');

        $labels = [];
        $valores = [];
        $porcentajes = [];
        $colores = [
            'efectivo' => '#10b981',
            'tarjeta' => '#3b82f6',
            'transferencia' => '#8b5cf6',
            'mixto' => '#f59e0b',
        ];
        $coloresLista = [];

        $mensajeBase = "💳 **Desglose de Métodos de Pago — {$etiqueta}:**\n\n"
            .'• **Recaudo Total:** $'.number_format($totalGeneral, 0, ',', '.')." COP ({$pedidosTotal} comandas)\n\n";

        foreach ($desglose as $item) {
            $metodo = $item->metodo_pago ?: 'otros';
            $monto = (float) $item->total_monto;
            $pct = $totalGeneral > 0 ? round(($monto / $totalGeneral) * 100, 1) : 0.0;
            $nombreFmt = Str::headline($metodo);

            $labels[] = $nombreFmt;
            $valores[] = $monto;
            $porcentajes[] = $pct;
            $coloresLista[] = $colores[$metodo] ?? '#6b7280';

            $mensajeBase .= "• **{$nombreFmt}:** \$".number_format($monto, 0, ',', '.')." COP ({$pct}% — {$item->total_pedidos} tickets)\n";
        }

        if ($desglose->isEmpty()) {
            $labels = ['Sin transacciones'];
            $valores = [0];
            $porcentajes = [100];
            $coloresLista = ['#6b7280'];
            $mensajeBase .= "No se registran pagos en el período seleccionado.\n";
        }

        $grafico = [
            'tipo' => 'doughnut',
            'titulo' => "Participación por Forma de Pago — {$etiqueta}",
            'subtitulo' => 'Distribución porcentual de recaudos',
            'etiquetas' => $labels,
            'valores' => $valores,
            'porcentajes' => $porcentajes,
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valores),
            'colores' => $coloresLista,
            'unidad' => 'COP',
        ];

        $contexto = [
            'periodo' => $etiqueta,
            'recaudo_total' => $totalGeneral,
            'desglose' => $desglose->toArray(),
        ];

        $mensajeFinal = $this->consultarLlmSiDisponible($mensajeOriginal ?: 'Métodos de pago en '.$etiqueta, $contexto, $mensajeBase, $usuario->sucursal_id) ?? $mensajeBase;

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeFinal,
            'datos' => [
                'kpis' => [
                    'ventas' => $totalGeneral,
                    'transacciones' => $pedidosTotal,
                    'ticket_promedio' => $pedidosTotal > 0 ? round($totalGeneral / $pedidosTotal, 2) : 0.0,
                    'variacion_ventas_pct' => 0.0,
                ],
                'grafico' => $grafico,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Ver Cuadre de Caja',
                'url' => '/caja',
                'icono' => 'account_balance_wallet',
            ],
        ];
    }

    /**
     * Consulta de platos y categorías más vendidas.
     */
    public function ejecutarConsultaTopProductos(User $usuario, ?array $rangoFecha = null, int $limite = 5, ?string $mensajeOriginal = null): array
    {
        $query = ItemPedido::query()
            ->join('pedidos', 'items_pedido.pedido_id', '=', 'pedidos.id')
            ->join('productos', 'items_pedido.producto_id', '=', 'productos.id')
            ->where('pedidos.sucursal_id', $usuario->sucursal_id)
            ->where('pedidos.estado', 'pagado');

        if ($rangoFecha) {
            $query->whereBetween('pedidos.pagado_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            $etiqueta = $rangoFecha['etiqueta'];
        } else {
            $etiqueta = 'Histórico Acumulado';
        }

        $top = (clone $query)
            ->select('productos.nombre as producto', DB::raw('SUM(items_pedido.cantidad) as total_cantidad'), DB::raw('SUM(items_pedido.subtotal) as total_ventas'))
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('total_ventas')
            ->limit($limite)
            ->get();

        $labels = [];
        $valores = [];
        $mensajeBase = "🍣 **Top {$limite} Platos Más Vendidos — {$etiqueta}:**\n\n";

        foreach ($top as $idx => $item) {
            $cant = (int) $item->total_cantidad;
            $monto = (float) $item->total_ventas;
            $labels[] = $item->producto;
            $valores[] = $monto;

            $medalla = match ($idx) {
                0 => '🥇',
                1 => '🥈',
                2 => '🥉',
                default => '•',
            };

            $mensajeBase .= "{$medalla} **{$item->producto}:** {$cant} unidades vendidas (\$".number_format($monto, 0, ',', '.')." COP)\n";
        }

        if ($top->isEmpty()) {
            $mensajeBase .= "No hay registros de ventas de ítems para este período.\n";
            $labels = ['Sin datos'];
            $valores = [0];
        }

        $grafico = [
            'tipo' => 'ranking',
            'titulo' => "Platos Estrella por Facturación — {$etiqueta}",
            'subtitulo' => 'Ranking de productos con mayor contribución',
            'etiquetas' => $labels,
            'valores' => $valores,
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valores),
            'colores' => ['#e0442e', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6'],
            'unidad' => 'COP',
        ];

        $contexto = [
            'periodo' => $etiqueta,
            'top_platos' => $top->toArray(),
        ];

        $mensajeFinal = $this->consultarLlmSiDisponible($mensajeOriginal ?: 'Top platos más vendidos', $contexto, $mensajeBase, $usuario->sucursal_id) ?? $mensajeBase;

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeFinal,
            'datos' => [
                'grafico' => $grafico,
                'top_productos' => $top->toArray(),
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Menú y Carta',
                'url' => '/menu',
                'icono' => 'restaurant_menu',
            ],
        ];
    }

    /**
     * Consulta de productividad y ventas por mesero.
     */
    public function ejecutarConsultaMeseros(User $usuario, ?array $rangoFecha = null, ?string $mensajeOriginal = null): array
    {
        $query = Pedido::query()
            ->join('users', 'pedidos.mesero_id', '=', 'users.id')
            ->where('pedidos.sucursal_id', $usuario->sucursal_id)
            ->where('pedidos.estado', 'pagado');

        if ($rangoFecha) {
            $query->whereBetween('pedidos.pagado_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            $etiqueta = $rangoFecha['etiqueta'];
        } else {
            $etiqueta = 'Semana Actual';
            $query->whereBetween('pedidos.pagado_en', [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfWeek()->endOfDay()]);
        }

        $meseros = (clone $query)
            ->select('users.name as mesero', DB::raw('SUM(pedidos.total) as total_ventas'), DB::raw('COUNT(pedidos.id) as comandas'), DB::raw('SUM(COALESCE(pedidos.propina, 0)) as total_propinas'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_ventas')
            ->get();

        $labels = [];
        $valores = [];
        $mensajeBase = "👨‍🍳 **Rendimiento de Equipo de Salón / Meseros — {$etiqueta}:**\n\n";

        foreach ($meseros as $idx => $m) {
            $ventas = (float) $m->total_ventas;
            $comandas = (int) $m->comandas;
            $propinas = (float) $m->total_propinas;
            $labels[] = $m->mesero;
            $valores[] = $ventas;

            $medalla = match ($idx) {
                0 => '🌟',
                1 => '⭐',
                default => '•',
            };

            $mensajeBase .= "{$medalla} **{$m->mesero}:** \$".number_format($ventas, 0, ',', '.')." COP ({$comandas} comandas | Propinas: \$".number_format($propinas, 0, ',', '.').")\n";
        }

        if ($meseros->isEmpty()) {
            $mensajeBase .= "No se registran comandas asociadas a meseros en este período.\n";
            $labels = ['Sin datos'];
            $valores = [0];
        }

        $grafico = [
            'tipo' => 'bar',
            'titulo' => "Ventas por Mesero — {$etiqueta}",
            'subtitulo' => 'Facturación generada en salón',
            'etiquetas' => $labels,
            'valores' => $valores,
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valores),
            'colores' => ['#e0442e', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6'],
            'unidad' => 'COP',
        ];

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeBase,
            'datos' => [
                'grafico' => $grafico,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Módulo de Salón',
                'url' => '/pos',
                'icono' => 'table_bar',
            ],
        ];
    }

    /**
     * Gráfico general comparativo (ej. Ventas por Cajas o Días de la semana).
     */
    public function ejecutarConsultaGraficoGeneral(User $usuario, ?array $rangoFecha = null, ?string $mensajeOriginal = null): array
    {
        $cajas = Caja::where('sucursal_id', $usuario->sucursal_id)->where('activa', true)->get();
        $labels = [];
        $valores = [];
        $etiqueta = $rangoFecha['etiqueta'] ?? 'Histórico acumulado';

        foreach ($cajas as $caja) {
            $query = Pedido::where('sucursal_id', $usuario->sucursal_id)
                ->where('estado', 'pagado')
                ->whereHas('turnoCaja', fn ($q) => $q->where('caja_id', $caja->id));
            if ($rangoFecha) {
                $query->whereBetween('pagado_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            }
            $total = (float) $query->sum('total');

            $labels[] = $caja->nombre;
            $valores[] = $total;
        }

        $grafico = [
            'tipo' => 'bar',
            'titulo' => 'Facturación Comparativa por Cajas — '.$etiqueta,
            'subtitulo' => 'Rendimiento por terminal de cobro',
            'etiquetas' => $labels ?: ['Caja Principal'],
            'valores' => $valores ?: [0],
            'valores_formateados' => array_map(fn ($v) => '$'.number_format($v, 0, ',', '.'), $valores ?: [0]),
            'colores' => ['#e0442e', '#f59e0b', '#10b981', '#3b82f6'],
            'unidad' => 'COP',
        ];

        $mensaje = "📈 **Gráfico Ejecutivo de Facturación por Terminales de Cobro ({$etiqueta}):**\n\n"
            .'He generado la visualización comparativa de ventas entre las distintas cajas de tu restaurante. '
            .'Revisa la gráfica interactiva a continuación:';

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensaje,
            'datos' => [
                'grafico' => $grafico,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Ver Reportes Avanzados',
                'url' => '/reportes',
                'icono' => 'monitoring',
            ],
        ];
    }

    /**
     * Generación de imagen o tarjeta infográfica ejecutiva de estadísticas.
     */
    public function ejecutarGeneracionInfografia(User $usuario, ?string $mensaje = null): array
    {
        $kpis = $this->dashboardService->kpisGenerales('hoy', $usuario->sucursal_id);
        $top = $this->dashboardService->topProductos('hoy', 3, $usuario->sucursal_id);

        $ventasFmt = number_format($kpis['ventas'], 0, ',', '.');
        $ticketFmt = number_format($kpis['ticket_promedio'], 0, ',', '.');
        $transacciones = $kpis['transacciones'];

        $mensajeRespuesta = "🖼️ **Infografía Ejecutiva Generada — RestoMaster Live Analytics:**\n\n"
            ."He compilado un informe visual de alto impacto con las estadísticas actuales del restaurante:\n\n"
            ."• 💵 **Facturación:** \${$ventasFmt} COP\n"
            ."• 🧾 **Tickets:** {$transacciones} pedidos cerrados\n"
            ."• 🎯 **Ticket Promedio:** \${$ticketFmt} COP\n\n"
            .'Puedes visualizar la tarjeta infográfica enriquecida en este panel o abrir el módulo de reportes para exportar a PDF / Excel.';

        return [
            'tipo' => 'infografia',
            'mensaje' => $mensajeRespuesta,
            'datos' => [
                'infografia' => [
                    'titulo' => 'Tablero Ejecutivo RestoMaster',
                    'subtitulo' => 'Reporte Operativo y Financiero en Vivo',
                    'fecha' => Carbon::now()->format('d/m/Y H:i'),
                    'ventas' => $kpis['ventas'],
                    'transacciones' => $transacciones,
                    'ticket_promedio' => $kpis['ticket_promedio'],
                    'top_plato' => $top[0]->producto ?? 'Sushi Clásico',
                ],
                'kpis' => $kpis,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Reportes para Exportar',
                'url' => '/reportes',
                'icono' => 'download',
            ],
        ];
    }

    /**
     * Fase 9.2 — Análisis ejecutivo de un reporte comparativo para admin/gerente.
     *
     * Núcleo determinista offline (tendencia + recomendaciones basadas en
     * datos reales) con enriquecimiento opcional del LLM cuando hay API.
     */
    public function analizarReporte(array $params, User $usuario): array
    {
        if (! $usuario->isAdmin() && ! $usuario->isGerente()) {
            throw new AuthorizationException('Solo administradores y gerentes acceden al análisis con IA.');
        }

        $desde = (string) ($params['desde'] ?? '');
        $hasta = (string) ($params['hasta'] ?? '');
        $comparar = filter_var($params['comparar'] ?? true, FILTER_VALIDATE_BOOLEAN);

        try {
            $inicio = Carbon::parse($desde)->startOfDay();
            $fin = Carbon::parse($hasta)->endOfDay();
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Rango de fechas no válido.');
        }

        if ($fin->lessThan($inicio)) {
            throw new \InvalidArgumentException('La fecha final debe ser posterior a la inicial.');
        }

        if ($inicio->diffInDays($fin) > 366) {
            throw new \InvalidArgumentException('El rango no puede superar los 366 días.');
        }

        $comparativos = app(ReportesComparativosService::class);
        $sucursalId = $usuario->sucursal_id;

        $desdeB = null;
        $hastaB = null;
        if ($comparar && ! empty($params['desde_b']) && ! empty($params['hasta_b'])) {
            $desdeB = (string) $params['desde_b'];
            $hastaB = (string) $params['hasta_b'];
            $comparativa = $comparativos->comparar($desde, $hasta, $desdeB, $hastaB, $sucursalId);
        } elseif ($comparar) {
            [$desdeB, $hastaB] = $comparativos->periodoAnteriorAutomatico($desde, $hasta);
            $comparativa = $comparativos->comparar($desde, $hasta, $desdeB, $hastaB, $sucursalId);
        } else {
            $kpis = $comparativos->kpisPeriodo($desde, $hasta, $sucursalId);
            $comparativa = [
                'a' => $kpis, 'b' => $kpis,
                'delta_ventas_pct' => 0.0, 'delta_comandas_pct' => 0.0,
                'delta_ticket' => 0.0, 'delta_devoluciones_pct' => 0.0,
            ];
        }

        $deltaVentas = (float) $comparativa['delta_ventas_pct'];
        $tendencia = $deltaVentas > 5 ? 'crecimiento' : ($deltaVentas < -5 ? 'caída' : 'meseta');

        $ventasFmt = number_format((float) $comparativa['a']['ventas'], 0, ',', '.');
        $transacciones = (int) $comparativa['a']['comandas'];

        $topProductos = $comparativos->topPeriodo($desde, $hasta, 5, $sucursalId);
        $topPlato = $topProductos[0]['producto'] ?? 'Sin datos';

        $reporteService = app(ReporteService::class);
        $serieVentas = $reporteService->datosGraficaVentas($desde, $hasta);
        $serieAnteriorVentas = ($comparar && $desdeB && $hastaB)
            ? $reporteService->datosGraficaVentas($desdeB, $hastaB)
            : null;

        $distribucion = $reporteService->distribucionCanalesYMetodos($desde, $hasta);

        $lenA = count($serieVentas['ventas'] ?? []);
        $lenB = $serieAnteriorVentas ? count($serieAnteriorVentas['ventas'] ?? []) : 0;
        $maxLen = max($lenA, $lenB);

        $graficasIa = [
            'tendencia_temporal' => [
                'etiquetas' => ($maxLen > 0 && $serieAnteriorVentas)
                    ? array_map(fn ($i) => 'Día '.($i + 1), range(0, $maxLen - 1))
                    : ($serieVentas['etiquetas'] ?? []),
                'serie_a' => $serieVentas['ventas'] ?? [],
                'serie_b' => $serieAnteriorVentas ? ($serieAnteriorVentas['ventas'] ?? []) : [],
                'label_a' => "Período A ({$desde} — {$hasta})",
                'label_b' => $desdeB ? "Período B ({$desdeB} — {$hastaB})" : 'Período Anterior',
            ],
            'top_productos' => [
                'nombres' => array_map(fn ($p) => Str::limit($p['producto'], 28), $topProductos),
                'ventas' => array_map(fn ($p) => (float) $p['total_ventas'], $topProductos),
                'cantidades' => array_map(fn ($p) => (int) $p['cantidad'], $topProductos),
            ],
            'canales' => [
                'etiquetas' => $distribucion['canales']['etiquetas'] ?? [],
                'series' => $distribucion['canales']['series'] ?? [],
            ],
            'comparativa_kpis' => [
                'etiquetas' => ['Facturación ($)', 'Ticket Promedio ($)', 'Comandas (uds)'],
                'label_a' => "Período A ({$desde})",
                'label_b' => $desdeB ? "Período B ({$desdeB})" : 'Período B',
                'valores_a' => [
                    (float) $comparativa['a']['ventas'],
                    (float) $comparativa['a']['ticket_promedio'],
                    (int) $comparativa['a']['comandas'],
                ],
                'valores_b' => [
                    (float) $comparativa['b']['ventas'],
                    (float) $comparativa['b']['ticket_promedio'],
                    (int) $comparativa['b']['comandas'],
                ],
            ],
        ];

        $resumenBase = "📊 **Análisis Ejecutivo ({$desde} — {$hasta}):**\n\n"
            ."El periodo cerró con **\${$ventasFmt} COP** en {$transacciones} comandas, "
            .($comparar ? "frente al periodo de comparación ({$comparativa['b']['desde']} — {$comparativa['b']['hasta']}). " : '')
            ."La variación de ventas fue de **{$deltaVentas}%**, lo que indica una etapa de **{$tendencia}**. "
            ."El plato líder del periodo fue **{$topPlato}**.\n\n"
            .'Ticket promedio de **$'.number_format((float) $comparativa['a']['ticket_promedio'], 0, ',', '.').' COP '
            .'(Δ $'.number_format((float) $comparativa['delta_ticket'], 0, ',', '.').' COP).';

        $resumenFinal = $this->consultarLlmSiDisponible(
            "Analiza este reporte comparativo del restaurante: {$desde} al {$hasta}",
            ['comparativa' => $comparativa, 'tendencia' => $tendencia, 'top_plato' => $topPlato],
            $resumenBase,
            $sucursalId
        ) ?? $resumenBase;

        $recomendaciones = $this->recomendacionesReporte($comparativa, $tendencia, $topPlato);

        return [
            'tipo' => 'reporte_ia',
            'mensaje' => $resumenFinal,
            'datos' => [
                'tendencia' => $tendencia,
                'delta_ventas_pct' => $deltaVentas,
                'recomendaciones' => $recomendaciones,
                'comparativa' => $comparativa,
                'graficas' => $graficasIa,
                'infografia' => [
                    'titulo' => 'Informe Ejecutivo Comparativo',
                    'subtitulo' => "{$desde} — {$hasta}",
                    'fecha' => Carbon::now()->format('d/m/Y H:i'),
                    'ventas' => $comparativa['a']['ventas'],
                    'transacciones' => $transacciones,
                    'ticket_promedio' => $comparativa['a']['ticket_promedio'],
                    'delta_ventas_pct' => $deltaVentas,
                    'tendencia' => $tendencia,
                    'top_plato' => $topPlato,
                ],
            ],
            'accion_rapida' => [
                'etiqueta' => 'Descargar Informe PDF',
                'url' => '/reportes/informe-ejecutivo',
                'icono' => 'download',
            ],
        ];
    }

    /**
     * Tres recomendaciones operativas deterministas según los datos.
     *
     * @return array<int, string>
     */
    protected function recomendacionesReporte(array $comparativa, string $tendencia, string $topPlato): array
    {
        $recs = [];

        $recs[] = match ($tendencia) {
            'crecimiento' => "Capitaliza el crecimiento ({$comparativa['delta_ventas_pct']}%): refuerza inventario del plato líder ({$topPlato}) y replica la mezcla de días fuertes en la programación de personal.",
            'caída' => "Frena la caída ({$comparativa['delta_ventas_pct']}%): activa una promoción de rescate entre semana y revisa dotación en las franjas de menor venta antes de recortar costos.",
            default => "Rompe la meseta ({$comparativa['delta_ventas_pct']}%): prueba un especial de fin de semana con {$topPlato} como gancho y mide el ticket promedio durante 7 días.",
        };

        $recs[] = $comparativa['a']['ticket_promedio'] < $comparativa['b']['ticket_promedio']
            ? 'El ticket promedio bajó $'.number_format(abs((float) $comparativa['delta_ticket']), 0, ',', '.').': entrena sugerencia de acompañamientos y postres en cada comanda.'
            : 'El ticket promedio se sostiene: documenta qué vende el mejor turno y conviértelo en guion de venta para todo el salón.';

        $recs[] = (float) $comparativa['a']['devoluciones'] > 0
            ? 'Hay $'.number_format((float) $comparativa['a']['devoluciones'], 0, ',', '.').' en devoluciones: audita los motivos en caja y refuerza control de calidad en cocina y barra.'
            : 'Sin devoluciones en el periodo: mantén el control de calidad y reconoce públicamente al turno con cero errores.';

        return array_values(array_slice($recs, 0, 3));
    }

    /**
     * Consulta conversacional libre a Gemini cuando el mensaje es una pregunta estratégica o no estructurada.
     */
    public function ejecutarConsultaConversacionalLlm(string $mensaje, User $usuario): ?array
    {
        $config = $this->obtenerConfiguracionIa($usuario->sucursal_id);
        if (! $config || app()->environment('testing')) {
            return null;
        }

        $apiKey = $config->obtenerApiKeyIa();
        if (empty($apiKey) || str_starts_with($apiKey, 'dummy')) {
            return null;
        }

        $contexto = $this->generarContextoOperativoGeneral($usuario->sucursal_id);
        $modelo = $config->ia_modelo ?: 'gemini-flash-lite-latest';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

        $systemInstruction = "Eres el Copiloto Ejecutivo IA de RestoMaster. Actúas con la misma calidez, inteligencia conversacional y precisión analítica de Gemini, especializado como Director de Operaciones y Analista Financiero del restaurante.\n"
            ."Tu misión es responder con empatía, cercanía ejecutiva, exactitud y tono profesional en español.\n"
            ."Usa los datos en vivo provistos en el contexto para responder preguntas sobre cajas, ventas, carta, insumos, clientes o recomendaciones operativas.\n"
            .'Utiliza formato Markdown limpio con negritas y emojis pertinentes para una lectura ágil en dispositivos móviles y de escritorio.';

        $prompt = "El Administrador pregunta: \"{$mensaje}\"\n\nContexto en vivo del restaurante en la base de datos:\n".json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $genConfig = [
            'maxOutputTokens' => 800,
            'temperature' => 0.35,
        ];
        if (str_contains($modelo, '2.5')) {
            $genConfig['thinkingConfig'] = ['thinkingBudget' => 0];
        }

        try {
            $response = Http::withoutVerifying()
                ->retry(2, 300)
                ->timeout(10)
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => $genConfig,
                ]);

            if ($response->successful()) {
                $texto = $response->json('candidates.0.content.parts.0.text');
                if (! empty($texto)) {
                    return [
                        'tipo' => 'texto',
                        'mensaje' => trim($texto),
                        'accion_rapida' => [
                            'etiqueta' => 'Abrir Módulo de Reportes',
                            'url' => '/reportes',
                            'icono' => 'analytics',
                        ],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error en llamada conversacional libre de Copiloto a Gemini: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Consulta a Gemini para enriquecer con comentarios directivos los datos reales obtenidos de la base de datos.
     */
    protected function consultarLlmSiDisponible(string $preguntaUsuario, array $datosContexto, string $resumenBase, ?int $sucursalId = null): ?string
    {
        $config = $this->obtenerConfiguracionIa($sucursalId);
        if (! $config || app()->environment('testing')) {
            return null;
        }

        $apiKey = $config->obtenerApiKeyIa();
        if (empty($apiKey) || str_starts_with($apiKey, 'dummy')) {
            return null;
        }

        $modelo = $config->ia_modelo ?: 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

        $systemInstruction = 'Eres el Copiloto Ejecutivo IA de RestoMaster. Actúas con la inteligencia analítica de Gemini para el restaurante. Comunica al Administrador los resultados con tono ejecutivo y cercano, destacando cifras clave en negrita y agregando observaciones operativas útiles. Usa Markdown limpio con emojis.';

        $prompt = "El Administrador preguntó: \"{$preguntaUsuario}\"\n\n"
            ."Datos reales extraídos de la base de datos de RestoMaster:\n"
            .json_encode($datosContexto, JSON_UNESCAPED_UNICODE)."\n\n"
            .'Instrucciones: Redacta una respuesta ejecutiva, empática y precisa que responda exactamente a su pregunta con base en estos datos reales. '
            .'Si la cifra de ventas es $0 en la fecha o caja específica solicitada, indícalo claramente con respeto y menciona qué días o turnos cercanos sí tuvieron actividad.';

        try {
            $response = Http::withoutVerifying()
                ->retry(2, 300)
                ->timeout(10)
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 800,
                        'temperature' => 0.3,
                        'thinkingConfig' => ['thinkingBudget' => 0],
                    ],
                ]);

            if ($response->successful()) {
                $texto = $response->json('candidates.0.content.parts.0.text');
                if (! empty($texto)) {
                    return trim($texto);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error consultando Gemini en AdminAiCopilotService: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Genera un volcado de contexto operativo para que el LLM tenga conocimiento global de la sucursal.
     */
    protected function generarContextoOperativoGeneral(?int $sucursalId = null): array
    {
        $kpisHoy = $this->dashboardService->kpisGenerales('hoy', $sucursalId);
        $kpisSemana = $this->dashboardService->kpisGenerales('semana', $sucursalId);
        $top = $this->dashboardService->topProductos('semana', 5, $sucursalId);
        $alertas = $this->dashboardService->insumosEnAlerta(5);
        $cajas = Caja::all(['id', 'nombre', 'codigo', 'tipo', 'activa']);
        $turnosRecientes = TurnoCaja::with('cajero')->latest('id')->take(3)->get(['id', 'caja_id', 'user_id', 'estado', 'apertura_en', 'monto_inicial']);

        return [
            'fecha_actual' => Carbon::now()->format('Y-m-d H:i (l)'),
            'cajas_en_sistema' => $cajas->toArray(),
            'total_cajas_activas' => $cajas->where('activa', true)->count(),
            'turnos_recientes' => $turnosRecientes->toArray(),
            'kpis_hoy' => $kpisHoy,
            'kpis_semana' => $kpisSemana,
            'top_productos_semana' => $top,
            'insumos_criticos_alerta' => $alertas['total_alertas'],
        ];
    }

    /**
     * Resumen de ventas de hoy con datos de gráfico y botón a reportes.
     */
    public function ejecutarResumenVentas(User $usuario): array
    {
        $kpis = $this->dashboardService->kpisGenerales('hoy', $usuario->sucursal_id);
        $topProductos = $this->dashboardService->topProductos('hoy', 5, $usuario->sucursal_id);
        $ventasPorHora = $this->dashboardService->ventasPorHora('hoy', $usuario->sucursal_id);

        $franjasGrafico = collect($ventasPorHora)
            ->filter(fn ($f) => $f['hora_num'] >= 11 && $f['hora_num'] <= 23)
            ->values()
            ->all();

        $ventasFormateadas = number_format($kpis['ventas'], 0, ',', '.');
        $ticketPromedioFormateado = number_format($kpis['ticket_promedio'], 0, ',', '.');
        $transacciones = $kpis['transacciones'];

        $mensaje = "📊 **Estadísticas de Ventas de Hoy:**\n\n"
            ."• **Ventas Totales:** \${$ventasFormateadas} COP\n"
            ."• **Comandas / Tickets:** {$transacciones} pedidos cerrados\n"
            ."• **Ticket Promedio:** \${$ticketPromedioFormateado} COP\n"
            .'• **Variación vs Período Anterior:** '.($kpis['variacion_ventas_pct'] >= 0 ? "+{$kpis['variacion_ventas_pct']}% 📈" : "{$kpis['variacion_ventas_pct']}% 📉")."\n\n";

        if (! empty($topProductos)) {
            $mensaje .= "🏆 **Platos más vendidos hoy:**\n";
            foreach (collect($topProductos)->take(3) as $idx => $p) {
                // Acceso seguro compatible con objetos Eloquent y arrays planos
                $totalVentas = is_array($p) ? ($p['total_ventas'] ?? 0) : ($p->total_ventas ?? 0);
                $producto = is_array($p) ? ($p['producto'] ?? 'N/A') : ($p->producto ?? 'N/A');
                $cantidad = is_array($p) ? ($p['cantidad'] ?? 0) : ($p->cantidad ?? 0);
                $subtotalFormateado = number_format((float) $totalVentas, 0, ',', '.');
                $mensaje .= ($idx + 1).". **{$producto}** ({$cantidad} uds — \${$subtotalFormateado})\n";
            }
        }

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensaje,
            'datos' => [
                'kpis' => $kpis,
                'top_productos' => $topProductos,
                'grafico_horas' => $franjasGrafico,
                'grafico' => [
                    'tipo' => 'bar',
                    'titulo' => 'Flujo de Facturación por Hora',
                    'subtitulo' => 'Franjas de servicio (11:00 — 23:00)',
                    'etiquetas' => collect($franjasGrafico)->map(fn ($f) => $f['hora'])->all(),
                    'valores' => collect($franjasGrafico)->map(fn ($f) => $f['ventas'])->all(),
                    'valores_formateados' => collect($franjasGrafico)->map(fn ($f) => '$'.number_format($f['ventas'], 0, ',', '.'))->all(),
                    'colores' => array_fill(0, count($franjasGrafico), '#e0442e'),
                    'unidad' => 'COP',
                ],
            ],
            'accion_rapida' => [
                'etiqueta' => 'Abrir Módulo de Reportes',
                'url' => '/reportes',
                'icono' => 'analytics',
            ],
        ];
    }

    /**
     * Auditoría de inventario crítico.
     */
    public function ejecutarAuditoriaInventario(): array
    {
        $alertas = $this->dashboardService->insumosEnAlerta(8);
        $totalAlertas = $alertas['total_alertas'];
        $costoReposicion = number_format($alertas['costo_reposicion_estimado'], 0, ',', '.');

        if ($totalAlertas === 0) {
            return [
                'tipo' => 'inventario',
                'mensaje' => "✅ **Inventario en Óptimas Condiciones:**\n\nNo se registran insumos con stock por debajo del mínimo de seguridad. Todos los ingredientes y materias primas están abastecidos adecuadamente.",
                'datos' => [
                    'total_alertas' => 0,
                    'costo_reposicion' => 0,
                    'insumos' => [],
                ],
                'accion_rapida' => [
                    'etiqueta' => 'Ver Inventario General',
                    'url' => '/inventario',
                    'icono' => 'inventory_2',
                ],
            ];
        }

        $mensaje = "⚠️ **Auditoría de Insumos Críticos ({$totalAlertas} en alerta):**\n\n"
            .'Se detectaron insumos que requieren reposición urgente para no afectar la operación de cocina y barra. '
            ."Costo estimado de reposición: **\${$costoReposicion} COP**.\n\n";

        $insumosResumen = [];
        foreach ($alertas['agotados']->take(4) as $item) {
            $mensaje .= "🔴 **AGOTADO:** {$item->nombre} (Stock: {$item->stock_actual} {$item->unidad_medida} | Mín: {$item->stock_minimo})\n";
            $insumosResumen[] = [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'stock_actual' => (float) $item->stock_actual,
                'stock_minimo' => (float) $item->stock_minimo,
                'unidad' => $item->unidad_medida,
                'estado' => 'agotado',
                'proveedor' => $item->proveedor_nombre ?? $item->proveedor?->nombre ?? 'Sin proveedor',
                'telefono' => $item->proveedor_telefono ?? $item->proveedor?->telefono ?? null,
            ];
        }

        foreach ($alertas['criticos']->take(4) as $item) {
            $mensaje .= "🟡 **BAJO STOCK:** {$item->nombre} (Stock: {$item->stock_actual} {$item->unidad_medida} | Mín: {$item->stock_minimo})\n";
            $insumosResumen[] = [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'stock_actual' => (float) $item->stock_actual,
                'stock_minimo' => (float) $item->stock_minimo,
                'unidad' => $item->unidad_medida,
                'estado' => 'critico',
                'proveedor' => $item->proveedor_nombre ?? $item->proveedor?->nombre ?? 'Sin proveedor',
                'telefono' => $item->proveedor_telefono ?? $item->proveedor?->telefono ?? null,
            ];
        }

        return [
            'tipo' => 'inventario',
            'mensaje' => $mensaje,
            'datos' => [
                'total_alertas' => $totalAlertas,
                'costo_reposicion' => $alertas['costo_reposicion_estimado'],
                'insumos' => $insumosResumen,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Ir al Módulo de Inventario',
                'url' => '/inventario',
                'icono' => 'inventory_2',
            ],
        ];
    }

    /**
     * Generación inteligente de encuesta de satisfacción según el contexto del administrador.
     */
    public function ejecutarGeneracionEncuesta(string $instruccion): array
    {
        $instruccionLower = Str::lower($instruccion);

        if (Str::contains($instruccionLower, ['delivery', 'domicilio', 'pedido a domicilio'])) {
            $nombre = 'Experiencia y Empaque en Delivery';
            $disparador = 'post_delivery';
            $delay = 1;
            $preguntas = [
                [
                    'id' => 1,
                    'tipo' => 'estrellas',
                    'texto' => '¿Cómo calificarías el tiempo de llegada y puntualidad de tu pedido?',
                    'requerida' => true,
                ],
                [
                    'id' => 2,
                    'tipo' => 'estrellas',
                    'texto' => '¿La temperatura y frescura del sushi y tus platos fue la adecuada?',
                    'requerida' => true,
                ],
                [
                    'id' => 3,
                    'tipo' => 'si_no',
                    'texto' => '¿El empaque llegó en perfecto estado, sellado y sin derrames?',
                    'requerida' => true,
                ],
                [
                    'id' => 4,
                    'tipo' => 'texto',
                    'texto' => '¿Algún comentario adicional para nuestro equipo de cocina y repartidores?',
                    'requerida' => false,
                ],
            ];
        } elseif (Str::contains($instruccionLower, ['reserva', 'mesa', 'cumpleaños', 'evento'])) {
            $nombre = 'Calidad de Servicio en Salón & Reservas';
            $disparador = 'post_reserva';
            $delay = 2;
            $preguntas = [
                [
                    'id' => 1,
                    'tipo' => 'estrellas',
                    'texto' => '¿Cómo calificarías la atención y amabilidad de tu mesero?',
                    'requerida' => true,
                ],
                [
                    'id' => 2,
                    'tipo' => 'estrellas',
                    'texto' => '¿Qué tan satisfecho quedaste con el sabor y presentación de los platos?',
                    'requerida' => true,
                ],
                [
                    'id' => 3,
                    'tipo' => 'si_no',
                    'texto' => '¿Tu mesa estuvo lista a la hora acordada en la reserva?',
                    'requerida' => true,
                ],
                [
                    'id' => 4,
                    'tipo' => 'texto',
                    'texto' => '¿Nos recomendarías con amigos o familiares para ocasiones especiales?',
                    'requerida' => false,
                ],
            ];
        } else {
            $nombre = 'Satisfacción General Gastronómica';
            $disparador = 'post_pago';
            $delay = 1;
            $preguntas = [
                [
                    'id' => 1,
                    'tipo' => 'estrellas',
                    'texto' => '¿Cómo calificarías tu experiencia general el día de hoy?',
                    'requerida' => true,
                ],
                [
                    'id' => 2,
                    'tipo' => 'estrellas',
                    'texto' => '¿Qué tan satisfecho estás con la relación precio / calidad?',
                    'requerida' => true,
                ],
                [
                    'id' => 3,
                    'tipo' => 'si_no',
                    'texto' => '¿Volverías a visitarnos próximamente?',
                    'requerida' => true,
                ],
                [
                    'id' => 4,
                    'tipo' => 'texto',
                    'texto' => '¿Qué podríamos mejorar para que tu próxima experiencia sea de 5 estrellas?',
                    'requerida' => false,
                ],
            ];
        }

        $mensaje = "📋 **He diseñado una nueva encuesta de satisfacción:**\n\n"
            ."• **Título:** {$nombre}\n"
            .'• **Disparador Automático:** '.Str::headline($disparador)." (envío a las {$delay}h del evento)\n"
            .'• **Preguntas Diseñadas:** '.count($preguntas)." preguntas optimizadas (estrellas, dicotómica y campo abierto).\n\n"
            .'¿Deseas guardarla automáticamente en la base de datos o abrir el Diseñador Visual de CRM?';

        return [
            'tipo' => 'encuesta',
            'mensaje' => $mensaje,
            'datos' => [
                'nombre' => $nombre,
                'disparador' => $disparador,
                'delay_horas' => $delay,
                'preguntas' => $preguntas,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Guardar Encuesta en CRM',
                'accion' => 'guardar_encuesta',
                'payload' => [
                    'nombre' => $nombre,
                    'disparador' => $disparador,
                    'delay_horas' => $delay,
                    'preguntas' => $preguntas,
                ],
                'url' => '/crm',
                'icono' => 'playlist_add_check',
            ],
        ];
    }

    /**
     * Generación inteligente de propuesta de promoción gastronómica.
     */
    public function ejecutarGeneracionPromocion(string $instruccion): array
    {
        $instruccionLower = Str::lower($instruccion);

        if (Str::contains($instruccionLower, ['2x1', 'dos por uno'])) {
            $nombre = 'Martes y Miércoles de 2x1 en Rolls Clásicos';
            $tipoDescuento = 'dos_por_uno';
            $valorDescuento = 50.00;
            $diasSemana = ['martes', 'miercoles'];
            $codigo = 'ROLLS2X1';
            $descripcion = 'Lleva 2 de tus rolls clásicos preferidos y paga solo uno. Válido en salón y pedidos para llevar.';
        } elseif (Str::contains($instruccionLower, ['delivery', 'domicilio', 'envio'])) {
            $nombre = 'Envío Gratis + 15% OFF en Pedidos Web';
            $tipoDescuento = 'porcentaje';
            $valorDescuento = 15.00;
            $diasSemana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            $codigo = 'DELIVERYTOP';
            $descripcion = '15% de descuento en toda la carta a domicilio y delivery sin costo para compras superiores a $50.000.';
        } else {
            $nombre = 'Noche Gourmet: 20% OFF en Omakase & Tragos de Autor';
            $tipoDescuento = 'porcentaje';
            $valorDescuento = 20.00;
            $diasSemana = ['jueves', 'viernes', 'sabado'];
            $codigo = 'GOURMET20';
            $descripcion = 'Disfruta de una experiencia sensorial inolvidable con 20% de descuento en selecciones premium.';
        }

        $mensaje = "🎁 **He redactado una propuesta de promoción gastronómica:**\n\n"
            ."• **Nombre:** {$nombre}\n"
            ."• **Cupón:** `{$codigo}`\n"
            .'• **Tipo:** '.Str::headline($tipoDescuento)." ({$valorDescuento}% de beneficio)\n"
            .'• **Días Activos:** '.implode(', ', array_map('ucfirst', $diasSemana))."\n"
            ."• **Descripción:** {$descripcion}\n\n"
            .'Puedes guardarla directamente para que aparezca en el Portal de Promociones y la Portada Web.';

        return [
            'tipo' => 'promocion',
            'mensaje' => $mensaje,
            'datos' => [
                'nombre' => $nombre,
                'codigo_cupon' => $codigo,
                'tipo_descuento' => $tipoDescuento,
                'valor_descuento' => $valorDescuento,
                'dias_semana' => $diasSemana,
                'descripcion' => $descripcion,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Guardar Promoción en el Sistema',
                'accion' => 'guardar_promocion',
                'payload' => [
                    'nombre' => $nombre,
                    'codigo_cupon' => $codigo,
                    'tipo_descuento' => $tipoDescuento,
                    'valor_descuento' => $valorDescuento,
                    'dias_semana' => $diasSemana,
                    'descripcion' => $descripcion,
                ],
                'url' => '/promociones/gestion',
                'icono' => 'local_offer',
            ],
        ];
    }

    /**
     * Guarda la encuesta generada por el Copiloto directamente en la base de datos.
     */
    public function guardarEncuesta(array $payload, ?int $sucursalId = null): Encuesta
    {
        return Encuesta::create([
            'sucursal_id' => $sucursalId,
            'nombre' => $payload['nombre'] ?? 'Encuesta Generada por Copiloto IA',
            'activa' => true,
            'disparador' => $payload['disparador'] ?? 'post_pago',
            'delay_horas' => (int) ($payload['delay_horas'] ?? 1),
            'preguntas' => $payload['preguntas'] ?? [],
        ]);
    }

    /**
     * Guarda la promoción generada por el Copiloto directamente en la base de datos.
     */
    public function guardarPromocion(array $payload, ?int $sucursalId = null): Promocion
    {
        $nombre = $payload['nombre'] ?? $payload['titulo'] ?? 'Promoción Copiloto IA';
        $slugBase = Str::slug($nombre);
        $slug = $slugBase;
        $c = 1;
        while (Promocion::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-{$c}";
            $c++;
        }

        return Promocion::create([
            'sucursal_id' => $sucursalId,
            'titulo' => $nombre,
            'slug' => $slug,
            'subtitulo' => 'Propuesta gourmet generada por Copiloto Ejecutivo IA',
            'descripcion' => $payload['descripcion'] ?? 'Promoción especial creada por el Copiloto Ejecutivo.',
            'terminos_condiciones' => 'Válido por tiempo limitado según disponibilidad.',
            'tipo_beneficio' => $payload['tipo_descuento'] ?? 'dos_por_uno',
            'descuento_porcentaje' => (float) ($payload['valor_descuento'] ?? 20.00),
            'fecha_inicio' => Carbon::today(),
            'fecha_fin' => Carbon::today()->addDays(30),
            'dias_semana' => $payload['dias_semana'] ?? ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
            'aplica_salon' => true,
            'aplica_delivery' => true,
            'mostrar_en_portada' => true,
            'activo' => true,
            'orden' => 10,
        ]);
    }

    protected function esConsultaMetodosPago(string $texto): bool
    {
        return (bool) preg_match('/\b(efectivo|tarjetas?|transferencias?|m[eé]todos?\s+de\s+pago|formas?\s+de\s+pago|cu[aá]nto\s+en\s+efectivo|desglose\s+de\s+pago)\b/iu', $texto);
    }

    protected function esIntencionEgresos(string $texto): bool
    {
        // Keywords directas de egresos/retiros
        if (preg_match('/\b(egresos?|retiros?|retira[dr]os?)\b/iu', $texto)) {
            return true;
        }
        // Patrones de dinero que sale de caja
        if (preg_match('/dinero.{0,30}(salido|sali[oó])\b/iu', $texto)) {
            return true;
        }
        // "cuánto se ha retirado", "cuanto ha retirado"
        if (preg_match('/cu[aá]nto.{0,20}retira/iu', $texto)) {
            return true;
        }
        // "salidas de caja", "movimientos de caja"
        if (preg_match('/\b(salidas?\s+de\s+caja|movimientos?\s+de\s+caja|base\s+de\s+caja)\b/iu', $texto)) {
            return true;
        }

        return false;
    }

    protected function esConsultaMeseros(string $texto): bool
    {
        return (bool) preg_match('/\b(meseros?|camareros?|atenci[oó]n|rendimiento\s+meseros|ventas?\s+por\s+mesero)\b/iu', $texto);
    }

    protected function esConsultaTopProductos(string $texto): bool
    {
        return (bool) preg_match('/\b(platos?|productos?|m[aá]s\s+vendidos?|top|carta|rolls?|platos?\s+estrella)\b/iu', $texto);
    }

    protected function esConsultaGrafico(string $texto): bool
    {
        return (bool) preg_match('/\b(gr[aá]ficos?|gr[aá]fica|diagramas?|curva|visual|comparativa|estad[ií]sticas?)\b/iu', $texto);
    }

    protected function esConsultaImagenOInfografia(string $texto): bool
    {
        return (bool) preg_match('/\b(im[aá]gen(es)?|infograf[ií]a|generar\s+imagen|exportar\s+gr[aá]fico|tarjeta\s+visual)\b/iu', $texto);
    }

    protected function esIntencionVentas(string $texto): bool
    {
        if (preg_match('/\b(ventas?|facturaci[oó]n|ingresos?|tickets?|kpis?)\b/iu', $texto)) {
            return true;
        }

        return Str::contains($texto, [
            'estadistica', 'estadísticas', 'cuanto hemos vendido', 'cuánto hemos vendido',
            'como van las ventas', 'cómo van las ventas', 'grafico de ventas', 'gráfico de ventas',
        ]);
    }

    protected function esIntencionInventario(string $texto): bool
    {
        return Str::contains($texto, [
            'inventario', 'insumo', 'insumos', 'stock', 'critico', 'crítico', 'agotado',
            'que falta', 'qué falta', 'materia prima', 'reabastecer', 'compras a proveedor', 'ordenes de compra', 'órdenes de compra', 'proveedor',
        ]);
    }

    protected function esIntencionEncuesta(string $texto): bool
    {
        return Str::contains($texto, [
            'encuesta', 'encuestas', 'satisfaccion', 'satisfacción', 'nps', 'preguntas',
            'diseñar encuesta', 'crear encuesta', 'calificar',
        ]);
    }

    protected function esIntencionPromocion(string $texto): bool
    {
        return Str::contains($texto, [
            'promocion', 'promoción', 'promo', 'promos', 'descuento', '2x1', 'dos por uno',
            'cupon', 'cupón', 'campaña', 'campaña de descuento',
        ]);
    }

    /**
     * Consulta de egresos, retiros y salidas de la base de caja.
     */
    public function ejecutarConsultaEgresosBase(User $usuario, ?array $rangoFecha = null, ?string $mensajeOriginal = null): array
    {
        // Obtener turnos de la sucursal en el período indicado
        $turnosQuery = TurnoCaja::query()
            ->whereHas('caja', fn ($q) => $q->where('sucursal_id', $usuario->sucursal_id));

        if ($rangoFecha) {
            $turnosQuery->where(function ($q) use ($rangoFecha) {
                $q->whereBetween('apertura_en', [$rangoFecha['inicio'], $rangoFecha['fin']])
                    ->orWhereBetween('cierre_en', [$rangoFecha['inicio'], $rangoFecha['fin']]);
            });
            $etiqueta = $rangoFecha['etiqueta'];
        } else {
            // Default: turnos del día actual
            $turnosQuery->where('apertura_en', '>=', Carbon::today()->startOfDay());
            $etiqueta = 'Hoy ('.Carbon::today()->format('d/m/Y').')';
        }

        $turnoIds = $turnosQuery->pluck('id');

        // Consultar movimientos tipo egreso/retiro en esos turnos
        $movimientos = MovimientoCaja::whereIn('turno_caja_id', $turnoIds)
            ->whereIn('tipo', ['egreso', 'retiro'])
            ->with('turno.caja')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalEgresos = (float) $movimientos->sum('monto');
        $totalRetiros = (float) $movimientos->where('tipo', 'retiro')->sum('monto');
        $totalEgresosPuros = (float) $movimientos->where('tipo', 'egreso')->sum('monto');
        $cantidadMov = $movimientos->count();

        // Calcular saldo restante vs monto inicial de los turnos
        $turnos = TurnoCaja::whereIn('id', $turnoIds)->with('caja')->get();
        $fondoInicial = (float) $turnos->sum('monto_inicial');
        $saldoEstimado = $fondoInicial - $totalEgresos;

        $mensajeBase = "💸 **Egresos y Retiros de Caja — {$etiqueta}:**\n\n"
            .'• **Total Salidas:** $'.number_format($totalEgresos, 0, ',', '.')." COP ({$cantidadMov} movimientos)\n"
            .'• **Retiros de Base:** $'.number_format($totalRetiros, 0, ',', '.')." COP\n"
            .'• **Egresos Operativos:** $'.number_format($totalEgresosPuros, 0, ',', '.')." COP\n"
            .'• **Fondo Inicial Total:** $'.number_format($fondoInicial, 0, ',', '.')." COP\n"
            .'• **Saldo Estimado en Base:** $'.number_format(max(0, $saldoEstimado), 0, ',', '.')." COP\n\n";

        if ($movimientos->isNotEmpty()) {
            $mensajeBase .= "📋 **Detalle de Movimientos:**\n";
            foreach ($movimientos->take(8) as $mov) {
                $tipo = $mov->tipo === 'retiro' ? '🔵 Retiro' : '🔴 Egreso';
                $cajaNombre = $mov->turno?->caja?->nombre ?? 'Caja';
                $concepto = $mov->concepto ?: 'Sin concepto registrado';
                $mensajeBase .= "• {$tipo} — \$".number_format((float) $mov->monto, 0, ',', '.')." COP | {$cajaNombre} | {$concepto}\n";
            }
        } else {
            $mensajeBase .= "✅ No se registran egresos ni retiros en este período.\n";
        }

        $grafico = [
            'tipo' => 'doughnut',
            'titulo' => "Composición de Salidas de Caja — {$etiqueta}",
            'subtitulo' => 'Retiros vs Egresos operativos',
            'etiquetas' => ['Retiros de Base', 'Egresos Operativos'],
            'valores' => [$totalRetiros, $totalEgresosPuros],
            'valores_formateados' => ['$'.number_format($totalRetiros, 0, ',', '.'), '$'.number_format($totalEgresosPuros, 0, ',', '.')],
            'colores' => ['#3b82f6', '#ef4444'],
            'unidad' => 'COP',
        ];

        $contexto = [
            'periodo' => $etiqueta,
            'total_egresos' => $totalEgresos,
            'total_retiros' => $totalRetiros,
            'egresos_puros' => $totalEgresosPuros,
            'fondo_inicial' => $fondoInicial,
            'saldo_estimado' => $saldoEstimado,
            'movimientos' => $movimientos->take(10)->toArray(),
        ];

        $mensajeFinal = $this->consultarLlmSiDisponible(
            $mensajeOriginal ?: 'Egresos de caja en '.$etiqueta,
            $contexto,
            $mensajeBase,
            $usuario->sucursal_id
        ) ?? $mensajeBase;

        return [
            'tipo' => 'ventas',
            'mensaje' => $mensajeFinal,
            'datos' => [
                'kpis' => [
                    'ventas' => $totalEgresos,
                    'transacciones' => $cantidadMov,
                    'ticket_promedio' => $cantidadMov > 0 ? round($totalEgresos / $cantidadMov, 2) : 0.0,
                    'variacion_ventas_pct' => 0.0,
                ],
                'grafico' => $grafico,
            ],
            'accion_rapida' => [
                'etiqueta' => 'Ver Módulo de Caja',
                'url' => '/caja',
                'icono' => 'point_of_sale',
            ],
        ];
    }

    protected function generarOrientacionPersuasiva(): array
    {
        $mensaje = "🔍 **No encontré datos sobre eso.**\n\n"
            ."Puedo ayudarte con las siguientes consultas analíticas:\n\n"
            ."• **Ventas del día:** *\"ventas de hoy\"* o *\"facturación de ayer\"*\n"
            ."• **Movimientos de caja:** *\"egresos de caja\"* o *\"cuánto se ha retirado\"*\n"
            ."• **Inventario crítico:** *\"qué insumos están agotados\"*\n"
            ."• **Top platos:** *\"platos más vendidos esta semana\"*\n"
            ."• **Rendimiento de meseros:** *\"ventas por mesero\"*\n\n"
            .'¿Cuál de estos análisis te gustaría consultar?';

        return [
            'tipo' => 'orientacion',
            'mensaje' => $mensaje,
        ];
    }
}
