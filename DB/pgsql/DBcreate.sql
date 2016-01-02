-- Creating a DB will probably be done via some setup routine.  This example from
-- the original Timesheets does, however, show you what must be done.

-- Role: "ts_admin"
-- DROP ROLE ts_admin;
CREATE ROLE ts_admin LOGIN PASSWORD 'ts*admin'
  NOSUPERUSER INHERIT NOCREATEDB NOCREATEROLE;
GRANT cl_dbadmins TO ts_admin;

-- Role: "ts_editor"
-- DROP ROLE ts_editor;
CREATE ROLE ts_editor LOGIN PASSWORD 'ts*editor'
  NOSUPERUSER INHERIT NOCREATEDB NOCREATEROLE;
GRANT cl_dbeditors TO ts_editor;

-- Role: "ts_reader"
-- DROP ROLE ts_reader;
CREATE ROLE ts_reader LOGIN PASSWORD 'ts*reader'
  NOSUPERUSER INHERIT NOCREATEDB NOCREATEROLE;
GRANT cl_dbreaders TO ts_reader;

-- Tablespace: timesheets
-- DROP TABLESPACE timesheets;
-- must give postgres ownership: sudo chown postgres <directory name>
CREATE TABLESPACE timesheets
       OWNER ts_admin
       LOCATION '/home/common/BikeStuff/SR2S/SR2S_Timesheets/DB/Data/postgreSQL';

-- Database: timesheets
-- DROP DATABASE timesheets;
CREATE DATABASE timesheets
  WITH OWNER = ts_admin
       TABLESPACE = timesheets
       ENCODING = 'UTF8'
       LC_COLLATE = 'en_US.UTF-8'
       LC_CTYPE = 'en_US.UTF-8'
       CONNECTION LIMIT = -1;

