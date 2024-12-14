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
CREATE TABLE `a2obj_apps_accounts_account` (
  `id` char(12) NOT NULL,
  `username` varchar(127) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `date_passwordset` double DEFAULT NULL,
  `date_loggedon` double DEFAULT NULL,
  `date_active` double DEFAULT NULL,
  `master_key` binary(48) DEFAULT NULL,
  `master_nonce` binary(24) DEFAULT NULL,
  `master_salt` binary(16) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `authsource` char(8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fullname` (`fullname`),
  KEY `authsource` (`authsource`),
  CONSTRAINT `a2obj_apps_accounts_account_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_policybase` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_accounts_account_ibfk_2` FOREIGN KEY (`authsource`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_actionlog` (
  `id` char(20) NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `account` char(12) DEFAULT NULL,
  `sudouser` char(12) DEFAULT NULL,
  `client` char(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`account`),
  CONSTRAINT `a2obj_apps_accounts_actionlog_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_authsource_external` (
  `id` char(8) NOT NULL,
  `enabled` tinyint(2) NOT NULL,
  `description` text DEFAULT NULL,
  `date_created` double DEFAULT NULL,
  `default_group` char(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `default_group` (`default_group`),
  CONSTRAINT `a2obj_apps_accounts_authsource_external_ibfk_1` FOREIGN KEY (`default_group`) REFERENCES `a2obj_apps_accounts_group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_authsource_ftp` (
  `id` char(8) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_accounts_authsource_ftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_authsource_imap` (
  `id` char(8) NOT NULL,
  `protocol` tinyint(2) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `port` smallint(6) DEFAULT NULL,
  `implssl` tinyint(1) NOT NULL,
  `secauth` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_accounts_authsource_imap_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_authsource_ldap` (
  `id` char(8) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `secure` tinyint(1) NOT NULL,
  `userprefix` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_accounts_authsource_ldap_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_config` (
  `id` char(1) NOT NULL,
  `version` varchar(255) NOT NULL,
  `createaccount` tinyint(2) NOT NULL,
  `usernameiscontact` tinyint(1) NOT NULL,
  `requirecontact` tinyint(2) NOT NULL,
  `default_group` char(12) DEFAULT NULL,
  `default_auth` char(8) DEFAULT NULL,
  `date_created` double NOT NULL,
  PRIMARY KEY (`id`),
  KEY `default_group` (`default_group`),
  KEY `default_auth` (`default_auth`),
  CONSTRAINT `a2obj_apps_accounts_config_ibfk_1` FOREIGN KEY (`default_group`) REFERENCES `a2obj_apps_accounts_group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `a2obj_apps_accounts_config_ibfk_2` FOREIGN KEY (`default_auth`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_group` (
  `id` char(12) NOT NULL,
  `name` varchar(127) NOT NULL,
  `priority` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  CONSTRAINT `a2obj_apps_accounts_group_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_policybase` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_groupjoin` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `account` char(12) NOT NULL,
  `group` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_group` (`account`,`group`),
  KEY `accounts` (`account`),
  KEY `groups` (`group`),
  CONSTRAINT `a2obj_apps_accounts_groupjoin_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_accounts_groupjoin_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_policybase` (
  `id` char(12) NOT NULL,
  `comment` text DEFAULT NULL,
  `date_created` double NOT NULL,
  `date_modified` double DEFAULT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `disabled` tinyint(1) DEFAULT NULL,
  `forcetf` tinyint(1) DEFAULT NULL,
  `allowcrypto` tinyint(1) DEFAULT NULL,
  `accountsearch` tinyint(4) DEFAULT NULL,
  `groupsearch` tinyint(4) DEFAULT NULL,
  `userdelete` tinyint(1) DEFAULT NULL,
  `limit_sessions` tinyint(4) DEFAULT NULL,
  `limit_contacts` tinyint(4) DEFAULT NULL,
  `limit_recoverykeys` tinyint(4) DEFAULT NULL,
  `session_timeout` bigint(20) DEFAULT NULL,
  `client_timeout` bigint(20) DEFAULT NULL,
  `max_password_age` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_resource_client` (
  `id` char(12) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `authkey` text NOT NULL,
  `lastaddr` varchar(255) NOT NULL,
  `useragent` text NOT NULL,
  `date_created` double NOT NULL,
  `date_active` double DEFAULT NULL,
  `date_loggedon` double DEFAULT NULL,
  `account` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`account`),
  KEY `date_active_account` (`date_active`,`account`),
  CONSTRAINT `a2obj_apps_accounts_resource_client_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_resource_contact` (
  `id` char(12) NOT NULL,
  `type` tinyint(2) NOT NULL,
  `address` varchar(127) NOT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT 0,
  `usefrom` tinyint(1) DEFAULT NULL,
  `public` tinyint(1) NOT NULL DEFAULT 0,
  `authkey` text DEFAULT NULL,
  `date_created` double NOT NULL,
  `account` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_address` (`type`,`address`),
  UNIQUE KEY `account_type_from` (`usefrom`,`account`,`type`) USING BTREE,
  KEY `address` (`address`),
  KEY `account` (`account`),
  CONSTRAINT `a2obj_apps_accounts_resource_contact_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_resource_recoverykey` (
  `id` char(12) NOT NULL,
  `authkey` text NOT NULL,
  `date_created` double NOT NULL,
  `master_key` binary(48) DEFAULT NULL,
  `master_nonce` binary(24) DEFAULT NULL,
  `master_salt` binary(16) DEFAULT NULL,
  `account` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`account`),
  CONSTRAINT `a2obj_apps_accounts_resource_recoverykey_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_resource_registerallow` (
  `id` char(12) NOT NULL,
  `date_created` double NOT NULL,
  `type` tinyint(2) NOT NULL,
  `value` varchar(127) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_resource_session` (
  `id` char(12) NOT NULL,
  `authkey` text NOT NULL,
  `date_active` double DEFAULT NULL,
  `date_created` double NOT NULL,
  `master_key` binary(48) DEFAULT NULL,
  `master_nonce` binary(24) DEFAULT NULL,
  `master_salt` binary(16) DEFAULT NULL,
  `account` char(12) NOT NULL,
  `client` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client` (`client`),
  KEY `account` (`account`),
  KEY `date_active_account` (`date_active`,`account`),
  CONSTRAINT `a2obj_apps_accounts_resource_session_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`),
  CONSTRAINT `a2obj_apps_accounts_resource_session_ibfk_2` FOREIGN KEY (`client`) REFERENCES `a2obj_apps_accounts_resource_client` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_resource_twofactor` (
  `id` char(12) NOT NULL,
  `comment` text DEFAULT NULL,
  `secret` varbinary(48) NOT NULL,
  `nonce` binary(24) DEFAULT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT 0,
  `date_created` double NOT NULL,
  `date_used` double DEFAULT NULL,
  `account` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account` (`account`),
  CONSTRAINT `a2obj_apps_accounts_resource_twofactor_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_accounts_resource_usedtoken` (
  `id` char(12) NOT NULL,
  `code` char(6) NOT NULL,
  `date_created` double NOT NULL,
  `twofactor` char(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `date_created` (`date_created`),
  KEY `twofactor` (`twofactor`),
  CONSTRAINT `a2obj_apps_accounts_resource_usedtoken_ibfk_1` FOREIGN KEY (`twofactor`) REFERENCES `a2obj_apps_accounts_resource_twofactor` (`id`)
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

