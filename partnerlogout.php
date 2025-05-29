<?php
session_start();
session_destroy();
header("Location: partnerlogin.php");
exit;
?>
