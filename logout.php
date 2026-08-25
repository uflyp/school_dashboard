<?php
require_once 'config.php';

if (isset($_SESSION['username'])) {
    log_activity("User '" . $_SESSION['username'] . "' logged out from system");
}

session_unset();
session_destroy();
header("Location: index.php");
exit();
