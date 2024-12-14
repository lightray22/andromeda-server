

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


CREATE TABLE public.a2obj_apps_core_actionlog (
    id character(20) NOT NULL,
    admin boolean,
    account character(12) DEFAULT NULL::bpchar,
    sudouser character(12) DEFAULT NULL::bpchar,
    client character(12) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_core_config (
    id character(1) NOT NULL,
    version character varying(255) NOT NULL,
    datadir text,
    apps text NOT NULL,
    date_created double precision NOT NULL,
    actionlog_db smallint NOT NULL,
    actionlog_file boolean NOT NULL,
    actionlog_details smallint NOT NULL,
    debug smallint NOT NULL,
    debug_http boolean NOT NULL,
    debug_dblog boolean NOT NULL,
    debug_filelog boolean NOT NULL,
    metrics smallint NOT NULL,
    metrics_dblog boolean NOT NULL,
    metrics_filelog boolean NOT NULL,
    read_only boolean NOT NULL,
    enabled boolean NOT NULL,
    email boolean NOT NULL
);



CREATE TABLE public.a2obj_core_emailer (
    id character(4) NOT NULL,
    type smallint NOT NULL,
    hosts text,
    username character varying(255) DEFAULT NULL::character varying,
    password text,
    from_address character varying(255) NOT NULL,
    from_name character varying(255) DEFAULT NULL::character varying,
    use_reply boolean,
    date_created double precision NOT NULL
);



CREATE TABLE public.a2obj_core_errors_errorlog (
    id character(12) NOT NULL,
    "time" double precision NOT NULL,
    addr character varying(255) NOT NULL,
    agent text NOT NULL,
    app character varying(255) DEFAULT NULL::character varying,
    action character varying(255) DEFAULT NULL::character varying,
    code bigint NOT NULL,
    file text NOT NULL,
    message text NOT NULL,
    trace_basic text,
    trace_full text,
    objects text,
    queries text,
    params text,
    hints text
);



CREATE TABLE public.a2obj_core_logging_actionlog (
    id character(20) NOT NULL,
    "time" double precision NOT NULL,
    addr character varying(255) NOT NULL,
    agent text NOT NULL,
    errcode bigint,
    errtext text,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    authuser character varying(255) DEFAULT NULL::character varying,
    params text,
    files text,
    details text
);



CREATE TABLE public.a2obj_core_logging_metricslog (
    id character(20) NOT NULL,
    actionlog character(20) DEFAULT NULL::bpchar,
    date_created double precision NOT NULL,
    peak_memory bigint NOT NULL,
    nincludes smallint NOT NULL,
    nobjects bigint NOT NULL,
    init_db_reads bigint NOT NULL,
    init_db_read_time double precision NOT NULL,
    init_db_writes bigint NOT NULL,
    init_db_write_time double precision NOT NULL,
    init_code_time double precision NOT NULL,
    init_autoloader_time double precision NOT NULL,
    init_total_time double precision NOT NULL,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    action_db_reads bigint NOT NULL,
    action_db_read_time double precision NOT NULL,
    action_db_writes bigint NOT NULL,
    action_db_write_time double precision NOT NULL,
    action_code_time double precision NOT NULL,
    action_autoloader_time double precision NOT NULL,
    action_total_time double precision NOT NULL,
    commit_db_reads bigint,
    commit_db_read_time double precision,
    commit_db_writes bigint,
    commit_db_write_time double precision,
    commit_code_time double precision,
    commit_autoloader_time double precision,
    commit_total_time double precision,
    db_reads bigint NOT NULL,
    db_read_time double precision NOT NULL,
    db_writes bigint NOT NULL,
    db_write_time double precision NOT NULL,
    code_time double precision NOT NULL,
    autoloader_time double precision NOT NULL,
    total_time double precision NOT NULL,
    gcstats text,
    rusage text,
    includes text,
    objects text,
    queries text,
    debughints text
);



ALTER TABLE ONLY public.a2obj_apps_core_actionlog
    ADD CONSTRAINT idx_78782_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_config
    ADD CONSTRAINT idx_78788_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_emailer
    ADD CONSTRAINT idx_78793_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_errors_errorlog
    ADD CONSTRAINT idx_78800_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_actionlog
    ADD CONSTRAINT idx_78807_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_metricslog
    ADD CONSTRAINT idx_78813_primary PRIMARY KEY (id);



CREATE INDEX idx_78800_action ON public.a2obj_core_errors_errorlog USING btree (action);



CREATE INDEX idx_78800_addr ON public.a2obj_core_errors_errorlog USING btree (addr);



CREATE INDEX idx_78800_app ON public.a2obj_core_errors_errorlog USING btree (app);



CREATE INDEX idx_78800_code ON public.a2obj_core_errors_errorlog USING btree (code);



CREATE INDEX idx_78800_time ON public.a2obj_core_errors_errorlog USING btree ("time");



CREATE INDEX idx_78807_addr ON public.a2obj_core_logging_actionlog USING btree (addr);



CREATE INDEX idx_78807_app_action ON public.a2obj_core_logging_actionlog USING btree (app, action);



CREATE INDEX idx_78807_time ON public.a2obj_core_logging_actionlog USING btree ("time");



CREATE UNIQUE INDEX idx_78813_actionlog ON public.a2obj_core_logging_metricslog USING btree (actionlog);



CREATE INDEX idx_78813_app_action ON public.a2obj_core_logging_metricslog USING btree (app, action);



ALTER TABLE ONLY public.a2obj_apps_core_actionlog
    ADD CONSTRAINT a2obj_apps_core_actionlog_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_core_logging_metricslog
    ADD CONSTRAINT a2obj_core_logging_metricslog_ibfk_1 FOREIGN KEY (actionlog) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE CASCADE ON DELETE SET NULL;



