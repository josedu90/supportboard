<?php
/**
 * ======================================
 * WORDPRESS CONFIGURATION FILE
 * ======================================
 *
 * This file include the WordPress database and SMTP informations
 */

$sb_db = array(
  'DB_NAME' => 'es_db',
  'DB_USER' => 'root',
  'DB_PASSWORD' => '',
  'DB_HOST' => 'localhost',
  'DB_PORT' => ''
);

$sb_config_email = array(
    'host' => 'smtps.aruba.it',
    'username' => 'federico@pixor.it',
    'password' => 'fede$k1o',
    'port' => '465',
    'SMTPSecure' => 'ssl',
    'email_from' => 'test@pixor.it'
);

$sb_wp_prefix = '';

?>