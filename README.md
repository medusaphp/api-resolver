#### account
``` sql
CREATE TABLE account
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

INSERT INTO account (account_username, account_password, account_enabled)
VALUES ("__NO_USER__", "__NO_USER__", true);

```
#### userpermission
``` sql
CREATE TABLE userpermission
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
```
#### accountip
``` sql
CREATE TABLE accountip
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
``` 

#### config example
``` json
{
  "internalNetworks": [
    "192.168.178.0/24",
    "192.168.168.0/24",
  ],
  "debugMode": true,
  "database": {
    "tablePrefix": "apiresolver_",
    "user": "MYDBUSER",
    "pass": "MYDBPASS_PLAINTEXT",
    "db": "DATABASE",
    "host": "loclahost",
    "port": 3306,
    "charset": "utf8mb4"
  }
}
```

## NGINX

### server
``` apacheconf
server {
    #api.example.com/<project>/<controller>/<controller-version>/ (api.example.com/main/Foo/Bar/0.0.1/)
    server_name api.example.com;

    listen 80;

    location /index.php {
        fastcgi_split_path_info ^(.+?\.php)(/.*)$;
        fastcgi_pass unix:/tmp/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param API_RESOLVER /tmp/nginx/api-resolver.sock;
        fastcgi_param API_SERVICES_LOCATION /var/www/my_services;
        include fastcgi_params;
        fastcgi_connect_timeout 30;
        fastcgi_read_timeout 600;
        fastcgi_send_timeout 600;
    }

    root /var/www/api_resolver/application/app/public;
    index index.php;

    access_log /var/log/nginx/access-apiserver.log;
    error_log /var/log/nginx/error-apiserver.log;

    location / {
        try_files $uri /index.php?$args;
    }
}
``` 

### entry points
``` apacheconf
server {
    listen unix:/tmp/nginx/api-resolver.sock;
    location /php8.0/ {
        rewrite /php8.0/(.*) /$1 break;
        proxy_redirect off;
        proxy_set_header Host $host;
        proxy_pass http://unix:/tmp/nginx/api-resolver-php8.0.sock;
    }
}

server {
    listen unix:/tmp/nginx/api-resolver-php8.0.sock;

    location ~ [^/]\.php(/|$) {

        if (!-f $request_filename) {
            return 404;
        }

        fastcgi_split_path_info ^(.+?\.php)(/.*)$;
        fastcgi_pass unix:/tmp/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_connect_timeout 30;
        fastcgi_read_timeout 600;
        fastcgi_send_timeout 600;

    }

    root /var/www/my_services/${http_x_service_resolver}/public;
    index index.php;

    access_log /var/log/nginx/access-apiphp8.log;
    error_log /var/log/nginx/error-apiphp8.log;
    location / {
        try_files $uri /index.php?$args;
    }
}

```
