# Maildiscover service

## Intended audience/users
* Sysadmins hosting their own mailservers
* Sysadmins hosting Nextcloud or other CardDAV/CalDAV servers
* Sysadmins having a Php stack available in their webserver

## Introduction
After trying several solutions for hosting and serving config files that allow mailusers to have their mailclients automatically setup their mail connections, I was frustrated with the complexity and lack of support for environments I feel comfortable working with (basically Php and nginx). Since my webhosting stack also includes Nextcloud and being an Apple user, I also wanted to add support for CardDAV and CalDAV in the Apple profiles. 

## nginx config 
There are lots of possibilities to configure your webserver, here is how I did it for two domains (`autodiscover.example.com` and `autoconfig.example.com`):

```nginx
server {
    server_name autodiscover.example.com ;
    ssl_certificate /etc/letsencrypt/live/autodiscover.example.com/fullchain.pem; 
    ssl_certificate_key /etc/letsencrypt/live/autodiscover.example.com/privkey.pem; 
    include snippets/autodiscover.conf ;
}

server {
    server_name autoconfig.example.com ;
    ssl_certificate /etc/letsencrypt/live/autoconfig.example.com/fullchain.pem; 
    ssl_certificate_key /etc/letsencrypt/live/autoconfig.example.com/privkey.pem; 
    include snippets/autodiscover.conf ;
}

server {
    if ($host ~ auto(discover|config)\.example\.com) {
        return 301 https://$host$request_uri;
    }


    listen 80 ;
    listen [::]:80 ;

    server_name ~auto(discover|config)\.example\.com;
    return 404;
}
```

The file `snippets/autodiscover.conf` contains this:

```nginx
root /opt/maildiscover ;
index index.php ;

access_log /var/log/nginx/autodiscover.access.log ;
error_log  /var/log/nginx/autodiscover.error.log error ;

location / {
    try_files $uri $uri/ /index.php$is_args$args;
    fastcgi_split_path_info ^(.+?\.php)(/.*)$;
    set $path_info $fastcgi_path_info;
    fastcgi_param PATH_INFO $path_info;
    fastcgi_index index.php;
    include fastcgi.conf;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_param   SCRIPT_FILENAME  $document_root/index.php;
}

listen [::]:443 ssl; # managed by Certbot
listen 443 ssl; # managed by Certbot
include /etc/letsencrypt/options-ssl-nginx.conf;
ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
```
