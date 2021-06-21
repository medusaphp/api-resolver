CREATE TABLE {{TABLE_PREFIX}}userpermission
(
    userpermission_id            INT unsigned NOT NULL AUTO_INCREMENT,
    userpermission_account_id INT unsigned NOT NULL,
    userpermission_service    VARCHAR(255) NOT NULL,
    userpermission_enabled    BOOL     DEFAULT FALSE,
    userpermission_updated    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    userpermission_created    DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`userpermission_id`),
    UNIQUE (`userpermission_account_id`, `userpermission_service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;