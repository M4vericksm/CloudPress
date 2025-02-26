<?php
define('DB_NAME', 'wordpress');
define('DB_USER', 'wordpress');
define('DB_PASSWORD', 'senha_segura');
define('DB_HOST', 'db');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

define('AUTH_KEY',         'coloque-uma-chave-unica-aqui');
define('SECURE_AUTH_KEY',  'coloque-uma-chave-unica-aqui');
define('LOGGED_IN_KEY',    'coloque-uma-chave-unica-aqui');
define('NONCE_KEY',        'coloque-uma-chave-unica-aqui');
define('AUTH_SALT',        'coloque-uma-chave-unica-aqui');
define('SECURE_AUTH_SALT', 'coloque-uma-chave-unica-aqui');
define('LOGGED_IN_SALT',   'coloque-uma-chave-unica-aqui');
define('NONCE_SALT',       'coloque-uma-chave-unica-aqui');

$table_prefix = 'wp_';

define('WP_DEBUG', false);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
