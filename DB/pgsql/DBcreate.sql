-- Creating a DB will probably be done via some setup routine.  This example from
-- the original Timesheets does, however, show you what must be done.

-- Rather than creating separate cluster/DB owners, just default to postgres as owner.
-- Use the default tablespace.
-- The quotes around the name preserve upper case.
-- Database: SR2S_PL_dev
-- DROP DATABASE "SR2S_PL_dev";
CREATE DATABASE "SR2S_PL_dev"
  WITH ENCODING = 'UTF8'
       LC_COLLATE = 'en_US.UTF-8'
       LC_CTYPE = 'en_US.UTF-8'
       CONNECTION LIMIT = -1;

-- In the old configuration, we created cluster users (cl_???) then inherited
-- privileges from them then created the DB.  Here, we first create the DB then assign
-- privileges by database (in phppgadmin, be sure to select the database, ie. don't default
-- to postgres):

-- Role: "pl_admin"
-- DROP ROLE pl_admin;
CREATE ROLE pl_admin LOGIN PASSWORD 'pl*admin'
  NOSUPERUSER INHERIT NOCREATEDB NOCREATEROLE;
GRANT ALL ON ALL TABLES IN SCHEMA public TO pl_admin;

-- Role: "pl_editor"
-- DROP ROLE pl_editor;
CREATE ROLE pl_editor LOGIN PASSWORD 'pl*editor'
  NOSUPERUSER INHERIT NOCREATEDB NOCREATEROLE;
GRANT SELECT, UPDATE, INSERT, DELETE ON ALL TABLES IN SCHEMA public TO pl_editor;

-- Role: "pl_reader"
-- DROP ROLE pl_reader;
CREATE ROLE pl_reader LOGIN PASSWORD 'pl*reader'
  NOSUPERUSER INHERIT NOCREATEDB NOCREATEROLE;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO pl_reader;

