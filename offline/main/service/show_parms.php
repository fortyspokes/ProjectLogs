<?php
//If debugging a production site, comment out this line:
if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] != 1) exit;
show_parms(array("_SESSION"=>$_SESSION),"");exit;
function show_parms($parms, $offset) {
	foreach ($parms as $key=>$value) {
		echo "<br>".$offset.$key;
		if (is_array($value)) {
			if (count($value) > 0) {
				echo "\n";
				show_parms($value,$offset."&nbsp&nbsp&nbsp&nbsp");
				continue;
			}
			$value = "(empty array)";
		}
		echo ": ".$value."\n";
	}
}
?>
