--
-- PostgreSQL database dump
--

\restrict tIqDfWIihjdSuy9gvA7ruNdDO3dzipSk4rgA5ProbX8GMKh8WYYncyPu8thAPgI

-- Dumped from database version 18.4
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: adminresto
--

INSERT INTO public.roles (id, nombre, slug, descripcion, created_at, updated_at) VALUES (1, 'Administrador', 'admin', 'Acceso total al sistema y configuración', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.roles (id, nombre, slug, descripcion, created_at, updated_at) VALUES (2, 'Gerente', 'gerente', 'Gestión operativa, inventarios y reportes', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.roles (id, nombre, slug, descripcion, created_at, updated_at) VALUES (3, 'Cajero', 'cajero', 'Punto de venta, control de caja y cobranza', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.roles (id, nombre, slug, descripcion, created_at, updated_at) VALUES (4, 'Mesero', 'mesero', 'Atención de mesas y toma de pedidos táctil', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.roles (id, nombre, slug, descripcion, created_at, updated_at) VALUES (5, 'Cocina', 'cocina', 'Pantalla KDS de preparación de sushi y cocina', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.roles (id, nombre, slug, descripcion, created_at, updated_at) VALUES (6, 'Barra', 'barra', 'Pantalla KDS de bebidas y barra', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.roles (id, nombre, slug, descripcion, created_at, updated_at) VALUES (7, 'Delivery', 'delivery', 'Repartidor y despacho de pedidos a domicilio', '2026-09-18 11:35:19', '2026-09-18 11:35:19');


--
-- Data for Name: sucursales; Type: TABLE DATA; Schema: public; Owner: adminresto
--

INSERT INTO public.sucursales (id, nombre, direccion, telefono, nit_ruc, activo, created_at, updated_at) VALUES (1, 'RestoMaster Principal', 'Cra 35 # 8A-12, Provenza, Medellín', '+57 300 123 4567', '901.458.789-3', true, '2026-09-18 11:35:19', '2026-09-18 11:35:19');


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: adminresto
--

INSERT INTO public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role_id, telefono, activo, sucursal_id) VALUES (1, 'Administrador RestoMaster', 'admin@restomaster.com', '2026-09-18 11:35:19', '$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq', NULL, '2026-09-18 11:35:19', '2026-09-18 11:35:19', 1, '+57 300 987 6543', true, 1);
INSERT INTO public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role_id, telefono, activo, sucursal_id) VALUES (2, 'Gerente de Operaciones', 'gerente@restomaster.com', '2026-09-18 11:35:19', '$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq', NULL, '2026-09-18 11:35:19', '2026-09-18 11:35:19', 2, '+57 300 111 2233', true, 1);
INSERT INTO public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role_id, telefono, activo, sucursal_id) VALUES (3, 'Cajero Principal', 'cajero@restomaster.com', '2026-09-18 11:35:19', '$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq', NULL, '2026-09-18 11:35:19', '2026-09-18 11:35:19', 3, '+57 300 222 3344', true, 1);
INSERT INTO public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role_id, telefono, activo, sucursal_id) VALUES (4, 'Mesero Turno Salón', 'mesero@restomaster.com', '2026-09-18 11:35:19', '$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq', NULL, '2026-09-18 11:35:19', '2026-09-18 11:35:19', 4, '+57 300 333 4455', true, 1);
INSERT INTO public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role_id, telefono, activo, sucursal_id) VALUES (5, 'Chef de Cocina KDS', 'cocina@restomaster.com', '2026-09-18 11:35:19', '$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq', NULL, '2026-09-18 11:35:19', '2026-09-18 11:35:19', 5, '+57 300 444 5566', true, 1);
INSERT INTO public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role_id, telefono, activo, sucursal_id) VALUES (6, 'Bartender Barra Bebidas', 'barra@restomaster.com', '2026-09-18 11:35:19', '$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq', NULL, '2026-09-18 11:35:19', '2026-09-18 11:35:19', 6, '+57 300 555 6677', true, 1);
INSERT INTO public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role_id, telefono, activo, sucursal_id) VALUES (7, 'Repartidor Delivery', 'delivery@restomaster.com', '2026-09-18 11:35:19', '$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq', NULL, '2026-09-18 11:35:19', '2026-09-18 11:35:19', 7, '+57 300 666 7788', true, 1);


--
-- Data for Name: asientos_contables; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: auditorias; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: cajas; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: configuraciones; Type: TABLE DATA; Schema: public; Owner: adminresto
--

INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (1, 'general', 'razon_social', '"RestoMaster Colombia S.A.S."', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (2, 'general', 'nit', '"901.458.789-3"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (3, 'general', 'direccion', '"Cra 35 # 8A-12, El Poblado"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (4, 'general', 'telefono', '"+57 300 123 4567"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (5, 'general', 'ciudad', '"Medell\u00edn, Colombia"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (6, 'general', 'regimen', '"Com\u00fan"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (7, 'general', 'moneda', '"COP"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (8, 'general', 'simbolo_moneda', '"$"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (9, 'general', 'impuesto_porcentaje', '8', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (10, 'general', 'costo_envio_base', '8000', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (11, 'dian', 'envio_activo', 'false', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (12, 'dian', 'ambiente', '"habilitacion"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (13, 'dian', 'tipo_documento', '"01"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (14, 'dian', 'resolucion_numero', '"1876400001234"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (15, 'dian', 'resolucion_fecha', '"2026-01-15"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (16, 'dian', 'prefijo', '"MP"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (17, 'dian', 'desde', '"1"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (18, 'dian', 'hasta', '"50000"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (19, 'dian', 'vigente', 'true', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (20, 'ticket_80mm', 'nombre_comercial', '"RESTOMASTER GASTRO"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (21, 'ticket_80mm', 'lema', '"Restaurante & Bar \u00b7 Cocina Artesanal y Parrilla"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (22, 'ticket_80mm', 'razon_social', '"RestoMaster Colombia S.A.S."', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (23, 'ticket_80mm', 'nit', '"901.458.789-3"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (24, 'ticket_80mm', 'regimen', '"IVA R\u00e9gimen Com\u00fan - Tarifa Especial"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (25, 'ticket_80mm', 'direccion', '"Cra 35 # 8A-12, El Poblado, Medell\u00edn"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (26, 'ticket_80mm', 'telefono', '"+57 (4) 444-5566 \u00b7 WhatsApp: +57 300 123 4567"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (27, 'ticket_80mm', 'ciudad', '"Medell\u00edn, Antioquia"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (28, 'ticket_80mm', 'mensaje_bienvenida', '"\u00a1Bienvenidos a una experiencia gastron\u00f3mica \u00fanica!"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (29, 'ticket_80mm', 'resolucion_dian', '"Resoluci\u00f3n DIAN N\u00b0 1876400001234 del 2026-01-15"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (30, 'ticket_80mm', 'rango_autorizado', '"Prefijo POS desde SEC-001 hasta SEC-50000"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (31, 'ticket_80mm', 'mostrar_desglose_impuestos', 'true', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (32, 'ticket_80mm', 'mostrar_datos_mesero', 'true', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (33, 'ticket_80mm', 'sugerir_propina', 'true', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (34, 'ticket_80mm', 'porcentaje_propina', '10', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (35, 'ticket_80mm', 'mensaje_propina', '"Propina sugerida 10%: El servicio es voluntario"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (36, 'ticket_80mm', 'pie_pagina', '"\u00a1Muchas gracias por su preferencia! Esperamos su pronta visita en RestoMaster."', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (37, 'ticket_80mm', 'redes_sociales', '"Instagram: @restomaster \u00b7 www.restomaster.co"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (38, 'ticket_80mm', 'politica_cambios', '"Verifique su pedido al momento de la entrega. Conserve este comprobante."', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (39, 'ticket_80mm', 'mostrar_qr', 'true', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (40, 'reservas', 'webhook_token', '"Pvs15RvyRETv23maTaiBRtp6Y0qPXOaisxMo0hk6AlsPTkzK"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (41, 'reservas', 'webhook_activo', 'false', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (42, 'database_external', 'host', '"127.0.0.1"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (43, 'database_external', 'port', '5432', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (44, 'database_external', 'database', '"restomaster"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (45, 'database_external', 'username', '"postgres"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (46, 'database_external', 'password', '""', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (47, 'database_external', 'sslmode', '"prefer"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (48, 'database_external', 'activo', 'false', '2026-09-18 11:35:19', '2026-09-18 11:35:19');
INSERT INTO public.configuraciones (id, grupo, clave, valor, created_at, updated_at) VALUES (49, 'impresion', 'pie_ticket', '"\u00a1Gracias por preferir RestoMaster!"', '2026-09-18 11:35:19', '2026-09-18 11:35:19');


--
-- Data for Name: insumos; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: cuentas_por_pagar; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: direcciones_cliente; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: impresoras; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: mesas; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: turnos_caja; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: pedidos; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: productos; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: items_pedido; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: adminresto
--

INSERT INTO public.migrations (id, migration, batch) VALUES (1, '0001_01_01_000000_create_users_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (2, '0001_01_01_000001_create_cache_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (3, '0001_01_01_000002_create_jobs_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (4, '2026_09_09_174723_create_roles_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (5, '2026_09_09_174728_add_role_and_profile_fields_to_users_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (6, '2026_09_09_174731_create_sucursales_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (7, '2026_09_09_174736_create_mesas_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (8, '2026_09_09_180000_create_categorias_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (9, '2026_09_09_180010_create_productos_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (10, '2026_09_09_180020_create_pedidos_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (11, '2026_09_09_180030_create_items_pedido_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (12, '2026_09_09_190000_create_cajas_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (13, '2026_09_09_190010_create_turnos_caja_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (14, '2026_09_09_190020_create_movimientos_caja_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (15, '2026_09_09_190030_create_asientos_contables_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (16, '2026_09_09_191000_create_insumos_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (17, '2026_09_09_191010_create_recetas_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (18, '2026_09_09_191020_create_movimientos_inventario_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (19, '2026_09_09_191030_add_inventario_descontado_to_items_pedido_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (20, '2026_09_09_193000_create_auditorias_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (21, '2026_09_09_193500_create_cuentas_por_pagar_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (22, '2026_09_09_193510_create_pagos_cxps_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (23, '2026_09_09_194000_create_clientes_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (24, '2026_09_09_194010_create_direcciones_cliente_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (25, '2026_09_09_194020_create_movimientos_puntos_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (26, '2026_09_09_194030_add_delivery_and_loyalty_fields_to_pedidos_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (27, '2026_09_09_200000_create_configuraciones_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (28, '2026_09_09_200010_create_reservas_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (29, '2026_09_09_200020_create_reserva_mesa_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (30, '2026_09_09_210000_create_impresoras_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (31, '2026_09_09_210010_create_trabajos_impresion_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (32, '2026_09_09_211000_add_sucursal_id_to_users_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (33, '2026_09_10_120000_add_performance_indexes_to_pedidos_and_items', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (34, '2026_09_10_183500_add_driver_nombre_to_impresoras_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (35, '2026_09_10_200000_harden_db_integrity_audit_fixes', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (36, '2026_09_10_220000_harden_historical_foreign_keys', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (37, '2026_09_10_230000_add_performance_audit_indexes', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (38, '2026_09_10_240000_harden_remaining_foreign_keys', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (39, '2026_09_10_250000_harden_items_pedido_fk', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (40, '2026_09_15_160000_add_total_ingresos_to_turnos_caja_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (41, '2026_09_15_170000_add_audit_missing_indexes', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (42, '2026_09_15_183000_add_sucursal_id_to_pedidos_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (43, '2026_09_15_190000_batch_b_dinero_turnos_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (44, '2026_09_16_143000_add_numero_factura_to_cuentas_por_pagar_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (45, '2026_09_16_160000_make_telefono_nullable_and_add_habeas_data_to_clientes_table', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (46, '2026_09_16_170000_add_mesero_id_and_propina_to_mesas_and_pedidos_tables', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (47, '2026_09_17_210000_convert_transactional_timestamps_to_timestamptz', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (48, '2026_09_17_220000_fix_timestamptz_bogota_offset', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (49, '2026_09_17_221000_add_idempotencia_uuid_to_pedidos', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (50, '2026_09_18_020000_fix_turnos_caja_unique_partial_index', 1);
INSERT INTO public.migrations (id, migration, batch) VALUES (51, '2026_09_18_100000_create_permission_user_table', 1);


--
-- Data for Name: movimientos_caja; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: movimientos_inventario; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: movimientos_puntos; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: pagos_cxps; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: permission_user; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: recetas; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: reservas; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: reserva_mesa; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: adminresto
--

INSERT INTO public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) VALUES ('aJlk5X9cvPlZOxB7YyzzrgGSyGPKI2rTxogmKtby', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJRNjZ3M25MRmtINm5rWjNsZ3hPV0xidnFxVEZaaTBUeGUxdk1PdG1VIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL2ludmVudGFyaW8ifSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1789749324);


--
-- Data for Name: trabajos_impresion; Type: TABLE DATA; Schema: public; Owner: adminresto
--



--
-- Name: asientos_contables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.asientos_contables_id_seq', 1, false);


--
-- Name: auditorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.auditorias_id_seq', 1, false);


--
-- Name: cajas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.cajas_id_seq', 1, false);


--
-- Name: categorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.categorias_id_seq', 1, false);


--
-- Name: clientes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.clientes_id_seq', 1, false);


--
-- Name: configuraciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.configuraciones_id_seq', 49, true);


--
-- Name: cuentas_por_pagar_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.cuentas_por_pagar_id_seq', 1, false);


--
-- Name: direcciones_cliente_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.direcciones_cliente_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: impresoras_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.impresoras_id_seq', 1, false);


--
-- Name: insumos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.insumos_id_seq', 1, false);


--
-- Name: items_pedido_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.items_pedido_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: mesas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.mesas_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.migrations_id_seq', 51, true);


--
-- Name: movimientos_caja_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.movimientos_caja_id_seq', 1, false);


--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.movimientos_inventario_id_seq', 1, false);


--
-- Name: movimientos_puntos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.movimientos_puntos_id_seq', 1, false);


--
-- Name: pagos_cxps_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.pagos_cxps_id_seq', 1, false);


--
-- Name: pedidos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.pedidos_id_seq', 1, false);


--
-- Name: permission_user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.permission_user_id_seq', 1, false);


--
-- Name: productos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.productos_id_seq', 1, false);


--
-- Name: recetas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.recetas_id_seq', 1, false);


--
-- Name: reservas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.reservas_id_seq', 1, false);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.roles_id_seq', 7, true);


--
-- Name: sucursales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.sucursales_id_seq', 1, true);


--
-- Name: trabajos_impresion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.trabajos_impresion_id_seq', 1, false);


--
-- Name: turnos_caja_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.turnos_caja_id_seq', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: adminresto
--

SELECT pg_catalog.setval('public.users_id_seq', 7, true);


--
-- PostgreSQL database dump complete
--

\unrestrict tIqDfWIihjdSuy9gvA7ruNdDO3dzipSk4rgA5ProbX8GMKh8WYYncyPu8thAPgI

