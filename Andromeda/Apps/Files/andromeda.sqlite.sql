CREATE TABLE `a2obj_apps_files_actionlog` (
  `id` char(20) NOT NULL
,  `admin` integer DEFAULT NULL
,  `account` char(12) DEFAULT NULL
,  `sudouser` char(12) DEFAULT NULL
,  `client` char(12) DEFAULT NULL
,  `file` char(16) DEFAULT NULL
,  `folder` char(16) DEFAULT NULL
,  `parent` char(16) DEFAULT NULL
,  `file_share` char(16) DEFAULT NULL
,  `folder_share` char(16) DEFAULT NULL
,  `parent_share` char(16) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_actionlog_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_config` (
  `id` char(1) NOT NULL
,  `version` varchar(255) NOT NULL
,  `date_created` double NOT NULL
,  `apiurl` text DEFAULT NULL
,  `rwchunksize` integer NOT NULL
,  `crchunksize` integer NOT NULL
,  `upload_maxsize` integer DEFAULT NULL
,  `timedstats` integer NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_items_filesystem_fsmanager` (
  `id` char(8) NOT NULL
,  `date_created` double NOT NULL
,  `type` integer NOT NULL
,  `readonly` integer NOT NULL
,  `owner` char(12) DEFAULT NULL
,  `name` varchar(127) DEFAULT NULL
,  `crypto_masterkey` binary(32) DEFAULT NULL
,  `crypto_chunksize` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`owner`,`name`)
,  CONSTRAINT `a2obj_apps_files_items_filesystem_fsmanager_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
);
CREATE TABLE `a2obj_apps_files_items_item` (
  `id` char(16) NOT NULL
,  `size` integer NOT NULL
,  `owner` char(12) DEFAULT NULL
,  `filesystem` char(8) NOT NULL
,  `date_created` double NOT NULL
,  `date_modified` double DEFAULT NULL
,  `date_accessed` double DEFAULT NULL
,  `description` text DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_items_item_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_items_item_ibfk_2` FOREIGN KEY (`filesystem`) REFERENCES `a2obj_apps_files_items_filesystem_fsmanager` (`id`)
);
CREATE TABLE `a2obj_apps_files_items_items_folder` (
  `id` char(16) NOT NULL
,  `count_subfiles` integer NOT NULL DEFAULT 0
,  `count_subfolders` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_items_items_folder_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_items_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_items_items_rootfolder` (
  `id` char(16) NOT NULL
,  `owner` char(12) DEFAULT NULL
,  `filesystem` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`owner`,`filesystem`)
,  CONSTRAINT `a2obj_apps_files_items_items_rootfolder_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_items_items_folder` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_items_subitem` (
  `id` char(16) NOT NULL
,  `name` varchar(255) NOT NULL
,  `parent` char(16) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`name`,`parent`)
,  CONSTRAINT `a2obj_apps_files_items_subitem_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_items_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_items_subitem_ibfk_2` FOREIGN KEY (`parent`) REFERENCES `a2obj_apps_files_items_items_folder` (`id`)
);
CREATE TABLE `a2obj_apps_files_limits_accounttimed` (
  `id` char(12) NOT NULL
,  `account` char(12) NOT NULL
,  `timeperiod` integer NOT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`account`,`timeperiod`)
,  CONSTRAINT `a2obj_apps_files_limits_accounttimed_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_timed` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_limits_accounttimed_ibfk_2` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
);
CREATE TABLE `a2obj_apps_files_limits_accounttotal` (
  `id` char(12) NOT NULL
,  `account` char(2) NOT NULL
,  `emailshare` integer DEFAULT NULL
,  `userstorage` integer DEFAULT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`account`)
,  CONSTRAINT `a2obj_apps_files_limits_accounttotal_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_total` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_limits_accounttotal_ibfk_2` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
);
CREATE TABLE `a2obj_apps_files_limits_filesystemtimed` (
  `id` char(8) NOT NULL
,  `filesystem` char(8) NOT NULL
,  `timeperiod` integer NOT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`filesystem`,`timeperiod`)
,  CONSTRAINT `a2obj_apps_files_limits_filesystemtimed_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_timed` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_limits_filesystemtimed_ibfk_2` FOREIGN KEY (`filesystem`) REFERENCES `a2obj_apps_files_items_filesystem_fsmanager` (`id`)
);
CREATE TABLE `a2obj_apps_files_limits_filesystemtotal` (
  `id` char(8) NOT NULL
,  `filesystem` char(8) NOT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`filesystem`)
,  CONSTRAINT `a2obj_apps_files_limits_filesystemtotal_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_total` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_limits_filesystemtotal_ibfk_2` FOREIGN KEY (`filesystem`) REFERENCES `a2obj_apps_files_items_filesystem_fsmanager` (`id`)
);
CREATE TABLE `a2obj_apps_files_limits_grouptimed` (
  `id` char(12) NOT NULL
,  `group` char(12) NOT NULL
,  `timeperiod` integer NOT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`group`,`timeperiod`)
,  CONSTRAINT `a2obj_apps_files_limits_grouptimed_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_timed` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_limits_grouptimed_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
);
CREATE TABLE `a2obj_apps_files_limits_grouptotal` (
  `id` char(12) NOT NULL
,  `group` char(12) NOT NULL
,  `emailshare` integer DEFAULT NULL
,  `userstorage` integer DEFAULT NULL
,  `track_items` integer DEFAULT NULL
,  `track_dlstats` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`group`)
,  CONSTRAINT `a2obj_apps_files_limits_grouptotal_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_total` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_files_limits_grouptotal_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
);
CREATE TABLE `a2obj_apps_files_limits_timed` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `max_stats_age` integer DEFAULT NULL
,  `limit_pubdownloads` integer DEFAULT NULL
,  `limit_bandwidth` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_limits_timedstats` (
  `id` char(12) NOT NULL
,  `limit` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `date_timestart` integer NOT NULL
,  `iscurrent` integer DEFAULT NULL
,  `count_size` integer NOT NULL DEFAULT 0
,  `count_items` integer NOT NULL DEFAULT 0
,  `count_shares` integer NOT NULL DEFAULT 0
,  `count_pubdownloads` integer NOT NULL DEFAULT 0
,  `count_bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
,  UNIQUE (`limit`,`date_timestart`)
,  UNIQUE (`limit`,`iscurrent`)
,  CONSTRAINT `a2obj_apps_files_limits_timedstats_ibfk_1` FOREIGN KEY (`limit`) REFERENCES `a2obj_apps_files_limits_timed` (`id`)
);
CREATE TABLE `a2obj_apps_files_limits_total` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `date_download` double DEFAULT NULL
,  `date_upload` double DEFAULT NULL
,  `itemsharing` integer DEFAULT NULL
,  `share2everyone` integer DEFAULT NULL
,  `share2groups` integer DEFAULT NULL
,  `publicupload` integer DEFAULT NULL
,  `publicmodify` integer DEFAULT NULL
,  `randomwrite` integer DEFAULT NULL
,  `count_size` integer NOT NULL DEFAULT 0
,  `count_items` integer NOT NULL DEFAULT 0
,  `count_shares` integer NOT NULL DEFAULT 0
,  `limit_size` integer DEFAULT NULL
,  `limit_items` integer DEFAULT NULL
,  `limit_shares` integer DEFAULT NULL
,  `count_pubdownloads` integer NOT NULL DEFAULT 0
,  `count_bandwidth` integer NOT NULL DEFAULT 0
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_files_social_comment` (
  `id` char(16) NOT NULL
,  `owner` char(12) NOT NULL
,  `item` char(16) NOT NULL
,  `comment` text NOT NULL
,  `date_created` double NOT NULL
,  `date_modified` double NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_social_comment_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_comment_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
);
CREATE TABLE `a2obj_apps_files_social_like` (
  `id` char(12) NOT NULL
,  `owner` char(12) NOT NULL
,  `item` char(16) NOT NULL
,  `date_created` double NOT NULL
,  `value` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`owner`,`item`)
,  CONSTRAINT `a2obj_apps_files_social_like_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_like_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
);
CREATE TABLE `a2obj_apps_files_social_share` (
  `id` char(16) NOT NULL
,  `item` char(16) NOT NULL
,  `owner` char(12) NOT NULL
,  `dest` char(12) DEFAULT NULL
,  `label` text DEFAULT NULL
,  `authkey` text DEFAULT NULL
,  `password` text DEFAULT NULL
,  `date_created` double NOT NULL
,  `date_accessed` double DEFAULT NULL
,  `count_accessed` integer NOT NULL DEFAULT 0
,  `limit_accessed` integer DEFAULT NULL
,  `date_expires` double DEFAULT NULL
,  `read` integer NOT NULL
,  `upload` integer NOT NULL
,  `modify` integer NOT NULL
,  `social` integer NOT NULL
,  `reshare` integer NOT NULL
,  `keepowner` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`item`,`owner`,`dest`)
,  CONSTRAINT `a2obj_apps_files_social_share_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_share_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_share_ibfk_3` FOREIGN KEY (`dest`) REFERENCES `a2obj_apps_accounts_policybase` (`id`)
);
CREATE TABLE `a2obj_apps_files_social_tag` (
  `id` char(16) NOT NULL
,  `owner` char(12) NOT NULL
,  `item` char(16) NOT NULL
,  `tag` varchar(127) NOT NULL
,  `date_created` double NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`item`,`tag`)
,  CONSTRAINT `a2obj_apps_files_social_tag_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_files_social_tag_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_items_item` (`id`)
);
CREATE TABLE `a2obj_apps_files_storage_ftp` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `implssl` integer NOT NULL
,  `username` varbinary(255) DEFAULT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` tinyblob DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_ftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_local` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_local_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_s3` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  `endpoint` text NOT NULL
,  `path_style` integer DEFAULT NULL
,  `port` integer DEFAULT NULL
,  `usetls` integer DEFAULT NULL
,  `region` varchar(64) NOT NULL
,  `bucket` varchar(64) NOT NULL
,  `accesskey` varbinary(144) NOT NULL
,  `accesskey_nonce` binary(24) DEFAULT NULL
,  `secretkey` varbinary(56) DEFAULT NULL
,  `secretkey_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_s3_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_sftp` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `hostkey` text NOT NULL
,  `username` varbinary(255) NOT NULL
,  `password` tinyblob DEFAULT NULL
,  `privkey` blob DEFAULT NULL
,  `keypass` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  `privkey_nonce` binary(24) DEFAULT NULL
,  `keypass_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_sftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_smb` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `workgroup` varchar(255) DEFAULT NULL
,  `username` varbinary(255) NOT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_smb_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_files_storage_storage` (
  `id` char(8) NOT NULL
,  `date_created` double NOT NULL
,  `filesystem` char(8) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`filesystem`)
,  CONSTRAINT `a2obj_apps_files_storage_storage_ibfk_1` FOREIGN KEY (`filesystem`) REFERENCES `a2obj_apps_files_items_filesystem_fsmanager` (`id`)
);
CREATE TABLE `a2obj_apps_files_storage_webdav` (
  `id` char(8) NOT NULL
,  `path` text NOT NULL
,  `endpoint` text NOT NULL
,  `username` varbinary(255) NOT NULL
,  `password` tinyblob DEFAULT NULL
,  `username_nonce` binary(24) DEFAULT NULL
,  `password_nonce` binary(24) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_files_storage_webdav_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX "idx_a2obj_apps_files_limits_grouptimed_group" ON "a2obj_apps_files_limits_grouptimed" (`group`);
CREATE INDEX "idx_a2obj_apps_files_limits_filesystemtimed_filesystem" ON "a2obj_apps_files_limits_filesystemtimed" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_actionlog_account" ON "a2obj_apps_files_actionlog" (`account`);
CREATE INDEX "idx_a2obj_apps_files_actionlog_file" ON "a2obj_apps_files_actionlog" (`file`);
CREATE INDEX "idx_a2obj_apps_files_actionlog_folder" ON "a2obj_apps_files_actionlog" (`folder`);
CREATE INDEX "idx_a2obj_apps_files_social_comment_item" ON "a2obj_apps_files_social_comment" (`item`);
CREATE INDEX "idx_a2obj_apps_files_social_comment_owner_item" ON "a2obj_apps_files_social_comment" (`owner`,`item`);
CREATE INDEX "idx_a2obj_apps_files_items_items_rootfolder_owner" ON "a2obj_apps_files_items_items_rootfolder" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_items_items_rootfolder_filesystem" ON "a2obj_apps_files_items_items_rootfolder" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_items_item_owner" ON "a2obj_apps_files_items_item" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_items_item_filesystem" ON "a2obj_apps_files_items_item" (`filesystem`);
CREATE INDEX "idx_a2obj_apps_files_social_share_dest" ON "a2obj_apps_files_social_share" (`dest`);
CREATE INDEX "idx_a2obj_apps_files_social_share_owner" ON "a2obj_apps_files_social_share" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_social_share_item" ON "a2obj_apps_files_social_share" (`item`);
CREATE INDEX "idx_a2obj_apps_files_items_subitem_parent" ON "a2obj_apps_files_items_subitem" (`parent`);
CREATE INDEX "idx_a2obj_apps_files_social_tag_owner" ON "a2obj_apps_files_social_tag" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_social_tag_item" ON "a2obj_apps_files_social_tag" (`item`);
CREATE INDEX "idx_a2obj_apps_files_items_filesystem_fsmanager_owner" ON "a2obj_apps_files_items_filesystem_fsmanager" (`owner`);
CREATE INDEX "idx_a2obj_apps_files_items_filesystem_fsmanager_name" ON "a2obj_apps_files_items_filesystem_fsmanager" (`name`);
CREATE INDEX "idx_a2obj_apps_files_social_like_item" ON "a2obj_apps_files_social_like" (`item`);
CREATE INDEX "idx_a2obj_apps_files_limits_accounttimed_account" ON "a2obj_apps_files_limits_accounttimed" (`account`);
