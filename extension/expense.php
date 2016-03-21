<?php
//copyright 2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//An example subtask extension: add expenses to hour logging
//This module is a variation of main/expenselog.php which explains some anomalies, ie. text amount
//field id="amount0".

$ExpTypes = array("ca"=>"cash","bi"=>"billed","mi"=>"mileage"); //make it easy to alter types in the future

//The Main State Gate cases:
define('LIST_DISP',	STATE::INIT + 1);
define('CHANGES',	STATE::INIT + 2);
//SCION State Gate cases:
define ('TYPE_DISP',		STATE::SELECT + 1);
define ('TYPE_PICK',		STATE::SELECTED + 1);
define ('ACTIVITY_DISP',	STATE::SELECT + 2);
define ('ACTIVITY_PICK',	STATE::SELECTED + 2);
define ('AMOUNT_DISP',		STATE::SELECT + 3);
define ('BUTTON_DISP',		STATE::SELECT + 4);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	setup();
	$_STATE->scion_start("Changes"); //create the child state stack
	$_STATE->status = LIST_DISP;
	$_STATE->replace(); //for loopback to see status
case LIST_DISP:
	log_list($_STATE);
	$_STATE->status = CHANGES;
	break 2;
case CHANGES:
	if (isset($_GET["reset"])) {
		$_STATE = $_STATE->loopback(LIST_DISP);
		break 1;
	}
	if (!(isset($_GET["agent"]) || isset($_POST["row"])))
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): GET/POST row not supplied");

	//Add/Update a row:
	$SCION = $_STATE->scion_pull(); //use the child thread
	$response = "@"; //initialized to do an eval
	while (1==1) { switch ($SCION->status) { //the SCION state gate
	case STATE::INIT:
		$SCION->agent = $_GET["agent"];
		$SCION->row = $_GET["row"]; //working on this displayed row
		$SCION->path = array();
		switch ($SCION->agent) {
		case "BN": //button => adding/updating amounts
			if ($SCION->row == 0) { //adding
				$SCION->path = array(TYPE_DISP,
									 ACTIVITY_DISP);
			}
			$SCION->path[] = AMOUNT_DISP;
			break;
		case "TP": //type
			$SCION->path[] = TYPE_DISP;
			break;
		case "AT": //activity
			if (isset($_GET["actupd"])) { //a direct update
				update_activity($SCION, $response);
				echo $response;
				break 3; //break out of here and the SCION state gate
			}
			log_list($SCION);
			$record = reset($SCION->records);
			$SCION->path[] = ACTIVITY_DISP;
			break;
		default:
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid agent ".$SCION->agent,true);
		} //switch for $SCION->agent
		$SCION->path[] = BUTTON_DISP;
		$response .= "document.getElementById('BN_".$SCION->row."')";
		$response .= ".innerHTML = \"<button type='button' name='btnReset' onclick='Reset()'>Cancel</button>\";\n";
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case TYPE_DISP:
		type_send($SCION, $response);
		$SCION->status = TYPE_PICK;
		echo $response;
		break 2; //break out
	case TYPE_PICK:
		type_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case ACTIVITY_DISP:
		activity_send($SCION, $response);
		$SCION->status = ACTIVITY_PICK;
		echo $response;
		break 2; //break out
	case ACTIVITY_PICK:
		activity_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case AMOUNT_DISP:
		amount_send($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case BUTTON_DISP:
		include_once "lib/callback/buttons.php";
		button_send($SCION, $response);
		echo $response;
		$SCION->status = STATE::CHANGE;
		break 2; //break out
	case STATE::CHANGE:
		changes($SCION, $response); //DO IT!
		echo $response;
		break 2; //break out
	} } //State Gate for $SCION->status
	$SCION->push();

	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): Invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate

function setup() {
	global $_STATE, $_DB;

	$sql = "SELECT * FROM ".$_DB->prefix."b00_timelog WHERE timelog_id=".$_GET["recid"].";";
	$_STATE->timelog = $_DB->query($sql)->fetchObject();
	$_STATE->logdate = new DateTime($_STATE->timelog->logdate);

	$sql = "SELECT a12.name AS task, a12.description AS taskdesc,
				a14.name AS subtask, a14.description AS subtaskdesc
			FROM ".$_DB->prefix."a12_task AS a12 JOIN ".$_DB->prefix."a14_subtask AS a14
			ON a12.task_id = a14.task_idref
			WHERE a14.subtask_id = ".$_STATE->timelog->subtask_idref.";";
	$row = $_DB->query($sql)->fetchObject();
	$_STATE->task = $row->task.": ".$row->taskdesc;
	$_STATE->subtask = $row->subtask.": ".$row->subtaskdesc;

	$sql = "SELECT a20.name AS accounting, a21.name AS account, a21.description AS accountdesc
			FROM ".$_DB->prefix."a20_accounting AS a20 JOIN ".$_DB->prefix."a21_account AS a21
			ON a20.accounting_id = a21.accounting_idref
			WHERE a21.account_id = ".$_STATE->timelog->account_idref.";";
	$row = $_DB->query($sql)->fetchObject();
	$_STATE->accounting = $row->accounting;
	if ($row->account == "*") {
		$_STATE->account = "N/A";
	} else {
		$_STATE->account = $row->account.": ".$row->accountdesc;
	}

	$sql = "SELECT mileage FROM ".$_DB->prefix."a10_project
			WHERE project_id=".$_STATE->project_id.";";
	$_STATE->mileage = $_DB->query($sql)->fetchObject()->mileage;
}

function log_list(&$state, $findrow=0) { //$findrow is 1 rel
	global $_STATE, $_DB;

	$state->records = array();

	$sql = "SELECT b20.*, b02.description AS activity, b02.activity_id
			FROM ".$_DB->prefix."b20_expenselog AS b20
			JOIN ".$_DB->prefix."b02_activity AS b02 ON b20.activity_idref=b02.activity_id
			WHERE logdate=:logdate
			AND person_idref=".$_STATE->person_id."
			AND subtask_idref=".$_STATE->timelog->subtask_idref."
			AND account_idref=".$_STATE->timelog->account_idref."
			;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':logdate', $_STATE->logdate->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->execute();
	$row_count = 1;
	while ($row = $stmt->fetchObject()) {
		if ($findrow > 0) { //looking for a specific row
			if ($row_count == $findrow) { //found it
				break;
			} else { //not this one
				continue;
			}
		}
		$record = array(
			"ID" =>			$row->expenselog_id,
			"amount" =>		$row->amount,
			"type" =>		$row->type,
			"activity" =>	substr($row->activity,0,50),
			"activity_id" => $row->activity_id,
			"row" =>		$row_count,
		);
		$state->records[strval($row->expenselog_id)] = $record;
		++$row_count;
	}
	$stmt->closeCursor();
}

//	CALL BACK SECTION
//These routines handle the various server 'call-backs' not included from lib/callback.
//A 'call-back' leaves the page intact while a request is sent back to the server and the response then handled via script.

//Populate the type pulldown selection list then collect the response via server call-back:
function type_send(&$state, &$HTML) {
	global $ExpTypes;

	$HTML .= "//types...\n";
   	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Select the expense type';\n";
	$HTML .= "fill = \"<select name='selType' id='selType' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";
	foreach($ExpTypes as $value => $name) {
		$HTML .= "fill += \"<option value='".$value."'>".$name."</option>\";\n";
	}
	$HTML .= "fill += \"</select>\";\n";
	$HTML .= "cell = document.getElementById('TP_".$state->row."');\n";
	$HTML .= "cell.innerHTML = fill;\n";
	$HTML .= "document.getElementById('selType').selectedIndex=-1;\n";

	return;
}

function type_select(&$state, &$HTML) {
	global $ExpTypes;

	$type = $_GET["row"];
	if (!array_key_exists($type, $ExpTypes)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid expense type ".$type,true);
	}
	$HTML .= "cell = document.getElementById('TP_".$state->row."');\n";
	$HTML .= "cell.innerHTML = '".$ExpTypes[$type]."';\n";
	$state->type = $type;
	$state->msgStatus = "";
}

//Populate the activity pulldown selection list then collect the response via server call-back:
function activity_list(&$state) {
	global $_STATE, $_DB;

	$state->records = array();

	$sql = "SELECT activity_id, activity FROM ".$_DB->prefix."v01_expenselog
			WHERE person_id=".$state->person_id." AND logdate=:logdate
			AND subtask_id=".$_STATE->timelog->subtask_idref."
			ORDER BY logdate;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':logdate', $_STATE->logdate->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->execute();
	while ($row = $stmt->fetchObject()) {
		$state->records[strval($row->activity_id)] = $row->activity;
	}
	$stmt->closeCursor();
}

function activity_send(&$state, &$HTML) {

	activity_list($state);

	$HTML .= "//Activities...\n";
   	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Select the activity';\n";
	$HTML .= "fill = \"<select name='selActivity' id='selActivity' size='1' onchange='select_activity(this)'>\";\n";
	$HTML .= "fill += \"<option value='\\\\AT0' selected>...new activity...\";\n"; //select_activity() recognizes the 'backslashAT0'...
	foreach($state->records as $value => $name) {
			$HTML .= "fill += \"<option value='".$value."'>".substr($name,0,50)."\";\n";
	}
	$HTML .= "fill += \"</select>\";\n";
	$HTML .= "cell = document.getElementById('AT_".$state->row."');\n";
	$HTML .= "cell.innerHTML = fill;\n";
	if (count($state->records) == 0) {
		$HTML .= "document.getElementById('selActivity').selectedIndex=0;\n";
		$HTML .= "select_activity(document.getElementById('selActivity'));\n";
	} else {
		$HTML .= "document.getElementById('selActivity').selectedIndex=-1;\n";
	}

	return count($state->records);
}

function activity_select(&$state, &$HTML) {

	if (!isset($_GET["row"])) return;
	$rec = $_GET["row"]; //get row number

	$state->activity_id = $rec;
	$state->msgStatus = "";
	if ($rec != 0) {
		activity_list($state); //restore the record list
		if (!array_key_exists($rec, $state->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid activity ".$rec,true);
		}
	}
}

//Send the amount text entry field via server call-back:
function amount_send(&$state, &$HTML) {
	global $_STATE, $_DB;

	$ratefound = false;
	$sql = "SELECT effective_asof, expire_after FROM ".$_DB->prefix."c02_rate
			WHERE person_idref=".$_STATE->person_id."
			AND project_idref=".$_STATE->project_id."
			AND effective_asof <= :logdate
			ORDER BY effective_asof;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':logdate', $_STATE->logdate->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->execute();
	while ($row = $stmt->fetchObject()) {
		if ((is_null($row->expire_after)) || (new DateTime($row->expire_after) >= $_STATE->logdate)) {
			$ratefound = true;
			break;
		}
	}
	$stmt->closeCursor();

	$cellID = "AM_".$state->row;
	$HTML .= "//Amount...\n";
	$HTML .= "cell = document.getElementById('".$cellID."');\n";
	$HTML .= "cellValue = cell.getAttribute('data-value');\n";
	if ($ratefound) {
		$HTML .= "fill = \"<input type='text' name='txtAmount' id='txtAmount0_ID' size='5'";
		$HTML .= " maxlength='6' class='number' onblur='audit_amount(this,99)' value='\"+cellValue+\"'>\";\n";
	} else {
		$HTML .= "fill = \"<div name='txtAmount' id='txtAmount0_ID'>";
		$HTML .= "Rate not available</div>\";\n";
	}
    $HTML .= "if (cell.getAttribute('data-recid') >= 0) {\n";
	$HTML .= "  cell.innerHTML = fill;\n";
	$HTML .= "}\n";
}
//end Call Back Section

//Audit the input for update/add:
function audit_amount(&$state, &$status) {
	global $_DB;

	if ($state->row > 0) { //updating (0 is add row)
		$record = reset($state->records);
		if ($record["ID"] == 0)
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid POST 1",true);
	}

	$state->msgStatus = "-"; //tell server_call to reset page

	if (!(isset($_POST["rec0"]) && isset($_POST["amount0"])))
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid POST 3",true);
	$recID = $_POST["rec0"]; //from data-recid attribute
	$amount = $_POST["amount0"];

	$state->msgStatus = "!Please enter a valid amount";
	if (!is_numeric($amount)) return false;
	if (($amount > 500) || ($amount < 0)) return false;
	if (($amount == 0) && ($recid == 0)) return false; //adding: must have an amount

	$state->msgStatus = "-"; //tell server_call to reset page

	if ($recID == 0) { //if adding hours, we're done
		$status = 'a';
		return true;
	}
	foreach ($state->records as $ID=>$record) { //find our record
		if ($record["ID"] == $recID) break;
		array_shift($state->records);
		if (count($state->records) == 0)
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid POST 5",true);
	}
	if ($amount == 0) {
		$status = 'd';
	} elseif ($amount == $record["amount"]) {
		$status = '';
	} else {
		$status = 'u';
	}

	return true;
}

//DB changes for update/add hours:
function add_log(&$state) {
	global $_DB;

	$sql = "INSERT INTO ".$_DB->prefix."b20_expenselog
			(activity_idref, person_idref, subtask_idref, account_idref, type, logdate, amount)
			VALUES (".$state->activity_id.", ".$state->person_id.",
					".$state->timelog->subtask_idref.", ".$state->timelog->account_idref.",
					'".$state->type."', '".$state->logdate->format('Y-m-d')."',
					".$_POST["amount0"].");";
	$_DB->exec($sql);

	$state->msgStatus = "-"; //tell server_call to reset page
}

function update_log(&$state) {
	global $_DB;

	$sql = "UPDATE ".$_DB->prefix."b20_expenselog
			SET amount=".$_POST["amount0"]."
			WHERE expenselog_id=".$_POST["rec"].";";
	$_DB->exec($sql);

	$state->msgStatus = "-"; //tell server_call to reset page
}

function delete_log(&$state) {
	global $_DB;

	$sql = "SELECT activity_idref FROM ".$_DB->prefix."b20_timelog
			WHERE expenselog_id=".$_POST["rec"].";";
	$stmt = $_DB->query($sql);
	$activity = $stmt->fetchObject()->activity_idref;
	$stmt->closeCursor();

	$sql = "DELETE FROM ".$_DB->prefix."b20_expenselog
			WHERE expenselog_id=".$state->recID.";";
	$_DB->exec($sql);

	$sql = "SELECT COUNT(*) AS count FROM ".$_DB->prefix."b20_expenselog WHERE activity_idref=".$activity."";
	$stmt = $_DB->query($sql);
	if ($stmt->fetchObject()->count == 0) {
		$sql = "DELETE FROM ".$_DB->prefix."b02_activity WHERE activity_id=".$activity."";
		$_DB->exec($sql);
	}
	$stmt->closeCursor();

	$state->msgStatus = "-"; //tell server_call to reset page
}

function add_activity(&$state) {
	global $_DB;

	$activity = COM_input_edit("act");

	$hash = md5($activity);
	$sql = "INSERT INTO ".$_DB->prefix."b02_activity (description) VALUES (:hash);";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT activity_id FROM ".$_DB->prefix."b02_activity WHERE description=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$state->activity_id = $stmt->fetchObject()->activity_id;
	$stmt->closeCursor();

	$sql = "UPDATE ".$_DB->prefix."b02_activity SET description=:desc WHERE activity_id=".$state->activity_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':desc',$activity,PDO::PARAM_STR);
	$stmt->execute();
}

function update_activity(&$state, &$response) {
	global $_DB;

	$activity = COM_string_decode($_GET["act"],-1);
	$sql = "UPDATE ".$_DB->prefix."b02_activity SET description=:desc
			WHERE activity_id=".$_GET["actupd"].";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':desc',$activity,PDO::PARAM_STR);
	$stmt->execute();

	$response = "."; //tell server_call we're done
	return true;
}

function new_amount(&$state) {

	log_list($state, $state->row); //find this row's records

	//Do audits:
	$record = reset($state->records);
	if ($state->type == "") $state->type = $record["type"];
	$status = "";
	if (!audit_amount($state, $status)) return;

	//Do DB changes:
	//	adding a row but didn't select existing activity:
	if (($state->row == 0) && ($state->activity_id == 0)) add_activity($state);

	switch ($status) {
	case 'a': //add
		add_log($state);
		break;
	case 'u': //update
		update_log($state);
		break;
	case 'd': //delete
		delete_log($state);
		break;
	}
}

function change_type(&$state) {
	global $_DB;

	log_list($state, $state->row); //find this row's records
	$record = reset($state->records);
	if ($state->type == $record["type"]) return; //no change

	foreach ($state->records as $ID=>$record) {
		if ($record["column"] < $state->columns[COL_OPEN]) continue;
		$sql = "UPDATE ".$_DB->prefix."b20_expenselog
				SET type='".$state->type."' WHERE expenselog_id=".$ID.";";
		$_DB->exec($sql);
	}
}

function change_activity(&$state) {
	global $_DB;

	if ($state->activity_id == 0) { //creating a new one
		add_activity($state);
	} else {
		activity_list($state); //restore the record list
		if (!array_key_exists($state->activity_id, $state->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid activity id ".$state->activity_id,true);
		}
	}

	log_list($state, $state->row); //find this row's records
	$record = reset($state->records);
	$old_activity = $record["activity_id"];
	if ($state->activity_id == $old_activity) return;

	foreach ($state->records as $ID=>$record) {
		if ($record["column"] < $state->columns[COL_OPEN]) continue;
		$sql = "UPDATE ".$_DB->prefix."b20_expenselog
				SET activity_idref=".$state->activity_id." WHERE expenselog_id=".$ID.";";
		$_DB->exec($sql);
	}

	$sql = "SELECT COUNT(*) AS count FROM ".$_DB->prefix."b20_expenselog WHERE activity_idref=".$old_activity."";
	$stmt = $_DB->query($sql);
	if ($stmt->fetchObject()->count == 0) {
		$sql = "DELETE FROM ".$_DB->prefix."b02_activity WHERE activity_id=".$old_activity."";
		$_DB->exec($sql);
	}
	$stmt->closeCursor();
}

function changes(&$state, &$response) {

	$response = "-"; //initialize to reset page

	switch ($state->agent) {
	case "BN": //button => adding/updating amounts
		new_amount($state);
		$response = $state->msgStatus;
		break;
	case "TP": //type
		change_type($state);
		break;
	case "AT": //activity
		change_activity($state);
		break;
	}
}

//-------end function code; begin HTML------------

$scripts = array("call_server.js","expenselog.js");
EX_pageStart($scripts); //required to set up the page
echo "<script>\n";
echo "var COLs = 1;\n";
echo "</script>\n";
EX_pageHead(); //required
?>

<table align='center' cellpadding='4' border='0'>
	<tr>
		<td class='label' style='font-weight:bold'>Task:</td>
		<td style='text-align:left'><?php echo $_STATE->task; ?></td>
	</tr>
	<tr>
		<td class='label' style='font-weight:bold'>Subtask:</td>
		<td style='text-align:left'><?php echo $_STATE->subtask; ?></td>
	</tr>
	<tr>
		<td class='label' style='font-weight:bold'><?php echo $_STATE->accounting; ?></td>
		<td style='text-align:left'><?php echo $_STATE->account; ?></td>
	</tr>
	<tr>
		<td class='label' style='font-weight:bold'>Log Date</td>
		<td style='text-align:left'><?php echo $_STATE->logdate->format("Y-m-d"); ?></td>
	</tr>
</table>
<div id="divPopopen_ID" class="popopen">
  Enter the new activity:<br>
	<textarea name="txtActivity" id="txtActivity_ID" rows="2" cols="50"></textarea><br>
	<input type="button" onclick="save_activity(true)" value="OK">
	<input type="button" id="cancelPop" onclick="save_activity(false)" value="cancel">
</div>
<table align="center" id="tblLog" cellpadding="4" border="2">
	<tr>
		<th width='100'>&nbsp;</th>
		<th width='10'>Type</th>
		<th width='30'>Amount</th>
		<th width='140'>Activity</th>
	</tr>
	<tr id="add">
		<td id="BN_0" data-recid="0" title="Click to add amounts for new expenses">
			<img src="<?php echo $_SESSION["BUTLER"]; ?>?IAm=IG&file=add.png&ver=<?php echo $_VERSION; ?>"></td>
		<td id="TP_0" data-recid=""></td>
		<td id='AM_0' data-recid='0' data-value=''></td>
		<td id="AT_0" data-recid="0" data-value="\"></td>
	</tr>
<?php
$row = 0;
foreach ($_STATE->records AS $ID=>$record) {
	++$row;
	echo "\t<tr>\n";
	echo "\t\t<td id='BN_".$row."' data-recid='".$row."' class=seq>".$row."</td>\n";
	echo "\t\t<td id='TP_".$row."' data-recid='".$record["type"]."'>".$ExpTypes[$record["type"]]."</td>\n";
	$amt = $record["amount"];
	$title = "";
	if ($record["type"] == "mi") {
		$amt = $_STATE->mileage * $amt;
		$title = " title='".$record["amount"]." entered * ".$_STATE->mileage. " rate'";
	}
	echo "\t\t<td id='AM_".$row."' data-recid='".$ID."'".$title.">".$amt."</td>\n";
	echo "\t\t<td id='AT_".$row."' data-recid='".$record["activity_id"]."' data-value='\\'>".
		  $record["activity"]."</td>\n";
	echo "\t</tr>\n";
}
?>
</table>
<?php
EX_pageEnd(); //required
?>
