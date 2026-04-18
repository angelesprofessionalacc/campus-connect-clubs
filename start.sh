#!/bin/sh

cat > /etc/nginx/sites-available/default <<EOF
server {
    listen ${PORT:-80};
    root /var/www/html;
    index index.html index.php;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location = /api/login {
        rewrite ^ /auth.php?action=login last;
    }
    location = /api/login_student {
        rewrite ^ /auth.php?action=login_student last;
    }
    location = /api/logout {
        rewrite ^ /auth.php?action=logout last;
    }
    location = /api/validate {
        rewrite ^ /auth.php?action=validate_session last;
    }
    location = /api/register_admin {
        rewrite ^ /auth.php?action=register_admin last;
    }
    location = /api/register_student {
        rewrite ^ /auth.php?action=register_student last;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param QUERY_STRING \$query_string;
    }

    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Allow-Headers "Content-Type, X-Session-Token";
    add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
}
EOF

php-fpm -D
nginx -g "daemon off;"
