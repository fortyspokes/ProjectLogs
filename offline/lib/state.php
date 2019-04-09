<?php
//copyright 2015, 2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

class STATE {
	public $ID; //(2 char?) process ID
	public $position = 0; //the position in the stack array of the serialized state object (SSO)
	public $thread;
	public $status;
	public $init = STATE::INIT; //the 'return to menu' status, ie no 'goback' button
	public $backup = -2; //Status to return to on a 'goback', ie. this->loopback($backup);
						//a neg $backup = # of levels to pop, ie. this->goback(-$backup);
						//initially set to -2 until all processes adopt $backup.
	public $heading = "";
	public $record_id = 0;
	public $records = array();
	public $fields = array();
	public $msgGreet = "";
	public $msgStatus = "";
	public $parent = "";
	public $child = ""; //an operating subthread (the scion)
	public $noSleep = array(); //clear out these user created vars when sleeping (to save memory)
	public $sleepers; //truncate some objects, eg. DateTime

	const INIT = 0;
	const SELECT = 10;
	const SELECTED = 20;
	const ENTRY = 30;
	const ADD = 40;
	const CHANGE = 50; //intended to include both update and delete of an existing record
	const UPDATE = 52;
	const DELETE = 54;
	const DONE = 90;
	const ERROR = 99;

function __construct($ID, $thread="_MAIN", $status=STATE::INIT) {
	$this->ID = $ID;
	$this->status = $status;
	$this->thread = $thread;
	$this->cleanup($thread);
	$_SESSION["STATE"][$thread] = array(serialize(clone($this)),);
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
	$this->fields = array();
	$this->msgGreet = "";
	$this->msgStatus = "";
	$this->sleepers = COM_sleep($this);
	return array_keys(get_object_vars($this));
}

function __wakeup() {
	COM_wakeup($this);
}

function setNoSleep($var) {
	$this->noSleep[] = $var;
}

public function __set($key, $value) { //set dynamic vars
	$this->$key = $value;
}

function cleanup($thread) {
	if ($thread == "") return;
	if (!isset($_SESSION["STATE"][$thread])) return;
	$child = unserialize(array_pop($_SESSION["STATE"][$thread]));
	$child = $child->child;
	if ($child != "")
		$this->cleanup($child);
	unset($_SESSION["STATE"][$thread]);
}

function scion_start($child, $status=STATE::INIT) {
	$this->cleanup($child);
	$this->cleanup($this->child);
	$this->child = "";
	$scion = clone($this);
	$scion->thread = $child;
	$scion->status = $status;
	$scion->parent = $this->thread;
	$_SESSION["STATE"][$child] = array();
	$scion->push();
	$this->child = $child;
	$this->replace();
	return $scion;
}

function scion_pull($generation=1) {
	$scion = $this;
	do {
		if ($scion->child == "") {
			if ($generation < 0) return $scion; //ie., asked for 'youngest'
			return false;
		}
		$scion = STATE_pull($scion->child);
	} while(--$generation != 0);
	return $scion;
}

function cut() { //remove the stack
	$parent = $this->parent;
	$this->cleanup($this->thread);
	$parent = STATE_pull($parent);
	$parent->child = "";
	$parent->replace();
}

public function goback($levels) {
//	$child = "";
	$pull = $this;
	while ($levels > 0) {
		if (count($_SESSION["STATE"][$this->thread]) < 2 ) break;
		$junk = unserialize(array_pop($_SESSION["STATE"][$this->thread]));
		$pull = STATE_pull($this->thread);
		if ($junk->child != $pull->child) {
			$this->cleanup($junk->child);
		}
		--$levels;
	}
	return $pull;
}

public function loopback($status) { //pop the stack until we find this state
	do {
		$state = $this->goback(1);
	} while(($state->status != $status) && ($state->position > 0));
	return $state;
}

public function push() { //push new SSO onto stack
	$clone = clone($this);
	$clone->position = count($_SESSION["STATE"][$this->thread]);
	$_SESSION["STATE"][$this->thread][$clone->position] = serialize($clone);
	return $clone;
}

public function replace() { //replace SSO with new properties
//primary use is update the status when 'falling through' the state gate
//instead of returning to the executive
	$clone = clone($this);
	$_SESSION["STATE"][$this->thread][$this->position] = serialize($clone);
}

} //end class STATE

function STATE_pull($thread="_MAIN", $prior=0) { //default pull is the latest in the thread
	return unserialize($_SESSION["STATE"][$thread][count($_SESSION["STATE"][$thread])-1-$prior]);
}

?>
