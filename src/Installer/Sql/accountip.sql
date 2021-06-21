CREATE TABLE {{TABLE_PREFIX}}accountip
(
    accountip_id         INT unsigned NOT NULL AUTO_INCREMENT,
    accountip_account_id INT unsigned NOT NULL,
    accountip_ip         VARCHAR(15) NOT NULL,
    accountip_enabled    BOOL     DEFAULT FALSE,
    accountip_updated    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    accountip_created    DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`accountip_id`),
    UNIQUE (`accountip_account_id`, `accountip_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;