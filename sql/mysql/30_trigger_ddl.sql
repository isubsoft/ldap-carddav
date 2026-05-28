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

/**************** Triggers ******************/

DROP TRIGGER IF EXISTS cards_addressbook_before;
DELIMITER //
CREATE TRIGGER cards_addressbook_before BEFORE INSERT ON cards_addressbook FOR EACH ROW 
BEGIN
	IF (NEW.user_specific <> '1' AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = '__SYS_USER') AND NOT EXISTS (SELECT 1 FROM cards_system_user)) THEN 
		INSERT INTO cards_user (user_id) VALUES ('__SYS_USER');
		INSERT INTO cards_system_user (user_id) VALUES ('__SYS_USER');
	END IF;
END //
DELIMITER ;

DROP TRIGGER IF EXISTS cards_backend_map_before;
DELIMITER //
CREATE TRIGGER cards_backend_map_before BEFORE INSERT ON cards_backend_map FOR EACH ROW
BEGIN
	IF (EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific = '1') AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id)) THEN 
		INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
END //

DELIMITER ;

DROP TRIGGER IF EXISTS cards_full_refresh_before;
DELIMITER //
CREATE TRIGGER cards_full_refresh_before BEFORE INSERT ON cards_full_refresh FOR EACH ROW
BEGIN
	IF (EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific = '1') AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id)) THEN 
		INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
END //
DELIMITER ;

DROP TRIGGER IF EXISTS cards_backend_sync_before;
DELIMITER //
CREATE TRIGGER cards_backend_sync_before BEFORE INSERT ON cards_backend_sync FOR EACH ROW
BEGIN
	IF (EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific = '1') AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id)) THEN 
		INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
END //
DELIMITER ;

DROP TRIGGER IF EXISTS cards_full_sync_before;
DELIMITER //
CREATE TRIGGER cards_full_sync_before BEFORE INSERT ON cards_full_sync FOR EACH ROW
BEGIN
	IF (EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific = '1') AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id)) THEN 
		INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
END //
DELIMITER ;
