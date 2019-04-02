//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

var CS_callback;
var CS_whoWas;
//var IAm must be defined by the page if 'who' not specified by caller
function server_call(type, content, who=IAm, service=server_answer) { //type: "POST", "GET"
	//set callbacks:
	CS_callback = service;
	CS_whoWas = who;
	var caller = new XMLHttpRequest();
	caller.onreadystatechange=function() {
		if (caller.readyState==4 && caller.status==200) {
			CS_callback(caller.responseText);
		}
	}
	if (type == "GET") {
		var URL_delim = "&"; if (who.indexOf("?") == -1 ) URL_delim = "?";
		caller.open("GET", who + URL_delim + "servercall=g&" + content, true);
		caller.send(null);
	} else {
		caller.open("POST", who, true);
		caller.setRequestHeader("Content-type","application/x-www-form-urlencoded");
		caller.send("servercall=p&" + content);
	}
}
function server_answer(resp) {
	server_put_debug(resp);
	var code = resp.charAt(0);
	var ID = resp.substr(1,2);
	var msg = resp.substr(3);
	resp = resp.slice(1);
	switch (code) {
	case "!": //display status msg, stay here
		document.getElementById("msgStatus_ID").innerHTML = resp;
		break;
	case "?": //ask for confirmation to continue, or stay here
		if (confirm(msg)) {
			server_call("GET", ID + "=OK", CS_whoWas);
		}
		break;
	case "&": //ask for info, always return
		var info = prompt(msg,"");
		server_call("GET", ID + "=" + encodeURIComponent(info), CS_whoWas);
		break;
	case "@": //HTML
		eval(resp);
		break;
	case "-": //re-draw the page
		var URL_delim = "&"; if (IAm.indexOf("?") == -1 ) URL_delim = "?";
		window.location = IAm + URL_delim + "reset";
		break;
	case ".": //the period: we're done with dialog, stay here
		break;
	default:
		alert("Whas up? "+resp.substr(0,200));
	}
}

function server_set_dbug() { //clear out the debug area
	dbugmsg = document.getElementById('msgDebug');
	if (dbugmsg !== undefined) {
		try {
			dbugmsg.innerHTML = '';
		} catch(dummy){}
	}
}
function server_put_debug(resp) { //add this stuff to debug
	var dbugmsg = document.getElementById('msgDebug');
	if (dbugmsg !== undefined) {
		try {
			dbugmsg.innerHTML += resp;
		} catch(dummy){}
	}
}

