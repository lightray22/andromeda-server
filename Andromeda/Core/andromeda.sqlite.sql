CREATE TABLE `a2obj_apps_core_actionlog` (
  `id` char(20) NOT NULL
,  `admin` integer DEFAULT NULL
,  `account` char(12) DEFAULT NULL
,  `sudouser` char(12) DEFAULT NULL
,  `client` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_core_actionlog_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_core_config` (
  `id` char(1) NOT NULL
,  `version` varchar(255) NOT NULL
,  `datadir` text DEFAULT NULL
,  `apps` text NOT NULL
,  `date_created` double NOT NULL
,  `actionlog_db` integer NOT NULL
,  `actionlog_file` integer NOT NULL
,  `actionlog_details` integer NOT NULL
,  `debug` integer NOT NULL
,  `debug_http` integer NOT NULL
,  `debug_dblog` integer NOT NULL
,  `debug_filelog` integer NOT NULL
,  `metrics` integer NOT NULL
,  `metrics_dblog` integer NOT NULL
,  `metrics_filelog` integer NOT NULL
,  `read_only` integer NOT NULL
,  `enabled` integer NOT NULL
,  `email` integer NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_emailer` (
  `id` char(8) NOT NULL
,  `type` integer NOT NULL
,  `hosts` text DEFAULT NULL
,  `username` varchar(255) DEFAULT NULL
,  `password` text DEFAULT NULL
,  `from_address` varchar(255) NOT NULL
,  `from_name` varchar(255) DEFAULT NULL
,  `use_reply` integer DEFAULT NULL
,  `date_created` double NOT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_errors_errorlog` (
  `id` char(12) NOT NULL
,  `time` double NOT NULL
,  `addr` varchar(255) NOT NULL
,  `agent` text NOT NULL
,  `app` varchar(255) DEFAULT NULL
,  `action` varchar(255) DEFAULT NULL
,  `code` integer NOT NULL
,  `file` text NOT NULL
,  `message` text NOT NULL
,  `trace_basic` longtext DEFAULT NULL
,  `trace_full` longtext DEFAULT NULL
,  `objects` longtext DEFAULT NULL
,  `queries` longtext DEFAULT NULL
,  `params` longtext DEFAULT NULL
,  `hints` longtext DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_actionlog` (
  `id` char(20) NOT NULL
,  `time` double NOT NULL
,  `addr` varchar(255) NOT NULL
,  `agent` text NOT NULL
,  `errcode` integer DEFAULT NULL
,  `errtext` text DEFAULT NULL
,  `app` varchar(255) NOT NULL
,  `action` varchar(255) NOT NULL
,  `authuser` varchar(255) DEFAULT NULL
,  `params` text DEFAULT NULL
,  `files` text DEFAULT NULL
,  `details` text DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_core_logging_metricslog` (
  `id` char(20) NOT NULL
,  `actionlog` char(20) DEFAULT NULL
,  `date_created` double NOT NULL
,  `peak_memory` integer NOT NULL
,  `nincludes` integer NOT NULL
,  `nobjects` integer NOT NULL
,  `init_db_reads` integer NOT NULL
,  `init_db_read_time` double NOT NULL
,  `init_db_writes` integer NOT NULL
,  `init_db_write_time` double NOT NULL
,  `init_code_time` double NOT NULL
,  `init_autoloader_time` double NOT NULL
,  `init_total_time` double NOT NULL
,  `app` varchar(255) NOT NULL
,  `action` varchar(255) NOT NULL
,  `action_db_reads` integer NOT NULL
,  `action_db_read_time` double NOT NULL
,  `action_db_writes` integer NOT NULL
,  `action_db_write_time` double NOT NULL
,  `action_code_time` double NOT NULL
,  `action_autoloader_time` double NOT NULL
,  `action_total_time` double NOT NULL
,  `commit_db_reads` integer DEFAULT NULL
,  `commit_db_read_time` double DEFAULT NULL
,  `commit_db_writes` integer DEFAULT NULL
,  `commit_db_write_time` double DEFAULT NULL
,  `commit_code_time` double DEFAULT NULL
,  `commit_autoloader_time` double DEFAULT NULL
,  `commit_total_time` double DEFAULT NULL
,  `db_reads` integer NOT NULL
,  `db_read_time` double NOT NULL
,  `db_writes` integer NOT NULL
,  `db_write_time` double NOT NULL
,  `code_time` double NOT NULL
,  `autoloader_time` double NOT NULL
,  `total_time` double NOT NULL
,  `gcstats` text DEFAULT NULL
,  `rusage` text DEFAULT NULL
,  `includes` longtext DEFAULT NULL
,  `objects` longtext DEFAULT NULL
,  `queries` longtext DEFAULT NULL
,  `debughints` longtext DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`actionlog`)
,  CONSTRAINT `a2obj_core_logging_metricslog_ibfk_1` FOREIGN KEY (`actionlog`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE INDEX "idx_a2obj_core_errors_errorlog_time" ON "a2obj_core_errors_errorlog" (`time`);
CREATE INDEX "idx_a2obj_core_errors_errorlog_code" ON "a2obj_core_errors_errorlog" (`code`);
CREATE INDEX "idx_a2obj_core_errors_errorlog_app" ON "a2obj_core_errors_errorlog" (`app`);
CREATE INDEX "idx_a2obj_core_errors_errorlog_action" ON "a2obj_core_errors_errorlog" (`action`);
CREATE INDEX "idx_a2obj_core_errors_errorlog_addr" ON "a2obj_core_errors_errorlog" (`addr`);
CREATE INDEX "idx_a2obj_core_logging_actionlog_time" ON "a2obj_core_logging_actionlog" (`time`);
CREATE INDEX "idx_a2obj_core_logging_actionlog_addr" ON "a2obj_core_logging_actionlog" (`addr`);
CREATE INDEX "idx_a2obj_core_logging_actionlog_app_action" ON "a2obj_core_logging_actionlog" (`app`,`action`);
CREATE INDEX "idx_a2obj_core_logging_metricslog_app_action" ON "a2obj_core_logging_metricslog" (`app`,`action`);
