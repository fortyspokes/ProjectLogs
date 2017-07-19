//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

LoaderS.push('init_cells();');

function init_cells() {
	var rows = document.getElementById('tblLog').rows;
	var title = "";
	var cell = rows[1].cells[0]; //add row 'button'
	cell.addEventListener("click", begin, true)
	for (var rndx=2; rndx<rows.length; rndx++) { //skip header and add rows
		var cells = rows[rndx].cells;
		for (var cndx=0; cndx<cells.length; cndx++) {
			var cell = cells[cndx];
			switch (cell.id.substr(0,3)) {
			case "BN_":
				if ((cell.title == '') || (cell.title === null) || (cell.title === undefined)) {
					cell.title = "Click to make changes to amounts (enter 0 to delete)";
					cell.addEventListener("click", begin, true)
				}
				break
			case "TK_":
			case "ST_":
			case "AC_":
				title = "Double click to get a full description";
				if (cell.getAttribute("data-recid") > 0) {
					title += "\nShift/Click to re-select";
					cell.addEventListener("click", beginShift, true)
				}
				cell.title = title;
				cell.ondblclick = new Function("get_desc(this)");
				break
			case "TP_":
				if (cell.getAttribute("data-recid") != "") {
					cell.title = "Shift/Click to re-select";
					cell.addEventListener("click", beginShift, true)
				}
				break
			case "AT_":
				title = "Double click to see full text and to update";
				if (cell.getAttribute("data-recid") > 0) {
					title += "\nShift/Click to re-select";
					cell.addEventListener("click", beginShift, true)
				}
				cell.title = title;
				cell.ondblclick = new Function("show_activity(this)");
				break
			}
		}
	}
	divActiv = document.getElementById("divPopopen_ID");
	txtActiv = document.getElementById("txtActivity_ID");
}

var myCell;
var set_desc; //the callback to set description
function get_desc(me,service) {
	//set defaults:
	service = service !== undefined ?  service : set_title;
	myCell = me;
	set_desc = service;
	var content = "getdesc=" + me.id.substr(0,2) + "&ID=" + Math.abs(me.getAttribute("data-recid"));
	server_call("GET", content);
}
function got_desc(desc) {
	set_desc(myCell,desc);
}
function set_title(who,desc) {
	var ndx = who.title.indexOf('\n');
	if (ndx == -1) ndx = who.title.length;
	who.title = desc + who.title.slice(ndx);
	alert(who.title);
}

function beginShift(evt) { //confirm shift key before doing begin()
	if (evt.shiftKey) { begin(evt); }
}
var captions = {loading:"loading, please wait..."};
on_path = false; //reset on reload of page
function begin(evt) {
	if (on_path) return; //update in process
	on_path = true;
	server_set_dbug(); //clear out the debug area
	proceed(evt.currentTarget,evt.currentTarget.id.substr(3));
}
function proceed(me,row,interim) {
	if (interim === undefined) {
		me.innerHTML = captions.loading;
	} else {
		me.innerHTML = interim;
	}
	server_call("GET","agent="+me.id.substr(0,2)+"&row="+row);
}

var divActiv; //set by init_cells()
var txtActiv;
var save_activity; //the callback from txtActiv
function show_activity(me) { //show existing activity
	if (on_path) return; //update in process
	myCell = me;
	if (me.getAttribute("data-value") == "\\") {
		get_desc(me,got_activity); //will come back to got_activity
		return;
	}
	save_activity = upd_act; //set callback
	txtActiv.value = me.getAttribute("data-value");
	divActiv.style.visibility = "visible";
	txtActiv.focus();
}
function got_activity(who,desc) {
	who.setAttribute("data-value",desc);
	show_activity(myCell); //got it, try again
}
function upd_act(doit) {
	divActiv.style.visibility = "hidden";
	if (!doit) return;
	myCell.setAttribute("data-value",txtActiv.value);
	myCell.innerHTML = txtActiv.value.substr(0,25);
	var content = "row="+myCell.id.substr(3);
	content += "&agent="+myCell.id.substr(0,2);
	content += "&actupd="+myCell.getAttribute("data-recid");
	content += "&act="+encodeURIComponent(txtActiv.value);
	server_call("GET", content);
}

function select_activity(me) { //select activity from dropdown
	myCell = me.parentNode; //the TD element
	var opt = me.options[me.selectedIndex];
	if (opt.value == "\\AT0") { //the 'add new' option
		save_activity = new_act; //set callback
		divActiv.style.visibility = "visible";
		txtActiv.focus();
	} else {
		proceed(myCell,opt.value,opt.text);
	}
}
function new_act(doit) {
	divActiv.style.visibility = "hidden";
	if (!doit) {
		document.getElementById('selActivity').selectedIndex=-1;
		return;
	}
	myCell.setAttribute("data-recid",0);
	myCell.setAttribute("data-value",txtActiv.value);
	proceed(myCell,0,txtActiv.value.substr(0,50));
}

var submitRow = -1;
function mouseDown(row) {
	submitRow = row;
}
function audit_amount(me, maxAmount) {
	if (me.value == "") return true;
	if (isNaN(me.value)) {
		alert("Amounts must be numeric");
		me.value = me.parentNode.value;
		me.focus
		submitRow = -1;
		return false;
	} else if (me.value > maxAmount) {
		if (!confirm(me.value+" seems high - are you sure?")) {
			me.value = me.parentNode.value;
			me.focus;
			submitRow = -1;
			return false;
		}
		if (submitRow > -1) {
			changes(submitRow);
		}
	} else if (me.value < 0) {
		alert("Please enter a valid amount");
		me.value = me.parentNode.value;
		me.focus;
		submitRow = -1;
		return false;
	} else if ((me.value == 0) && (me.defaultValue != 0)) {
		if (!confirm("Are you sure you want to delete this record?")) {
			me.value = me.parentNode.value;
			me.focus;
			submitRow = -1;
			return false;
		}
		if (submitRow > -1) {
			changes(submitRow);
		}
	}
	return true;
}

function get_cell_recid(cellID) {
	var cell = document.getElementById(cellID);
	if ((cell === undefined) || (cell == null)) return "0";
	return cell.getAttribute("data-recid");
}

var awaitingServer = false;
function changes(row) {
	if (awaitingServer) return;
	var content = "row="+row;
	content += "&act="+encodeURIComponent(document.getElementById("txtActivity_ID").value);
	content += "&task="+get_cell_recid("TK_"+row);
	content += "&subtask="+get_cell_recid("ST_"+row);
	content += "&account="+get_cell_recid("AC_"+row);
	content += "&type="+get_cell_recid("TP_"+row);
	content += "&activity="+get_cell_recid("AT_"+row);
	for (ndx=0; ndx < COLs; ndx++) {
		amount = document.getElementById("txtAmount"+ndx+"_ID");
		if (amount === null) continue; //closed, dups, or in List mode
		if (amount.tagName == "INPUT") {
			content += "&rec"+ndx+"="+amount.parentNode.getAttribute("data-recid");
			content += "&amount"+ndx+"="+amount.value;
		}
	}

	awaitingServer = true;
	server_call("POST", content);
	awaitingServer = false;
}

function Reset() {
	var URL_delim = "&"; if (IAm.indexOf("?") == -1 ) URL_delim = "?";
	window.location = IAm + URL_delim + "reset";
}

