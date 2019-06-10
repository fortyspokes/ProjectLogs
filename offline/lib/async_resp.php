<?php
//copyright 2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
/*Asynchronous response:
Send a message to the client then continue with the script.  Useful as a progress notification.

The client page must contain the Javascript output by AR_client() and it must have a mechanism (a button?)
to call the AR_client_open() function.  The optional message sent on instantiation can be retrieved by the
server via a GET["AR_msg"].  Useful to tell the close function to reload.

When the EventSource object is instantiated, the client will call the server.  The interaction must then start
with AR_open() (any previously buffered response will be erased; if a partial response has been sent, this open
will error out with 'headers already sent').  All responses to the client during this interaction must be
through AR_send(); this message will be evaluated by the client Javascript.  Calling AR_close() will end the
interaction though it can be started again by the client by calling AR_client_open().

If AR_close(true), page will reload, ie. can on to next state gate.  A new reload request URL including
parameters can be specified.

If the server module exits while the interaction is open, the connection is broken and the client will initiate
a re-start - so, don't do it.
*/

function AR_open() {
	ob_clean();
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
}

function AR_send($msg) {
	echo "data: @".$msg."\n\n";
	while (ob_get_level() > 0) {
		ob_end_flush();
	}
  	flush();
	sleep(1); //give it a chance to get there
}

function AR_close($reload=false, $server="*") {
	if ($reload) {
		echo "data: -".$server."\n\n";
	} else {
		echo "data: .\n\n";
	}
	while (ob_get_level() > 0) {
		ob_end_flush();
	}
	flush();
	return;
}

function AR_client() {

	$HTML = array();
	$HTML[] = "var AR_client;";
	$HTML[] = "function AR_client_open(AR_msg='**', server=IAm) {";
	$HTML[] = "	if(typeof(EventSource) === 'undefined') {";
	$HTML[] = "		alert('EventSource not supported');";
	$HTML[] = "		return;";
	$HTML[] = "	}";
	$HTML[] = "	if ((AR_client !== undefined) && (AR_client.readyState == 1)) { //if open";
	$HTML[] = "		alert ('opened');";
	$HTML[] = "		return;";
	$HTML[] = "	}";
	$HTML[] = "	AR_client = new EventSource(server+'&AR_msg='+AR_msg);";
	$HTML[] = "	AR_client.onmessage = function(event) {";
	$HTML[] = "		var resp = event.data;";
	$HTML[] = "		var code = resp.charAt(0);";
	$HTML[] = "		resp = resp.slice(1);";
	$HTML[] = "		switch (code) {";
	$HTML[] = "		case '@': //HTML";
	$HTML[] = "			eval(resp);";
	$HTML[] = "			break;";
	$HTML[] = "		case '.': //the period: we're done";
	$HTML[] = "			AR_client.close();";
	$HTML[] = "			break;";
	$HTML[] = "		case '-': //finish & reload";
	$HTML[] = "			AR_client.close();";
	$HTML[] = "			if (resp !== '*') {";
	$HTML[] = "				window.location.replace(resp);";
	$HTML[] = "			} else {";
	$HTML[] = "				window.location.replace(server);";
	$HTML[] = "			}";
	$HTML[] = "			break;";
	$HTML[] = "		default:";
	$HTML[] = "			alert('Whas up? '+code+resp.substr(0,200));";
	$HTML[] = "		}";
	$HTML[] = "	};";
	$HTML[] = "	AR_client.onerror = function(event) {";
	$HTML[] = "		alert ('AR_client error....');";
	$HTML[] = "		AR_client.close();";
	$HTML[] = "	};";
	$HTML[] = "}";
	return $HTML;

} //function AR_client
?>
