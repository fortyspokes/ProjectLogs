<?php
//copyright 2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Generalized processing to send properties with downloaded logs.

//Instructions:
//Expects to see task/subtask/account/event_props in the log record containing the respective
//element_id; will replace this field with all relevant property value ids.
//Will mark this orgs properties as used then will report the used ones via $this->send_all().

class PROPS_SEND {

	private $elements = array(); //the 3char table ids, name, and record offset
	private $stmt;

	public $noSleep = array(); //clear out these user created vars when sleeping (to save memory)
	private $records = array();


function __construct($elements) {
	foreach ($elements as $element) {
		switch ($element) {
		case "a12":
			$this->elements["task"] = array($element,0);
			break;
		case "a14":
			$this->elements["subtask"] = array($element,0);
			break;
		case "a21":
			$this->elements["account"] = array($element,0);
			break;
		case "a30":
			$this->elements["event"] = array($element,0);
			break;
		}
	}
	$this->noSleep[] = "records";
	$this->get_recs();
}

function __sleep() { //don't save this stuff - temporary and too long
	foreach ($this->noSleep as $temp) {
		if (is_array($this->{$temp})) {
			$this->{$temp} = array();
		} else {
			$this->{$temp} = false;
		}
	}
	$this->records = array();
   	return array_keys(get_object_vars($this));
}

function __wakeup() {
	$this->get_recs();
}

public function __set($key, $value) { //set dynamic vars
	$this->$key = $value;
}

function setNoSleep($var) {
	$this->noSleep[] = $var;
}

public function send_all(&$file) {
	$outline = array();
	$outline[0] = "<properties>";
	$outline[1] = "<start>";
	fputcsv($file, $outline);
	foreach ($this->records as $value) {
		if ($value["name_id"] != 0) continue;
		$outline[0] = $value["value_id"];
		$outline[1] = $value["name"];
		$outline[2] = $value["value"];
		fputcsv($file, $outline);
	}
	$outline[0] = "<properties>";
	$outline[1] = "<end>";
	$outline[2] = "";
	fputcsv($file, $outline);
}

public function init($headers) {
	global $_DB;

	$ndx = 0;
	foreach ($headers as $name) {
		if (substr($name,-6) == "_props") {
			$element = substr($name,0,strlen($name)-6);
			if (array_key_exists($element,$this->elements)) {
				$this->elements[$element][1] = $ndx; //set offset
			}
		}
		++$ndx;
	}
	$sql = "";
	foreach ($this->elements as $name=>$element) {
		$sql .= " OR (element_table = '".$element[0]."' AND element_idref = :".$name."_id)";
	}
	$sql = "SELECT prop_value_idref AS value_id, element_table AS table_name
				FROM ".$_DB->prefix."e04_prop_element
				WHERE".substr($sql,3).";";
	$this->stmt = $_DB->prepare($sql);
}

public function add_ids(&$log) {
	//find the property values for each element:
	foreach ($this->elements as $name=>$element) {
		$this->stmt->bindvalue(':'.$name.'_id',$log[$element[1]],db_connect::PARAM_INT);
		$log[$element[1]] = ""; //clear the element_id field
	}
	$this->stmt->execute();
	while ($row = $this->stmt->fetchObject()) {
		switch ($row->table_name) {
		case "a12":
			$log[$this->elements["task"][1]] .= " ".$row->value_id;
			break;
		case "a14":
			$log[$this->elements["subtask"][1]] .= " ".$row->value_id;
			break;
		case "a21":
			$log[$this->elements["account"][1]] .= " ".$row->value_id;
			break;
		case "a30":
			$log[$this->elements["event"][1]] .= " ".$row->value_id;
			break;
		}
		foreach ($this->records as $key=>$value) {
			if ($value["value_id"] == $row->value_id) {
				$this->records[$key]["name_id"] = 0; //this value appears here
				break;
			}
		}
	}
}

private function get_recs() { //all properties for this org
	global $_DB;

	$sql = "SELECT e00.name, e00.property_id AS name_id,
				e02.name AS value, e02.prop_value_id AS value_id
			FROM (
				SELECT property_id, name FROM ".$_DB->prefix."e00_property
				WHERE organization_idref = ".$_SESSION["organization_id"]."
			) AS e00
			JOIN ".$_DB->prefix."e02_prop_value AS e02 ON e02.property_idref = e00.property_id
			ORDER BY name, value;";
	$stmt = $_DB->query($sql);
	$this->records = array();
	while ($row = $stmt->fetchObject()) {
		$record = array(
				"name" =>		$row->name,
				"name_id" =>	$row->name_id,
				"value" =>		$row->value,
				"value_id" =>	$row->value_id,
		);
		$this->records[] = $record;
	}
	$stmt->closeCursor();
}

} //end class
?>
