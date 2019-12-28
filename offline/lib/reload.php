<?php
//copyright 2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Reload the top page; if returning a server call, send script only
function reload_top($script_only=false) {
	session_commit();
	ob_clean();
	if (!$script_only) {
		echo "<html><head><script>\n";
	} else {
		echo "@"; //do an eval
	}
	echo "top.location=\"https://".$_SESSION["HOST"]."?user=".$_SESSION["user"]."\"\n";
	if (!$script_only)
		echo "</script></head></html>\n";
	exit;
}
?>
