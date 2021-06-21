CREATE TABLE {{TABLE_PREFIX}}webadminaccount
(
    webadminaccount_id       INT unsigned NOT NULL AUTO_INCREMENT,
    webadminaccount_username VARCHAR(255) NOT NULL,
    webadminaccount_password VARCHAR(255) NOT NULL,
    webadminaccount_enabled  BOOL     DEFAULT FALSE,
    webadminaccount_updated  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    webadminaccount_created  DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`webadminaccount_id`),
    UNIQUE (`webadminaccount_username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO {{TABLE_PREFIX}}webadminaccount (webadminaccount_username, webadminaccount_password, webadminaccount_enabled)
VALUES ("admin", "$2y$10$xQwvkmOBTagW7WNwijfJ6OTd8Yli/mGktkSt16eqpG/vitfHIGtWW", true);