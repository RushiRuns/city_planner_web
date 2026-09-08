<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/session.php";
require_once __DIR__ . "/includes/audit.php";
startSecureSession();
if (isLoggedIn()) { logAudit("logout","auth",$_SESSION["admin_email"]??""); }
destroySession();
header("Location: " . BASE_URL . "index.php?msg=logged_out");
exit();
