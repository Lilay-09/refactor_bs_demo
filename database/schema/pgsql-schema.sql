--
-- PostgreSQL database dump
--

-- Dumped from database version 16.2
-- Dumped by pg_dump version 16.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: adjustment_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adjustment_types (
    id bigint NOT NULL,
    name character varying(30) NOT NULL,
    name_kh character varying(50),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: adjustment_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adjustment_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: adjustment_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adjustment_types_id_seq OWNED BY public.adjustment_types.id;


--
-- Name: branches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.branches (
    id bigint NOT NULL,
    name character varying(100) NOT NULL,
    name_km character varying(150) NOT NULL,
    address character varying(250) NOT NULL,
    email character varying(100),
    phone character varying(25) NOT NULL,
    company_id bigint NOT NULL,
    description character varying(500),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL
);


--
-- Name: branches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.branches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.branches_id_seq OWNED BY public.branches.id;


--
-- Name: brands; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.brands (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(100)
);


--
-- Name: brands_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.brands_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: brands_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.brands_id_seq OWNED BY public.brands.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(100),
    parent_cate_id bigint
);


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: cities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cities (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(100),
    country_id bigint NOT NULL
);


--
-- Name: cities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cities_id_seq OWNED BY public.cities.id;


--
-- Name: companies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.companies (
    id bigint NOT NULL,
    name character varying(100) NOT NULL,
    name_km character varying(150) NOT NULL,
    address character varying(250) NOT NULL,
    email character varying(100),
    company_type character varying(255) NOT NULL,
    phone character varying(25) NOT NULL,
    description character varying(500),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL
);


--
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- Name: countries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.countries (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(100)
);


--
-- Name: countries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.countries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: countries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.countries_id_seq OWNED BY public.countries.id;


--
-- Name: daily_stocks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.daily_stocks (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    stock_location_id integer NOT NULL,
    variant_id bigint NOT NULL,
    begin_qty integer,
    ending_qty integer,
    adjustment_qty integer,
    transfer_in_qty integer,
    transfer_out_qty integer,
    sold_qty integer,
    purchase_qty integer
);


--
-- Name: daily_stocks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.daily_stocks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: daily_stocks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.daily_stocks_id_seq OWNED BY public.daily_stocks.id;


--
-- Name: districts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.districts (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(100),
    city_id bigint NOT NULL
);


--
-- Name: districts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.districts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: districts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.districts_id_seq OWNED BY public.districts.id;


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
-- Name: models; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.models (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(100),
    brand_id bigint NOT NULL
);


--
-- Name: models_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.models_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: models_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.models_id_seq OWNED BY public.models.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: product_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_groups (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(100)
);


--
-- Name: product_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_groups_id_seq OWNED BY public.product_groups.id;


--
-- Name: product_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_tags (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(100)
);


--
-- Name: product_tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_tags_id_seq OWNED BY public.product_tags.id;


--
-- Name: product_variant_specifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_variant_specifications (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    value character varying(200) NOT NULL,
    product_id bigint,
    variant_id bigint
);


--
-- Name: product_variant_specifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_variant_specifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_variant_specifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_variant_specifications_id_seq OWNED BY public.product_variant_specifications.id;


--
-- Name: product_variant_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_variant_tags (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    tag character varying(255) NOT NULL,
    product_id bigint,
    variant_id bigint
);


--
-- Name: product_variant_tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_variant_tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_variant_tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_variant_tags_id_seq OWNED BY public.product_variant_tags.id;


--
-- Name: product_variant_uoms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_variant_uoms (
    variant_id bigint NOT NULL,
    uom_id bigint NOT NULL
);


--
-- Name: product_variants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_variants (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    product_id bigint NOT NULL,
    size character varying(30),
    color character varying(30),
    sku character varying(50),
    weight character varying(30),
    width character varying(30),
    length character varying(30),
    expires_at date,
    condition character varying(30) DEFAULT 'new'::character varying NOT NULL,
    condition_percentage character varying(10) DEFAULT '100%'::character varying NOT NULL,
    material character varying(50),
    cost numeric(8,2),
    retail_price numeric(8,2),
    wholesale_price numeric(8,2)
);


--
-- Name: product_variants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_variants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_variants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_variants_id_seq OWNED BY public.product_variants.id;


--
-- Name: products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.products (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    code character varying(100),
    description character varying(250),
    model_id bigint NOT NULL,
    category_id bigint NOT NULL,
    country_id bigint,
    group_id bigint NOT NULL,
    cost numeric(8,2),
    retail_price numeric(8,2),
    wholesale_price numeric(8,2)
);


--
-- Name: products_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.products_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


--
-- Name: purchase_order_expenses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_order_expenses (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    purchase_order_id bigint NOT NULL,
    expense_type character varying(255) DEFAULT 'Goods'::character varying NOT NULL,
    amount numeric(15,2) NOT NULL,
    expense_date date DEFAULT '2024-08-16'::date NOT NULL,
    description character varying(255),
    CONSTRAINT purchase_order_expenses_expense_type_check CHECK (((expense_type)::text = ANY ((ARRAY['Goods'::character varying, 'Shipping'::character varying, 'Handling'::character varying, 'Tax'::character varying, 'Discount'::character varying, 'Other'::character varying])::text[])))
);


--
-- Name: purchase_order_expenses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_order_expenses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_order_expenses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_order_expenses_id_seq OWNED BY public.purchase_order_expenses.id;


--
-- Name: purchase_order_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_order_items (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    purchase_id bigint NOT NULL,
    product_id bigint NOT NULL,
    variant_id bigint NOT NULL,
    qty integer NOT NULL,
    total_price numeric(8,2) NOT NULL,
    unit_price numeric(8,2) NOT NULL,
    discount_amount numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    discount_type character varying(30) DEFAULT '%'::character varying NOT NULL,
    received_qty integer DEFAULT 0 NOT NULL,
    expires_at date,
    remarks character varying(250)
);


--
-- Name: purchase_order_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_order_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_order_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_order_items_id_seq OWNED BY public.purchase_order_items.id;


--
-- Name: purchase_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_orders (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    vendor_id bigint NOT NULL,
    po_code character varying(255),
    issue_date date NOT NULL,
    discount_amount numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    discount_type character varying(255) DEFAULT '%'::character varying NOT NULL,
    total_amount numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    due_amount numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    paid_amount numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    approve_date date,
    approve_uid bigint,
    remarks character varying(250),
    expect_arrival_date date,
    cancel_date date,
    cancel_remarks character varying(250),
    status_id smallint DEFAULT '1'::smallint NOT NULL,
    receive_uid bigint,
    reject_uid bigint,
    reject_remarks bigint
);


--
-- Name: purchase_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_orders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_orders_id_seq OWNED BY public.purchase_orders.id;


--
-- Name: purchase_statuses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_statuses (
    id bigint NOT NULL,
    name character varying(30) NOT NULL,
    name_kh character varying(50),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: purchase_statuses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_statuses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_statuses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_statuses_id_seq OWNED BY public.purchase_statuses.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(50) NOT NULL,
    description character varying(300),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    company_id bigint NOT NULL
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
-- Name: stock_location_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_location_types (
    id bigint NOT NULL,
    name character varying(30) NOT NULL,
    name_kh character varying(50),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: stock_location_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_location_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_location_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_location_types_id_seq OWNED BY public.stock_location_types.id;


--
-- Name: stock_locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_locations (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    type_id bigint NOT NULL,
    description character varying(250),
    address character varying(150),
    address_kh character varying(200)
);


--
-- Name: stock_locations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_locations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_locations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_locations_id_seq OWNED BY public.stock_locations.id;


--
-- Name: stock_movement_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_movement_types (
    id bigint NOT NULL,
    name character varying(30) NOT NULL,
    name_kh character varying(50),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: stock_movement_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_movement_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_movement_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_movement_types_id_seq OWNED BY public.stock_movement_types.id;


--
-- Name: stocks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stocks (
    sku character varying(50) NOT NULL,
    batch_number character varying(50),
    stock_location_id bigint NOT NULL,
    variant_id bigint NOT NULL,
    qty integer NOT NULL,
    cost numeric(8,2),
    wholesale_price numeric(8,2),
    retail_price numeric(8,2),
    expiration_date date,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    id bigint NOT NULL,
    CONSTRAINT stocks_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying, 'pending'::character varying, 'reserved'::character varying])::text[])))
);


--
-- Name: stocks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stocks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stocks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stocks_id_seq OWNED BY public.stocks.id;


--
-- Name: uoms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.uoms (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(50),
    conversion_name character varying(50),
    conversion_rate numeric(8,2)
);


--
-- Name: uoms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.uoms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: uoms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.uoms_id_seq OWNED BY public.uoms.id;


--
-- Name: user_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_roles (
    user_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: user_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_sessions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    rf_token character varying(600) NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    generate_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_refresh_date timestamp(0) without time zone
);


--
-- Name: user_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_sessions_id_seq OWNED BY public.user_sessions.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    first_name character varying(50) NOT NULL,
    last_name character varying(50) NOT NULL,
    user_name character varying(100),
    email character varying(100),
    phone character varying(255),
    password character varying(300) NOT NULL,
    system_admin boolean DEFAULT false NOT NULL,
    lock boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL
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
-- Name: vendor_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendor_types (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50) NOT NULL,
    name_kh character varying(100)
);


--
-- Name: vendor_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendor_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendor_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendor_types_id_seq OWNED BY public.vendor_types.id;


--
-- Name: vendors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendors (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    create_uid bigint NOT NULL,
    update_uid bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    name character varying(50),
    name_kh character varying(150),
    address character varying(250),
    address_kh character varying(300),
    phone character varying(30) NOT NULL,
    email character varying(100),
    vendor_type_id bigint NOT NULL
);


--
-- Name: vendors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendors_id_seq OWNED BY public.vendors.id;


--
-- Name: adjustment_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adjustment_types ALTER COLUMN id SET DEFAULT nextval('public.adjustment_types_id_seq'::regclass);


--
-- Name: branches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches ALTER COLUMN id SET DEFAULT nextval('public.branches_id_seq'::regclass);


--
-- Name: brands id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.brands ALTER COLUMN id SET DEFAULT nextval('public.brands_id_seq'::regclass);


--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: cities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities ALTER COLUMN id SET DEFAULT nextval('public.cities_id_seq'::regclass);


--
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- Name: countries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries ALTER COLUMN id SET DEFAULT nextval('public.countries_id_seq'::regclass);


--
-- Name: daily_stocks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_stocks ALTER COLUMN id SET DEFAULT nextval('public.daily_stocks_id_seq'::regclass);


--
-- Name: districts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.districts ALTER COLUMN id SET DEFAULT nextval('public.districts_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: models id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.models ALTER COLUMN id SET DEFAULT nextval('public.models_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: product_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups ALTER COLUMN id SET DEFAULT nextval('public.product_groups_id_seq'::regclass);


--
-- Name: product_tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_tags ALTER COLUMN id SET DEFAULT nextval('public.product_tags_id_seq'::regclass);


--
-- Name: product_variant_specifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_specifications ALTER COLUMN id SET DEFAULT nextval('public.product_variant_specifications_id_seq'::regclass);


--
-- Name: product_variant_tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_tags ALTER COLUMN id SET DEFAULT nextval('public.product_variant_tags_id_seq'::regclass);


--
-- Name: product_variants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variants ALTER COLUMN id SET DEFAULT nextval('public.product_variants_id_seq'::regclass);


--
-- Name: products id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products ALTER COLUMN id SET DEFAULT nextval('public.products_id_seq'::regclass);


--
-- Name: purchase_order_expenses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_expenses ALTER COLUMN id SET DEFAULT nextval('public.purchase_order_expenses_id_seq'::regclass);


--
-- Name: purchase_order_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items ALTER COLUMN id SET DEFAULT nextval('public.purchase_order_items_id_seq'::regclass);


--
-- Name: purchase_orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders ALTER COLUMN id SET DEFAULT nextval('public.purchase_orders_id_seq'::regclass);


--
-- Name: purchase_statuses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_statuses ALTER COLUMN id SET DEFAULT nextval('public.purchase_statuses_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: stock_location_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_location_types ALTER COLUMN id SET DEFAULT nextval('public.stock_location_types_id_seq'::regclass);


--
-- Name: stock_locations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_locations ALTER COLUMN id SET DEFAULT nextval('public.stock_locations_id_seq'::regclass);


--
-- Name: stock_movement_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movement_types ALTER COLUMN id SET DEFAULT nextval('public.stock_movement_types_id_seq'::regclass);


--
-- Name: stocks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks ALTER COLUMN id SET DEFAULT nextval('public.stocks_id_seq'::regclass);


--
-- Name: uoms id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.uoms ALTER COLUMN id SET DEFAULT nextval('public.uoms_id_seq'::regclass);


--
-- Name: user_sessions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_sessions ALTER COLUMN id SET DEFAULT nextval('public.user_sessions_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: vendor_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_types ALTER COLUMN id SET DEFAULT nextval('public.vendor_types_id_seq'::regclass);


--
-- Name: vendors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors ALTER COLUMN id SET DEFAULT nextval('public.vendors_id_seq'::regclass);


--
-- Name: adjustment_types adjustment_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adjustment_types
    ADD CONSTRAINT adjustment_types_pkey PRIMARY KEY (id);


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- Name: brands brands_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.brands
    ADD CONSTRAINT brands_pkey PRIMARY KEY (id);


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
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: cities cities_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_name_unique UNIQUE (name);


--
-- Name: cities cities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_pkey PRIMARY KEY (id);


--
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- Name: countries countries_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_name_unique UNIQUE (name);


--
-- Name: countries countries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_pkey PRIMARY KEY (id);


--
-- Name: daily_stocks daily_stocks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_stocks
    ADD CONSTRAINT daily_stocks_pkey PRIMARY KEY (id);


--
-- Name: districts districts_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.districts
    ADD CONSTRAINT districts_name_unique UNIQUE (name);


--
-- Name: districts districts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.districts
    ADD CONSTRAINT districts_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: models models_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.models
    ADD CONSTRAINT models_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: product_groups product_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups
    ADD CONSTRAINT product_groups_pkey PRIMARY KEY (id);


--
-- Name: product_tags product_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_tags
    ADD CONSTRAINT product_tags_pkey PRIMARY KEY (id);


--
-- Name: product_variant_specifications product_variant_specifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_specifications
    ADD CONSTRAINT product_variant_specifications_pkey PRIMARY KEY (id);


--
-- Name: product_variant_tags product_variant_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_tags
    ADD CONSTRAINT product_variant_tags_pkey PRIMARY KEY (id);


--
-- Name: product_variants product_variants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variants
    ADD CONSTRAINT product_variants_pkey PRIMARY KEY (id);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: purchase_order_expenses purchase_order_expenses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_expenses
    ADD CONSTRAINT purchase_order_expenses_pkey PRIMARY KEY (id);


--
-- Name: purchase_order_items purchase_order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_pkey PRIMARY KEY (id);


--
-- Name: purchase_orders purchase_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_pkey PRIMARY KEY (id);


--
-- Name: purchase_orders purchase_orders_po_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_po_code_unique UNIQUE (po_code);


--
-- Name: purchase_statuses purchase_statuses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_statuses
    ADD CONSTRAINT purchase_statuses_pkey PRIMARY KEY (id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: stock_location_types stock_location_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_location_types
    ADD CONSTRAINT stock_location_types_pkey PRIMARY KEY (id);


--
-- Name: stock_locations stock_locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_locations
    ADD CONSTRAINT stock_locations_pkey PRIMARY KEY (id);


--
-- Name: stock_movement_types stock_movement_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movement_types
    ADD CONSTRAINT stock_movement_types_pkey PRIMARY KEY (id);


--
-- Name: stocks stocks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_pkey PRIMARY KEY (id);


--
-- Name: stocks stocks_sku_batch_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_sku_batch_number_unique UNIQUE (sku, batch_number);


--
-- Name: stocks stocks_sku_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_sku_unique UNIQUE (sku);


--
-- Name: uoms uoms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.uoms
    ADD CONSTRAINT uoms_pkey PRIMARY KEY (id);


--
-- Name: user_sessions user_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_sessions
    ADD CONSTRAINT user_sessions_pkey PRIMARY KEY (id);


--
-- Name: user_sessions user_sessions_rf_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_sessions
    ADD CONSTRAINT user_sessions_rf_token_unique UNIQUE (rf_token);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_phone_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_phone_unique UNIQUE (phone);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: vendor_types vendor_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_types
    ADD CONSTRAINT vendor_types_pkey PRIMARY KEY (id);


--
-- Name: vendors vendors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: stocks_stock_location_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stocks_stock_location_id_index ON public.stocks USING btree (stock_location_id);


--
-- Name: stocks_variant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stocks_variant_id_index ON public.stocks USING btree (variant_id);


--
-- Name: branches branches_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: brands brands_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.brands
    ADD CONSTRAINT brands_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: brands brands_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.brands
    ADD CONSTRAINT brands_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: brands brands_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.brands
    ADD CONSTRAINT brands_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: brands brands_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.brands
    ADD CONSTRAINT brands_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: categories categories_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: categories categories_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: categories categories_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: categories categories_parent_cate_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_parent_cate_id_foreign FOREIGN KEY (parent_cate_id) REFERENCES public.categories(id);


--
-- Name: categories categories_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: cities cities_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: cities cities_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: cities cities_country_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_country_id_foreign FOREIGN KEY (country_id) REFERENCES public.countries(id);


--
-- Name: cities cities_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: cities cities_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: countries countries_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: countries countries_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: countries countries_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: countries countries_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: daily_stocks daily_stocks_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_stocks
    ADD CONSTRAINT daily_stocks_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: daily_stocks daily_stocks_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_stocks
    ADD CONSTRAINT daily_stocks_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: daily_stocks daily_stocks_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_stocks
    ADD CONSTRAINT daily_stocks_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: daily_stocks daily_stocks_stock_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_stocks
    ADD CONSTRAINT daily_stocks_stock_location_id_foreign FOREIGN KEY (stock_location_id) REFERENCES public.stock_locations(id);


--
-- Name: daily_stocks daily_stocks_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_stocks
    ADD CONSTRAINT daily_stocks_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: daily_stocks daily_stocks_variant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_stocks
    ADD CONSTRAINT daily_stocks_variant_id_foreign FOREIGN KEY (variant_id) REFERENCES public.product_variants(id);


--
-- Name: districts districts_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.districts
    ADD CONSTRAINT districts_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: districts districts_city_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.districts
    ADD CONSTRAINT districts_city_id_foreign FOREIGN KEY (city_id) REFERENCES public.cities(id);


--
-- Name: districts districts_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.districts
    ADD CONSTRAINT districts_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: districts districts_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.districts
    ADD CONSTRAINT districts_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: districts districts_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.districts
    ADD CONSTRAINT districts_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: models models_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.models
    ADD CONSTRAINT models_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: models models_brand_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.models
    ADD CONSTRAINT models_brand_id_foreign FOREIGN KEY (brand_id) REFERENCES public.brands(id);


--
-- Name: models models_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.models
    ADD CONSTRAINT models_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: models models_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.models
    ADD CONSTRAINT models_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: models models_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.models
    ADD CONSTRAINT models_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: product_groups product_groups_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups
    ADD CONSTRAINT product_groups_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: product_groups product_groups_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups
    ADD CONSTRAINT product_groups_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: product_groups product_groups_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups
    ADD CONSTRAINT product_groups_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: product_groups product_groups_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups
    ADD CONSTRAINT product_groups_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: product_tags product_tags_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_tags
    ADD CONSTRAINT product_tags_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: product_tags product_tags_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_tags
    ADD CONSTRAINT product_tags_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: product_tags product_tags_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_tags
    ADD CONSTRAINT product_tags_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: product_tags product_tags_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_tags
    ADD CONSTRAINT product_tags_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: product_variant_specifications product_variant_specifications_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_specifications
    ADD CONSTRAINT product_variant_specifications_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: product_variant_specifications product_variant_specifications_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_specifications
    ADD CONSTRAINT product_variant_specifications_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: product_variant_specifications product_variant_specifications_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_specifications
    ADD CONSTRAINT product_variant_specifications_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: product_variant_specifications product_variant_specifications_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_specifications
    ADD CONSTRAINT product_variant_specifications_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: product_variant_specifications product_variant_specifications_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_specifications
    ADD CONSTRAINT product_variant_specifications_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: product_variant_specifications product_variant_specifications_variant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_specifications
    ADD CONSTRAINT product_variant_specifications_variant_id_foreign FOREIGN KEY (variant_id) REFERENCES public.product_variants(id);


--
-- Name: product_variant_tags product_variant_tags_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_tags
    ADD CONSTRAINT product_variant_tags_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: product_variant_tags product_variant_tags_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_tags
    ADD CONSTRAINT product_variant_tags_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: product_variant_tags product_variant_tags_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_tags
    ADD CONSTRAINT product_variant_tags_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: product_variant_tags product_variant_tags_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_tags
    ADD CONSTRAINT product_variant_tags_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: product_variant_tags product_variant_tags_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_tags
    ADD CONSTRAINT product_variant_tags_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: product_variant_tags product_variant_tags_variant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_tags
    ADD CONSTRAINT product_variant_tags_variant_id_foreign FOREIGN KEY (variant_id) REFERENCES public.product_variants(id);


--
-- Name: product_variant_uoms product_variant_uoms_uom_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_uoms
    ADD CONSTRAINT product_variant_uoms_uom_id_foreign FOREIGN KEY (uom_id) REFERENCES public.uoms(id);


--
-- Name: product_variant_uoms product_variant_uoms_variant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variant_uoms
    ADD CONSTRAINT product_variant_uoms_variant_id_foreign FOREIGN KEY (variant_id) REFERENCES public.product_variants(id);


--
-- Name: product_variants product_variants_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variants
    ADD CONSTRAINT product_variants_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: product_variants product_variants_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variants
    ADD CONSTRAINT product_variants_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: product_variants product_variants_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variants
    ADD CONSTRAINT product_variants_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: product_variants product_variants_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variants
    ADD CONSTRAINT product_variants_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: product_variants product_variants_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_variants
    ADD CONSTRAINT product_variants_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: products products_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: products products_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id);


--
-- Name: products products_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: products products_country_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_country_id_foreign FOREIGN KEY (country_id) REFERENCES public.countries(id);


--
-- Name: products products_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: products products_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.product_groups(id);


--
-- Name: products products_model_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_model_id_foreign FOREIGN KEY (model_id) REFERENCES public.models(id);


--
-- Name: products products_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: purchase_order_expenses purchase_order_expenses_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_expenses
    ADD CONSTRAINT purchase_order_expenses_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: purchase_order_expenses purchase_order_expenses_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_expenses
    ADD CONSTRAINT purchase_order_expenses_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: purchase_order_expenses purchase_order_expenses_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_expenses
    ADD CONSTRAINT purchase_order_expenses_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: purchase_order_expenses purchase_order_expenses_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_expenses
    ADD CONSTRAINT purchase_order_expenses_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE CASCADE;


--
-- Name: purchase_order_expenses purchase_order_expenses_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_expenses
    ADD CONSTRAINT purchase_order_expenses_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: purchase_order_items purchase_order_items_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: purchase_order_items purchase_order_items_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: purchase_order_items purchase_order_items_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: purchase_order_items purchase_order_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: purchase_order_items purchase_order_items_purchase_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_purchase_id_foreign FOREIGN KEY (purchase_id) REFERENCES public.purchase_orders(id);


--
-- Name: purchase_order_items purchase_order_items_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: purchase_order_items purchase_order_items_variant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_variant_id_foreign FOREIGN KEY (variant_id) REFERENCES public.product_variants(id);


--
-- Name: purchase_orders purchase_orders_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: purchase_orders purchase_orders_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: purchase_orders purchase_orders_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: purchase_orders purchase_orders_receive_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_receive_uid_foreign FOREIGN KEY (receive_uid) REFERENCES public.users(id);


--
-- Name: purchase_orders purchase_orders_reject_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_reject_uid_foreign FOREIGN KEY (reject_uid) REFERENCES public.users(id);


--
-- Name: purchase_orders purchase_orders_status_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_status_id_foreign FOREIGN KEY (status_id) REFERENCES public.purchase_statuses(id);


--
-- Name: purchase_orders purchase_orders_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: purchase_orders purchase_orders_vendor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id);


--
-- Name: roles roles_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: stock_locations stock_locations_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_locations
    ADD CONSTRAINT stock_locations_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: stock_locations stock_locations_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_locations
    ADD CONSTRAINT stock_locations_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: stock_locations stock_locations_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_locations
    ADD CONSTRAINT stock_locations_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: stock_locations stock_locations_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_locations
    ADD CONSTRAINT stock_locations_type_id_foreign FOREIGN KEY (type_id) REFERENCES public.stock_location_types(id);


--
-- Name: stock_locations stock_locations_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_locations
    ADD CONSTRAINT stock_locations_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: stocks stocks_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: stocks stocks_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: stocks stocks_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: stocks stocks_stock_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_stock_location_id_foreign FOREIGN KEY (stock_location_id) REFERENCES public.stock_locations(id);


--
-- Name: stocks stocks_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: stocks stocks_variant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_variant_id_foreign FOREIGN KEY (variant_id) REFERENCES public.product_variants(id);


--
-- Name: uoms uoms_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.uoms
    ADD CONSTRAINT uoms_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: uoms uoms_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.uoms
    ADD CONSTRAINT uoms_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: uoms uoms_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.uoms
    ADD CONSTRAINT uoms_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: uoms uoms_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.uoms
    ADD CONSTRAINT uoms_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: vendor_types vendor_types_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_types
    ADD CONSTRAINT vendor_types_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: vendor_types vendor_types_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_types
    ADD CONSTRAINT vendor_types_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: vendor_types vendor_types_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_types
    ADD CONSTRAINT vendor_types_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: vendor_types vendor_types_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendor_types
    ADD CONSTRAINT vendor_types_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: vendors vendors_branch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES public.branches(id);


--
-- Name: vendors vendors_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: vendors vendors_create_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_create_uid_foreign FOREIGN KEY (create_uid) REFERENCES public.users(id);


--
-- Name: vendors vendors_update_uid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_update_uid_foreign FOREIGN KEY (update_uid) REFERENCES public.users(id);


--
-- Name: vendors vendors_vendor_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendors
    ADD CONSTRAINT vendors_vendor_type_id_foreign FOREIGN KEY (vendor_type_id) REFERENCES public.vendor_types(id);


--
-- PostgreSQL database dump complete
--

--
-- PostgreSQL database dump
--

-- Dumped from database version 16.2
-- Dumped by pg_dump version 16.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2024_06_17_123609_companies_table	1
2	2024_06_18_050027_branches_table	1
3	2024_06_18_062425_users_table	1
4	2024_06_18_062531_roles_table	1
5	2024_06_18_063524_user_roles_table	1
6	2024_06_19_041932_user_sessions	1
7	2024_07_27_082736_countries_table	1
8	2024_07_27_082806_cities_table	1
9	2024_07_27_083638_districts_table	1
10	2024_08_07_072336_create_personal_access_tokens_table	1
11	2024_08_07_074842_create_cache_table	1
12	2024_08_08_065955_create_categories_table	1
13	2024_08_08_071044_create_brands_table	1
14	2024_08_08_071224_create_models_table	1
15	2024_08_09_071423_create_product_groups_table	1
16	2024_08_09_095127_create_products_table	1
17	2024_08_09_100901_create_product_variants_table	1
18	2024_08_10_060626_create_product_tags_table	1
19	2024_08_10_070314_add_col_material_to_product_variants_table	1
20	2024_08_12_124904_create_product_variant_tags_table	1
21	2024_08_13_023816_create_product_variant_specifications_table	1
22	2024_08_13_044041_add_prices_to_products_table	1
23	2024_08_13_083514_add_prices_to_product_variants_table	1
24	2024_08_13_145624_create_vendor_types_table	1
25	2024_08_13_145959_create_vendors_table	1
26	2024_08_14_044912_create_purchase_statuses_table	1
27	2024_08_14_044939_create_purchase_orders_table	1
28	2024_08_14_052322_create_adjustment_types_table	1
29	2024_08_14_052332_create_stock_movement_types_table	1
30	2024_08_14_052556_create_stock_location_types_table	1
31	2024_08_14_061352_create_purchase_order_items_table	1
32	2024_08_15_093019_create_stock_locations_table	1
33	2024_08_15_153259_create_purchase_order_expenses_table	1
34	2024_08_16_075321_create_daily_stocks_table	1
35	2024_08_16_090636_create_stocks_table	1
36	2024_08_16_103135_create_uoms_table	1
37	2024_08_16_103408_create_product_variant_uoms_table	1
38	2024_08_16_165431_modify_stocks_table_primary_key	1
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 38, true);


--
-- PostgreSQL database dump complete
--

