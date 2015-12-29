<?php
if ((($_SESSION["person_id"] != 0) && ($_SESSION["_SITE_CONF"]["RUNLEVEL"] != 1)) ||
	!$_PERMITS->can_pass(PERMITS::_SUPERUSER))
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

echo "PHP_VERSION_ID = ".PHP_VERSION_ID."<br>\n";

phpinfo();
?>
