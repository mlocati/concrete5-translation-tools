CREATE TABLE IF NOT EXISTS C5TTPackage (
	pHandle varchar(50) NOT NULL COMMENT 'Handle',
	pName varchar(100) NOT NULL COMMENT 'Name',
	pSourceUrl varchar(250) DEFAULT NULL COMMENT 'URL to source code',
	pDisabled tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Disabled?',
	PRIMARY KEY (pHandle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='List of packages';

CREATE TABLE IF NOT EXISTS C5TTUser (
	uId int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'User identifier',
	uUsername varchar(50) NOT NULL COMMENT 'Username',
	uPassword varchar(50) NOT NULL COMMENT 'Password',
	uDisabled tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Disabled?',
	uName varchar(50) NOT NULL COMMENT 'Name',
	PRIMARY KEY (uId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Operators list';
