<?php

namespace Database\Seeders;

use App\Models\CrmConfiguracion;
use App\Models\CrmIaPlantillaPrivilegio;
use Illuminate\Database\Seeder;

class CrmIaPlantillaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plantillas = [
            [
                'nombre' => 'Hostess Completa (Recomendada)',
                'slug' => 'hostess-completa',
                'descripcion' => 'Informa carta y precios, verifica disponibilidad de mesas y agenda reservas con límite de hasta 6 personas.',
                'es_sistema' => true,
                'permitir_menu' => true,
                'permitir_precios' => true,
                'permitir_alergenos' => true,
                'permitir_verificar_mesas' => true,
                'permitir_crear_reservas' => true,
                'max_personas_reserva' => 6,
                'permitir_cancelar_reservas' => false,
                'permitir_promociones' => true,
                'permitir_puntos_vip' => false,
                'directivas_sistema' => 'Eres la anfitriona oficial de RestoMaster. Sé amable, concisa y cordial. Promueve la experiencia gastronómica. Si solicitan reservas para más comensales del límite permitido, deriva cordialmente al comensal con un asesor humano.',
                'tono_conducta' => 'amable_calido',
                'prompt_personalidad' => 'Saluda siempre con una bienvenida cálida y hospitalaria al restaurante. Usa un tono acogedor y afectuoso, felicitando al cliente si menciona una fecha especial (cumpleaños, aniversario). Puedes usar emojis gastronómicos de forma prudente (🍷🥩✨).',
            ],
            [
                'nombre' => 'Solo Informativa / Menú',
                'slug' => 'solo-menu',
                'descripcion' => 'Responde dudas del menú, precios, alérgenos y horarios; tiene prohibido agendar o alterar reservas en el sistema.',
                'es_sistema' => true,
                'permitir_menu' => true,
                'permitir_precios' => true,
                'permitir_alergenos' => true,
                'permitir_verificar_mesas' => false,
                'permitir_crear_reservas' => false,
                'max_personas_reserva' => 0,
                'permitir_cancelar_reservas' => false,
                'permitir_promociones' => true,
                'permitir_puntos_vip' => false,
                'directivas_sistema' => 'Proporciona información detallada sobre nuestros platos, ingredientes y recomendaciones. Si el cliente desea reservar, indícale amablemente que puede hacerlo a través de nuestro sitio web o llamando al restaurante.',
                'tono_conducta' => 'entusiasta_gourmet',
                'prompt_personalidad' => 'Comunícate como un apasionado sommelier y chef anfitrión. Describe los platos destacando la textura de los cortes a la brasa, los aromas de la leña y las notas frescas de la coctelería.',
            ],
            [
                'nombre' => 'Estricta / Modo Silencioso',
                'slug' => 'estricta',
                'descripcion' => 'Respuestas breves y precisas. No sugiere maridajes extensos ni ejecuta ninguna transacción.',
                'es_sistema' => true,
                'permitir_menu' => true,
                'permitir_precios' => false,
                'permitir_alergenos' => true,
                'permitir_verificar_mesas' => false,
                'permitir_crear_reservas' => false,
                'max_personas_reserva' => 0,
                'permitir_cancelar_reservas' => false,
                'permitir_promociones' => false,
                'permitir_puntos_vip' => false,
                'directivas_sistema' => 'Respuestas directas y formales. No des precios ni realices reservas. Limítate a horarios y confirmación de ingredientes si hay dudas de alergias.',
                'tono_conducta' => 'conciso_directo',
                'prompt_personalidad' => 'Respuestas estrictamente ejecutivas, de máximo dos oraciones. Cero florituras, directo a la respuesta solicitada.',
            ],
        ];

        foreach ($plantillas as $p) {
            CrmIaPlantillaPrivilegio::updateOrCreate(
                ['slug' => $p['slug'], 'sucursal_id' => null],
                $p
            );
        }

        // Asociar la plantilla por defecto en la configuración de CRM si no tiene una asignada
        $config = CrmConfiguracion::first();
        if ($config && empty($config->ia_plantilla_privilegio_id)) {
            $default = CrmIaPlantillaPrivilegio::where('slug', 'hostess-completa')->first();
            if ($default) {
                $config->update(['ia_plantilla_privilegio_id' => $default->id]);
            }
        }
    }
}
