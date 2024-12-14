
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_apps_core_actionlog` (
  `id` char(20) NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `account` char(12) DEFAULT NULL,
  `sudouser` char(12) DEFAULT NULL,
  `client` char(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `a2obj_apps_core_actionlog_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_config` (
  `id` char(1) NOT NULL,
  `version` varchar(255) NOT NULL,
  `datadir` text DEFAULT NULL,
  `apps` text NOT NULL,
  `date_created` double NOT NULL,
  `actionlog_db` tinyint(2) NOT NULL,
  `actionlog_file` tinyint(1) NOT NULL,
  `actionlog_details` tinyint(2) NOT NULL,
  `debug` tinyint(2) NOT NULL,
  `debug_http` tinyint(1) NOT NULL,
  `debug_dblog` tinyint(1) NOT NULL,
  `debug_filelog` tinyint(1) NOT NULL,
  `metrics` tinyint(2) NOT NULL,
  `metrics_dblog` tinyint(1) NOT NULL,
  `metrics_filelog` tinyint(1) NOT NULL,
  `read_only` tinyint(1) NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `email` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_emailer` (
  `id` char(4) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `hosts` text DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `from_address` varchar(255) NOT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `use_reply` tinyint(1) DEFAULT NULL,
  `date_created` double NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_errors_errorlog` (
  `id` char(12) NOT NULL,
  `time` double NOT NULL,
  `addr` varchar(255) NOT NULL,
  `agent` text NOT NULL,
  `app` varchar(255) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `code` int(11) NOT NULL,
  `file` text NOT NULL,
  `message` text NOT NULL,
  `trace_basic` longtext DEFAULT NULL,
  `trace_full` longtext DEFAULT NULL,
  `objects` longtext DEFAULT NULL,
  `queries` longtext DEFAULT NULL,
  `params` longtext DEFAULT NULL,
  `hints` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `code` (`code`),
  KEY `app` (`app`(191)),
  KEY `action` (`action`(191)),
  KEY `addr` (`addr`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_logging_actionlog` (
  `id` char(20) NOT NULL,
  `time` double NOT NULL,
  `addr` varchar(255) NOT NULL,
  `agent` text NOT NULL,
  `errcode` int(11) DEFAULT NULL,
  `errtext` text DEFAULT NULL,
  `app` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `authuser` varchar(255) DEFAULT NULL,
  `params` text DEFAULT NULL,
  `files` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `addr` (`addr`),
  KEY `app_action` (`app`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `a2obj_core_logging_metricslog` (
  `id` char(20) NOT NULL,
  `actionlog` char(20) DEFAULT NULL,
  `date_created` double NOT NULL,
  `peak_memory` int(11) NOT NULL,
  `nincludes` smallint(6) NOT NULL,
  `nobjects` int(11) NOT NULL,
  `init_db_reads` int(11) NOT NULL,
  `init_db_read_time` double NOT NULL,
  `init_db_writes` int(11) NOT NULL,
  `init_db_write_time` double NOT NULL,
  `init_code_time` double NOT NULL,
  `init_autoloader_time` double NOT NULL,
  `init_total_time` double NOT NULL,
  `app` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `action_db_reads` int(11) NOT NULL,
  `action_db_read_time` double NOT NULL,
  `action_db_writes` int(11) NOT NULL,
  `action_db_write_time` double NOT NULL,
  `action_code_time` double NOT NULL,
  `action_autoloader_time` double NOT NULL,
  `action_total_time` double NOT NULL,
  `commit_db_reads` int(11) DEFAULT NULL,
  `commit_db_read_time` double DEFAULT NULL,
  `commit_db_writes` int(11) DEFAULT NULL,
  `commit_db_write_time` double DEFAULT NULL,
  `commit_code_time` double DEFAULT NULL,
  `commit_autoloader_time` double DEFAULT NULL,
  `commit_total_time` double DEFAULT NULL,
  `db_reads` int(11) NOT NULL,
  `db_read_time` double NOT NULL,
  `db_writes` int(11) NOT NULL,
  `db_write_time` double NOT NULL,
  `code_time` double NOT NULL,
  `autoloader_time` double NOT NULL,
  `total_time` double NOT NULL,
  `gcstats` text DEFAULT NULL,
  `rusage` text DEFAULT NULL,
  `includes` longtext DEFAULT NULL,
  `objects` longtext DEFAULT NULL,
  `queries` longtext DEFAULT NULL,
  `debughints` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `actionlog` (`actionlog`) USING BTREE,
  KEY `app_action` (`app`,`action`),
  CONSTRAINT `a2obj_core_logging_metricslog_ibfk_1` FOREIGN KEY (`actionlog`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

