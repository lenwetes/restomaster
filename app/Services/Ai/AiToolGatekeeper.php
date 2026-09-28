<?php

namespace App\Services\Ai;

use App\Models\Cliente;
use App\Models\CrmConfiguracion;
use App\Models\CrmIaPlantillaPrivilegio;

class AiToolGatekeeper
{
    /**
     * Palabras clave o patrones estrictamente bloqueados en canales de comensales/públicos.
     */
    protected const PATRONES_BLOQUEADOS = [
        'venta',
        'ventas',
        'vendieron',
        'vendio',
        'vendió',
        'facturacion',
        'facturación',
        'facturado',
        'ganancia',
        'ganancias',
        'ingreso',
        'ingresos',
        'arqueo',
        'cierre de caja',
        'reporte z',
        'food cost',
        'costo de insumo',
        'costo insumo',
        'costo de',
        'costos de',
        'margen de ganancia',
        'margen bruto',
        'cuenta por pagar',
        'proveedor',
        'asiento contable',
        'balance general',
        'password',
        'contraseña',
        'contrasena',
        'secret',
        'token',
        'drop table',
        'select from',
        'cuanto ganan',
        'cuánto ganan',
        'cuanto vendieron',
        'cuánto vendieron',
        'ventas de hoy',
        'ventas del día',
    ];

    /**
     * Obtiene la plantilla activa asociada a la configuración CRM.
     */
    public function obtenerPlantillaActiva(?int $sucursalId = null): CrmIaPlantillaPrivilegio
    {
        $config = CrmConfiguracion::activa($sucursalId);

        if ($config->ia_plantilla_privilegio_id && $config->plantillaIa) {
            return $config->plantillaIa;
        }

        return CrmIaPlantillaPrivilegio::defaultHostess() ?? new CrmIaPlantillaPrivilegio([
            'nombre' => 'Hostess Básica Fallback',
            'permitir_menu' => true,
            'permitir_precios' => true,
            'permitir_alergenos' => true,
            'permitir_verificar_mesas' => true,
            'permitir_crear_reservas' => true,
            'max_personas_reserva' => 6,
            'permitir_promociones' => true,
            'permitir_puntos_vip' => false,
        ]);
    }

    /**
     * Devuelve el listado de nombres de Tools autorizadas para el LLM.
     * Solo las herramientas autorizadas en la plantilla se devuelven.
     *
     * @return array<string>
     */
    public function obtenerNombresToolsAutorizadas(?CrmIaPlantillaPrivilegio $plantilla = null): array
    {
        $plantilla = $plantilla ?? $this->obtenerPlantillaActiva();
        $tools = [];

        if ($plantilla->permitir_menu) {
            $tools[] = 'consultar_menu';
        }

        if ($plantilla->permitir_verificar_mesas) {
            $tools[] = 'verificar_disponibilidad_mesas';
        }

        if ($plantilla->permitir_crear_reservas) {
            $tools[] = 'crear_reserva';
        }

        if ($plantilla->permitir_promociones) {
            $tools[] = 'consultar_promociones';
        }

        if ($plantilla->permitir_puntos_vip) {
            $tools[] = 'consultar_puntos_cliente';
        }

        return $tools;
    }

    /**
     * Evalúa si un mensaje de usuario intenta acceder a información financiera o confidencial.
     */
    public function esConsultaProhibida(string $mensaje): bool
    {
        $mensajeNormalizado = mb_strtolower(trim($mensaje), 'UTF-8');

        foreach (self::PATRONES_BLOQUEADOS as $patron) {
            if (str_contains($mensajeNormalizado, $patron)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida si la cantidad de comensales para una reserva excede el límite permitido por la plantilla.
     */
    public function excedeLimiteComensales(int $personas, ?CrmIaPlantillaPrivilegio $plantilla = null): bool
    {
        $plantilla = $plantilla ?? $this->obtenerPlantillaActiva();
        $max = $plantilla->max_personas_reserva;

        return $max > 0 && $personas > $max;
    }

    /**
     * Genera el prompt de directivas del sistema enriquecido con las restricciones de la plantilla activa.
     */
    public function construirSystemPrompt(?CrmIaPlantillaPrivilegio $plantilla = null, ?Cliente $cliente = null): string
    {
        $plantilla = $plantilla ?? $this->obtenerPlantillaActiva();
        $tools = $this->obtenerNombresToolsAutorizadas($plantilla);

        $prompt = "Eres el asistente inteligente oficial de RestoMaster. Atiendes de manera cordial, profesional y segura.\n\n";
        $prompt .= "REGLAS DE CONDUCTA Y SEGURIDAD ESTRICTAS (INVIOLABLES):\n";
        $prompt .= "1. Jamás reveles ventas, ganancias, costos de insumos, contraseñas ni datos internos del restaurante.\n";
        $prompt .= '2. Solo puedes realizar acciones para las que tengas herramientas habilitadas: '.implode(', ', $tools).".\n";

        if (! $plantilla->permitir_crear_reservas) {
            $prompt .= "3. NO tienes autorización para crear reservas. Si el cliente pide reservar, explícale cordialmente que debe hacerlo a través del enlace web oficial o contactar telefónicamente.\n";
        } else {
            $prompt .= "3. El límite máximo para reservas automáticas es de {$plantilla->max_personas_reserva} personas. Si piden más comensales, indícales que para eventos grandes deben comunicarse directamente con la administración.\n";
        }

        if (! $plantilla->permitir_precios) {
            $prompt .= "4. No informes precios exactos de los platos. Describe únicamente ingredientes y preparaciones.\n";
        }

        $linkDelivery = route('delivery.publico');
        $prompt .= "5. PEDIDOS A DOMICILIO Y ASISTENTE DE DELIVERY: Si el usuario pregunta si puede hacer un pedido, ordenar o pedir delivery, facilítale de inmediato el enlace oficial a nuestro asistente y portal de delivery en línea: {$linkDelivery}. Explícale que allí puede seleccionar sus platos y pedir a domicilio, y ofrécele adicionalmente que si prefiere visitarnos en el restaurante puedes agendarle una mesa en segundos.\n";
        $prompt .= "6. REGLA ANTI-BUCLE Y PERSUASIÓN ACTIVA: Si la conversación ya ha iniciado, NUNCA repitas saludos genéricos de bienvenida como '¡Hola! Soy la anfitriona virtual...'. Cuando el usuario pregunte por algo fuera de tu alcance o no reconocido, reconoce su inquietud con empatía y conduce la conversación persuasivamente hacia lo que SÍ puedes hacer: reservar mesa o recomendar platos de la carta, cerrando con una pregunta de llamado a la acción clara.\n";

        // Tono y Personalidad
        $tonos = [
            'amable_calido' => 'TONO DE COMUNICACIÓN: Sé sumamente cálido, hospitalario, empático y acogedor. Da la bienvenida con afecto y usa emojis gastronómicos sutiles (✨🍷🥩).',
            'entusiasta_gourmet' => 'TONO DE COMUNICACIÓN: Exprésate como un apasionado sommelier y chef anfitrión. Destaca técnicas al fuego, texturas, aromas y maridajes de autor.',
            'formal_elegante' => 'TONO DE COMUNICACIÓN: Trata al comensal con suma distinción y respeto (de "usted"). Lenguaje sobrio, protocolario y altamente refinado.',
            'conciso_directo' => 'TONO DE COMUNICACIÓN: Respuestas sumamente breves y directas (máximo 2 oraciones). Sin rodeos ni preámbulos extensos.',
            'personalizado' => 'TONO DE COMUNICACIÓN: Adopta estrictamente la personalidad definida por la gerencia.',
        ];

        $tonoClave = $plantilla->tono_conducta ?: 'amable_calido';
        $prompt .= "\n".($tonos[$tonoClave] ?? $tonos['amable_calido'])."\n";

        if (! empty($plantilla->prompt_personalidad)) {
            $prompt .= "\nPERSONALIDAD Y ESTILO DE LA CASA:\n".$plantilla->prompt_personalidad."\n";
        }

        if (! empty($plantilla->directivas_sistema)) {
            $prompt .= "\nDIRECTIVAS ESPECÍFICAS DE LA CASA:\n".$plantilla->directivas_sistema."\n";
        }

        // Inyección del perfil 360° del huésped (Alergias, preferencias, VIP)
        if ($cliente) {
            $prompt .= "\nPERFIL DEL HUÉSPED ATENDIDO:\n";
            $prompt .= "- Nombre: {$cliente->nombre}\n";
            if (! empty($cliente->alergias)) {
                $prompt .= "- ALERGIAS Y RESTRICCIONES ALIMENTARIAS (CRÍTICO): {$cliente->alergias}. NUNCA sugieras platos que contengan o puedan tener trazas de estos ingredientes bajo ninguna circunstancia.\n";
            }
            if (! empty($cliente->preferencias)) {
                $prompt .= "- Preferencias gastronómicas / de maridaje: {$cliente->preferencias}\n";
            }
            if ($cliente->isVip()) {
                $prompt .= "- Estatus: Huésped VIP distinguido. Ofrece una atención con máxima deferencia y cortesía especial.\n";
            }
        }

        return $prompt;
    }
}
