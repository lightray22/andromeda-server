CREATE TABLE `a2obj_apps_accounts_account` (
  `id` char(12) NOT NULL
,  `username` varchar(127) NOT NULL
,  `fullname` varchar(255) DEFAULT NULL
,  `date_passwordset` double DEFAULT NULL
,  `date_loggedon` double DEFAULT NULL
,  `date_active` double DEFAULT NULL
,  `master_key` binary(48) DEFAULT NULL
,  `master_nonce` binary(24) DEFAULT NULL
,  `master_salt` binary(16) DEFAULT NULL
,  `password` text DEFAULT NULL
,  `authsource` char(8) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`username`)
,  CONSTRAINT `a2obj_apps_accounts_account_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_policybase` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_accounts_account_ibfk_2` FOREIGN KEY (`authsource`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`)
);
CREATE TABLE `a2obj_apps_accounts_actionlog` (
  `id` char(20) NOT NULL
,  `admin` integer DEFAULT NULL
,  `account` char(12) DEFAULT NULL
,  `sudouser` char(12) DEFAULT NULL
,  `client` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_actionlog_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_core_logging_actionlog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_accounts_authsource_external` (
  `id` char(8) NOT NULL
,  `enabled` integer NOT NULL
,  `description` text DEFAULT NULL
,  `date_created` double DEFAULT NULL
,  `default_group` char(12) DEFAULT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_authsource_external_ibfk_1` FOREIGN KEY (`default_group`) REFERENCES `a2obj_apps_accounts_group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_accounts_authsource_ftp` (
  `id` char(8) NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `implssl` integer NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_authsource_ftp_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_accounts_authsource_imap` (
  `id` char(8) NOT NULL
,  `protocol` integer NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `port` integer DEFAULT NULL
,  `implssl` integer NOT NULL
,  `secauth` integer NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_authsource_imap_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_accounts_authsource_ldap` (
  `id` char(8) NOT NULL
,  `hostname` varchar(255) NOT NULL
,  `secure` integer NOT NULL
,  `userprefix` varchar(255) NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_authsource_ldap_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_accounts_config` (
  `id` char(1) NOT NULL
,  `version` varchar(255) NOT NULL
,  `createaccount` integer NOT NULL
,  `usernameiscontact` integer NOT NULL
,  `requirecontact` integer NOT NULL
,  `default_group` char(12) DEFAULT NULL
,  `default_auth` char(8) DEFAULT NULL
,  `date_created` double NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_config_ibfk_1` FOREIGN KEY (`default_group`) REFERENCES `a2obj_apps_accounts_group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
,  CONSTRAINT `a2obj_apps_accounts_config_ibfk_2` FOREIGN KEY (`default_auth`) REFERENCES `a2obj_apps_accounts_authsource_external` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_accounts_group` (
  `id` char(12) NOT NULL
,  `name` varchar(127) NOT NULL
,  `priority` integer NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`name`)
,  CONSTRAINT `a2obj_apps_accounts_group_ibfk_1` FOREIGN KEY (`id`) REFERENCES `a2obj_apps_accounts_policybase` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE `a2obj_apps_accounts_groupjoin` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `account` char(12) NOT NULL
,  `group` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`account`,`group`)
,  CONSTRAINT `a2obj_apps_accounts_groupjoin_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_accounts_groupjoin_ibfk_2` FOREIGN KEY (`group`) REFERENCES `a2obj_apps_accounts_group` (`id`)
);
CREATE TABLE `a2obj_apps_accounts_policybase` (
  `id` char(12) NOT NULL
,  `comment` text DEFAULT NULL
,  `date_created` double NOT NULL
,  `date_modified` double DEFAULT NULL
,  `admin` integer DEFAULT NULL
,  `disabled` integer DEFAULT NULL
,  `forcetf` integer DEFAULT NULL
,  `allowcrypto` integer DEFAULT NULL
,  `accountsearch` integer DEFAULT NULL
,  `groupsearch` integer DEFAULT NULL
,  `userdelete` integer DEFAULT NULL
,  `limit_sessions` integer DEFAULT NULL
,  `limit_contacts` integer DEFAULT NULL
,  `limit_recoverykeys` integer DEFAULT NULL
,  `session_timeout` integer DEFAULT NULL
,  `client_timeout` integer DEFAULT NULL
,  `max_password_age` integer DEFAULT NULL
,  PRIMARY KEY (`id`)
);
CREATE TABLE `a2obj_apps_accounts_resource_client` (
  `id` char(12) NOT NULL
,  `name` varchar(255) DEFAULT NULL
,  `authkey` text NOT NULL
,  `lastaddr` varchar(255) NOT NULL
,  `useragent` text NOT NULL
,  `date_created` double NOT NULL
,  `date_active` double DEFAULT NULL
,  `date_loggedon` double DEFAULT NULL
,  `account` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_resource_client_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
);
CREATE TABLE `a2obj_apps_accounts_resource_contact` (
  `id` char(12) NOT NULL
,  `type` integer NOT NULL
,  `address` varchar(127) NOT NULL
,  `valid` integer NOT NULL DEFAULT 0
,  `usefrom` integer DEFAULT NULL
,  `public` integer NOT NULL DEFAULT 0
,  `authkey` text DEFAULT NULL
,  `date_created` double NOT NULL
,  `account` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`type`,`address`)
,  UNIQUE (`usefrom`,`account`,`type`)
,  CONSTRAINT `a2obj_apps_accounts_resource_contact_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
);
CREATE TABLE `a2obj_apps_accounts_resource_recoverykey` (
  `id` char(12) NOT NULL
,  `authkey` text NOT NULL
,  `date_created` double NOT NULL
,  `master_key` binary(48) DEFAULT NULL
,  `master_nonce` binary(24) DEFAULT NULL
,  `master_salt` binary(16) DEFAULT NULL
,  `account` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_resource_recoverykey_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
);
CREATE TABLE `a2obj_apps_accounts_resource_registerallow` (
  `id` char(12) NOT NULL
,  `date_created` double NOT NULL
,  `type` integer NOT NULL
,  `value` varchar(127) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`type`,`value`)
);
CREATE TABLE `a2obj_apps_accounts_resource_session` (
  `id` char(12) NOT NULL
,  `authkey` text NOT NULL
,  `date_active` double DEFAULT NULL
,  `date_created` double NOT NULL
,  `master_key` binary(48) DEFAULT NULL
,  `master_nonce` binary(24) DEFAULT NULL
,  `master_salt` binary(16) DEFAULT NULL
,  `account` char(12) NOT NULL
,  `client` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  UNIQUE (`client`)
,  CONSTRAINT `a2obj_apps_accounts_resource_session_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
,  CONSTRAINT `a2obj_apps_accounts_resource_session_ibfk_2` FOREIGN KEY (`client`) REFERENCES `a2obj_apps_accounts_resource_client` (`id`)
);
CREATE TABLE `a2obj_apps_accounts_resource_twofactor` (
  `id` char(12) NOT NULL
,  `comment` text DEFAULT NULL
,  `secret` varbinary(48) NOT NULL
,  `nonce` binary(24) DEFAULT NULL
,  `valid` integer NOT NULL DEFAULT 0
,  `date_created` double NOT NULL
,  `date_used` double DEFAULT NULL
,  `account` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_resource_twofactor_ibfk_1` FOREIGN KEY (`account`) REFERENCES `a2obj_apps_accounts_account` (`id`)
);
CREATE TABLE `a2obj_apps_accounts_resource_usedtoken` (
  `id` char(12) NOT NULL
,  `code` char(6) NOT NULL
,  `date_created` double NOT NULL
,  `twofactor` char(12) NOT NULL
,  PRIMARY KEY (`id`)
,  CONSTRAINT `a2obj_apps_accounts_resource_usedtoken_ibfk_1` FOREIGN KEY (`twofactor`) REFERENCES `a2obj_apps_accounts_resource_twofactor` (`id`)
);
CREATE INDEX "idx_a2obj_apps_accounts_resource_client_account" ON "a2obj_apps_accounts_resource_client" (`account`);
CREATE INDEX "idx_a2obj_apps_accounts_resource_client_date_active_account" ON "a2obj_apps_accounts_resource_client" (`date_active`,`account`);
CREATE INDEX "idx_a2obj_apps_accounts_resource_usedtoken_date_created" ON "a2obj_apps_accounts_resource_usedtoken" (`date_created`);
CREATE INDEX "idx_a2obj_apps_accounts_resource_usedtoken_twofactor" ON "a2obj_apps_accounts_resource_usedtoken" (`twofactor`);
CREATE INDEX "idx_a2obj_apps_accounts_resource_contact_address" ON "a2obj_apps_accounts_resource_contact" (`address`);
CREATE INDEX "idx_a2obj_apps_accounts_resource_contact_account" ON "a2obj_apps_accounts_resource_contact" (`account`);
CREATE INDEX "idx_a2obj_apps_accounts_actionlog_account" ON "a2obj_apps_accounts_actionlog" (`account`);
CREATE INDEX "idx_a2obj_apps_accounts_resource_session_account" ON "a2obj_apps_accounts_resource_session" (`account`);
CREATE INDEX "idx_a2obj_apps_accounts_resource_session_date_active_account" ON "a2obj_apps_accounts_resource_session" (`date_active`,`account`);
CREATE INDEX "idx_a2obj_apps_accounts_resource_twofactor_account" ON "a2obj_apps_accounts_resource_twofactor" (`account`);
CREATE INDEX "idx_a2obj_apps_accounts_authsource_external_default_group" ON "a2obj_apps_accounts_authsource_external" (`default_group`);
CREATE INDEX "idx_a2obj_apps_accounts_groupjoin_accounts" ON "a2obj_apps_accounts_groupjoin" (`account`);
CREATE INDEX "idx_a2obj_apps_accounts_groupjoin_groups" ON "a2obj_apps_accounts_groupjoin" (`group`);
CREATE INDEX "idx_a2obj_apps_accounts_config_default_group" ON "a2obj_apps_accounts_config" (`default_group`);
CREATE INDEX "idx_a2obj_apps_accounts_config_default_auth" ON "a2obj_apps_accounts_config" (`default_auth`);
CREATE INDEX "idx_a2obj_apps_accounts_account_fullname" ON "a2obj_apps_accounts_account" (`fullname`);
CREATE INDEX "idx_a2obj_apps_accounts_account_authsource" ON "a2obj_apps_accounts_account" (`authsource`);
CREATE INDEX "idx_a2obj_apps_accounts_resource_recoverykey_account" ON "a2obj_apps_accounts_resource_recoverykey" (`account`);
