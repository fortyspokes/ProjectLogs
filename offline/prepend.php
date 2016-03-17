<?php
require_once "lib/permits.php";
$_PERMITS = new PERMITS();
//Successful login sets a "_LEGAL_" permit so that subsequent modules can get through this gate;
//Publicly viewable pages, eg. login.php, will declare a $_TEMP_PERMIT = "_LEGAL_"
if (!$_PERMITS->can_pass("_LEGAL_")) { //must be logged in; prevents specifying module in URL to bypass login
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit; not _LEGAL_");
}
?>
