<?php
/**
 * TinyFileManager - File management tool
 * This is a simplified version of TinyFileManager for LiteWP
 */

// Security check
if (!isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] !== 'localhost') {
    die('Access denied');
}

// Include TinyFileManager core
require_once 'tinyfilemanager-core.php';

// Configure TinyFileManager
$filemanager = new TinyFileManager();
$filemanager->setTitle('LiteWP File Manager');

// Set root directory
$filemanager->setRoot('/usr/local/litewp/websites');

// Set allowed file types
$filemanager->setAllowedExtensions(['php', 'html', 'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'pdf', 'zip']);

// Set max file size (10MB)
$filemanager->setMaxFileSize(10 * 1024 * 1024);

// Run TinyFileManager
$filemanager->run();
?> 