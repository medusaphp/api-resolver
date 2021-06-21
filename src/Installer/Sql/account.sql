CREATE TABLE {{TABLE_PREFIX}}account
(
    account_id       INT unsigned NOT NULL AUTO_INCREMENT,
    account_username VARCHAR(255) NOT NULL,
    account_password VARCHAR(255) NOT NULL,
    account_enabled  BOOL     DEFAULT FALSE,
    account_updated  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    account_created  DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`account_id`),
    UNIQUE (`account_username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;