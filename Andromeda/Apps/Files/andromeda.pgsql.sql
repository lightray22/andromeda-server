

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


CREATE TABLE public.a2obj_apps_files_actionlog (
    id character(20) NOT NULL,
    admin boolean,
    account character(12) DEFAULT NULL::bpchar,
    sudouser character(12) DEFAULT NULL::bpchar,
    client character(12) DEFAULT NULL::bpchar,
    file character(16) DEFAULT NULL::bpchar,
    folder character(16) DEFAULT NULL::bpchar,
    parent character(16) DEFAULT NULL::bpchar,
    file_share character(16) DEFAULT NULL::bpchar,
    folder_share character(16) DEFAULT NULL::bpchar,
    parent_share character(16) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_files_config (
    id character(1) NOT NULL,
    version character varying(255) NOT NULL,
    date_created double precision NOT NULL,
    apiurl text,
    rwchunksize bigint NOT NULL,
    crchunksize bigint NOT NULL,
    upload_maxsize bigint,
    timedstats boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_files_items_folder (
    id character(16) NOT NULL,
    count_subfiles bigint DEFAULT '0'::bigint NOT NULL,
    count_subfolders bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_items_item (
    id character(16) NOT NULL,
    size bigint NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    storage character(8) NOT NULL,
    date_created double precision NOT NULL,
    date_modified double precision,
    date_accessed double precision,
    description text
);



CREATE TABLE public.a2obj_apps_files_items_rootfolder (
    id character(16) NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    storage character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_files_items_subitem (
    id character(16) NOT NULL,
    name character varying(255) NOT NULL,
    parent character(16) NOT NULL
);



CREATE TABLE public.a2obj_apps_files_limits_accounttimed (
    id character(12) NOT NULL,
    account character(12) NOT NULL,
    timeperiod bigint NOT NULL,
    track_items boolean,
    track_dlstats boolean
);



CREATE TABLE public.a2obj_apps_files_limits_accounttotal (
    id character(12) NOT NULL,
    account character(2) NOT NULL,
    emailshare boolean,
    userstorage boolean,
    track_items boolean,
    track_dlstats boolean
);



CREATE TABLE public.a2obj_apps_files_limits_grouptimed (
    id character(12) NOT NULL,
    "group" character(12) NOT NULL,
    timeperiod bigint NOT NULL,
    track_items smallint,
    track_dlstats smallint
);



CREATE TABLE public.a2obj_apps_files_limits_grouptotal (
    id character(12) NOT NULL,
    "group" character(12) NOT NULL,
    emailshare boolean,
    userstorage boolean,
    track_items smallint,
    track_dlstats smallint
);



CREATE TABLE public.a2obj_apps_files_limits_storagetimed (
    id character(8) NOT NULL,
    storage character(8) NOT NULL,
    timeperiod bigint NOT NULL,
    track_items boolean,
    track_dlstats boolean
);



CREATE TABLE public.a2obj_apps_files_limits_storagetotal (
    id character(8) NOT NULL,
    storage character(8) NOT NULL,
    track_items boolean,
    track_dlstats boolean
);



CREATE TABLE public.a2obj_apps_files_limits_timed (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    max_stats_age bigint,
    limit_pubdownloads bigint,
    limit_bandwidth bigint
);



CREATE TABLE public.a2obj_apps_files_limits_timedstats (
    id character(12) NOT NULL,
    "limit" character(12) NOT NULL,
    date_created double precision NOT NULL,
    date_timestart bigint NOT NULL,
    iscurrent boolean,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_items bigint DEFAULT '0'::bigint NOT NULL,
    count_shares bigint DEFAULT '0'::bigint NOT NULL,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_limits_total (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    date_download double precision,
    date_upload double precision,
    itemsharing boolean,
    share2everyone boolean,
    share2groups boolean,
    publicupload boolean,
    publicmodify boolean,
    randomwrite boolean,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_items bigint DEFAULT '0'::bigint NOT NULL,
    count_shares bigint DEFAULT '0'::bigint NOT NULL,
    limit_size bigint,
    limit_items bigint,
    limit_shares bigint,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_social_comment (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character(16) NOT NULL,
    comment text NOT NULL,
    date_created double precision NOT NULL,
    date_modified double precision NOT NULL
);



CREATE TABLE public.a2obj_apps_files_social_like (
    id character(12) NOT NULL,
    owner character(12) NOT NULL,
    item character(16) NOT NULL,
    date_created double precision NOT NULL,
    value boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_files_social_share (
    id character(16) NOT NULL,
    item character(16) NOT NULL,
    owner character(12) NOT NULL,
    dest character(12) DEFAULT NULL::bpchar,
    label text,
    authkey text,
    password text,
    date_created double precision NOT NULL,
    date_accessed double precision,
    count_accessed bigint DEFAULT '0'::bigint NOT NULL,
    limit_accessed bigint,
    date_expires double precision,
    read boolean NOT NULL,
    upload boolean NOT NULL,
    modify boolean NOT NULL,
    social boolean NOT NULL,
    reshare boolean NOT NULL,
    keepowner boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_files_social_tag (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character(16) NOT NULL,
    tag character varying(127) NOT NULL,
    date_created double precision NOT NULL
);



CREATE TABLE public.a2obj_apps_files_storage_ftp (
    id character(8) NOT NULL,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    username bytea,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea
);



CREATE TABLE public.a2obj_apps_files_storage_local (
    id character(8) NOT NULL,
    path text NOT NULL
);



CREATE TABLE public.a2obj_apps_files_storage_s3 (
    id character(8) NOT NULL,
    endpoint text NOT NULL,
    path_style boolean,
    port smallint,
    usetls boolean,
    region character varying(64) NOT NULL,
    bucket character varying(64) NOT NULL,
    accesskey bytea,
    accesskey_nonce bytea DEFAULT NULL::bytea,
    secretkey bytea,
    secretkey_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_storage_sftp (
    id character(8) NOT NULL,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    hostkey text,
    username bytea NOT NULL,
    password bytea,
    privkey bytea,
    keypass bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea,
    privkey_nonce bytea DEFAULT NULL::bytea,
    keypass_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_storage_smb (
    id character(8) NOT NULL,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    workgroup character varying(255) DEFAULT NULL::character varying,
    username bytea,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_storage_storage (
    id character(8) NOT NULL,
    date_created double precision NOT NULL,
    fstype smallint NOT NULL,
    readonly boolean DEFAULT false NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    name character varying(127) DEFAULT 'Default'::character varying NOT NULL,
    crypto_masterkey bytea DEFAULT NULL::bytea,
    crypto_chunksize bigint
);



CREATE TABLE public.a2obj_apps_files_storage_webdav (
    id character(8) NOT NULL,
    path text NOT NULL,
    endpoint text NOT NULL,
    username bytea NOT NULL,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea
);



ALTER TABLE ONLY public.a2obj_apps_files_actionlog
    ADD CONSTRAINT idx_134677_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_config
    ADD CONSTRAINT idx_134689_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_items_folder
    ADD CONSTRAINT idx_134694_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT idx_134699_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_items_rootfolder
    ADD CONSTRAINT idx_134705_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_items_subitem
    ADD CONSTRAINT idx_134709_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttimed
    ADD CONSTRAINT idx_134712_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttotal
    ADD CONSTRAINT idx_134715_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptimed
    ADD CONSTRAINT idx_134718_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptotal
    ADD CONSTRAINT idx_134721_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetimed
    ADD CONSTRAINT idx_134724_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetotal
    ADD CONSTRAINT idx_134727_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_timed
    ADD CONSTRAINT idx_134730_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_timedstats
    ADD CONSTRAINT idx_134733_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_total
    ADD CONSTRAINT idx_134741_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_social_comment
    ADD CONSTRAINT idx_134749_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_social_like
    ADD CONSTRAINT idx_134754_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT idx_134757_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_social_tag
    ADD CONSTRAINT idx_134764_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_ftp
    ADD CONSTRAINT idx_134767_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_local
    ADD CONSTRAINT idx_134773_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_s3
    ADD CONSTRAINT idx_134778_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_sftp
    ADD CONSTRAINT idx_134785_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_smb
    ADD CONSTRAINT idx_134794_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_storage
    ADD CONSTRAINT idx_134802_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT idx_134811_primary PRIMARY KEY (id);



CREATE INDEX idx_134677_account ON public.a2obj_apps_files_actionlog USING btree (account);



CREATE INDEX idx_134677_file ON public.a2obj_apps_files_actionlog USING btree (file);



CREATE INDEX idx_134677_folder ON public.a2obj_apps_files_actionlog USING btree (folder);



CREATE INDEX idx_134699_owner ON public.a2obj_apps_files_items_item USING btree (owner);



CREATE INDEX idx_134699_storage ON public.a2obj_apps_files_items_item USING btree (storage);



CREATE INDEX idx_134705_owner ON public.a2obj_apps_files_items_rootfolder USING btree (owner);



CREATE UNIQUE INDEX idx_134705_owner_storage ON public.a2obj_apps_files_items_rootfolder USING btree (owner, storage);



CREATE INDEX idx_134705_storage ON public.a2obj_apps_files_items_rootfolder USING btree (storage);



CREATE UNIQUE INDEX idx_134709_name_parent ON public.a2obj_apps_files_items_subitem USING btree (name, parent);



CREATE INDEX idx_134709_parent ON public.a2obj_apps_files_items_subitem USING btree (parent);



CREATE INDEX idx_134712_account ON public.a2obj_apps_files_limits_accounttimed USING btree (account);



CREATE UNIQUE INDEX idx_134712_account_timeperiod ON public.a2obj_apps_files_limits_accounttimed USING btree (account, timeperiod);



CREATE UNIQUE INDEX idx_134715_account ON public.a2obj_apps_files_limits_accounttotal USING btree (account);



CREATE INDEX idx_134718_group ON public.a2obj_apps_files_limits_grouptimed USING btree ("group");



CREATE UNIQUE INDEX idx_134718_group_timeperiod ON public.a2obj_apps_files_limits_grouptimed USING btree ("group", timeperiod);



CREATE UNIQUE INDEX idx_134721_group ON public.a2obj_apps_files_limits_grouptotal USING btree ("group");



CREATE INDEX idx_134724_storage ON public.a2obj_apps_files_limits_storagetimed USING btree (storage);



CREATE UNIQUE INDEX idx_134724_storage_timeperiod ON public.a2obj_apps_files_limits_storagetimed USING btree (storage, timeperiod);



CREATE UNIQUE INDEX idx_134727_storage ON public.a2obj_apps_files_limits_storagetotal USING btree (storage);



CREATE UNIQUE INDEX idx_134733_limit_iscurrent ON public.a2obj_apps_files_limits_timedstats USING btree ("limit", iscurrent);



CREATE UNIQUE INDEX idx_134733_limit_timestart ON public.a2obj_apps_files_limits_timedstats USING btree ("limit", date_timestart);



CREATE INDEX idx_134749_item ON public.a2obj_apps_files_social_comment USING btree (item);



CREATE INDEX idx_134749_owner_item ON public.a2obj_apps_files_social_comment USING btree (owner, item);



CREATE INDEX idx_134754_item ON public.a2obj_apps_files_social_like USING btree (item);



CREATE UNIQUE INDEX idx_134754_owner_item ON public.a2obj_apps_files_social_like USING btree (owner, item);



CREATE INDEX idx_134757_dest ON public.a2obj_apps_files_social_share USING btree (dest);



CREATE INDEX idx_134757_item ON public.a2obj_apps_files_social_share USING btree (item);



CREATE UNIQUE INDEX idx_134757_item_owner_dest ON public.a2obj_apps_files_social_share USING btree (item, owner, dest);



CREATE INDEX idx_134757_owner ON public.a2obj_apps_files_social_share USING btree (owner);



CREATE INDEX idx_134764_item ON public.a2obj_apps_files_social_tag USING btree (item);



CREATE UNIQUE INDEX idx_134764_item_tag ON public.a2obj_apps_files_social_tag USING btree (item, tag);



CREATE INDEX idx_134764_owner ON public.a2obj_apps_files_social_tag USING btree (owner);



CREATE INDEX idx_134802_name ON public.a2obj_apps_files_storage_storage USING btree (name);



CREATE INDEX idx_134802_owner ON public.a2obj_apps_files_storage_storage USING btree (owner);



CREATE UNIQUE INDEX idx_134802_owner_name ON public.a2obj_apps_files_storage_storage USING btree (owner, name);



ALTER TABLE ONLY public.a2obj_apps_files_actionlog
    ADD CONSTRAINT a2obj_apps_files_actionlog_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_items_folder
    ADD CONSTRAINT a2obj_apps_files_items_folder_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT a2obj_apps_files_items_item_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT a2obj_apps_files_items_item_ibfk_2 FOREIGN KEY (storage) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_items_rootfolder
    ADD CONSTRAINT a2obj_apps_files_items_rootfolder_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_items_folder(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_items_subitem
    ADD CONSTRAINT a2obj_apps_files_items_subitem_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_items_subitem
    ADD CONSTRAINT a2obj_apps_files_items_subitem_ibfk_2 FOREIGN KEY (parent) REFERENCES public.a2obj_apps_files_items_folder(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttimed
    ADD CONSTRAINT a2obj_apps_files_limits_accounttimed_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttimed
    ADD CONSTRAINT a2obj_apps_files_limits_accounttimed_ibfk_2 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttotal
    ADD CONSTRAINT a2obj_apps_files_limits_accounttotal_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_total(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttotal
    ADD CONSTRAINT a2obj_apps_files_limits_accounttotal_ibfk_2 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptimed
    ADD CONSTRAINT a2obj_apps_files_limits_grouptimed_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptimed
    ADD CONSTRAINT a2obj_apps_files_limits_grouptimed_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptotal
    ADD CONSTRAINT a2obj_apps_files_limits_grouptotal_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_total(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptotal
    ADD CONSTRAINT a2obj_apps_files_limits_grouptotal_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetimed
    ADD CONSTRAINT a2obj_apps_files_limits_storagetimed_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetimed
    ADD CONSTRAINT a2obj_apps_files_limits_storagetimed_ibfk_2 FOREIGN KEY (storage) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetotal
    ADD CONSTRAINT a2obj_apps_files_limits_storagetotal_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_total(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetotal
    ADD CONSTRAINT a2obj_apps_files_limits_storagetotal_ibfk_2 FOREIGN KEY (storage) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_timedstats
    ADD CONSTRAINT a2obj_apps_files_limits_timedstats_ibfk_1 FOREIGN KEY ("limit") REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_social_comment
    ADD CONSTRAINT a2obj_apps_files_social_comment_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_social_comment
    ADD CONSTRAINT a2obj_apps_files_social_comment_ibfk_2 FOREIGN KEY (item) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_social_like
    ADD CONSTRAINT a2obj_apps_files_social_like_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_social_like
    ADD CONSTRAINT a2obj_apps_files_social_like_ibfk_2 FOREIGN KEY (item) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT a2obj_apps_files_social_share_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT a2obj_apps_files_social_share_ibfk_2 FOREIGN KEY (item) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT a2obj_apps_files_social_share_ibfk_3 FOREIGN KEY (dest) REFERENCES public.a2obj_apps_accounts_policybase(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_social_tag
    ADD CONSTRAINT a2obj_apps_files_social_tag_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_social_tag
    ADD CONSTRAINT a2obj_apps_files_social_tag_ibfk_2 FOREIGN KEY (item) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_storage_storage
    ADD CONSTRAINT a2obj_apps_files_storage_fsmanager_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_storage_ftp
    ADD CONSTRAINT a2obj_apps_files_storage_ftp_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_storage_local
    ADD CONSTRAINT a2obj_apps_files_storage_local_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_storage_s3
    ADD CONSTRAINT a2obj_apps_files_storage_s3_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_storage_sftp
    ADD CONSTRAINT a2obj_apps_files_storage_sftp_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_storage_smb
    ADD CONSTRAINT a2obj_apps_files_storage_smb_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT a2obj_apps_files_storage_webdav_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;



