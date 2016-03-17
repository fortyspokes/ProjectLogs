<?php
ob_start();
ob_clean(); //remove the default 'Content-type' header
header("Content-Type: text/css; charset: UTF-8");

include "css/".$_SESSION["_SITE_CONF"]["THEME"]."/".$_GET["file"].".css";

ob_end_flush();
exit;
?>
