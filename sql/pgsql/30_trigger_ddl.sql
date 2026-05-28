/***************************************************************************
*
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
* 
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <https://www.gnu.org/licenses/>.
*
***************************************************************************/

/**************** Triggers functions ******************/

CREATE OR REPLACE FUNCTION cards_addressbook_before()
RETURNS TRIGGER
LANGUAGE PLPGSQL
AS $$
BEGIN
    -- Your trigger logic here
    -- For example, to log changes to another table:
    -- INSERT INTO audit_log (table_name, old_data, new_data)
    -- VALUES (TG_TABLE_NAME, OLD::text, NEW::text);

    -- For BEFORE triggers, you can modify NEW or return NULL to skip the operation.
    -- For AFTER triggers, you typically return NEW or OLD.
    
	IF NEW.user_specific <> '1' AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = '__SYS_USER') AND NOT EXISTS (SELECT 1 FROM cards_system_user) THEN
		INSERT INTO cards_user (user_id) VALUES ('__SYS_USER');
		INSERT INTO cards_system_user (user_id) VALUES ('__SYS_USER');
	END IF;
    
  RETURN NEW; -- Or OLD for DELETE triggers, or NULL to skip the operation for BEFORE triggers
END;
$$;

CREATE OR REPLACE FUNCTION cards_backend_map_before()
RETURNS TRIGGER
LANGUAGE PLPGSQL
AS $$
BEGIN
	IF EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific = '1') AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id) THEN
		INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
    
  RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION cards_full_refresh_before()
RETURNS TRIGGER
LANGUAGE PLPGSQL
AS $$
BEGIN
	IF EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific = '1') AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id) THEN
		INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
    
  RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION cards_backend_sync_before()
RETURNS TRIGGER
LANGUAGE PLPGSQL
AS $$
BEGIN
	IF EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific = '1') AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id) THEN
		INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
    
  RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION cards_full_sync_before()
RETURNS TRIGGER
LANGUAGE PLPGSQL
AS $$
BEGIN
	IF EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific = '1') AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id) THEN
		INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
    
  RETURN NEW;
END;
$$;

/**************** Triggers ******************/

DROP TRIGGER IF EXISTS cards_addressbook_before ON cards_addressbook;
CREATE TRIGGER cards_addressbook_before BEFORE INSERT ON cards_addressbook FOR EACH ROW
EXECUTE FUNCTION cards_addressbook_before();

DROP TRIGGER IF EXISTS cards_backend_map_before ON cards_backend_map;
CREATE TRIGGER cards_backend_map_before BEFORE INSERT ON cards_backend_map FOR EACH ROW
EXECUTE FUNCTION cards_backend_map_before();

DROP TRIGGER IF EXISTS cards_full_refresh_before ON cards_full_refresh;
CREATE TRIGGER cards_full_refresh_before BEFORE INSERT ON cards_full_refresh FOR EACH ROW
EXECUTE FUNCTION cards_full_refresh_before();

DROP TRIGGER IF EXISTS cards_backend_sync_before ON cards_backend_sync;
CREATE TRIGGER cards_backend_sync_before BEFORE INSERT ON cards_backend_sync FOR EACH ROW
EXECUTE FUNCTION cards_backend_sync_before();

DROP TRIGGER IF EXISTS cards_full_sync_before ON cards_full_sync;
CREATE TRIGGER cards_full_sync_before BEFORE INSERT ON cards_full_sync FOR EACH ROW
EXECUTE FUNCTION cards_full_sync_before();
