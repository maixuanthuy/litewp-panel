<?php
/**
 * Adminer - Database management tool
 * This is a simplified version of Adminer for LiteWP
 */

// Security check
if (!isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] !== 'localhost') {
    die('Access denied');
}

// Include Adminer core
require_once 'adminer-core.php';

// Configure Adminer
$adminer = new Adminer();
$adminer->setTitle('LiteWP Database Manager');

// Set default server
if (!isset($_GET['server'])) {
    $_GET['server'] = 'localhost';
}

// Set default username if not provided
if (!isset($_GET['username']) && !isset($_POST['auth']['driver'])) {
    $_GET['username'] = 'root';
}

// Run Adminer
$adminer->run();
?> 