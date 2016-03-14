; <?php header("Location: https://".$_SERVER["HTTP_HOST"]); ?> prevents hacking
;copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
;See license text at http://www.apache.org/licenses/LICENSE-2.0

;                          The site specific configuration file

;The startup index.php will look for this file starting at SCRIPT_FILENAME's directory,
;assume its directory is the root (OUR_ROOT) directory for the site, and will parse it into
;an array which is then saved as $_SESSION["_SITE_CONF"].

_MORE[] = ../offline/more_conf.php
_MORE[] = ../offline/evenmore_conf.php
;The _MORE files will be parsed after this file allowing them to be put somewhere out of danger,
;ie. not under the Document Root.  Note that _MORE[] specifies an array of files.  If a value is
;duplicated, either within a file or between files, the last one specified is active.  These
;files are found by appending to OUR_ROOT.

;The following parms will typically be defined in a _MORE file:
;Note that "[ROOT]" prepended to any parm will be replaced with OUR_ROOT.

_INCLUDE[] = [ROOT]../offline/lib
_INCLUDE[] = [ROOT]../offline/main
;The _INCLUDE paths will be added to the PHP include path.

_EXTENSIONS = [ROOT]../extension/
;directory of subtask extension modules - needs ending backslash

_STASH = [ROOT]../stash
;Logs_Prune puts backup files here under ./prunings/
;Save/Refresh puts saved.csv files here under ./refresh/

PAGETITLE = "Project Logs (development)"

THEME=grays
;the initial css theme subdirectory; can be overidden by preferences

RUNLEVEL=1
;0=production; 1=development; 2=beta/demo: controls things like menu items and STATE display

TZO=-5
;TimeZoneOffset from Greenwich, in hours, of the server time; west is negative
;test a TZ on US East Coast

DBMANAGER=pgsql
;DBMANAGER=mysql
DBPREFIX = ""
;DBPREFIX = "t_"
;the DBPREFIX is appended to the name of each table in the DB to create 'virtual' DBs and is required
DBCONN="host=localhost;dbname=ProjectLogs" ;NOTE: order of variables is important to mysql
;The first char is the delimiter between user and password:
DBREADER=":ts_reader:ts*reader"
DBEDITOR=":ts_editor:ts*editor"

