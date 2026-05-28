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

/**************** Tables ******************/

CREATE TABLE cards_user
(
	user_id VARCHAR(255) NOT NULL,
	CONSTRAINT cards_user_pk PRIMARY KEY (user_id)
);

CREATE TABLE cards_system_user
(
	user_id VARCHAR(255) NOT NULL,
	CONSTRAINT cards_system_user_pk PRIMARY KEY (user_id),
	CONSTRAINT cards_system_user_fk01 FOREIGN KEY (user_id) REFERENCES cards_user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE cards_addressbook
(
	addressbook_id  VARCHAR(255) NOT NULL,
	user_specific CHAR(1) NOT NULL DEFAULT '1',
	writable CHAR(1) NOT NULL DEFAULT '1',
	CONSTRAINT cards_addressbook_pk PRIMARY KEY (addressbook_id)
);

CREATE TABLE cards_backend_map
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	card_uri VARCHAR(255) NOT NULL,
	card_uid VARCHAR(255) NOT NULL,
	backend_id VARCHAR(255) NOT NULL,
	create_sync_token BIGINT NOT NULL,
	modify_sync_token BIGINT NULL,
	delete_sync_token BIGINT NULL,
	CONSTRAINT cards_backend_map_pk PRIMARY KEY (user_id, addressbook_id, card_uri),
	CONSTRAINT cards_backend_map_fk01 FOREIGN KEY(user_id) REFERENCES cards_user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT cards_backend_map_fk02 FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook (addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE UNIQUE INDEX cards_backend_map_uk01 ON cards_backend_map (user_id, addressbook_id, card_uid);
CREATE UNIQUE INDEX cards_backend_map_uk02 ON cards_backend_map (user_id, addressbook_id, backend_id);
CREATE INDEX cards_backend_map_idx01 ON cards_backend_map (user_id, addressbook_id, create_sync_token);
CREATE INDEX cards_backend_map_idx02 ON cards_backend_map (user_id, addressbook_id, modify_sync_token);
CREATE INDEX cards_backend_map_idx03 ON cards_backend_map (user_id, addressbook_id, delete_sync_token);

CREATE TABLE cards_full_refresh
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	sync_token BIGINT NOT NULL,
	CONSTRAINT cards_full_refresh_pk PRIMARY KEY (user_id, addressbook_id),
	CONSTRAINT cards_full_refresh_fk01 FOREIGN KEY(user_id) REFERENCES cards_user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT cards_full_refresh_fk02 FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook (addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE cards_backend_sync
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	sync_token BIGINT NOT NULL,
	CONSTRAINT cards_backend_sync_pk PRIMARY KEY (user_id, addressbook_id),
	CONSTRAINT cards_backend_sync_fk01 FOREIGN KEY(user_id) REFERENCES cards_user(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT cards_backend_sync_fk02 FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook(addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE cards_full_sync
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	sync_token BIGINT NOT NULL,
	CONSTRAINT cards_full_sync_pk PRIMARY KEY (user_id, addressbook_id),
	CONSTRAINT cards_full_sync_fk01 FOREIGN KEY(user_id) REFERENCES cards_user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT cards_full_sync_fk02 FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook (addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE propertystorage (
	id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
	path TEXT NOT NULL,
	name TEXT NOT NULL,
	valuetype INTEGER,
	value MEDIUMBLOB
);
CREATE UNIQUE INDEX path_property ON propertystorage (path(600), name(100));

CREATE TABLE entity_cache (
	entity_id VARCHAR(255) NOT NULL PRIMARY KEY,
	backend_id VARCHAR(255)
);
