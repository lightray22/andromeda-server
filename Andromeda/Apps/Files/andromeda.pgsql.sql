

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



CREATE TABLE public.a2obj_apps_files_items_filesystem_fsmanager (
    id character(8) NOT NULL,
    date_created double precision NOT NULL,
    type smallint NOT NULL,
    readonly boolean NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    name character varying(127) DEFAULT NULL::character varying,
    crypto_masterkey bytea DEFAULT NULL::bytea,
    crypto_chunksize bigint
);



CREATE TABLE public.a2obj_apps_files_items_item (
    id character(16) NOT NULL,
    size bigint NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    filesystem character(8) NOT NULL,
    date_created double precision NOT NULL,
    date_modified double precision,
    date_accessed double precision,
    description text
);



CREATE TABLE public.a2obj_apps_files_items_items_folder (
    id character(16) NOT NULL,
    count_subfiles bigint DEFAULT '0'::bigint NOT NULL,
    count_subfolders bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_items_items_rootfolder (
    id character(16) NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    filesystem character(12) NOT NULL
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



CREATE TABLE public.a2obj_apps_files_limits_filesystemtimed (
    id character(8) NOT NULL,
    filesystem character(8) NOT NULL,
    timeperiod bigint NOT NULL,
    track_items boolean,
    track_dlstats boolean
);



CREATE TABLE public.a2obj_apps_files_limits_filesystemtotal (
    id character(8) NOT NULL,
    filesystem character(8) NOT NULL,
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
    path text NOT NULL,
    endpoint text NOT NULL,
    path_style boolean,
    port smallint,
    usetls boolean,
    region character varying(64) NOT NULL,
    bucket character varying(64) NOT NULL,
    accesskey bytea NOT NULL,
    accesskey_nonce bytea DEFAULT NULL::bytea,
    secretkey bytea,
    secretkey_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_storage_sftp (
    id character(8) NOT NULL,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    hostkey text NOT NULL,
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
    username bytea NOT NULL,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_storage_storage (
    id character(8) NOT NULL,
    date_created double precision NOT NULL,
    filesystem character(8) NOT NULL
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
    ADD CONSTRAINT idx_129276_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_config
    ADD CONSTRAINT idx_129288_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_items_filesystem_fsmanager
    ADD CONSTRAINT idx_129293_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT idx_129301_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_items_items_folder
    ADD CONSTRAINT idx_129307_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_items_items_rootfolder
    ADD CONSTRAINT idx_129312_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_items_subitem
    ADD CONSTRAINT idx_129316_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttimed
    ADD CONSTRAINT idx_129319_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttotal
    ADD CONSTRAINT idx_129322_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_filesystemtimed
    ADD CONSTRAINT idx_129325_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_filesystemtotal
    ADD CONSTRAINT idx_129328_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptimed
    ADD CONSTRAINT idx_129331_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptotal
    ADD CONSTRAINT idx_129334_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_timed
    ADD CONSTRAINT idx_129337_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_timedstats
    ADD CONSTRAINT idx_129340_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_total
    ADD CONSTRAINT idx_129348_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_social_comment
    ADD CONSTRAINT idx_129356_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_social_like
    ADD CONSTRAINT idx_129361_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT idx_129364_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_social_tag
    ADD CONSTRAINT idx_129371_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_ftp
    ADD CONSTRAINT idx_129374_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_local
    ADD CONSTRAINT idx_129380_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_s3
    ADD CONSTRAINT idx_129385_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_sftp
    ADD CONSTRAINT idx_129392_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_smb
    ADD CONSTRAINT idx_129401_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_storage
    ADD CONSTRAINT idx_129409_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT idx_129412_primary PRIMARY KEY (id);



CREATE INDEX idx_129276_account ON public.a2obj_apps_files_actionlog USING btree (account);



CREATE INDEX idx_129276_file ON public.a2obj_apps_files_actionlog USING btree (file);



CREATE INDEX idx_129276_folder ON public.a2obj_apps_files_actionlog USING btree (folder);



CREATE INDEX idx_129293_name ON public.a2obj_apps_files_items_filesystem_fsmanager USING btree (name);



CREATE INDEX idx_129293_owner ON public.a2obj_apps_files_items_filesystem_fsmanager USING btree (owner);



CREATE UNIQUE INDEX idx_129293_owner_name ON public.a2obj_apps_files_items_filesystem_fsmanager USING btree (owner, name);



CREATE INDEX idx_129301_filesystem ON public.a2obj_apps_files_items_item USING btree (filesystem);



CREATE INDEX idx_129301_owner ON public.a2obj_apps_files_items_item USING btree (owner);



CREATE INDEX idx_129312_filesystem ON public.a2obj_apps_files_items_items_rootfolder USING btree (filesystem);



CREATE INDEX idx_129312_owner ON public.a2obj_apps_files_items_items_rootfolder USING btree (owner);



CREATE UNIQUE INDEX idx_129312_owner_filesystem ON public.a2obj_apps_files_items_items_rootfolder USING btree (owner, filesystem);



CREATE UNIQUE INDEX idx_129316_name_parent ON public.a2obj_apps_files_items_subitem USING btree (name, parent);



CREATE INDEX idx_129316_parent ON public.a2obj_apps_files_items_subitem USING btree (parent);



CREATE INDEX idx_129319_account ON public.a2obj_apps_files_limits_accounttimed USING btree (account);



CREATE UNIQUE INDEX idx_129319_account_timeperiod ON public.a2obj_apps_files_limits_accounttimed USING btree (account, timeperiod);



CREATE UNIQUE INDEX idx_129322_account ON public.a2obj_apps_files_limits_accounttotal USING btree (account);



CREATE INDEX idx_129325_filesystem ON public.a2obj_apps_files_limits_filesystemtimed USING btree (filesystem);



CREATE UNIQUE INDEX idx_129325_filesystem_timeperiod ON public.a2obj_apps_files_limits_filesystemtimed USING btree (filesystem, timeperiod);



CREATE UNIQUE INDEX idx_129328_filesystem ON public.a2obj_apps_files_limits_filesystemtotal USING btree (filesystem);



CREATE INDEX idx_129331_group ON public.a2obj_apps_files_limits_grouptimed USING btree ("group");



CREATE UNIQUE INDEX idx_129331_group_timeperiod ON public.a2obj_apps_files_limits_grouptimed USING btree ("group", timeperiod);



CREATE UNIQUE INDEX idx_129334_group ON public.a2obj_apps_files_limits_grouptotal USING btree ("group");



CREATE UNIQUE INDEX idx_129340_limit_iscurrent ON public.a2obj_apps_files_limits_timedstats USING btree ("limit", iscurrent);



CREATE UNIQUE INDEX idx_129340_limit_timestart ON public.a2obj_apps_files_limits_timedstats USING btree ("limit", date_timestart);



CREATE INDEX idx_129356_item ON public.a2obj_apps_files_social_comment USING btree (item);



CREATE INDEX idx_129356_owner_item ON public.a2obj_apps_files_social_comment USING btree (owner, item);



CREATE INDEX idx_129361_item ON public.a2obj_apps_files_social_like USING btree (item);



CREATE UNIQUE INDEX idx_129361_owner_item ON public.a2obj_apps_files_social_like USING btree (owner, item);



CREATE INDEX idx_129364_dest ON public.a2obj_apps_files_social_share USING btree (dest);



CREATE INDEX idx_129364_item ON public.a2obj_apps_files_social_share USING btree (item);



CREATE UNIQUE INDEX idx_129364_item_owner_dest ON public.a2obj_apps_files_social_share USING btree (item, owner, dest);



CREATE INDEX idx_129364_owner ON public.a2obj_apps_files_social_share USING btree (owner);



CREATE INDEX idx_129371_item ON public.a2obj_apps_files_social_tag USING btree (item);



CREATE UNIQUE INDEX idx_129371_item_tag ON public.a2obj_apps_files_social_tag USING btree (item, tag);



CREATE INDEX idx_129371_owner ON public.a2obj_apps_files_social_tag USING btree (owner);



CREATE UNIQUE INDEX idx_129409_filesystem ON public.a2obj_apps_files_storage_storage USING btree (filesystem);



ALTER TABLE ONLY public.a2obj_apps_files_actionlog
    ADD CONSTRAINT a2obj_apps_files_actionlog_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_items_filesystem_fsmanager
    ADD CONSTRAINT a2obj_apps_files_items_filesystem_fsmanager_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT a2obj_apps_files_items_item_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT a2obj_apps_files_items_item_ibfk_2 FOREIGN KEY (filesystem) REFERENCES public.a2obj_apps_files_items_filesystem_fsmanager(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_items_items_folder
    ADD CONSTRAINT a2obj_apps_files_items_items_folder_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_items_items_rootfolder
    ADD CONSTRAINT a2obj_apps_files_items_items_rootfolder_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_items_items_folder(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_items_subitem
    ADD CONSTRAINT a2obj_apps_files_items_subitem_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_items_subitem
    ADD CONSTRAINT a2obj_apps_files_items_subitem_ibfk_2 FOREIGN KEY (parent) REFERENCES public.a2obj_apps_files_items_items_folder(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttimed
    ADD CONSTRAINT a2obj_apps_files_limits_accounttimed_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttimed
    ADD CONSTRAINT a2obj_apps_files_limits_accounttimed_ibfk_2 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttotal
    ADD CONSTRAINT a2obj_apps_files_limits_accounttotal_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_total(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttotal
    ADD CONSTRAINT a2obj_apps_files_limits_accounttotal_ibfk_2 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_filesystemtimed
    ADD CONSTRAINT a2obj_apps_files_limits_filesystemtimed_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_filesystemtimed
    ADD CONSTRAINT a2obj_apps_files_limits_filesystemtimed_ibfk_2 FOREIGN KEY (filesystem) REFERENCES public.a2obj_apps_files_items_filesystem_fsmanager(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_filesystemtotal
    ADD CONSTRAINT a2obj_apps_files_limits_filesystemtotal_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_total(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_filesystemtotal
    ADD CONSTRAINT a2obj_apps_files_limits_filesystemtotal_ibfk_2 FOREIGN KEY (filesystem) REFERENCES public.a2obj_apps_files_items_filesystem_fsmanager(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptimed
    ADD CONSTRAINT a2obj_apps_files_limits_grouptimed_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptimed
    ADD CONSTRAINT a2obj_apps_files_limits_grouptimed_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptotal
    ADD CONSTRAINT a2obj_apps_files_limits_grouptotal_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_total(id) ON UPDATE CASCADE ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptotal
    ADD CONSTRAINT a2obj_apps_files_limits_grouptotal_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



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



ALTER TABLE ONLY public.a2obj_apps_files_storage_storage
    ADD CONSTRAINT a2obj_apps_files_storage_storage_ibfk_1 FOREIGN KEY (filesystem) REFERENCES public.a2obj_apps_files_items_filesystem_fsmanager(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT a2obj_apps_files_storage_webdav_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;



