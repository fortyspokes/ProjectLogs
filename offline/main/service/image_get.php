<?php
ob_start();
ob_clean(); //remove the default 'Content-type' header
$file = $_GET["file"];
$ext = pathinfo($file, PATHINFO_EXTENSION);
switch ($ext) {
	case "jpg":
		$ext = "jpeg";
		break;
	case "ico":
		$ext = "vnd.microsoft.icon";
		break;
}
header('Content-Type: image/'.$ext);

readfile("images/".$file,true); //true=>look thru includes

ob_end_flush();
exit;
?>
