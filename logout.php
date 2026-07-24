<?php
require_once 'includes/auth.php';
logout_user();
header('Location: ' . BASE_URL . 'index.php');
exit;
