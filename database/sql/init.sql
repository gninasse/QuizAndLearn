--
-- PostgreSQL database dump
--

\restrict nDYM0KuxImAi8hKZaPdZm7E7JUIo6WFRFdRWYuk91iP4b57T9c7TPvPzmhrQUf5

-- Dumped from database version 16.14 (Ubuntu 16.14-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.14 (Ubuntu 16.14-0ubuntu0.24.04.1)

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
-- Name: activity_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_log (
    id bigint NOT NULL,
    log_name character varying(255),
    description text NOT NULL,
    subject_type character varying(255),
    subject_id bigint,
    causer_type character varying(255),
    causer_id bigint,
    properties json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    event character varying(255),
    batch_uuid uuid,
    module character varying(255),
    context json,
    ip_address character varying(255),
    user_agent text,
    causer_roles jsonb,
    expires_at timestamp(0) without time zone,
    retention_months integer
);


--
-- Name: activity_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_log_id_seq OWNED BY public.activity_log.id;


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
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    user_name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    service character varying(255),
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    avatar character varying(255)
);


--
-- Name: cores_users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cores_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cores_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cores_users_id_seq OWNED BY public.users.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
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
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modules (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    version character varying(255) DEFAULT '1.0.0'::character varying NOT NULL,
    is_active boolean DEFAULT false NOT NULL,
    is_required boolean DEFAULT false NOT NULL,
    dependencies json,
    config json,
    icon character varying(255),
    sort_order integer DEFAULT 0 NOT NULL,
    installed_at timestamp(0) without time zone,
    activated_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: modules_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.modules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: modules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.modules_id_seq OWNED BY public.modules.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    label character varying(255),
    module character varying(255),
    category character varying(255),
    description text,
    "group" character varying(255),
    sort_order integer DEFAULT 0 NOT NULL,
    is_visible boolean DEFAULT true NOT NULL
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    description character varying(255)
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
-- Name: activity_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log ALTER COLUMN id SET DEFAULT nextval('public.activity_log_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: modules id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules ALTER COLUMN id SET DEFAULT nextval('public.modules_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.cores_users_id_seq'::regclass);


--
-- Data for Name: activity_log; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.activity_log (id, log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at, updated_at, event, batch_uuid, module, context, ip_address, user_agent, causer_roles, expires_at, retention_months) FROM stdin;
1	core	created	Modules\\Core\\Models\\Role	16	Modules\\Core\\Models\\User	3	{"attributes":{"id":16,"name":"aaddd","guard_name":"web","created_at":"2026-01-16T00:57:41.000000Z","updated_at":"2026-01-16T00:57:41.000000Z","description":"ddddd"}}	2026-01-16 00:57:41	2026-01-16 00:57:41	created	\N	core	{"route":"cores.roles.store","method":"POST","url":"https:\\/\\/keystone.local\\/cores\\/roles"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-16 00:57:41	12
2	core	deleted	Modules\\Core\\Models\\Role	16	Modules\\Core\\Models\\User	3	{"old":{"id":16,"name":"aaddd","guard_name":"web","created_at":"2026-01-16T00:57:41.000000Z","updated_at":"2026-01-16T00:57:41.000000Z","description":"ddddd"}}	2026-01-16 00:57:48	2026-01-16 00:57:48	deleted	\N	core	{"route":"cores.roles.destroy","method":"DELETE","url":"https:\\/\\/keystone.local\\/cores\\/roles\\/16"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-16 00:57:48	12
3	core	deleted	Modules\\Core\\Models\\Role	15	Modules\\Core\\Models\\User	3	{"old":{"id":15,"name":"aad","guard_name":"web","created_at":"2026-01-16T00:54:48.000000Z","updated_at":"2026-01-16T00:54:48.000000Z","description":"ddd"}}	2026-01-16 00:57:55	2026-01-16 00:57:55	deleted	\N	core	{"route":"cores.roles.destroy","method":"DELETE","url":"https:\\/\\/keystone.local\\/cores\\/roles\\/15"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-16 00:57:55	12
4	permissions	permission_revoked	Modules\\Core\\Models\\Role	14	Modules\\Core\\Models\\User	3	{"permission":"cores.users.index","action":"revoked"}	2026-01-16 01:03:55	2026-01-16 01:03:55	\N	\N	core	{"route":"cores.roles.toggle-permission","method":"POST","url":"https:\\/\\/keystone.local\\/cores\\/roles\\/14\\/toggle-permission"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-16 01:03:55	12
5	permissions	permission_given	Modules\\Core\\Models\\Role	14	Modules\\Core\\Models\\User	3	{"permission":"cores.users.store","action":"given"}	2026-01-16 01:03:58	2026-01-16 01:03:58	\N	\N	core	{"route":"cores.roles.toggle-permission","method":"POST","url":"https:\\/\\/keystone.local\\/cores\\/roles\\/14\\/toggle-permission"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-16 01:03:58	12
6	core	deleted	Modules\\Core\\Models\\Role	14	Modules\\Core\\Models\\User	3	{"old":{"id":14,"name":"aa","guard_name":"web","created_at":"2026-01-16T00:44:09.000000Z","updated_at":"2026-01-16T00:44:09.000000Z","description":"ddd"}}	2026-01-16 01:06:27	2026-01-16 01:06:27	deleted	\N	core	{"route":"cores.roles.destroy","method":"DELETE","url":"https:\\/\\/keystone.local\\/cores\\/roles\\/14"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-16 01:06:27	12
7	core	user_activated	Modules\\Core\\Models\\User	8	Modules\\Core\\Models\\User	3	{"attributes":{"updated_at":"2026-01-16T01:19:31.000000Z","is_active":true},"old":{"updated_at":"2026-01-16T01:13:54.000000Z","is_active":false}}	2026-01-16 01:19:31	2026-01-16 01:19:31	updated	\N	core	{"route":"cores.users.toggle-status","method":"POST","url":"https:\\/\\/keystone.local\\/cores\\/users\\/8\\/toggle-status"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-16 01:19:31	12
8	core	updated	Modules\\Core\\Models\\User	3	Modules\\Core\\Models\\User	3	{"attributes":{"remember_token":"u6e9RzZkRhp5c8efvGA2EhwbHJN5fbtaOJ89cvXnx3EIQLZuZx2qTcnFEcBy"},"old":{"remember_token":"WbR39rdoHMNGFwMf9FqjNe46HtjLRO1sutsNvznRmZ610Ame9W0LcyIWdwgF"}}	2026-01-16 01:30:46	2026-01-16 01:30:46	updated	\N	core	{"route":"logout","method":"POST","url":"https:\\/\\/keystone.local\\/logout"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-16 01:30:46	12
9	core	updated	Modules\\Core\\Models\\User	3	Modules\\Core\\Models\\User	3	{"attributes":{"remember_token":"Ev2KpH8eUq9cnwjvur4txe4JhrM1OFevDaJnrQehlWdWcAau1qxO3sXpJ5xN"},"old":{"remember_token":"u6e9RzZkRhp5c8efvGA2EhwbHJN5fbtaOJ89cvXnx3EIQLZuZx2qTcnFEcBy"}}	2026-01-16 01:37:22	2026-01-16 01:37:22	updated	\N	core	{"route":"logout","method":"POST","url":"https:\\/\\/keystone.local\\/logout"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-16 01:37:22	12
10	core	updated	Modules\\Core\\Models\\User	3	Modules\\Core\\Models\\User	3	{"attributes":{"remember_token":"LrnG6z0sNLrnKRBswarUwWQ2W9BcHdEC9MZdKJCu2TO8uwibmOJhB0xhOtQr"},"old":{"remember_token":"Ev2KpH8eUq9cnwjvur4txe4JhrM1OFevDaJnrQehlWdWcAau1qxO3sXpJ5xN"}}	2026-01-19 20:53:48	2026-01-19 20:53:48	updated	\N	core	{"route":"logout","method":"POST","url":"https:\\/\\/keystone.local\\/logout"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-19 20:53:48	12
11	permissions	permission_given	Modules\\Core\\Models\\Role	12	Modules\\Core\\Models\\User	3	{"permission":"cores.users.reset-password","action":"given"}	2026-01-19 20:56:40	2026-01-19 20:56:40	\N	\N	core	{"route":"cores.roles.toggle-permission","method":"POST","url":"https:\\/\\/keystone.local\\/cores\\/roles\\/12\\/toggle-permission"}	127.0.0.1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	["super-admin"]	2027-01-19 20:56:40	12
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
laravel-cache-spatie.permission.cache	a:3:{s:5:"alias";a:11:{s:1:"a";s:2:"id";s:1:"b";s:4:"name";s:1:"c";s:10:"guard_name";s:1:"f";s:5:"label";s:1:"g";s:6:"module";s:1:"h";s:8:"category";s:11:"description";s:11:"description";s:5:"group";s:5:"group";s:10:"sort_order";s:10:"sort_order";s:10:"is_visible";s:10:"is_visible";s:1:"r";s:5:"roles";}s:11:"permissions";a:20:{i:0;a:11:{s:1:"a";i:30;s:1:"b";s:17:"cores.users.index";s:1:"c";s:3:"web";s:1:"f";s:30:"Voir la liste des utilisateurs";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:30:"Voir la liste des utilisateurs";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:2:{i:0;i:11;i:1;i:12;}}i:1;a:11:{s:1:"a";i:31;s:1:"b";s:17:"cores.users.store";s:1:"c";s:3:"web";s:1:"f";s:21:"Créer un utilisateur";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:21:"Créer un utilisateur";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:2:{i:0;i:11;i:1;i:12;}}i:2;a:11:{s:1:"a";i:32;s:1:"b";s:18:"cores.users.update";s:1:"c";s:3:"web";s:1:"f";s:23:"Modifier un utilisateur";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:23:"Modifier un utilisateur";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:2:{i:0;i:11;i:1;i:12;}}i:3;a:11:{s:1:"a";i:33;s:1:"b";s:19:"cores.users.destroy";s:1:"c";s:3:"web";s:1:"f";s:24:"Supprimer un utilisateur";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:24:"Supprimer un utilisateur";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:4;a:11:{s:1:"a";i:34;s:1:"b";s:26:"cores.users.reset-password";s:1:"c";s:3:"web";s:1:"f";s:30:"Réinitialiser le mot de passe";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:30:"Réinitialiser le mot de passe";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:2:{i:0;i:11;i:1;i:12;}}i:5;a:11:{s:1:"a";i:35;s:1:"b";s:25:"cores.users.toggle-status";s:1:"c";s:3:"web";s:1:"f";s:34:"Activer/Désactiver un utilisateur";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:34:"Activer/Désactiver un utilisateur";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:6;a:11:{s:1:"a";i:36;s:1:"b";s:17:"cores.roles.index";s:1:"c";s:3:"web";s:1:"f";s:24:"Voir la liste des rôles";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:24:"Voir la liste des rôles";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:7;a:11:{s:1:"a";i:37;s:1:"b";s:17:"cores.roles.store";s:1:"c";s:3:"web";s:1:"f";s:15:"Créer un rôle";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:15:"Créer un rôle";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:8;a:11:{s:1:"a";i:38;s:1:"b";s:18:"cores.roles.update";s:1:"c";s:3:"web";s:1:"f";s:17:"Modifier un rôle";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:17:"Modifier un rôle";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:9;a:11:{s:1:"a";i:39;s:1:"b";s:19:"cores.roles.destroy";s:1:"c";s:3:"web";s:1:"f";s:18:"Supprimer un rôle";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:18:"Supprimer un rôle";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:10;a:11:{s:1:"a";i:40;s:1:"b";s:23:"cores.permissions.index";s:1:"c";s:3:"web";s:1:"f";s:31:"Voir la matrice des permissions";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:31:"Voir la matrice des permissions";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:11;a:11:{s:1:"a";i:41;s:1:"b";s:24:"cores.permissions.toggle";s:1:"c";s:3:"web";s:1:"f";s:24:"Modifier les permissions";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:24:"Modifier les permissions";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:2:{i:0;i:11;i:1;i:12;}}i:12;a:11:{s:1:"a";i:42;s:1:"b";s:22:"cores.permissions.sync";s:1:"c";s:3:"web";s:1:"f";s:28:"Synchroniser les permissions";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:28:"Synchroniser les permissions";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:2:{i:0;i:11;i:1;i:12;}}i:13;a:11:{s:1:"a";i:43;s:1:"b";s:19:"cores.modules.index";s:1:"c";s:3:"web";s:1:"f";s:25:"Voir la liste des modules";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:25:"Voir la liste des modules";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:14;a:11:{s:1:"a";i:44;s:1:"b";s:18:"cores.modules.show";s:1:"c";s:3:"web";s:1:"f";s:29:"Voir les détails d'un module";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:29:"Voir les détails d'un module";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:15;a:11:{s:1:"a";i:45;s:1:"b";s:21:"cores.modules.install";s:1:"c";s:3:"web";s:1:"f";s:19:"Installer un module";s:1:"g";s:4:"core";s:1:"h";s:7:"install";s:11:"description";s:19:"Installer un module";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:16;a:11:{s:1:"a";i:46;s:1:"b";s:23:"cores.modules.uninstall";s:1:"c";s:3:"web";s:1:"f";s:23:"Désinstaller un module";s:1:"g";s:4:"core";s:1:"h";s:5:"other";s:11:"description";s:23:"Désinstaller un module";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:17;a:11:{s:1:"a";i:47;s:1:"b";s:20:"cores.modules.enable";s:1:"c";s:3:"web";s:1:"f";s:17:"Activer un module";s:1:"g";s:4:"core";s:1:"h";s:6:"enable";s:11:"description";s:17:"Activer un module";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:18;a:11:{s:1:"a";i:48;s:1:"b";s:21:"cores.modules.disable";s:1:"c";s:3:"web";s:1:"f";s:21:"Désactiver un module";s:1:"g";s:4:"core";s:1:"h";s:7:"disable";s:11:"description";s:21:"Désactiver un module";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}i:19;a:11:{s:1:"a";i:49;s:1:"b";s:23:"cores.modules.configure";s:1:"c";s:3:"web";s:1:"f";s:20:"Configurer un module";s:1:"g";s:4:"core";s:1:"h";s:9:"configure";s:11:"description";s:20:"Configurer un module";s:5:"group";N;s:10:"sort_order";i:0;s:10:"is_visible";b:1;s:1:"r";a:1:{i:0;i:11;}}}s:5:"roles";a:2:{i:0;a:4:{s:1:"a";i:11;s:1:"b";s:11:"super-admin";s:1:"c";s:3:"web";s:11:"description";N;}i:1;a:4:{s:1:"a";i:12;s:1:"b";s:5:"admin";s:1:"c";s:3:"web";s:11:"description";s:2:"gt";}}}	1770627560
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
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
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2025_12_18_214056_create_permission_tables	2
5	2025_12_24_103711_create_cores_users_table	3
6	2025_12_24_151836_add_is_active_to_users_table	4
7	2025_12_26_034554_replace_users_table_with_cores_users	5
8	2025_12_26_042753_add_label_to_permissions_table	6
9	2026_01_08_110151_create_modules_table	7
10	2026_01_08_113203_add_module_field_to_permissions_table	8
11	2026_01_08_114055_add_modules_fileds_to_permissions	9
12	2026_01_14_120808_add_avatar_to_users_table	10
13	2026_01_14_201208_add_description_to_roles_table	11
14	2026_01_15_211310_create_activity_log_table	12
15	2026_01_15_211311_add_event_column_to_activity_log_table	12
16	2026_01_15_211312_add_batch_uuid_column_to_activity_log_table	12
17	2026_01_15_213029_add_module_to_activity_log_table	13
18	2026_01_15_232214_add_roles_expiration_to_activity_log	14
19	2026_01_16_005035_fix_activity_log_json_index	15
\.


--
-- Data for Name: model_has_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.model_has_permissions (permission_id, model_type, model_id) FROM stdin;
\.


--
-- Data for Name: model_has_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.model_has_roles (role_id, model_type, model_id) FROM stdin;
11	Modules\\Core\\Models\\User	3
12	Modules\\Core\\Models\\User	8
\.


--
-- Data for Name: modules; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.modules (id, name, slug, description, version, is_active, is_required, dependencies, config, icon, sort_order, installed_at, activated_at, created_at, updated_at, deleted_at) FROM stdin;
20	Core	core	Module principal de Keystone gérant l'authentification, les utilisateurs, les rôles, les permissions et la gestion des modules. Il inclura prochainement une table 'configs' pour la gestion centralisée des configurations système.	1.0.0	t	f	\N	\N	\N	0	2026-01-08 23:11:58	2026-01-09 10:39:16	2026-01-08 23:11:58	2026-01-09 10:39:16	\N
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permissions (id, name, guard_name, created_at, updated_at, label, module, category, description, "group", sort_order, is_visible) FROM stdin;
30	cores.users.index	web	2026-01-11 22:35:19	2026-01-11 22:35:19	Voir la liste des utilisateurs	core	other	Voir la liste des utilisateurs	\N	0	t
31	cores.users.store	web	2026-01-11 22:35:19	2026-01-11 22:35:19	Créer un utilisateur	core	other	Créer un utilisateur	\N	0	t
32	cores.users.update	web	2026-01-11 22:35:19	2026-01-11 22:35:19	Modifier un utilisateur	core	other	Modifier un utilisateur	\N	0	t
33	cores.users.destroy	web	2026-01-11 22:35:19	2026-01-11 22:35:19	Supprimer un utilisateur	core	other	Supprimer un utilisateur	\N	0	t
34	cores.users.reset-password	web	2026-01-11 22:35:19	2026-01-11 22:35:19	Réinitialiser le mot de passe	core	other	Réinitialiser le mot de passe	\N	0	t
35	cores.users.toggle-status	web	2026-01-11 22:35:19	2026-01-11 22:35:19	Activer/Désactiver un utilisateur	core	other	Activer/Désactiver un utilisateur	\N	0	t
36	cores.roles.index	web	2026-01-11 22:35:19	2026-01-11 22:35:19	Voir la liste des rôles	core	other	Voir la liste des rôles	\N	0	t
37	cores.roles.store	web	2026-01-11 22:35:19	2026-01-11 22:35:19	Créer un rôle	core	other	Créer un rôle	\N	0	t
38	cores.roles.update	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Modifier un rôle	core	other	Modifier un rôle	\N	0	t
39	cores.roles.destroy	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Supprimer un rôle	core	other	Supprimer un rôle	\N	0	t
40	cores.permissions.index	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Voir la matrice des permissions	core	other	Voir la matrice des permissions	\N	0	t
41	cores.permissions.toggle	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Modifier les permissions	core	other	Modifier les permissions	\N	0	t
42	cores.permissions.sync	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Synchroniser les permissions	core	other	Synchroniser les permissions	\N	0	t
43	cores.modules.index	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Voir la liste des modules	core	other	Voir la liste des modules	\N	0	t
44	cores.modules.show	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Voir les détails d'un module	core	other	Voir les détails d'un module	\N	0	t
45	cores.modules.install	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Installer un module	core	install	Installer un module	\N	0	t
46	cores.modules.uninstall	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Désinstaller un module	core	other	Désinstaller un module	\N	0	t
47	cores.modules.enable	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Activer un module	core	enable	Activer un module	\N	0	t
48	cores.modules.disable	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Désactiver un module	core	disable	Désactiver un module	\N	0	t
49	cores.modules.configure	web	2026-01-11 22:35:20	2026-01-11 22:35:20	Configurer un module	core	configure	Configurer un module	\N	0	t
\.


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.role_has_permissions (permission_id, role_id) FROM stdin;
31	11
32	11
33	11
34	11
35	11
36	11
37	11
38	11
39	11
40	11
41	11
42	11
43	11
44	11
45	11
46	11
47	11
48	11
49	11
30	11
30	12
41	12
42	12
31	12
32	12
34	12
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, name, guard_name, created_at, updated_at, description) FROM stdin;
11	super-admin	web	2026-01-08 22:16:18	2026-01-08 22:16:18	\N
12	admin	web	2026-01-08 22:17:11	2026-01-14 20:47:58	gt
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
dx5nnwj18CqlNXdx2fXyUkJ2uWurx0KpJN9WxvXj	\N	192.168.122.79	Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0	YTozOntzOjY6Il90b2tlbiI7czo0MDoiUDVaS1FWOXZxR0JJdHlQQ1lrSkQwWWRFUUJPcENEQ2dYUVN3eDZ0cCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6Mjg6Imh0dHBzOi8va2V5c3RvbmUubG9jYWwvbG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19	1771933504
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, last_name, user_name, email, service, email_verified_at, password, remember_token, created_at, updated_at, is_active, avatar) FROM stdin;
3	ibrahim	gninasse	ibrahim	ibrahim.gninasse@gmail.com	it	\N	$2y$12$KnWUpTubFSWIXlBk4sfXneeTmGl5MWIlUb/ou3Y9lMmLKAOaY/bXW	LrnG6z0sNLrnKRBswarUwWQ2W9BcHdEC9MZdKJCu2TO8uwibmOJhB0xhOtQr	2025-12-24 17:14:49	2025-12-26 03:31:59	t	\N
4	idrissa	gninasse	idrissa	idrissa@gmail.com	it	\N	$2y$12$uwe7r8O08yHSBFdmCl9MveSUfMcS0UHqfJ8dSOR6W846O0eRM0h96	\N	2025-12-26 01:16:09	2026-01-05 14:43:07	t	\N
8	aa	aa	aa	aa@a.com	aa	\N	$2y$12$tfJLEs6RrPkGmPDDNFdI2ujUBhcbGhWfOVgth/EhZ5vYm8TGS2QpK	\N	2026-01-14 15:08:56	2026-01-16 01:19:31	t	avatars/1768403335_aa.png
\.


--
-- Name: activity_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.activity_log_id_seq', 11, true);


--
-- Name: cores_users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cores_users_id_seq', 8, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 19, true);


--
-- Name: modules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.modules_id_seq', 20, true);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permissions_id_seq', 49, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 16, true);


--
-- Name: activity_log activity_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log
    ADD CONSTRAINT activity_log_pkey PRIMARY KEY (id);


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
-- Name: users cores_users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT cores_users_email_unique UNIQUE (email);


--
-- Name: users cores_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT cores_users_pkey PRIMARY KEY (id);


--
-- Name: users cores_users_user_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT cores_users_user_name_unique UNIQUE (user_name);


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
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: modules modules_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_name_unique UNIQUE (name);


--
-- Name: modules modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_pkey PRIMARY KEY (id);


--
-- Name: modules modules_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_slug_unique UNIQUE (slug);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: activity_log_causer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_causer_id_index ON public.activity_log USING btree (causer_id);


--
-- Name: activity_log_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_created_at_index ON public.activity_log USING btree (created_at);


--
-- Name: activity_log_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_expires_at_index ON public.activity_log USING btree (expires_at);


--
-- Name: activity_log_log_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_log_name_index ON public.activity_log USING btree (log_name);


--
-- Name: activity_log_module_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_module_index ON public.activity_log USING btree (module);


--
-- Name: activity_log_subject_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_subject_id_index ON public.activity_log USING btree (subject_id);


--
-- Name: causer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX causer ON public.activity_log USING btree (causer_type, causer_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: modules_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modules_is_active_index ON public.modules USING btree (is_active);


--
-- Name: modules_is_required_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modules_is_required_index ON public.modules USING btree (is_required);


--
-- Name: modules_slug_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX modules_slug_index ON public.modules USING btree (slug);


--
-- Name: permissions_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX permissions_category_index ON public.permissions USING btree (category);


--
-- Name: permissions_module_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX permissions_module_index ON public.permissions USING btree (module);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: subject; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subject ON public.activity_log USING btree (subject_type, subject_id);


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict nDYM0KuxImAi8hKZaPdZm7E7JUIo6WFRFdRWYuk91iP4b57T9c7TPvPzmhrQUf5

