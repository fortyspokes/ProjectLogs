<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
require_once "prepend.php";
require_once "lib/common.php";

if (isset($state)) {
	$msgStatus = $state->msgStatus;
} else {
	$msgStatus = "";
}
?>
<html>
<head>
<title>SR2S Timesheets Main</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo $_SESSION["BUTLER"]; ?>?IAm=CG&file=main&ver=<?php echo $_VERSION; ?>" type="text/css">
<script language="JavaScript">
<!--
window.onload = function() {
  top.reset_menu();
  top.frames['headframe'].document.getElementById('msgHead_ID').innerHTML = 'Menu';
}
//-->
</script>
</head>

<body>
<div class="status"><?php echo $msgStatus; ?></div>
</p>
<div class="greet">To continue, select an action from the menu...</div>
</body>
</html>
<?php
exit; //this module can be included within other code as an exit or can stand alone as a location re-assign
?>
