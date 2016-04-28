//Preferences:
//copyright 2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

LoaderS.push('PF_init();');

function PF_init() {
	divPopped = document.getElementById("divPop_ID");
	divReplace = document.getElementById("replacePop");
}

var divPopped;
var divReplace;
var myCell;
var where;
var popped = false;
function show_pop(ID,who) {
	if (popped) return; //update in process
	popped = true;
	myCell = document.getElementById(ID);
	var content = "who="+who;
	server_call("GET",content,IAm,fill_pop);
}
function fill_pop(fill) {
	server_put_debug(fill);
	eval(fill); //sets fill & where
	divReplace.innerHTML = fill;
	divPopped.style.visibility = "visible";
//	divReplace.firstElementChild.focus();
}
function close_pop(doit) {
	popped = false
	divPopped.style.visibility = "hidden";
	if (!doit) return;
	eval(where); //set where to return value
	var content = "what="+encodeURIComponent(where);
	server_call("GET",content,IAm,update_cell);
}
function update_cell(fill) {
	myCell.innerHTML = fill;
}

