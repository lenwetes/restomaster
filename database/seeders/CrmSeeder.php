<?php

namespace Database\Seeders;

use App\Models\CrmAutomatizacion;
use App\Models\CrmConfiguracion;
use App\Models\CrmPlantilla;
use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Configuración por defecto
        CrmConfiguracion::firstOrCreate(
            ['sucursal_id' => null],
            [
                'whatsapp_proveedor' => 'meta_cloud',
                'whatsapp_phone_number_id' => '105938472910482',
                'whatsapp_waba_id' => '392817492019482',
                'whatsapp_access_token' => 'EAAXsampleMetaCloudApiTokenRestoMaster992',
                'whatsapp_webhook_secret' => 'restomaster_crm_webhook_secret',
                'whatsapp_telefono_pruebas' => '+573001234567',
                'email_activo' => true,
                'email_remitente_nombre' => 'RestoMaster Experiencia',
                'email_remitente_correo' => 'experiencia@restomaster.com',
                'horario_envio_inicio' => '10:00',
                'horario_envio_fin' => '22:30',
                'delay_encuesta_minutos' => 15,
                'winback_dias_inactividad' => 45,
            ]
        );

        // 2. Plantillas WhatsApp
        $plantillaEncuestaWa = CrmPlantilla::updateOrCreate(
            ['codigo' => 'encuesta_satisfaccion_wa'],
            [
                'nombre' => 'Encuesta de Satisfacción Post-Consumo (WhatsApp)',
                'canal' => 'whatsapp',
                'categoria' => 'encuesta',
                'asunto' => null,
                'contenido' => "¡Hola {nombre}! 🍣 Gracias por visitarnos en {restaurante}. Tu opinión es vital para nuestro equipo. ¿Nos regalas 30 segundos calificando tu experiencia?\n\n⭐ Califícanos aquí y recibe +50 puntos VIP: {url_encuesta}\n\n¡Esperamos verte muy pronto de nuevo!",
                'whatsapp_template_name' => 'encuesta_post_consumo_v1',
                'whatsapp_language_code' => 'es',
                'activa' => true,
            ]
        );

        $plantillaReservaConfirmadaWa = CrmPlantilla::updateOrCreate(
            ['codigo' => 'reserva_confirmada_wa'],
            [
                'nombre' => 'Confirmación de Reserva (WhatsApp)',
                'canal' => 'whatsapp',
                'categoria' => 'reserva',
                'asunto' => null,
                'contenido' => "¡Hola {nombre}! 🛎️ Tu reserva en {restaurante} está *CONFIRMADA*.\n\n📅 Fecha: {fecha_reserva}\n⏰ Hora: {hora_reserva}\n👥 Personas: {personas}\n🪑 Mesa: {mesa}\n\nTe esperamos puntualmente. Si necesitas hacer algún cambio, responde a este mensaje.",
                'whatsapp_template_name' => 'reserva_confirmada_v1',
                'whatsapp_language_code' => 'es',
                'activa' => true,
            ]
        );

        $plantillaReservaRecordatorioWa = CrmPlantilla::updateOrCreate(
            ['codigo' => 'reserva_recordatorio_wa'],
            [
                'nombre' => 'Recordatorio 2 Horas Antes de Reserva (WhatsApp)',
                'canal' => 'whatsapp',
                'categoria' => 'reserva',
                'asunto' => null,
                'contenido' => '¡Hola {nombre}! ⏱️ Te recordamos que hoy tienes una reserva en {restaurante} a las *{hora_reserva}* (Mesa {mesa}). Tu mesa y nuestro equipo están listos para recibirte. ¡Nos vemos pronto!',
                'whatsapp_template_name' => 'reserva_recordatorio_2h_v1',
                'whatsapp_language_code' => 'es',
                'activa' => true,
            ]
        );

        $plantillaWinbackWa = CrmPlantilla::updateOrCreate(
            ['codigo' => 'winback_cliente_inactivo_wa'],
            [
                'nombre' => 'Reactivación de Clientes Inactivos (WhatsApp)',
                'canal' => 'whatsapp',
                'categoria' => 'marketing',
                'asunto' => null,
                'contenido' => '¡Hola {nombre}! 🍣 Hace tiempo que no te vemos en {restaurante} y te extrañamos. Queremos invitarte una copa de cortesía o un postre especial en tu próxima visita mencionando el código *VUELVEVIP*. ¡Reserva tu mesa aquí: {url_reserva}!',
                'whatsapp_template_name' => 'winback_inactivos_v1',
                'whatsapp_language_code' => 'es',
                'activa' => true,
            ]
        );

        $plantillaCumpleanosWa = CrmPlantilla::updateOrCreate(
            ['codigo' => 'cumpleanos_cliente_wa'],
            [
                'nombre' => 'Felicitación de Cumpleaños VIP (WhatsApp)',
                'canal' => 'whatsapp',
                'categoria' => 'cumpleanos',
                'asunto' => null,
                'contenido' => '🎂 ¡Feliz Cumpleaños {nombre}! 🎉 De parte de todo el equipo de {restaurante} te deseamos un día extraordinario. Ven a celebrar esta semana con nosotros y el postre de la casa va por nuestra cuenta. ¡Te esperamos!',
                'whatsapp_template_name' => 'cumpleanos_vip_v1',
                'whatsapp_language_code' => 'es',
                'activa' => true,
            ]
        );

        // 3. Plantillas Email
        $plantillaEncuestaEmail = CrmPlantilla::updateOrCreate(
            ['codigo' => 'encuesta_satisfaccion_email'],
            [
                'nombre' => 'Encuesta de Satisfacción Post-Consumo (Email)',
                'canal' => 'email',
                'categoria' => 'encuesta',
                'asunto' => '¿Cómo estuvo tu experiencia en RestoMaster? 🍣 Califícanos',
                'contenido' => "<p>Hola <strong>{nombre}</strong>,</p><p>Muchas gracias por visitarnos en {restaurante}. Esperamos que hayas disfrutado de nuestros platos y la atención de nuestro equipo.</p><p>¿Nos ayudas con 30 segundos de tu tiempo para evaluar nuestro servicio? Al responder sumarás <strong>+50 puntos VIP</strong> a tu cuenta de fidelización.</p><p style='text-align: center; margin: 25px 0;'><a href='{url_encuesta}' style='background-color: #ef4444; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>Calificar mi Experiencia ⭐</a></p><p>¡Gracias por ser parte de nuestra comunidad!</p>",
                'whatsapp_template_name' => null,
                'whatsapp_language_code' => 'es',
                'activa' => true,
            ]
        );

        // 4. Automatizaciones y Disparadores
        CrmAutomatizacion::updateOrCreate(
            ['evento_disparador' => 'pedido_cobrado'],
            [
                'nombre' => 'Encuesta de Satisfacción Post-Cobro',
                'canal' => 'whatsapp',
                'plantilla_whatsapp_id' => $plantillaEncuestaWa->id,
                'plantilla_email_id' => $plantillaEncuestaEmail->id,
                'delay_minutos' => 15,
                'activa' => true,
                'condiciones' => ['min_total' => 0, 'solo_con_cliente' => true],
                'total_disparos' => 0,
            ]
        );

        CrmAutomatizacion::updateOrCreate(
            ['evento_disparador' => 'reserva_confirmada'],
            [
                'nombre' => 'Confirmación Inmediata de Reserva',
                'canal' => 'whatsapp',
                'plantilla_whatsapp_id' => $plantillaReservaConfirmadaWa->id,
                'plantilla_email_id' => null,
                'delay_minutos' => 0,
                'activa' => true,
                'condiciones' => [],
                'total_disparos' => 0,
            ]
        );

        CrmAutomatizacion::updateOrCreate(
            ['evento_disparador' => 'reserva_recordatorio_2h'],
            [
                'nombre' => 'Recordatorio 2 Horas Antes de Reserva',
                'canal' => 'whatsapp',
                'plantilla_whatsapp_id' => $plantillaReservaRecordatorioWa->id,
                'plantilla_email_id' => null,
                'delay_minutos' => 0,
                'activa' => true,
                'condiciones' => [],
                'total_disparos' => 0,
            ]
        );

        CrmAutomatizacion::updateOrCreate(
            ['evento_disparador' => 'cliente_inactivo'],
            [
                'nombre' => 'Reactivación de Clientes Inactivos (Winback 45 días)',
                'canal' => 'whatsapp',
                'plantilla_whatsapp_id' => $plantillaWinbackWa->id,
                'plantilla_email_id' => null,
                'delay_minutos' => 0,
                'activa' => true,
                'condiciones' => ['dias_inactividad' => 45],
                'total_disparos' => 0,
            ]
        );

        CrmAutomatizacion::updateOrCreate(
            ['evento_disparador' => 'cliente_cumpleanos'],
            [
                'nombre' => 'Felicitación y Regalo de Cumpleaños VIP',
                'canal' => 'whatsapp',
                'plantilla_whatsapp_id' => $plantillaCumpleanosWa->id,
                'plantilla_email_id' => null,
                'delay_minutos' => 0,
                'activa' => true,
                'condiciones' => [],
                'total_disparos' => 0,
            ]
        );
    }
}
