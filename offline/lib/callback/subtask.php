<?php
//copyright 2015,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Populate the subtask pulldown selection list then collect the response via server call-back:
function subtask_list(&$state) {
	global $_DB;

	$state->records = array();

	$sql = "SELECT * FROM ".$_DB->prefix."a14_subtask
			WHERE task_idref=".$state->task_id." ORDER BY name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$element = array();
		if ($row->name == "*") {
			$element[0] = "N/A";
		} else {
			$element[0] = substr($row->name.": ".$row->description,0,25);
		}
		$element[1] = "";
		if (!is_null($row->inactive_asof)) {
			$inact = new DateTime($row->inactive_asof);
			if ($inact <= $state->from_date) continue;
			if ($state->to_date >= $inact)
				$element[1] = $row->inactive_asof;
		}
		$state->records[strval($row->subtask_id)] = $element;
	}
	$stmt->closeCursor();
}

function subtask_send(&$state, &$HTML) {

	subtask_list($state);

	$HTML .= "//Subtasks...\n";
	if (count($state->records) == 1) {
		reset($state->records);
		$state->subtask_id = intval(key($state->records)); //subtask_select wants to see this
	} else {
    	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Select the subtask';\n";
		$HTML .= "fill = \"<select name='selSubtask' id='selSubtask' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";
		foreach($state->records as $value => $name) {
			$title = $name[1];
			$opacity = "1.0";
			if ($title != "") {
				$date = explode("-", $title);
				$date[1] -= 1; //month is 0 rel in JS
				$title = " title='inactive as of ".$title."'";
				$opacity = "0.5";
			}
			$HTML .= "fill += \"<option ".$title." value='".$value."' style='opacity:".$opacity."'>".$name[0]."\";\n";
		}
		$HTML .= "fill += \"</select>\";\n";
		$HTML .= "cell = document.getElementById('ST_".$state->row."');\n";
		$HTML .= "cell.innerHTML = fill;\n";
		$HTML .= "document.getElementById('selSubtask').selectedIndex=-1;\n";
	}

	return count($state->records);
}

function subtask_select(&$state, &$HTML, $rec=-1) {

	if ($rec < 0) { //checking returned
		if (!isset($_GET["row"])) return;
		$rec = $_GET["row"]; //get row number
	}

	subtask_list($state); //restore the record list
	if (!array_key_exists($rec, $state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid subtask id ".$rec,true);
	}
	$record = $state->records[$rec];
	if ($record[1] != "") {
		$inactive = new DateTime($record[1]);
		$diff = date_diff($state->from_date, $inactive)->days;
		if ($diff < $state->columns[COL_INACTIVE]) {
			$state->columns[COL_INACTIVE] = $diff;
			$state->columns[COL_AGENT] = "subtask";
		}
		$record[0] .= "<br>(inactive as of ".$record[1].")";
	}
	$state->subtask_id = $rec;
	$state->msgStatus = "";
	$HTML .= "cell = document.getElementById('ST_".$state->row."');\n";
	$HTML .= "cell.innerHTML = '".$record[0]."';\n";
}
?>
