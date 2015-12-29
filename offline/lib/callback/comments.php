<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Send the comments text entry field via server call-back:
function comments_send(&$state, &$HTML) {

	$HTML .= "//Comments...\n";
	$HTML .= "cell = document.getElementById('CM_".$state->row."');\n";
	$HTML .= "cell.ondblclick = new Function('select_comments(this)');\n";
	$HTML .= "cell.title = 'Double click to add/update';\n";
	$HTML .= "select_comments(cell);\n";
}
?>
