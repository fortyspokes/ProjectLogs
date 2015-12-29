<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Send the enter/cancel buttons via server call-back:
function button_send(&$state, &$HTML) {
	$HTML .= "//Buttons...\n";
	$HTML .= "cellID = 'BN_".$state->row."';\n";
	$HTML .= "cell = document.getElementById(cellID);\n";
	$HTML .= "cell.title = '';\n";
   	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Enter your hours';\n";
	//onclick=onmousedown + onmouseup; if audit_count() caused by onblur of numbers issues confirm(),
	//onmouseup will not happen; in that case, mouseDown() will assure new_info() gets executed:
	$HTML .= "fill = \"<button type='button' onclick='changes(".$state->row.")' onmousedown='mouseDown(".$state->row.")'>";
	$HTML .= "Submit</button>";
	$HTML .= "<br><button type='button' name='btnReset' onclick='Reset()'>Cancel</button>";
	$HTML .= "\";\n";
	$HTML .= "cell.innerHTML = fill;\n";
}
?>
