/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

CREATE TABLE cards_deleted 
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	card_uri VARCHAR(255) NOT NULL,
	sync_token BIGINT NOT NULL
);
CREATE INDEX cards_deleted_idx01 ON cards_deleted (user_id, addressbook_id, sync_token);

CREATE TABLE cards_backend_map
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	card_uri VARCHAR(255) NOT NULL,
	card_uid VARCHAR(255) NOT NULL,
	backend_id VARCHAR(255) NOT NULL,
	PRIMARY KEY (user_id, addressbook_id, card_uri)
);
CREATE UNIQUE INDEX cards_backend_map_uk01 ON cards_backend_map (user_id, addressbook_id, card_uid);
CREATE UNIQUE INDEX cards_backend_map_uk02 ON cards_backend_map (user_id, addressbook_id, backend_id);

CREATE TABLE cards_full_sync
(
	addressbook_id  VARCHAR(255) NOT NULL PRIMARY KEY,
	full_sync_ts BIGINT NOT NULL
);
