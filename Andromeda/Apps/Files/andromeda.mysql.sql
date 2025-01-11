/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_actionlog` (
  `id` char(20) NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `account` char(12) DEFAULT NULL,
  `sudouser` char(12) DEFAULT NULL,
  `client` char(12) DEFAULT NULL,
  `file` char(16) DEFAULT NULL,
  `folder` char(16) DEFAULT NULL,
  `parent` char(16) DEFAULT NULL,
  `file_share` char(16) DEFAULT NULL,
  `folder_share` char(16) DEFAULT NULL,
  `parent_share` char(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`account`),
  KEY `file` (`file`),
  KEY `folder` (`folder`),
  CONSTRAINT `a2obj_apps_files_actionlog_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_comment` (
  `id` char(16) NOT NULL,
  `owner` char(12) NOT NULL,
  `item` char(16) NOT NULL,
  `comment` text NOT NULL,
  `date_created` double NOT NULL,
  `date_modified` double NOT NULL,
  PRIMARY KEY (`id`),
  KEY `item` (`item`),
  KEY `owner_item` (`owner`,`item`),
  CONSTRAINT `a2obj_apps_files_comment_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_comment_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_config` (
  `id` char(1) NOT NULL,
  `version` varchar(255) NOT NULL,
  `date_created` double NOT NULL,
  `apiurl` text DEFAULT NULL,
  `rwchunksize` int(11) NOT NULL,
  `crchunksize` int(11) NOT NULL,
  `upload_maxsize` bigint(20) DEFAULT NULL,
  `timedstats` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_filesystem_fsmanager` (
  `id` char(8) NOT NULL,
  `date_created` double NOT NULL,
  `type` tinyint(2) NOT NULL,
  `readonly` tinyint(1) NOT NULL,
  `owner` char(12) DEFAULT NULL,
  `name` varchar(127) DEFAULT NULL,
  `crypto_masterkey` binary(32) DEFAULT NULL,
  `crypto_chunksize` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_name` (`owner`,`name`),
  KEY `owner` (`owner`),
  KEY `name` (`name`),
  CONSTRAINT `a2obj_apps_files_filesystem_fsmanager_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_folder` (
  `id` char(16) NOT NULL,
  `count_subfiles` int(11) NOT NULL DEFAULT 0,
  `count_subfolders` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_folder_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_item` (
  `id` char(16) NOT NULL,
  `size` bigint(20) NOT NULL,
  `owner` char(12) DEFAULT NULL,
  `filesystem` char(8) NOT NULL,
  `date_created` double NOT NULL,
  `date_modified` double DEFAULT NULL,
  `date_accessed` double DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `owner` (`owner`),
  KEY `filesystem` (`filesystem`),
  CONSTRAINT `a2obj_apps_files_item_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_item_ibfk_2` FOREIGN KEY (`filesystem`) REFERENCES `a2obj_apps_files_filesystem_fsmanager` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_like` (
  `id` char(12) NOT NULL,
  `owner` char(12) NOT NULL,
  `item` char(16) NOT NULL,
  `date_created` double NOT NULL,
  `value` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_item` (`owner`,`item`),
  KEY `item` (`item`),
  CONSTRAINT `a2obj_apps_files_like_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_like_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_accounttimed` (
  `id` char(12) NOT NULL,
  `account` char(12) NOT NULL,
  `timeperiod` int(11) NOT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_timeperiod` (`account`,`timeperiod`),
  KEY `account` (`account`),
  CONSTRAINT `a2obj_apps_files_limits_accounttimed_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_timed` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_limits_accounttimed_ibfk_2` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_accounttotal` (
  `id` char(12) NOT NULL,
  `account` char(2) NOT NULL,
  `emailshare` tinyint(1) DEFAULT NULL,
  `userstorage` tinyint(1) DEFAULT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account` (`account`),
  CONSTRAINT `a2obj_apps_files_limits_accounttotal_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_total` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_limits_accounttotal_ibfk_2` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_filesystemtimed` (
  `id` char(8) NOT NULL,
  `filesystem` char(8) NOT NULL,
  `timeperiod` int(11) NOT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filesystem_timeperiod` (`filesystem`,`timeperiod`),
  KEY `filesystem` (`filesystem`),
  CONSTRAINT `a2obj_apps_files_limits_filesystemtimed_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_timed` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_limits_filesystemtimed_ibfk_2` FOREIGN KEY (`filesystem`) REFERENCES `a2obj_apps_files_filesystem_fsmanager` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_filesystemtotal` (
  `id` char(8) NOT NULL,
  `filesystem` char(8) NOT NULL,
  `track_items` tinyint(1) DEFAULT NULL,
  `track_dlstats` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filesystem` (`filesystem`),
  CONSTRAINT `a2obj_apps_files_limits_filesystemtotal_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_total` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_limits_filesystemtotal_ibfk_2` FOREIGN KEY (`filesystem`) REFERENCES `a2obj_apps_files_filesystem_fsmanager` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_grouptimed` (
  `id` char(12) NOT NULL,
  `group` char(12) NOT NULL,
  `timeperiod` int(11) NOT NULL,
  `track_items` tinyint(2) DEFAULT NULL,
  `track_dlstats` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_timeperiod` (`group`,`timeperiod`),
  KEY `group` (`group`),
  CONSTRAINT `a2obj_apps_files_limits_grouptimed_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_timed` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_limits_grouptimed_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_grouptotal` (
  `id` char(12) NOT NULL,
  `group` char(12) NOT NULL,
  `emailshare` tinyint(1) DEFAULT NULL,
  `userstorage` tinyint(1) DEFAULT NULL,
  `track_items` tinyint(2) DEFAULT NULL,
  `track_dlstats` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group` (`group`),
  CONSTRAINT `a2obj_apps_files_limits_grouptotal_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_limits_total` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_limits_grouptotal_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_timed` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `max_stats_age` bigint(20) DEFAULT NULL,
  `limit_pubdownloads` int(11) DEFAULT NULL,
  `limit_bandwidth` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_timedstats` (
  `id` char(12) NOT NULL,
  `limit` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `date_timestart` bigint(20) NOT NULL,
  `iscurrent` tinyint(1) DEFAULT NULL,
  `count_size` bigint(20) NOT NULL DEFAULT 0,
  `count_items` int(11) NOT NULL DEFAULT 0,
  `count_shares` int(11) NOT NULL DEFAULT 0,
  `count_pubdownloads` int(11) NOT NULL DEFAULT 0,
  `count_bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `limit_timestart` (`limit`,`date_timestart`),
  UNIQUE KEY `limit_iscurrent` (`limit`,`iscurrent`),
  CONSTRAINT `a2obj_apps_files_limits_timedstats_ibfk_1` FOREIGN KEY (`limit`) REFERENCES `a2obj_apps_files_limits_timed` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_limits_total` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `date_download` double DEFAULT NULL,
  `date_upload` double DEFAULT NULL,
  `itemsharing` tinyint(1) DEFAULT NULL,
  `share2everyone` tinyint(1) DEFAULT NULL,
  `share2groups` tinyint(1) DEFAULT NULL,
  `publicupload` tinyint(1) DEFAULT NULL,
  `publicmodify` tinyint(1) DEFAULT NULL,
  `randomwrite` tinyint(1) DEFAULT NULL,
  `count_size` bigint(20) NOT NULL DEFAULT 0,
  `count_items` int(11) NOT NULL DEFAULT 0,
  `count_shares` int(11) NOT NULL DEFAULT 0,
  `limit_size` bigint(20) DEFAULT NULL,
  `limit_items` int(11) DEFAULT NULL,
  `limit_shares` int(11) DEFAULT NULL,
  `count_pubdownloads` int(11) NOT NULL DEFAULT 0,
  `count_bandwidth` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_rootfolder` (
  `id` char(16) NOT NULL,
  `owner` char(12) DEFAULT NULL,
  `filesystem` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_filesystem` (`owner`,`filesystem`),
  KEY `owner` (`owner`),
  KEY `filesystem` (`filesystem`),
  CONSTRAINT `a2obj_apps_files_rootfolder_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_folder` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_share` (
  `id` char(16) NOT NULL,
  `item` char(16) NOT NULL,
  `owner` char(12) NOT NULL,
  `dest` char(12) DEFAULT NULL,
  `label` text DEFAULT NULL,
  `authkey` text DEFAULT NULL,
  `password` text DEFAULT NULL,
  `date_created` double NOT NULL,
  `date_accessed` double DEFAULT NULL,
  `count_accessed` int(11) NOT NULL DEFAULT 0,
  `limit_accessed` int(11) DEFAULT NULL,
  `date_expires` double DEFAULT NULL,
  `read` tinyint(1) NOT NULL,
  `upload` tinyint(1) NOT NULL,
  `modify` tinyint(1) NOT NULL,
  `social` tinyint(1) NOT NULL,
  `reshare` tinyint(1) NOT NULL,
  `keepowner` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_owner_dest` (`item`,`owner`,`dest`),
  KEY `dest` (`dest`),
  KEY `owner` (`owner`),
  KEY `item` (`item`),
  CONSTRAINT `a2obj_apps_files_share_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_share_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_item` (`id`),
  CONSTRAINT `a2obj_apps_files_share_ibfk_3` FOREIGN KEY (`dest`) REFERENCES `a2obj_apps_accounts_policybase` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_ftp` (
  `id` char(8) NOT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `username` varbinary(255) DEFAULT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` tinyblob DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_ftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_local` (
  `id` char(8) NOT NULL,
  `path` text NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_local_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_s3` (
  `id` char(8) NOT NULL,
  `path` text NOT NULL,
  `endpoint` text NOT NULL,
  `path_style` tinyint(1) DEFAULT NULL,
  `port` smallint(6) DEFAULT NULL,
  `usetls` tinyint(1) DEFAULT NULL,
  `region` varchar(64) NOT NULL,
  `bucket` varchar(64) NOT NULL,
  `accesskey` varbinary(144) NOT NULL,
  `accesskey_nonce` binary(24) DEFAULT NULL,
  `secretkey` varbinary(56) DEFAULT NULL,
  `secretkey_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_s3_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_sftp` (
  `id` char(8) NOT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `hostkey` text NOT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `privkey` blob DEFAULT NULL,
  `keypass` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  `privkey_nonce` binary(24) DEFAULT NULL,
  `keypass_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_sftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_smb` (
  `id` char(8) NOT NULL,
  `path` text NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `workgroup` varchar(255) DEFAULT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_smb_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_storage` (
  `id` char(8) NOT NULL,
  `date_created` double NOT NULL,
  `filesystem` char(8) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filesystem` (`filesystem`),
  CONSTRAINT `a2obj_apps_files_storage_storage_ibfk_1` FOREIGN KEY (`filesystem`) REFERENCES `a2obj_apps_files_filesystem_fsmanager` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_storage_webdav` (
  `id` char(8) NOT NULL,
  `path` text NOT NULL,
  `endpoint` text NOT NULL,
  `username` varbinary(255) NOT NULL,
  `password` tinyblob DEFAULT NULL,
  `username_nonce` binary(24) DEFAULT NULL,
  `password_nonce` binary(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_files_storage_webdav_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_storage_storage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_subitem` (
  `id` char(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `parent` char(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_parent` (`name`,`parent`),
  KEY `parent` (`parent`),
  CONSTRAINT `a2obj_apps_files_subitem_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_files_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_files_subitem_ibfk_2` FOREIGN KEY (`parent`) REFERENCES `a2obj_apps_files_folder` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_files_tag` (
  `id` char(16) NOT NULL,
  `owner` char(12) NOT NULL,
  `item` char(16) NOT NULL,
  `tag` varchar(127) NOT NULL,
  `date_created` double NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_tag` (`item`,`tag`),
  KEY `owner` (`owner`),
  KEY `item` (`item`),
  CONSTRAINT `a2obj_apps_files_tag_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_files_tag_ibfk_2` FOREIGN KEY (`item`) REFERENCES `a2obj_apps_files_item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

