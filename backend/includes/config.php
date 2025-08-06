<?php
// LiteWP Configuration File

// Database configuration
define('DB_PATH', '/usr/local/litewp/config/panel.db');

// Panel paths
define('LITEWP_ROOT', '/usr/local/litewp');
define('PANEL_ROOT', LITEWP_ROOT . '/panel');
define('WEBSITES_ROOT', LITEWP_ROOT . '/websites');
define('BACKUP_ROOT', LITEWP_ROOT . '/backups');
define('LOGS_ROOT', LITEWP_ROOT . '/logs');

// Panel settings
define('PANEL_NAME', 'LiteWP');
define('PANEL_VERSION', '1.0.0');
define('DEFAULT_PHP_VERSION', '8.3');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// Backup settings
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_MAX_SIZE', '10GB');

// WordPress settings
define('WP_CLI_PATH', '/usr/local/bin/wp');
define('WP_DEFAULT_THEME', 'twentytwentyfour');
define('WP_DEFAULT_PLUGINS', ['litespeed-cache']);

// SSL settings
define('SSL_PROVIDER', 'letsencrypt');
define('SSL_EMAIL', 'admin@litewp.local');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOGS_ROOT . '/panel_errors.log');

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_strict_mode', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function get_setting($key, $default = null) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray()) {
        return $row['value'];
    }
    
    return $default;
}

function set_setting($key, $value) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (:key, :value, CURRENT_TIMESTAMP)');
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->bindValue(':value', $value, SQLITE3_TEXT);
    return $stmt->execute();
}

function log_message($message, $level = 'INFO') {
    $log_file = LOGS_ROOT . '/panel.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function sanitize_input($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validate_domain($domain) {
    return filter_var($domain, FILTER_VALIDATE_DOMAIN) !== false;
}

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function error_response($message, $status_code = 400) {
    json_response(['error' => $message], $status_code);
}

function success_response($data = null, $message = 'Success') {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    json_response($response);
} 