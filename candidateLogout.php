<?php
require_once 'includes/bootstrap.php';
session_destroy();
header("Location: candidateLogin.php");
exit;
?>
