<?php
/**
 * Main route loader for User Module
 *
 * Include this file in your main index.php or api.php file.
 *
 * Example in index.php:
 * -----------------------------------
 * if (strpos($requestUri, '/api/user') === 0) {
 *     require_once __DIR__ . '/routes/user.php';
 *     exit;
 * }
 * -----------------------------------
 */

require_once __DIR__ . '/../modules/user/routes.php';