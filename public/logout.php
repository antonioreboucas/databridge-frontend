<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-client.php';
require_once __DIR__ . '/../includes/auth.php';

do_logout();
header('Location: ' . BASEURL . '/login.php');
exit;
