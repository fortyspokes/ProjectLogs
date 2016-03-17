<?php
ob_start();
ob_clean(); //remove the default 'Content-type' header
header("Content-Type: text/javascript; charset: UTF-8");

include "scripts/".$_GET["file"];

ob_end_flush();
exit;
?>
