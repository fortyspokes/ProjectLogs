; <?php header("Location: https://".$_SERVER["HTTP_HOST"]); ?> prevents hacking
;copyright 2015-2016,2018 C.D.Price. Licensed under Apache License, Version 2.0
;See license text at http://www.apache.org/licenses/LICENSE-2.0

;                          The site specific configuration file

;The startup index.php will look for this file starting at $_SERVER['SCRIPT_FILENAME']'s directory.
;(See php.net -> Documentation -> Language Reference: Predefined Variables for $_SERVER documentation.)
;Once found, this file's directory is the root ($_SESSION["OUR_ROOT"]) directory for the site.  We then
;parse it into an array which is saved as $_SESSION["_SITE_CONF"].  ($_SESSION documentation is found
;at the same page as that for $_SERVER.)

;The here documented parms (left side of the equation) are reserved words.  The values specified are
;for example purposes only.

;A superuser can inspect these parms online using the "Site Config" menu item.  They can also be
;changed for a specific session (ie. changing a parm applies only to the signed on superuser) - BE
;CAREFUL!

_MORE[] = ../offline/more_conf.php
_MORE[] = ../offline/evenmore_conf.php
;The _MORE files will be parsed after this file allowing them to be put somewhere out of danger,
;ie. not under the Document Root.  Note that the brackets indicate that _MORE[] specifies an array of
;files with each _MORE[] being an element in that array.  If a parm is duplicated, either within a file
;or between files, the value of the last one specified is active.  These files are found by appending to
;OUR_ROOT.

;The rest of the parms described here will typically be defined in a _MORE[] file:

;Note that "[ROOT]" prepended to any parm will be replaced with the value of $_SESSION["OUR_ROOT"].

_INCLUDE[] = [ROOT]../offline/lib
_INCLUDE[] = [ROOT]../offline/main
;The paths in the _INCLUDE[] array are added to the PHP include paths for a given session.

_EXTENSIONS = [ROOT]../extension/
;Directory of subtask extension modules - needs ending backslash

_STASH = [ROOT]../stash
;Logs_Prune puts backup files under <_SESSION["_STASH"]>./prunings/
;Save/Refresh puts saved.csv files under <_SESSION["_STASH"]>./refresh/

PAGETITLE = "Project Logs (development)"

THEME=grays
;The initial css theme subdirectory; can be overidden by preferences.  These THEMEs and their
;subdirectories are installation dependent.

RUNLEVEL=1
;0=production; 1=development; 2=beta/demo: controls things like menu items and STATE display
;When the production system has an error, the superuser can change this parm online to "1" to see the
;displayed error message.

TZO=-5 ;US East Coast
;TimeZoneOffset from Greenwich, in hours, of the server time; west is negative

DBMANAGER=pgsql
;DBMANAGER=mysql
;The database module is lib/db_<_SESSION["DBMANAGER"]>.php.

DBPREFIX = ""
;DBPREFIX = "t_"
;The DBPREFIX is appended to the name of each table in the DB to create 'virtual' DBs and is required
;(can, of course, be an empty string as shown above).  It is reccommended to use only non-empty
;prefixes in development so that any reference in SQL code to a non-prefixed table/view name will
;error out (it definitely does happen).

DBCONN="host=localhost;dbname=ProjectLogs" ;NOTE: order of variables is important to mysql
;The DB connection string passed to the database module's object construction.

DBREADER=":ts_reader:ts*reader" ;Limited to reading DB info (not presently used by the system).
DBEDITOR="!ts_editor!ts*editor" ;The normal user's DB access: can change data but not DB structure
DBADMIN="*ts_admin*ts!admin"    ;An admin level used only by a superuser
;These parms are the three DB access levels as defined in the DB.  The first char of the value is the
;delimiter between following user and password.

