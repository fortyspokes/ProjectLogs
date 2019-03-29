<?php
//copyright 2015,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Populate the date pulldown selection list then collect the response via server call-back:
function date_send(&$state, &$HTML) {

	$HTML .= "//Date...\n";
   	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Select the date';\n";
	$HTML .= "fill = \"<select name='selDate' id='selDate' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";

	$dayadd = new DateInterval('P1D');
	$day=clone $state->from_date;
	for ($ndx=0; $ndx < $state->columns[COL_COUNT]; $ndx++,$day->add($dayadd)) {
		if ($ndx < $state->columns[COL_OPEN]) continue;
		if ($ndx >= $state->columns[COL_INACTIVE]) break;
		$style = ($day->format("N") > 5)?" class='weekend'":"";
		$style .= " style='text-align:right'";
		$listing = $day->format("D m-d");
		$HTML .= "fill += \"<option value='".$ndx."'".$style."'>".$listing."</option>\";\n";
	}
	$HTML .= "fill += \"</select>\";\n";
	$HTML .= "cell = document.getElementById('DT_".$state->row."');\n";
	$HTML .= "cell.innerHTML = fill;\n";
	$HTML .= "document.getElementById('selDate').selectedIndex=-1;\n";
}

function date_select(&$state, &$HTML) {

	$rec = strval($_GET["row"]);
	if (($rec < $state->columns[COL_OPEN]) || ($rec >= $state->columns[COL_INACTIVE])) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid date ".$rec,true);
	}
	$state->msgStatus = "";
	$HTML .= "cell = document.getElementById('DT_".$state->row."');\n";
	$state->logdate = new DATE_FIELD("txtLog","logdate",FALSE,FALSE,FALSE,0,FALSE,clone($state->from_date));
	$state->logdate->value->add(new DateInterval('P'.$rec.'D'));
	$HTML .= "cell.innerHTML = '".$state->logdate->format()."';\n";
}
?>
