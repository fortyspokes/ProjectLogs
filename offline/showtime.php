<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

$_SESSION["THEME"] = $_SESSION["_SITE_CONF"]["THEME"]; //THEME can be changed and can revert back

?>
<html>
<head>
<title><?php echo $_SESSION['_SITE_CONF']['PAGETITLE'] ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link rel="shortcut icon" href="<?php echo $_SESSION["BUTLER"]; ?>?IAm=IG&file=logo.ico&ver=<?php echo $_VERSION; ?>">
<script language="JavaScript">
if (top != self) { //a timeout will force this
	top.location = "https://<?php echo $_SESSION["HOST"]; ?>";
}

window.onload = function() {
	reload_head();
}

function reload_head() {
	top.frames["headframe"].location = "<?php echo $_SESSION["BUTLER"]; ?>?IAm=HD";
}

function reload_main() {
	top.frames["mainframe"].location = "<?php echo $_SESSION["BUTLER"]; ?>?IAm=MA";
}

function reload_menu() {
	menuframe.location = "<?php echo $_SESSION["BUTLER"]; ?>?IAm=MU";
}

function reset_menu() {
	menuframe.restore_attrs();
}

function load_task(task) {
	process = task.split(":");
	if (process[0].charAt(0) == "!") {
		url = process[0].substr(1);
	} else {
		url = "<?php echo $_SESSION["BUTLER"]; ?>?IAm=EX&init=" + process[0];
	}
	self.frames["mainframe"].location = url + "&head=" + encodeURI(process[1]);
}

function OnOff(owner, element) {
	var style = self.frames[owner].document.getElementById(element).style;
	style.visibility = (style.visibility=='visible')?'hidden':'visible';
}
</script>
</head>
<body>
<table border="0" cellspacing="0" cellpadding="0" width="100%" height="100%">
<tr><td colspan="2">
<iframe name="headframe" scrolling="no" height="110" width="100%" frameborder="no" border="0" framespacing="0">
</iframe>
</td></tr>
<tr height="100%"><td height="100%" width="150">
<iframe name="menuframe" height="100%" width="150" frameborder="no" marginwidth="5"
  src="<?php echo $_SESSION["BUTLER"]; ?>?IAm=MU&init=MU"></iframe>
</td><td width="100%">
<iframe name="mainframe" height="100%" width="100%" frameborder="no" marginwidth="5"
  src="<?php echo $_SESSION["BUTLER"]; ?>?IAm=LI&init=LI"></iframe>
</td></tr>
</table>

</body>
</html>
