--
-- PostgreSQL database dump
--

\restrict gTkbImYzkCH4ZcZ7reL3mk9GjeU0ILzP4jI0dKUEbfhxsvJtbKQOhai1rhlcEuM

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

ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_sucursal_id_foreign;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_role_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turnos_caja DROP CONSTRAINT IF EXISTS turnos_caja_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turnos_caja DROP CONSTRAINT IF EXISTS turnos_caja_cerrado_por_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.turnos_caja DROP CONSTRAINT IF EXISTS turnos_caja_caja_id_foreign;
ALTER TABLE IF EXISTS ONLY public.trabajos_impresion DROP CONSTRAINT IF EXISTS trabajos_impresion_usuario_id_foreign;
ALTER TABLE IF EXISTS ONLY public.trabajos_impresion DROP CONSTRAINT IF EXISTS trabajos_impresion_turno_caja_id_foreign;
ALTER TABLE IF EXISTS ONLY public.trabajos_impresion DROP CONSTRAINT IF EXISTS trabajos_impresion_reimpreso_por_id_foreign;
ALTER TABLE IF EXISTS ONLY public.trabajos_impresion DROP CONSTRAINT IF EXISTS trabajos_impresion_pedido_id_foreign;
ALTER TABLE IF EXISTS ONLY public.trabajos_impresion DROP CONSTRAINT IF EXISTS trabajos_impresion_impresora_id_foreign;
ALTER TABLE IF EXISTS ONLY public.reservas DROP CONSTRAINT IF EXISTS reservas_sucursal_id_foreign;
ALTER TABLE IF EXISTS ONLY public.reservas DROP CONSTRAINT IF EXISTS reservas_created_by_foreign;
ALTER TABLE IF EXISTS ONLY public.reservas DROP CONSTRAINT IF EXISTS reservas_confirmado_por_foreign;
ALTER TABLE IF EXISTS ONLY public.reservas DROP CONSTRAINT IF EXISTS reservas_cliente_id_foreign;
ALTER TABLE IF EXISTS ONLY public.reserva_mesa DROP CONSTRAINT IF EXISTS reserva_mesa_reserva_id_foreign;
ALTER TABLE IF EXISTS ONLY public.reserva_mesa DROP CONSTRAINT IF EXISTS reserva_mesa_mesa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.recetas DROP CONSTRAINT IF EXISTS recetas_producto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.recetas DROP CONSTRAINT IF EXISTS recetas_insumo_id_foreign;
ALTER TABLE IF EXISTS ONLY public.productos DROP CONSTRAINT IF EXISTS productos_categoria_id_foreign;
ALTER TABLE IF EXISTS ONLY public.permission_user DROP CONSTRAINT IF EXISTS permission_user_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_usuario_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_turno_caja_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_sucursal_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_repartidor_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_mesero_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_mesa_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_direccion_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_cliente_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pagos_cxps DROP CONSTRAINT IF EXISTS pagos_cxps_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.pagos_cxps DROP CONSTRAINT IF EXISTS pagos_cxps_cuenta_por_pagar_id_foreign;
ALTER TABLE IF EXISTS ONLY public.movimientos_puntos DROP CONSTRAINT IF EXISTS movimientos_puntos_usuario_id_foreign;
ALTER TABLE IF EXISTS ONLY public.movimientos_puntos DROP CONSTRAINT IF EXISTS movimientos_puntos_pedido_id_foreign;
ALTER TABLE IF EXISTS ONLY public.movimientos_puntos DROP CONSTRAINT IF EXISTS movimientos_puntos_cliente_id_foreign;
ALTER TABLE IF EXISTS ONLY public.movimientos_inventario DROP CONSTRAINT IF EXISTS movimientos_inventario_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.movimientos_inventario DROP CONSTRAINT IF EXISTS movimientos_inventario_pedido_id_foreign;
ALTER TABLE IF EXISTS ONLY public.movimientos_inventario DROP CONSTRAINT IF EXISTS movimientos_inventario_insumo_id_foreign;
ALTER TABLE IF EXISTS ONLY public.movimientos_caja DROP CONSTRAINT IF EXISTS movimientos_caja_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.movimientos_caja DROP CONSTRAINT IF EXISTS movimientos_caja_turno_caja_id_foreign;
ALTER TABLE IF EXISTS ONLY public.mesas DROP CONSTRAINT IF EXISTS mesas_sucursal_id_foreign;
ALTER TABLE IF EXISTS ONLY public.mesas DROP CONSTRAINT IF EXISTS mesas_mesero_id_foreign;
ALTER TABLE IF EXISTS ONLY public.items_pedido DROP CONSTRAINT IF EXISTS items_pedido_producto_id_foreign;
ALTER TABLE IF EXISTS ONLY public.items_pedido DROP CONSTRAINT IF EXISTS items_pedido_pedido_id_foreign;
ALTER TABLE IF EXISTS ONLY public.direcciones_cliente DROP CONSTRAINT IF EXISTS direcciones_cliente_cliente_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cuentas_por_pagar DROP CONSTRAINT IF EXISTS cuentas_por_pagar_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cuentas_por_pagar DROP CONSTRAINT IF EXISTS cuentas_por_pagar_insumo_id_foreign;
ALTER TABLE IF EXISTS ONLY public.cajas DROP CONSTRAINT IF EXISTS cajas_sucursal_id_foreign;
ALTER TABLE IF EXISTS ONLY public.auditorias DROP CONSTRAINT IF EXISTS auditorias_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.asientos_contables DROP CONSTRAINT IF EXISTS asientos_contables_user_id_foreign;
DROP INDEX IF EXISTS public.turnos_caja_caja_id_estado_index;
DROP INDEX IF EXISTS public.turnos_caja_caja_id_abierto_unique;
DROP INDEX IF EXISTS public.trabajos_impresion_pedido_id_tipo_index;
DROP INDEX IF EXISTS public.trabajos_impresion_estado_created_at_index;
DROP INDEX IF EXISTS public.sessions_user_id_index;
DROP INDEX IF EXISTS public.sessions_last_activity_index;
DROP INDEX IF EXISTS public.reservas_fecha_estado_index;
DROP INDEX IF EXISTS public.reservas_cliente_id_index;
DROP INDEX IF EXISTS public.productos_slug_index;
DROP INDEX IF EXISTS public.pedidos_usuario_id_index;
DROP INDEX IF EXISTS public.pedidos_turno_caja_id_index;
DROP INDEX IF EXISTS public.pedidos_tipo_estado_delivery_index;
DROP INDEX IF EXISTS public.pedidos_sucursal_id_index;
DROP INDEX IF EXISTS public.pedidos_mesero_id_index;
DROP INDEX IF EXISTS public.pedidos_mesa_id_estado_index;
DROP INDEX IF EXISTS public.pedidos_estado_pagado_en_index;
DROP INDEX IF EXISTS public.pedidos_estado_estado_delivery_index;
DROP INDEX IF EXISTS public.pedidos_estado_created_at_index;
DROP INDEX IF EXISTS public.pedidos_cliente_id_index;
DROP INDEX IF EXISTS public.pedidos_canal_origen_estado_index;
DROP INDEX IF EXISTS public.pagos_cxps_cuenta_por_pagar_id_index;
DROP INDEX IF EXISTS public.movimientos_inventario_tipo_index;
DROP INDEX IF EXISTS public.movimientos_inventario_insumo_id_created_at_index;
DROP INDEX IF EXISTS public.movimientos_caja_turno_caja_id_tipo_index;
DROP INDEX IF EXISTS public.mesas_mesero_id_index;
DROP INDEX IF EXISTS public.jobs_queue_index;
DROP INDEX IF EXISTS public.items_pedido_producto_id_index;
DROP INDEX IF EXISTS public.items_pedido_pedido_id_inventario_descontado_index;
DROP INDEX IF EXISTS public.items_pedido_estado_cocina_area_cocina_index;
DROP INDEX IF EXISTS public.insumos_stock_actual_index;
DROP INDEX IF EXISTS public.insumos_categoria_activo_index;
DROP INDEX IF EXISTS public.failed_jobs_connection_queue_failed_at_index;
DROP INDEX IF EXISTS public.cuentas_por_pagar_proveedor_nombre_estado_index;
DROP INDEX IF EXISTS public.cuentas_por_pagar_numero_factura_index;
DROP INDEX IF EXISTS public.cuentas_por_pagar_insumo_id_index;
DROP INDEX IF EXISTS public.cuentas_por_pagar_fecha_vencimiento_index;
DROP INDEX IF EXISTS public.configuraciones_grupo_index;
DROP INDEX IF EXISTS public.clientes_telefono_index;
DROP INDEX IF EXISTS public.cache_locks_expiration_index;
DROP INDEX IF EXISTS public.cache_expiration_index;
DROP INDEX IF EXISTS public.auditorias_entidad_entidad_id_index;
DROP INDEX IF EXISTS public.auditorias_created_at_index;
DROP INDEX IF EXISTS public.auditorias_accion_index;
DROP INDEX IF EXISTS public.asientos_contables_referencia_tipo_referencia_id_index;
DROP INDEX IF EXISTS public.asientos_contables_fecha_tipo_index;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_email_unique;
ALTER TABLE IF EXISTS ONLY public.turnos_caja DROP CONSTRAINT IF EXISTS turnos_caja_pkey;
ALTER TABLE IF EXISTS ONLY public.trabajos_impresion DROP CONSTRAINT IF EXISTS trabajos_impresion_pkey;
ALTER TABLE IF EXISTS ONLY public.sucursales DROP CONSTRAINT IF EXISTS sucursales_pkey;
ALTER TABLE IF EXISTS ONLY public.sessions DROP CONSTRAINT IF EXISTS sessions_pkey;
ALTER TABLE IF EXISTS ONLY public.roles DROP CONSTRAINT IF EXISTS roles_slug_unique;
ALTER TABLE IF EXISTS ONLY public.roles DROP CONSTRAINT IF EXISTS roles_pkey;
ALTER TABLE IF EXISTS ONLY public.reservas DROP CONSTRAINT IF EXISTS reservas_token_publico_unique;
ALTER TABLE IF EXISTS ONLY public.reservas DROP CONSTRAINT IF EXISTS reservas_pkey;
ALTER TABLE IF EXISTS ONLY public.reserva_mesa DROP CONSTRAINT IF EXISTS reserva_mesa_pkey;
ALTER TABLE IF EXISTS ONLY public.recetas DROP CONSTRAINT IF EXISTS recetas_producto_id_insumo_id_unique;
ALTER TABLE IF EXISTS ONLY public.recetas DROP CONSTRAINT IF EXISTS recetas_pkey;
ALTER TABLE IF EXISTS ONLY public.productos DROP CONSTRAINT IF EXISTS productos_pkey;
ALTER TABLE IF EXISTS ONLY public.permission_user DROP CONSTRAINT IF EXISTS permission_user_user_id_permission_unique;
ALTER TABLE IF EXISTS ONLY public.permission_user DROP CONSTRAINT IF EXISTS permission_user_pkey;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_pkey;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_idempotencia_uuid_unique;
ALTER TABLE IF EXISTS ONLY public.pedidos DROP CONSTRAINT IF EXISTS pedidos_codigo_unique;
ALTER TABLE IF EXISTS ONLY public.password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_pkey;
ALTER TABLE IF EXISTS ONLY public.pagos_cxps DROP CONSTRAINT IF EXISTS pagos_cxps_pkey;
ALTER TABLE IF EXISTS ONLY public.movimientos_puntos DROP CONSTRAINT IF EXISTS movimientos_puntos_pkey;
ALTER TABLE IF EXISTS ONLY public.movimientos_inventario DROP CONSTRAINT IF EXISTS movimientos_inventario_pkey;
ALTER TABLE IF EXISTS ONLY public.movimientos_caja DROP CONSTRAINT IF EXISTS movimientos_caja_pkey;
ALTER TABLE IF EXISTS ONLY public.migrations DROP CONSTRAINT IF EXISTS migrations_pkey;
ALTER TABLE IF EXISTS ONLY public.mesas DROP CONSTRAINT IF EXISTS mesas_pkey;
ALTER TABLE IF EXISTS ONLY public.jobs DROP CONSTRAINT IF EXISTS jobs_pkey;
ALTER TABLE IF EXISTS ONLY public.job_batches DROP CONSTRAINT IF EXISTS job_batches_pkey;
ALTER TABLE IF EXISTS ONLY public.items_pedido DROP CONSTRAINT IF EXISTS items_pedido_pkey;
ALTER TABLE IF EXISTS ONLY public.insumos DROP CONSTRAINT IF EXISTS insumos_pkey;
ALTER TABLE IF EXISTS ONLY public.insumos DROP CONSTRAINT IF EXISTS insumos_codigo_unique;
ALTER TABLE IF EXISTS ONLY public.impresoras DROP CONSTRAINT IF EXISTS impresoras_pkey;
ALTER TABLE IF EXISTS ONLY public.failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_uuid_unique;
ALTER TABLE IF EXISTS ONLY public.failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_pkey;
ALTER TABLE IF EXISTS ONLY public.direcciones_cliente DROP CONSTRAINT IF EXISTS direcciones_cliente_pkey;
ALTER TABLE IF EXISTS ONLY public.cuentas_por_pagar DROP CONSTRAINT IF EXISTS cuentas_por_pagar_pkey;
ALTER TABLE IF EXISTS ONLY public.configuraciones DROP CONSTRAINT IF EXISTS configuraciones_pkey;
ALTER TABLE IF EXISTS ONLY public.configuraciones DROP CONSTRAINT IF EXISTS configuraciones_grupo_clave_unique;
ALTER TABLE IF EXISTS ONLY public.clientes DROP CONSTRAINT IF EXISTS clientes_pkey;
ALTER TABLE IF EXISTS ONLY public.categorias DROP CONSTRAINT IF EXISTS categorias_slug_unique;
ALTER TABLE IF EXISTS ONLY public.categorias DROP CONSTRAINT IF EXISTS categorias_pkey;
ALTER TABLE IF EXISTS ONLY public.cajas DROP CONSTRAINT IF EXISTS cajas_pkey;
ALTER TABLE IF EXISTS ONLY public.cajas DROP CONSTRAINT IF EXISTS cajas_codigo_unique;
ALTER TABLE IF EXISTS ONLY public.cache DROP CONSTRAINT IF EXISTS cache_pkey;
ALTER TABLE IF EXISTS ONLY public.cache_locks DROP CONSTRAINT IF EXISTS cache_locks_pkey;
ALTER TABLE IF EXISTS ONLY public.auditorias DROP CONSTRAINT IF EXISTS auditorias_pkey;
ALTER TABLE IF EXISTS ONLY public.asientos_contables DROP CONSTRAINT IF EXISTS asientos_contables_pkey;
ALTER TABLE IF EXISTS public.users ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.turnos_caja ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.trabajos_impresion ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.sucursales ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.roles ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.reservas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.recetas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.productos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.permission_user ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.pedidos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.pagos_cxps ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.movimientos_puntos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.movimientos_inventario ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.movimientos_caja ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.migrations ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.mesas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.jobs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.items_pedido ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.insumos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.impresoras ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.failed_jobs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.direcciones_cliente ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cuentas_por_pagar ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.configuraciones ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.clientes ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.categorias ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cajas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.auditorias ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.asientos_contables ALTER COLUMN id DROP DEFAULT;
DROP SEQUENCE IF EXISTS public.users_id_seq;
DROP TABLE IF EXISTS public.users;
DROP SEQUENCE IF EXISTS public.turnos_caja_id_seq;
DROP TABLE IF EXISTS public.turnos_caja;
DROP SEQUENCE IF EXISTS public.trabajos_impresion_id_seq;
DROP TABLE IF EXISTS public.trabajos_impresion;
DROP SEQUENCE IF EXISTS public.sucursales_id_seq;
DROP TABLE IF EXISTS public.sucursales;
DROP TABLE IF EXISTS public.sessions;
DROP SEQUENCE IF EXISTS public.roles_id_seq;
DROP TABLE IF EXISTS public.roles;
DROP SEQUENCE IF EXISTS public.reservas_id_seq;
DROP TABLE IF EXISTS public.reservas;
DROP TABLE IF EXISTS public.reserva_mesa;
DROP SEQUENCE IF EXISTS public.recetas_id_seq;
DROP TABLE IF EXISTS public.recetas;
DROP SEQUENCE IF EXISTS public.productos_id_seq;
DROP TABLE IF EXISTS public.productos;
DROP SEQUENCE IF EXISTS public.permission_user_id_seq;
DROP TABLE IF EXISTS public.permission_user;
DROP SEQUENCE IF EXISTS public.pedidos_id_seq;
DROP TABLE IF EXISTS public.pedidos;
DROP TABLE IF EXISTS public.password_reset_tokens;
DROP SEQUENCE IF EXISTS public.pagos_cxps_id_seq;
DROP TABLE IF EXISTS public.pagos_cxps;
DROP SEQUENCE IF EXISTS public.movimientos_puntos_id_seq;
DROP TABLE IF EXISTS public.movimientos_puntos;
DROP SEQUENCE IF EXISTS public.movimientos_inventario_id_seq;
DROP TABLE IF EXISTS public.movimientos_inventario;
DROP SEQUENCE IF EXISTS public.movimientos_caja_id_seq;
DROP TABLE IF EXISTS public.movimientos_caja;
DROP SEQUENCE IF EXISTS public.migrations_id_seq;
DROP TABLE IF EXISTS public.migrations;
DROP SEQUENCE IF EXISTS public.mesas_id_seq;
DROP TABLE IF EXISTS public.mesas;
DROP SEQUENCE IF EXISTS public.jobs_id_seq;
DROP TABLE IF EXISTS public.jobs;
DROP TABLE IF EXISTS public.job_batches;
DROP SEQUENCE IF EXISTS public.items_pedido_id_seq;
DROP TABLE IF EXISTS public.items_pedido;
DROP SEQUENCE IF EXISTS public.insumos_id_seq;
DROP TABLE IF EXISTS public.insumos;
DROP SEQUENCE IF EXISTS public.impresoras_id_seq;
DROP TABLE IF EXISTS public.impresoras;
DROP SEQUENCE IF EXISTS public.failed_jobs_id_seq;
DROP TABLE IF EXISTS public.failed_jobs;
DROP SEQUENCE IF EXISTS public.direcciones_cliente_id_seq;
DROP TABLE IF EXISTS public.direcciones_cliente;
DROP SEQUENCE IF EXISTS public.cuentas_por_pagar_id_seq;
DROP TABLE IF EXISTS public.cuentas_por_pagar;
DROP SEQUENCE IF EXISTS public.configuraciones_id_seq;
DROP TABLE IF EXISTS public.configuraciones;
DROP SEQUENCE IF EXISTS public.clientes_id_seq;
DROP TABLE IF EXISTS public.clientes;
DROP SEQUENCE IF EXISTS public.categorias_id_seq;
DROP TABLE IF EXISTS public.categorias;
DROP SEQUENCE IF EXISTS public.cajas_id_seq;
DROP TABLE IF EXISTS public.cajas;
DROP TABLE IF EXISTS public.cache_locks;
DROP TABLE IF EXISTS public.cache;
DROP SEQUENCE IF EXISTS public.auditorias_id_seq;
DROP TABLE IF EXISTS public.auditorias;
DROP SEQUENCE IF EXISTS public.asientos_contables_id_seq;
DROP TABLE IF EXISTS public.asientos_contables;
SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: asientos_contables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asientos_contables (
    id bigint NOT NULL,
    fecha date NOT NULL,
    tipo character varying(255) NOT NULL,
    cuenta character varying(255) NOT NULL,
    concepto character varying(255) NOT NULL,
    monto numeric(12,2) NOT NULL,
    referencia_tipo character varying(255),
    referencia_id bigint,
    user_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: asientos_contables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asientos_contables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asientos_contables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asientos_contables_id_seq OWNED BY public.asientos_contables.id;


--
-- Name: auditorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.auditorias (
    id bigint NOT NULL,
    user_id bigint,
    accion character varying(255) NOT NULL,
    entidad character varying(255) NOT NULL,
    entidad_id bigint,
    descripcion character varying(255),
    datos json,
    ip character varying(45),
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: auditorias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.auditorias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: auditorias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.auditorias_id_seq OWNED BY public.auditorias.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cajas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cajas (
    id bigint NOT NULL,
    sucursal_id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    codigo character varying(255) NOT NULL,
    activa boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cajas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cajas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cajas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cajas_id_seq OWNED BY public.cajas.id;


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    icono character varying(255) DEFAULT '🍣'::character varying NOT NULL,
    orden integer DEFAULT 0 NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: categorias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_id_seq OWNED BY public.categorias.id;


--
-- Name: clientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    telefono character varying(255),
    email character varying(255),
    documento character varying(255),
    tier character varying(255) DEFAULT 'regular'::character varying NOT NULL,
    puntos_fidelidad integer DEFAULT 0 NOT NULL,
    total_gastado numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    visitas_count integer DEFAULT 0 NOT NULL,
    alergias text,
    preferencias text,
    notas text,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    acepta_tratamiento_datos boolean DEFAULT false NOT NULL,
    fecha_autorizacion_datos timestamp(0) without time zone,
    canal_autorizacion_datos character varying(50),
    autoriza_whatsapp boolean DEFAULT false NOT NULL,
    autoriza_email boolean DEFAULT false NOT NULL
);


--
-- Name: clientes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_id_seq OWNED BY public.clientes.id;


--
-- Name: configuraciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuraciones (
    id bigint NOT NULL,
    grupo character varying(255) NOT NULL,
    clave character varying(255) NOT NULL,
    valor json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: configuraciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configuraciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configuraciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuraciones_id_seq OWNED BY public.configuraciones.id;


--
-- Name: cuentas_por_pagar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuentas_por_pagar (
    id bigint NOT NULL,
    proveedor_nombre character varying(255) NOT NULL,
    proveedor_nit character varying(30),
    insumo_id bigint,
    concepto character varying(255) NOT NULL,
    monto_total numeric(12,2) NOT NULL,
    saldo_pendiente numeric(12,2) NOT NULL,
    fecha_emision date NOT NULL,
    fecha_vencimiento date,
    estado character varying(255) DEFAULT 'pendiente'::character varying NOT NULL,
    notas character varying(255),
    user_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    numero_factura character varying(50)
);


--
-- Name: cuentas_por_pagar_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuentas_por_pagar_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuentas_por_pagar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuentas_por_pagar_id_seq OWNED BY public.cuentas_por_pagar.id;


--
-- Name: direcciones_cliente; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.direcciones_cliente (
    id bigint NOT NULL,
    cliente_id bigint NOT NULL,
    etiqueta character varying(255) DEFAULT 'Casa'::character varying NOT NULL,
    direccion character varying(255) NOT NULL,
    referencia_apto character varying(255),
    barrio_ciudad character varying(255),
    telefono_contacto character varying(255),
    notas_entrega text,
    es_predeterminada boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: direcciones_cliente_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.direcciones_cliente_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: direcciones_cliente_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.direcciones_cliente_id_seq OWNED BY public.direcciones_cliente.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: impresoras; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.impresoras (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    tipo_conexion character varying(255) DEFAULT 'red_ip'::character varying NOT NULL,
    ip_address character varying(255),
    puerto integer DEFAULT 9100 NOT NULL,
    area character varying(255) DEFAULT 'todas'::character varying NOT NULL,
    ancho_columnas integer DEFAULT 48 NOT NULL,
    copias integer DEFAULT 1 NOT NULL,
    activa boolean DEFAULT true NOT NULL,
    descripcion character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    driver_nombre character varying(255)
);


--
-- Name: impresoras_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.impresoras_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: impresoras_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.impresoras_id_seq OWNED BY public.impresoras.id;


--
-- Name: insumos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.insumos (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    codigo character varying(255) NOT NULL,
    categoria character varying(255) NOT NULL,
    unidad_medida character varying(255) NOT NULL,
    stock_actual numeric(12,3) DEFAULT '0'::numeric NOT NULL,
    stock_minimo numeric(12,3) DEFAULT '1'::numeric NOT NULL,
    capacidad_maxima numeric(12,3) DEFAULT '100'::numeric NOT NULL,
    costo_unitario numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    proveedor_nombre character varying(255),
    proveedor_nit character varying(255),
    proveedor_telefono character varying(255),
    ubicacion_almacen character varying(255),
    temperatura_almacen character varying(255),
    imagen character varying(255),
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: insumos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.insumos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: insumos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.insumos_id_seq OWNED BY public.insumos.id;


--
-- Name: items_pedido; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.items_pedido (
    id bigint NOT NULL,
    pedido_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    nombre_producto character varying(255) NOT NULL,
    cantidad integer DEFAULT 1 NOT NULL,
    precio_unitario numeric(10,2) NOT NULL,
    subtotal numeric(10,2) NOT NULL,
    area_cocina character varying(255) DEFAULT 'sushi'::character varying NOT NULL,
    estado_cocina character varying(255) DEFAULT 'pendiente'::character varying NOT NULL,
    notas character varying(255),
    iniciado_en timestamp with time zone,
    listo_en timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    inventario_descontado boolean DEFAULT false NOT NULL
);


--
-- Name: items_pedido_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.items_pedido_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: items_pedido_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.items_pedido_id_seq OWNED BY public.items_pedido.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: mesas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mesas (
    id bigint NOT NULL,
    sucursal_id bigint NOT NULL,
    numero character varying(255) NOT NULL,
    capacidad integer DEFAULT 4 NOT NULL,
    zona character varying(255) DEFAULT 'salon'::character varying NOT NULL,
    estado character varying(255) DEFAULT 'libre'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    mesero_id bigint
);


--
-- Name: mesas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mesas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mesas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mesas_id_seq OWNED BY public.mesas.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: movimientos_caja; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.movimientos_caja (
    id bigint NOT NULL,
    turno_caja_id bigint NOT NULL,
    user_id bigint NOT NULL,
    tipo character varying(255) NOT NULL,
    concepto character varying(255) NOT NULL,
    monto numeric(12,2) NOT NULL,
    metodo_pago character varying(255) DEFAULT 'efectivo'::character varying NOT NULL,
    numero_comprobante character varying(255),
    autorizado_por character varying(255),
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: movimientos_caja_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.movimientos_caja_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimientos_caja_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimientos_caja_id_seq OWNED BY public.movimientos_caja.id;


--
-- Name: movimientos_inventario; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.movimientos_inventario (
    id bigint NOT NULL,
    insumo_id bigint NOT NULL,
    tipo character varying(255) NOT NULL,
    cantidad numeric(12,3) NOT NULL,
    saldo_anterior numeric(12,3) NOT NULL,
    saldo_posterior numeric(12,3) NOT NULL,
    costo_unitario numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    costo_total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    pedido_id bigint,
    user_id bigint,
    motivo character varying(255),
    referencia_documento character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.movimientos_inventario_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimientos_inventario_id_seq OWNED BY public.movimientos_inventario.id;


--
-- Name: movimientos_puntos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.movimientos_puntos (
    id bigint NOT NULL,
    cliente_id bigint NOT NULL,
    pedido_id bigint,
    tipo character varying(255) NOT NULL,
    puntos integer NOT NULL,
    saldo_anterior integer NOT NULL,
    saldo_nuevo integer NOT NULL,
    concepto character varying(255) NOT NULL,
    usuario_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: movimientos_puntos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.movimientos_puntos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimientos_puntos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimientos_puntos_id_seq OWNED BY public.movimientos_puntos.id;


--
-- Name: pagos_cxps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pagos_cxps (
    id bigint NOT NULL,
    cuenta_por_pagar_id bigint NOT NULL,
    user_id bigint,
    monto numeric(12,2) NOT NULL,
    metodo_pago character varying(255) DEFAULT 'efectivo'::character varying NOT NULL,
    fecha_pago date NOT NULL,
    concepto character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: pagos_cxps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pagos_cxps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pagos_cxps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pagos_cxps_id_seq OWNED BY public.pagos_cxps.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: pedidos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pedidos (
    id bigint NOT NULL,
    codigo character varying(255) NOT NULL,
    tipo character varying(255) DEFAULT 'mesa'::character varying NOT NULL,
    estado character varying(255) DEFAULT 'creado'::character varying NOT NULL,
    mesa_id bigint,
    usuario_id bigint,
    nombre_cliente character varying(255),
    telefono_cliente character varying(255),
    direccion_delivery text,
    subtotal numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    descuento numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    metodo_pago character varying(255),
    monto_pagado numeric(10,2),
    cambio numeric(10,2),
    notas text,
    pagado_en timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    turno_caja_id bigint,
    cliente_id bigint,
    direccion_id bigint,
    repartidor_id bigint,
    estado_delivery character varying(255),
    canal_origen character varying(255) DEFAULT 'pos'::character varying NOT NULL,
    costo_envio numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    puntos_ganados integer DEFAULT 0 NOT NULL,
    puntos_canjeados integer DEFAULT 0 NOT NULL,
    descuento_puntos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    recaudo_liquidado boolean DEFAULT false NOT NULL,
    hora_despacho timestamp with time zone,
    hora_entrega timestamp with time zone,
    sucursal_id bigint,
    monto_pago_efectivo numeric(12,2),
    monto_pago_tarjeta numeric(12,2),
    mesero_id bigint,
    propina numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    porcentaje_propina numeric(5,2) DEFAULT '0'::numeric,
    idempotencia_uuid uuid
);


--
-- Name: pedidos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pedidos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pedidos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pedidos_id_seq OWNED BY public.pedidos.id;


--
-- Name: permission_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permission_user (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    permission character varying(64) NOT NULL,
    tipo character varying(16) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permission_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permission_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permission_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permission_user_id_seq OWNED BY public.permission_user.id;


--
-- Name: productos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.productos (
    id bigint NOT NULL,
    categoria_id bigint,
    nombre character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    descripcion text,
    precio numeric(10,2) NOT NULL,
    costo numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    area_cocina character varying(255) DEFAULT 'sushi'::character varying NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    imagen character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: productos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.productos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: productos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.productos_id_seq OWNED BY public.productos.id;


--
-- Name: recetas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recetas (
    id bigint NOT NULL,
    producto_id bigint NOT NULL,
    insumo_id bigint NOT NULL,
    cantidad numeric(12,3) NOT NULL,
    merma_esperada_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    notas character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: recetas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recetas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recetas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recetas_id_seq OWNED BY public.recetas.id;


--
-- Name: reserva_mesa; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reserva_mesa (
    reserva_id bigint NOT NULL,
    mesa_id bigint NOT NULL
);


--
-- Name: reservas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reservas (
    id bigint NOT NULL,
    sucursal_id bigint,
    cliente_id bigint,
    nombre_contacto character varying(255) NOT NULL,
    telefono_contacto character varying(30) NOT NULL,
    email_contacto character varying(120),
    fecha date NOT NULL,
    hora_llegada time(0) without time zone NOT NULL,
    duracion_min smallint DEFAULT '120'::smallint NOT NULL,
    personas smallint NOT NULL,
    estado character varying(255) DEFAULT 'solicitada'::character varying NOT NULL,
    origen character varying(255) DEFAULT 'sistema'::character varying NOT NULL,
    notas text,
    anticipo numeric(12,2),
    confirmado_por bigint,
    token_publico character varying(64) NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: reservas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reservas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reservas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reservas_id_seq OWNED BY public.reservas.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    descripcion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: sucursales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sucursales (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    direccion character varying(255),
    telefono character varying(255),
    nit_ruc character varying(255),
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sucursales_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sucursales_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sucursales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sucursales_id_seq OWNED BY public.sucursales.id;


--
-- Name: trabajos_impresion; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trabajos_impresion (
    id bigint NOT NULL,
    tipo character varying(255) NOT NULL,
    pedido_id bigint,
    turno_caja_id bigint,
    impresora_id bigint NOT NULL,
    area character varying(255) DEFAULT 'general'::character varying NOT NULL,
    contenido_texto text NOT NULL,
    contenido_raw text,
    estado character varying(255) DEFAULT 'pendiente'::character varying NOT NULL,
    intentos integer DEFAULT 0 NOT NULL,
    error_mensaje text,
    impreso_en timestamp(0) without time zone,
    usuario_id bigint,
    reimpreso_por_id bigint,
    veces_reimpreso integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: trabajos_impresion_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trabajos_impresion_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trabajos_impresion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trabajos_impresion_id_seq OWNED BY public.trabajos_impresion.id;


--
-- Name: turnos_caja; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.turnos_caja (
    id bigint NOT NULL,
    caja_id bigint NOT NULL,
    user_id bigint NOT NULL,
    apertura_en timestamp with time zone NOT NULL,
    cierre_en timestamp with time zone,
    monto_inicial numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_ventas_efectivo numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_ventas_tarjeta numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_ventas_transferencia numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_egresos numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_retiros numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    monto_esperado_efectivo numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    monto_real_efectivo numeric(12,2),
    diferencia numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    estado character varying(255) DEFAULT 'abierto'::character varying NOT NULL,
    notas_apertura text,
    notas_cierre text,
    cerrado_por_user_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    total_ingresos numeric(12,2) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: turnos_caja_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.turnos_caja_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: turnos_caja_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.turnos_caja_id_seq OWNED BY public.turnos_caja.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    role_id bigint,
    telefono character varying(255),
    activo boolean DEFAULT true NOT NULL,
    sucursal_id bigint
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: asientos_contables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asientos_contables ALTER COLUMN id SET DEFAULT nextval('public.asientos_contables_id_seq'::regclass);


--
-- Name: auditorias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditorias ALTER COLUMN id SET DEFAULT nextval('public.auditorias_id_seq'::regclass);


--
-- Name: cajas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas ALTER COLUMN id SET DEFAULT nextval('public.cajas_id_seq'::regclass);


--
-- Name: categorias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id SET DEFAULT nextval('public.categorias_id_seq'::regclass);


--
-- Name: clientes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes ALTER COLUMN id SET DEFAULT nextval('public.clientes_id_seq'::regclass);


--
-- Name: configuraciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones ALTER COLUMN id SET DEFAULT nextval('public.configuraciones_id_seq'::regclass);


--
-- Name: cuentas_por_pagar id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_por_pagar ALTER COLUMN id SET DEFAULT nextval('public.cuentas_por_pagar_id_seq'::regclass);


--
-- Name: direcciones_cliente id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direcciones_cliente ALTER COLUMN id SET DEFAULT nextval('public.direcciones_cliente_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: impresoras id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.impresoras ALTER COLUMN id SET DEFAULT nextval('public.impresoras_id_seq'::regclass);


--
-- Name: insumos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.insumos ALTER COLUMN id SET DEFAULT nextval('public.insumos_id_seq'::regclass);


--
-- Name: items_pedido id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items_pedido ALTER COLUMN id SET DEFAULT nextval('public.items_pedido_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: mesas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesas ALTER COLUMN id SET DEFAULT nextval('public.mesas_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: movimientos_caja id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_caja ALTER COLUMN id SET DEFAULT nextval('public.movimientos_caja_id_seq'::regclass);


--
-- Name: movimientos_inventario id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario ALTER COLUMN id SET DEFAULT nextval('public.movimientos_inventario_id_seq'::regclass);


--
-- Name: movimientos_puntos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_puntos ALTER COLUMN id SET DEFAULT nextval('public.movimientos_puntos_id_seq'::regclass);


--
-- Name: pagos_cxps id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_cxps ALTER COLUMN id SET DEFAULT nextval('public.pagos_cxps_id_seq'::regclass);


--
-- Name: pedidos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos ALTER COLUMN id SET DEFAULT nextval('public.pedidos_id_seq'::regclass);


--
-- Name: permission_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permission_user ALTER COLUMN id SET DEFAULT nextval('public.permission_user_id_seq'::regclass);


--
-- Name: productos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos ALTER COLUMN id SET DEFAULT nextval('public.productos_id_seq'::regclass);


--
-- Name: recetas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recetas ALTER COLUMN id SET DEFAULT nextval('public.recetas_id_seq'::regclass);


--
-- Name: reservas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservas ALTER COLUMN id SET DEFAULT nextval('public.reservas_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: sucursales id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursales ALTER COLUMN id SET DEFAULT nextval('public.sucursales_id_seq'::regclass);


--
-- Name: trabajos_impresion id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trabajos_impresion ALTER COLUMN id SET DEFAULT nextval('public.trabajos_impresion_id_seq'::regclass);


--
-- Name: turnos_caja id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos_caja ALTER COLUMN id SET DEFAULT nextval('public.turnos_caja_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: asientos_contables; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.asientos_contables (id, fecha, tipo, cuenta, concepto, monto, referencia_tipo, referencia_id, user_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: auditorias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.auditorias (id, user_id, accion, entidad, entidad_id, descripcion, datos, ip, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: cajas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cajas (id, sucursal_id, nombre, codigo, activa, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.categorias (id, nombre, slug, icono, orden, activo, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.clientes (id, nombre, telefono, email, documento, tier, puntos_fidelidad, total_gastado, visitas_count, alergias, preferencias, notas, activo, created_at, updated_at, deleted_at, acepta_tratamiento_datos, fecha_autorizacion_datos, canal_autorizacion_datos, autoriza_whatsapp, autoriza_email) FROM stdin;
\.


--
-- Data for Name: configuraciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.configuraciones (id, grupo, clave, valor, created_at, updated_at) FROM stdin;
1	general	razon_social	"RestoMaster Colombia S.A.S."	2026-09-18 11:35:19	2026-09-18 11:35:19
2	general	nit	"901.458.789-3"	2026-09-18 11:35:19	2026-09-18 11:35:19
3	general	direccion	"Cra 35 # 8A-12, El Poblado"	2026-09-18 11:35:19	2026-09-18 11:35:19
4	general	telefono	"+57 300 123 4567"	2026-09-18 11:35:19	2026-09-18 11:35:19
5	general	ciudad	"Medell\\u00edn, Colombia"	2026-09-18 11:35:19	2026-09-18 11:35:19
6	general	regimen	"Com\\u00fan"	2026-09-18 11:35:19	2026-09-18 11:35:19
7	general	moneda	"COP"	2026-09-18 11:35:19	2026-09-18 11:35:19
8	general	simbolo_moneda	"$"	2026-09-18 11:35:19	2026-09-18 11:35:19
9	general	impuesto_porcentaje	8	2026-09-18 11:35:19	2026-09-18 11:35:19
10	general	costo_envio_base	8000	2026-09-18 11:35:19	2026-09-18 11:35:19
11	dian	envio_activo	false	2026-09-18 11:35:19	2026-09-18 11:35:19
12	dian	ambiente	"habilitacion"	2026-09-18 11:35:19	2026-09-18 11:35:19
13	dian	tipo_documento	"01"	2026-09-18 11:35:19	2026-09-18 11:35:19
14	dian	resolucion_numero	"1876400001234"	2026-09-18 11:35:19	2026-09-18 11:35:19
15	dian	resolucion_fecha	"2026-01-15"	2026-09-18 11:35:19	2026-09-18 11:35:19
16	dian	prefijo	"MP"	2026-09-18 11:35:19	2026-09-18 11:35:19
17	dian	desde	"1"	2026-09-18 11:35:19	2026-09-18 11:35:19
18	dian	hasta	"50000"	2026-09-18 11:35:19	2026-09-18 11:35:19
19	dian	vigente	true	2026-09-18 11:35:19	2026-09-18 11:35:19
20	ticket_80mm	nombre_comercial	"RESTOMASTER GASTRO"	2026-09-18 11:35:19	2026-09-18 11:35:19
21	ticket_80mm	lema	"Restaurante & Bar \\u00b7 Cocina Artesanal y Parrilla"	2026-09-18 11:35:19	2026-09-18 11:35:19
22	ticket_80mm	razon_social	"RestoMaster Colombia S.A.S."	2026-09-18 11:35:19	2026-09-18 11:35:19
23	ticket_80mm	nit	"901.458.789-3"	2026-09-18 11:35:19	2026-09-18 11:35:19
24	ticket_80mm	regimen	"IVA R\\u00e9gimen Com\\u00fan - Tarifa Especial"	2026-09-18 11:35:19	2026-09-18 11:35:19
25	ticket_80mm	direccion	"Cra 35 # 8A-12, El Poblado, Medell\\u00edn"	2026-09-18 11:35:19	2026-09-18 11:35:19
26	ticket_80mm	telefono	"+57 (4) 444-5566 \\u00b7 WhatsApp: +57 300 123 4567"	2026-09-18 11:35:19	2026-09-18 11:35:19
27	ticket_80mm	ciudad	"Medell\\u00edn, Antioquia"	2026-09-18 11:35:19	2026-09-18 11:35:19
28	ticket_80mm	mensaje_bienvenida	"\\u00a1Bienvenidos a una experiencia gastron\\u00f3mica \\u00fanica!"	2026-09-18 11:35:19	2026-09-18 11:35:19
29	ticket_80mm	resolucion_dian	"Resoluci\\u00f3n DIAN N\\u00b0 1876400001234 del 2026-01-15"	2026-09-18 11:35:19	2026-09-18 11:35:19
30	ticket_80mm	rango_autorizado	"Prefijo POS desde SEC-001 hasta SEC-50000"	2026-09-18 11:35:19	2026-09-18 11:35:19
31	ticket_80mm	mostrar_desglose_impuestos	true	2026-09-18 11:35:19	2026-09-18 11:35:19
32	ticket_80mm	mostrar_datos_mesero	true	2026-09-18 11:35:19	2026-09-18 11:35:19
33	ticket_80mm	sugerir_propina	true	2026-09-18 11:35:19	2026-09-18 11:35:19
34	ticket_80mm	porcentaje_propina	10	2026-09-18 11:35:19	2026-09-18 11:35:19
35	ticket_80mm	mensaje_propina	"Propina sugerida 10%: El servicio es voluntario"	2026-09-18 11:35:19	2026-09-18 11:35:19
36	ticket_80mm	pie_pagina	"\\u00a1Muchas gracias por su preferencia! Esperamos su pronta visita en RestoMaster."	2026-09-18 11:35:19	2026-09-18 11:35:19
37	ticket_80mm	redes_sociales	"Instagram: @restomaster \\u00b7 www.restomaster.co"	2026-09-18 11:35:19	2026-09-18 11:35:19
38	ticket_80mm	politica_cambios	"Verifique su pedido al momento de la entrega. Conserve este comprobante."	2026-09-18 11:35:19	2026-09-18 11:35:19
39	ticket_80mm	mostrar_qr	true	2026-09-18 11:35:19	2026-09-18 11:35:19
40	reservas	webhook_token	"Pvs15RvyRETv23maTaiBRtp6Y0qPXOaisxMo0hk6AlsPTkzK"	2026-09-18 11:35:19	2026-09-18 11:35:19
41	reservas	webhook_activo	false	2026-09-18 11:35:19	2026-09-18 11:35:19
42	database_external	host	"127.0.0.1"	2026-09-18 11:35:19	2026-09-18 11:35:19
43	database_external	port	5432	2026-09-18 11:35:19	2026-09-18 11:35:19
44	database_external	database	"restomaster"	2026-09-18 11:35:19	2026-09-18 11:35:19
45	database_external	username	"postgres"	2026-09-18 11:35:19	2026-09-18 11:35:19
46	database_external	password	""	2026-09-18 11:35:19	2026-09-18 11:35:19
47	database_external	sslmode	"prefer"	2026-09-18 11:35:19	2026-09-18 11:35:19
48	database_external	activo	false	2026-09-18 11:35:19	2026-09-18 11:35:19
49	impresion	pie_ticket	"\\u00a1Gracias por preferir RestoMaster!"	2026-09-18 11:35:19	2026-09-18 11:35:19
\.


--
-- Data for Name: cuentas_por_pagar; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuentas_por_pagar (id, proveedor_nombre, proveedor_nit, insumo_id, concepto, monto_total, saldo_pendiente, fecha_emision, fecha_vencimiento, estado, notas, user_id, created_at, updated_at, numero_factura) FROM stdin;
\.


--
-- Data for Name: direcciones_cliente; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.direcciones_cliente (id, cliente_id, etiqueta, direccion, referencia_apto, barrio_ciudad, telefono_contacto, notas_entrega, es_predeterminada, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: impresoras; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.impresoras (id, nombre, tipo_conexion, ip_address, puerto, area, ancho_columnas, copias, activa, descripcion, created_at, updated_at, driver_nombre) FROM stdin;
\.


--
-- Data for Name: insumos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.insumos (id, nombre, codigo, categoria, unidad_medida, stock_actual, stock_minimo, capacidad_maxima, costo_unitario, proveedor_nombre, proveedor_nit, proveedor_telefono, ubicacion_almacen, temperatura_almacen, imagen, activo, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: items_pedido; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.items_pedido (id, pedido_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal, area_cocina, estado_cocina, notas, iniciado_en, listo_en, created_at, updated_at, inventario_descontado) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: mesas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.mesas (id, sucursal_id, numero, capacidad, zona, estado, created_at, updated_at, mesero_id) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_09_09_174723_create_roles_table	1
5	2026_09_09_174728_add_role_and_profile_fields_to_users_table	1
6	2026_09_09_174731_create_sucursales_table	1
7	2026_09_09_174736_create_mesas_table	1
8	2026_09_09_180000_create_categorias_table	1
9	2026_09_09_180010_create_productos_table	1
10	2026_09_09_180020_create_pedidos_table	1
11	2026_09_09_180030_create_items_pedido_table	1
12	2026_09_09_190000_create_cajas_table	1
13	2026_09_09_190010_create_turnos_caja_table	1
14	2026_09_09_190020_create_movimientos_caja_table	1
15	2026_09_09_190030_create_asientos_contables_table	1
16	2026_09_09_191000_create_insumos_table	1
17	2026_09_09_191010_create_recetas_table	1
18	2026_09_09_191020_create_movimientos_inventario_table	1
19	2026_09_09_191030_add_inventario_descontado_to_items_pedido_table	1
20	2026_09_09_193000_create_auditorias_table	1
21	2026_09_09_193500_create_cuentas_por_pagar_table	1
22	2026_09_09_193510_create_pagos_cxps_table	1
23	2026_09_09_194000_create_clientes_table	1
24	2026_09_09_194010_create_direcciones_cliente_table	1
25	2026_09_09_194020_create_movimientos_puntos_table	1
26	2026_09_09_194030_add_delivery_and_loyalty_fields_to_pedidos_table	1
27	2026_09_09_200000_create_configuraciones_table	1
28	2026_09_09_200010_create_reservas_table	1
29	2026_09_09_200020_create_reserva_mesa_table	1
30	2026_09_09_210000_create_impresoras_table	1
31	2026_09_09_210010_create_trabajos_impresion_table	1
32	2026_09_09_211000_add_sucursal_id_to_users_table	1
33	2026_09_10_120000_add_performance_indexes_to_pedidos_and_items	1
34	2026_09_10_183500_add_driver_nombre_to_impresoras_table	1
35	2026_09_10_200000_harden_db_integrity_audit_fixes	1
36	2026_09_10_220000_harden_historical_foreign_keys	1
37	2026_09_10_230000_add_performance_audit_indexes	1
38	2026_09_10_240000_harden_remaining_foreign_keys	1
39	2026_09_10_250000_harden_items_pedido_fk	1
40	2026_09_15_160000_add_total_ingresos_to_turnos_caja_table	1
41	2026_09_15_170000_add_audit_missing_indexes	1
42	2026_09_15_183000_add_sucursal_id_to_pedidos_table	1
43	2026_09_15_190000_batch_b_dinero_turnos_table	1
44	2026_09_16_143000_add_numero_factura_to_cuentas_por_pagar_table	1
45	2026_09_16_160000_make_telefono_nullable_and_add_habeas_data_to_clientes_table	1
46	2026_09_16_170000_add_mesero_id_and_propina_to_mesas_and_pedidos_tables	1
47	2026_09_17_210000_convert_transactional_timestamps_to_timestamptz	1
48	2026_09_17_220000_fix_timestamptz_bogota_offset	1
49	2026_09_17_221000_add_idempotencia_uuid_to_pedidos	1
50	2026_09_18_020000_fix_turnos_caja_unique_partial_index	1
51	2026_09_18_100000_create_permission_user_table	1
\.


--
-- Data for Name: movimientos_caja; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.movimientos_caja (id, turno_caja_id, user_id, tipo, concepto, monto, metodo_pago, numero_comprobante, autorizado_por, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: movimientos_inventario; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.movimientos_inventario (id, insumo_id, tipo, cantidad, saldo_anterior, saldo_posterior, costo_unitario, costo_total, pedido_id, user_id, motivo, referencia_documento, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: movimientos_puntos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.movimientos_puntos (id, cliente_id, pedido_id, tipo, puntos, saldo_anterior, saldo_nuevo, concepto, usuario_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: pagos_cxps; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pagos_cxps (id, cuenta_por_pagar_id, user_id, monto, metodo_pago, fecha_pago, concepto, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: pedidos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pedidos (id, codigo, tipo, estado, mesa_id, usuario_id, nombre_cliente, telefono_cliente, direccion_delivery, subtotal, descuento, total, metodo_pago, monto_pagado, cambio, notas, pagado_en, created_at, updated_at, turno_caja_id, cliente_id, direccion_id, repartidor_id, estado_delivery, canal_origen, costo_envio, puntos_ganados, puntos_canjeados, descuento_puntos, recaudo_liquidado, hora_despacho, hora_entrega, sucursal_id, monto_pago_efectivo, monto_pago_tarjeta, mesero_id, propina, porcentaje_propina, idempotencia_uuid) FROM stdin;
\.


--
-- Data for Name: permission_user; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permission_user (id, user_id, permission, tipo, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: productos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.productos (id, categoria_id, nombre, slug, descripcion, precio, costo, area_cocina, activo, imagen, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: recetas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.recetas (id, producto_id, insumo_id, cantidad, merma_esperada_pct, notas, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: reserva_mesa; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.reserva_mesa (reserva_id, mesa_id) FROM stdin;
\.


--
-- Data for Name: reservas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.reservas (id, sucursal_id, cliente_id, nombre_contacto, telefono_contacto, email_contacto, fecha, hora_llegada, duracion_min, personas, estado, origen, notas, anticipo, confirmado_por, token_publico, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, nombre, slug, descripcion, created_at, updated_at) FROM stdin;
1	Administrador	admin	Acceso total al sistema y configuración	2026-09-18 11:35:19	2026-09-18 11:35:19
2	Gerente	gerente	Gestión operativa, inventarios y reportes	2026-09-18 11:35:19	2026-09-18 11:35:19
3	Cajero	cajero	Punto de venta, control de caja y cobranza	2026-09-18 11:35:19	2026-09-18 11:35:19
4	Mesero	mesero	Atención de mesas y toma de pedidos táctil	2026-09-18 11:35:19	2026-09-18 11:35:19
5	Cocina	cocina	Pantalla KDS de preparación de sushi y cocina	2026-09-18 11:35:19	2026-09-18 11:35:19
6	Barra	barra	Pantalla KDS de bebidas y barra	2026-09-18 11:35:19	2026-09-18 11:35:19
7	Delivery	delivery	Repartidor y despacho de pedidos a domicilio	2026-09-18 11:35:19	2026-09-18 11:35:19
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
aJlk5X9cvPlZOxB7YyzzrgGSyGPKI2rTxogmKtby	\N	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36	eyJfdG9rZW4iOiJRNjZ3M25MRmtINm5rWjNsZ3hPV0xidnFxVEZaaTBUeGUxdk1PdG1VIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL2ludmVudGFyaW8ifSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9pbnZlbnRhcmlvIiwicm91dGUiOiJpbnZlbnRhcmlvIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=	1789749323
\.


--
-- Data for Name: sucursales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sucursales (id, nombre, direccion, telefono, nit_ruc, activo, created_at, updated_at) FROM stdin;
1	RestoMaster Principal	Cra 35 # 8A-12, Provenza, Medellín	+57 300 123 4567	901.458.789-3	t	2026-09-18 11:35:19	2026-09-18 11:35:19
\.


--
-- Data for Name: trabajos_impresion; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.trabajos_impresion (id, tipo, pedido_id, turno_caja_id, impresora_id, area, contenido_texto, contenido_raw, estado, intentos, error_mensaje, impreso_en, usuario_id, reimpreso_por_id, veces_reimpreso, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: turnos_caja; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.turnos_caja (id, caja_id, user_id, apertura_en, cierre_en, monto_inicial, total_ventas_efectivo, total_ventas_tarjeta, total_ventas_transferencia, total_egresos, total_retiros, monto_esperado_efectivo, monto_real_efectivo, diferencia, estado, notas_apertura, notas_cierre, cerrado_por_user_id, created_at, updated_at, total_ingresos) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role_id, telefono, activo, sucursal_id) FROM stdin;
1	Administrador RestoMaster	admin@restomaster.com	2026-09-18 11:35:19	$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq	\N	2026-09-18 11:35:19	2026-09-18 11:35:19	1	+57 300 987 6543	t	1
2	Gerente de Operaciones	gerente@restomaster.com	2026-09-18 11:35:19	$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq	\N	2026-09-18 11:35:19	2026-09-18 11:35:19	2	+57 300 111 2233	t	1
3	Cajero Principal	cajero@restomaster.com	2026-09-18 11:35:19	$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq	\N	2026-09-18 11:35:19	2026-09-18 11:35:19	3	+57 300 222 3344	t	1
4	Mesero Turno Salón	mesero@restomaster.com	2026-09-18 11:35:19	$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq	\N	2026-09-18 11:35:19	2026-09-18 11:35:19	4	+57 300 333 4455	t	1
5	Chef de Cocina KDS	cocina@restomaster.com	2026-09-18 11:35:19	$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq	\N	2026-09-18 11:35:19	2026-09-18 11:35:19	5	+57 300 444 5566	t	1
6	Bartender Barra Bebidas	barra@restomaster.com	2026-09-18 11:35:19	$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq	\N	2026-09-18 11:35:19	2026-09-18 11:35:19	6	+57 300 555 6677	t	1
7	Repartidor Delivery	delivery@restomaster.com	2026-09-18 11:35:19	$2y$12$pWVHfA/ip7AaqWGbxnfmZOc0AxuKpjPCzUysvMWf3D/f/IC0.ZZcq	\N	2026-09-18 11:35:19	2026-09-18 11:35:19	7	+57 300 666 7788	t	1
\.


--
-- Name: asientos_contables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.asientos_contables_id_seq', 1, false);


--
-- Name: auditorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.auditorias_id_seq', 1, false);


--
-- Name: cajas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cajas_id_seq', 1, false);


--
-- Name: categorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.categorias_id_seq', 1, false);


--
-- Name: clientes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_id_seq', 1, false);


--
-- Name: configuraciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.configuraciones_id_seq', 49, true);


--
-- Name: cuentas_por_pagar_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuentas_por_pagar_id_seq', 1, false);


--
-- Name: direcciones_cliente_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.direcciones_cliente_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: impresoras_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.impresoras_id_seq', 1, false);


--
-- Name: insumos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.insumos_id_seq', 1, false);


--
-- Name: items_pedido_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.items_pedido_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: mesas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.mesas_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 51, true);


--
-- Name: movimientos_caja_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.movimientos_caja_id_seq', 1, false);


--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.movimientos_inventario_id_seq', 1, false);


--
-- Name: movimientos_puntos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.movimientos_puntos_id_seq', 1, false);


--
-- Name: pagos_cxps_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pagos_cxps_id_seq', 1, false);


--
-- Name: pedidos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pedidos_id_seq', 1, false);


--
-- Name: permission_user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permission_user_id_seq', 1, false);


--
-- Name: productos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.productos_id_seq', 1, false);


--
-- Name: recetas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.recetas_id_seq', 1, false);


--
-- Name: reservas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.reservas_id_seq', 1, false);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 7, true);


--
-- Name: sucursales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.sucursales_id_seq', 1, true);


--
-- Name: trabajos_impresion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.trabajos_impresion_id_seq', 1, false);


--
-- Name: turnos_caja_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.turnos_caja_id_seq', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 7, true);


--
-- Name: asientos_contables asientos_contables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asientos_contables
    ADD CONSTRAINT asientos_contables_pkey PRIMARY KEY (id);


--
-- Name: auditorias auditorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditorias
    ADD CONSTRAINT auditorias_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: cajas cajas_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_codigo_unique UNIQUE (codigo);


--
-- Name: cajas cajas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_slug_unique UNIQUE (slug);


--
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id);


--
-- Name: configuraciones configuraciones_grupo_clave_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones
    ADD CONSTRAINT configuraciones_grupo_clave_unique UNIQUE (grupo, clave);


--
-- Name: configuraciones configuraciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones
    ADD CONSTRAINT configuraciones_pkey PRIMARY KEY (id);


--
-- Name: cuentas_por_pagar cuentas_por_pagar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_por_pagar
    ADD CONSTRAINT cuentas_por_pagar_pkey PRIMARY KEY (id);


--
-- Name: direcciones_cliente direcciones_cliente_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direcciones_cliente
    ADD CONSTRAINT direcciones_cliente_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: impresoras impresoras_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.impresoras
    ADD CONSTRAINT impresoras_pkey PRIMARY KEY (id);


--
-- Name: insumos insumos_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.insumos
    ADD CONSTRAINT insumos_codigo_unique UNIQUE (codigo);


--
-- Name: insumos insumos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.insumos
    ADD CONSTRAINT insumos_pkey PRIMARY KEY (id);


--
-- Name: items_pedido items_pedido_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items_pedido
    ADD CONSTRAINT items_pedido_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: mesas mesas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesas
    ADD CONSTRAINT mesas_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: movimientos_caja movimientos_caja_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_caja
    ADD CONSTRAINT movimientos_caja_pkey PRIMARY KEY (id);


--
-- Name: movimientos_inventario movimientos_inventario_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_pkey PRIMARY KEY (id);


--
-- Name: movimientos_puntos movimientos_puntos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_puntos
    ADD CONSTRAINT movimientos_puntos_pkey PRIMARY KEY (id);


--
-- Name: pagos_cxps pagos_cxps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_cxps
    ADD CONSTRAINT pagos_cxps_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: pedidos pedidos_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_codigo_unique UNIQUE (codigo);


--
-- Name: pedidos pedidos_idempotencia_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_idempotencia_uuid_unique UNIQUE (idempotencia_uuid);


--
-- Name: pedidos pedidos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_pkey PRIMARY KEY (id);


--
-- Name: permission_user permission_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permission_user
    ADD CONSTRAINT permission_user_pkey PRIMARY KEY (id);


--
-- Name: permission_user permission_user_user_id_permission_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permission_user
    ADD CONSTRAINT permission_user_user_id_permission_unique UNIQUE (user_id, permission);


--
-- Name: productos productos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_pkey PRIMARY KEY (id);


--
-- Name: recetas recetas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recetas
    ADD CONSTRAINT recetas_pkey PRIMARY KEY (id);


--
-- Name: recetas recetas_producto_id_insumo_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recetas
    ADD CONSTRAINT recetas_producto_id_insumo_id_unique UNIQUE (producto_id, insumo_id);


--
-- Name: reserva_mesa reserva_mesa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reserva_mesa
    ADD CONSTRAINT reserva_mesa_pkey PRIMARY KEY (reserva_id, mesa_id);


--
-- Name: reservas reservas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservas
    ADD CONSTRAINT reservas_pkey PRIMARY KEY (id);


--
-- Name: reservas reservas_token_publico_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservas
    ADD CONSTRAINT reservas_token_publico_unique UNIQUE (token_publico);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_slug_unique UNIQUE (slug);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: sucursales sucursales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursales
    ADD CONSTRAINT sucursales_pkey PRIMARY KEY (id);


--
-- Name: trabajos_impresion trabajos_impresion_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trabajos_impresion
    ADD CONSTRAINT trabajos_impresion_pkey PRIMARY KEY (id);


--
-- Name: turnos_caja turnos_caja_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos_caja
    ADD CONSTRAINT turnos_caja_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: asientos_contables_fecha_tipo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX asientos_contables_fecha_tipo_index ON public.asientos_contables USING btree (fecha, tipo);


--
-- Name: asientos_contables_referencia_tipo_referencia_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX asientos_contables_referencia_tipo_referencia_id_index ON public.asientos_contables USING btree (referencia_tipo, referencia_id);


--
-- Name: auditorias_accion_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditorias_accion_index ON public.auditorias USING btree (accion);


--
-- Name: auditorias_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditorias_created_at_index ON public.auditorias USING btree (created_at);


--
-- Name: auditorias_entidad_entidad_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX auditorias_entidad_entidad_id_index ON public.auditorias USING btree (entidad, entidad_id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: clientes_telefono_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clientes_telefono_index ON public.clientes USING btree (telefono);


--
-- Name: configuraciones_grupo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX configuraciones_grupo_index ON public.configuraciones USING btree (grupo);


--
-- Name: cuentas_por_pagar_fecha_vencimiento_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cuentas_por_pagar_fecha_vencimiento_index ON public.cuentas_por_pagar USING btree (fecha_vencimiento);


--
-- Name: cuentas_por_pagar_insumo_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cuentas_por_pagar_insumo_id_index ON public.cuentas_por_pagar USING btree (insumo_id);


--
-- Name: cuentas_por_pagar_numero_factura_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cuentas_por_pagar_numero_factura_index ON public.cuentas_por_pagar USING btree (numero_factura);


--
-- Name: cuentas_por_pagar_proveedor_nombre_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cuentas_por_pagar_proveedor_nombre_estado_index ON public.cuentas_por_pagar USING btree (proveedor_nombre, estado);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: insumos_categoria_activo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX insumos_categoria_activo_index ON public.insumos USING btree (categoria, activo);


--
-- Name: insumos_stock_actual_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX insumos_stock_actual_index ON public.insumos USING btree (stock_actual);


--
-- Name: items_pedido_estado_cocina_area_cocina_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX items_pedido_estado_cocina_area_cocina_index ON public.items_pedido USING btree (estado_cocina, area_cocina);


--
-- Name: items_pedido_pedido_id_inventario_descontado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX items_pedido_pedido_id_inventario_descontado_index ON public.items_pedido USING btree (pedido_id, inventario_descontado);


--
-- Name: items_pedido_producto_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX items_pedido_producto_id_index ON public.items_pedido USING btree (producto_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: mesas_mesero_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mesas_mesero_id_index ON public.mesas USING btree (mesero_id);


--
-- Name: movimientos_caja_turno_caja_id_tipo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX movimientos_caja_turno_caja_id_tipo_index ON public.movimientos_caja USING btree (turno_caja_id, tipo);


--
-- Name: movimientos_inventario_insumo_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX movimientos_inventario_insumo_id_created_at_index ON public.movimientos_inventario USING btree (insumo_id, created_at);


--
-- Name: movimientos_inventario_tipo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX movimientos_inventario_tipo_index ON public.movimientos_inventario USING btree (tipo);


--
-- Name: pagos_cxps_cuenta_por_pagar_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pagos_cxps_cuenta_por_pagar_id_index ON public.pagos_cxps USING btree (cuenta_por_pagar_id);


--
-- Name: pedidos_canal_origen_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_canal_origen_estado_index ON public.pedidos USING btree (canal_origen, estado);


--
-- Name: pedidos_cliente_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_cliente_id_index ON public.pedidos USING btree (cliente_id);


--
-- Name: pedidos_estado_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_estado_created_at_index ON public.pedidos USING btree (estado, created_at);


--
-- Name: pedidos_estado_estado_delivery_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_estado_estado_delivery_index ON public.pedidos USING btree (estado, estado_delivery);


--
-- Name: pedidos_estado_pagado_en_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_estado_pagado_en_index ON public.pedidos USING btree (estado, pagado_en);


--
-- Name: pedidos_mesa_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_mesa_id_estado_index ON public.pedidos USING btree (mesa_id, estado);


--
-- Name: pedidos_mesero_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_mesero_id_index ON public.pedidos USING btree (mesero_id);


--
-- Name: pedidos_sucursal_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_sucursal_id_index ON public.pedidos USING btree (sucursal_id);


--
-- Name: pedidos_tipo_estado_delivery_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_tipo_estado_delivery_index ON public.pedidos USING btree (tipo, estado_delivery);


--
-- Name: pedidos_turno_caja_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_turno_caja_id_index ON public.pedidos USING btree (turno_caja_id);


--
-- Name: pedidos_usuario_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX pedidos_usuario_id_index ON public.pedidos USING btree (usuario_id);


--
-- Name: productos_slug_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX productos_slug_index ON public.productos USING btree (slug);


--
-- Name: reservas_cliente_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reservas_cliente_id_index ON public.reservas USING btree (cliente_id);


--
-- Name: reservas_fecha_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reservas_fecha_estado_index ON public.reservas USING btree (fecha, estado);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: trabajos_impresion_estado_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trabajos_impresion_estado_created_at_index ON public.trabajos_impresion USING btree (estado, created_at);


--
-- Name: trabajos_impresion_pedido_id_tipo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trabajos_impresion_pedido_id_tipo_index ON public.trabajos_impresion USING btree (pedido_id, tipo);


--
-- Name: turnos_caja_caja_id_abierto_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX turnos_caja_caja_id_abierto_unique ON public.turnos_caja USING btree (caja_id) WHERE ((estado)::text = 'abierto'::text);


--
-- Name: turnos_caja_caja_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX turnos_caja_caja_id_estado_index ON public.turnos_caja USING btree (caja_id, estado);


--
-- Name: asientos_contables asientos_contables_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asientos_contables
    ADD CONSTRAINT asientos_contables_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: auditorias auditorias_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditorias
    ADD CONSTRAINT auditorias_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cajas cajas_sucursal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_sucursal_id_foreign FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE RESTRICT;


--
-- Name: cuentas_por_pagar cuentas_por_pagar_insumo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_por_pagar
    ADD CONSTRAINT cuentas_por_pagar_insumo_id_foreign FOREIGN KEY (insumo_id) REFERENCES public.insumos(id) ON DELETE SET NULL;


--
-- Name: cuentas_por_pagar cuentas_por_pagar_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_por_pagar
    ADD CONSTRAINT cuentas_por_pagar_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: direcciones_cliente direcciones_cliente_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.direcciones_cliente
    ADD CONSTRAINT direcciones_cliente_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE RESTRICT;


--
-- Name: items_pedido items_pedido_pedido_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items_pedido
    ADD CONSTRAINT items_pedido_pedido_id_foreign FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE RESTRICT;


--
-- Name: items_pedido items_pedido_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.items_pedido
    ADD CONSTRAINT items_pedido_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE RESTRICT;


--
-- Name: mesas mesas_mesero_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesas
    ADD CONSTRAINT mesas_mesero_id_foreign FOREIGN KEY (mesero_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: mesas mesas_sucursal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mesas
    ADD CONSTRAINT mesas_sucursal_id_foreign FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE RESTRICT;


--
-- Name: movimientos_caja movimientos_caja_turno_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_caja
    ADD CONSTRAINT movimientos_caja_turno_caja_id_foreign FOREIGN KEY (turno_caja_id) REFERENCES public.turnos_caja(id) ON DELETE RESTRICT;


--
-- Name: movimientos_caja movimientos_caja_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_caja
    ADD CONSTRAINT movimientos_caja_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: movimientos_inventario movimientos_inventario_insumo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_insumo_id_foreign FOREIGN KEY (insumo_id) REFERENCES public.insumos(id) ON DELETE RESTRICT;


--
-- Name: movimientos_inventario movimientos_inventario_pedido_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_pedido_id_foreign FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE SET NULL;


--
-- Name: movimientos_inventario movimientos_inventario_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: movimientos_puntos movimientos_puntos_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_puntos
    ADD CONSTRAINT movimientos_puntos_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE RESTRICT;


--
-- Name: movimientos_puntos movimientos_puntos_pedido_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_puntos
    ADD CONSTRAINT movimientos_puntos_pedido_id_foreign FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE SET NULL;


--
-- Name: movimientos_puntos movimientos_puntos_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_puntos
    ADD CONSTRAINT movimientos_puntos_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: pagos_cxps pagos_cxps_cuenta_por_pagar_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_cxps
    ADD CONSTRAINT pagos_cxps_cuenta_por_pagar_id_foreign FOREIGN KEY (cuenta_por_pagar_id) REFERENCES public.cuentas_por_pagar(id) ON DELETE RESTRICT;


--
-- Name: pagos_cxps pagos_cxps_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_cxps
    ADD CONSTRAINT pagos_cxps_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: pedidos pedidos_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;


--
-- Name: pedidos pedidos_direccion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_direccion_id_foreign FOREIGN KEY (direccion_id) REFERENCES public.direcciones_cliente(id) ON DELETE SET NULL;


--
-- Name: pedidos pedidos_mesa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_mesa_id_foreign FOREIGN KEY (mesa_id) REFERENCES public.mesas(id) ON DELETE SET NULL;


--
-- Name: pedidos pedidos_mesero_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_mesero_id_foreign FOREIGN KEY (mesero_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: pedidos pedidos_repartidor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_repartidor_id_foreign FOREIGN KEY (repartidor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: pedidos pedidos_sucursal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_sucursal_id_foreign FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE SET NULL;


--
-- Name: pedidos pedidos_turno_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_turno_caja_id_foreign FOREIGN KEY (turno_caja_id) REFERENCES public.turnos_caja(id) ON DELETE SET NULL;


--
-- Name: pedidos pedidos_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: permission_user permission_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permission_user
    ADD CONSTRAINT permission_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: productos productos_categoria_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES public.categorias(id) ON DELETE SET NULL;


--
-- Name: recetas recetas_insumo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recetas
    ADD CONSTRAINT recetas_insumo_id_foreign FOREIGN KEY (insumo_id) REFERENCES public.insumos(id) ON DELETE RESTRICT;


--
-- Name: recetas recetas_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recetas
    ADD CONSTRAINT recetas_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE RESTRICT;


--
-- Name: reserva_mesa reserva_mesa_mesa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reserva_mesa
    ADD CONSTRAINT reserva_mesa_mesa_id_foreign FOREIGN KEY (mesa_id) REFERENCES public.mesas(id) ON DELETE RESTRICT;


--
-- Name: reserva_mesa reserva_mesa_reserva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reserva_mesa
    ADD CONSTRAINT reserva_mesa_reserva_id_foreign FOREIGN KEY (reserva_id) REFERENCES public.reservas(id) ON DELETE RESTRICT;


--
-- Name: reservas reservas_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservas
    ADD CONSTRAINT reservas_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;


--
-- Name: reservas reservas_confirmado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservas
    ADD CONSTRAINT reservas_confirmado_por_foreign FOREIGN KEY (confirmado_por) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: reservas reservas_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservas
    ADD CONSTRAINT reservas_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: reservas reservas_sucursal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservas
    ADD CONSTRAINT reservas_sucursal_id_foreign FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE SET NULL;


--
-- Name: trabajos_impresion trabajos_impresion_impresora_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trabajos_impresion
    ADD CONSTRAINT trabajos_impresion_impresora_id_foreign FOREIGN KEY (impresora_id) REFERENCES public.impresoras(id) ON DELETE RESTRICT;


--
-- Name: trabajos_impresion trabajos_impresion_pedido_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trabajos_impresion
    ADD CONSTRAINT trabajos_impresion_pedido_id_foreign FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE SET NULL;


--
-- Name: trabajos_impresion trabajos_impresion_reimpreso_por_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trabajos_impresion
    ADD CONSTRAINT trabajos_impresion_reimpreso_por_id_foreign FOREIGN KEY (reimpreso_por_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: trabajos_impresion trabajos_impresion_turno_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trabajos_impresion
    ADD CONSTRAINT trabajos_impresion_turno_caja_id_foreign FOREIGN KEY (turno_caja_id) REFERENCES public.turnos_caja(id) ON DELETE SET NULL;


--
-- Name: trabajos_impresion trabajos_impresion_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trabajos_impresion
    ADD CONSTRAINT trabajos_impresion_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: turnos_caja turnos_caja_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos_caja
    ADD CONSTRAINT turnos_caja_caja_id_foreign FOREIGN KEY (caja_id) REFERENCES public.cajas(id) ON DELETE RESTRICT;


--
-- Name: turnos_caja turnos_caja_cerrado_por_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos_caja
    ADD CONSTRAINT turnos_caja_cerrado_por_user_id_foreign FOREIGN KEY (cerrado_por_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: turnos_caja turnos_caja_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.turnos_caja
    ADD CONSTRAINT turnos_caja_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: users users_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE SET NULL;


--
-- Name: users users_sucursal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_sucursal_id_foreign FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict gTkbImYzkCH4ZcZ7reL3mk9GjeU0ILzP4jI0dKUEbfhxsvJtbKQOhai1rhlcEuM

