<?php
/**
 * Logout (usa el helper: limpia cookie + sesión)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/auth.php';

logoutUser();
header('Location: index.php');
exit;
