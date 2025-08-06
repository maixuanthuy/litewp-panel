<?php
require_once '../backend/includes/config.php';
require_once '../backend/includes/auth.php';

$auth = new Auth();
$auth->logout();

// Redirect to login page
header('Location: /login.php');
exit; 