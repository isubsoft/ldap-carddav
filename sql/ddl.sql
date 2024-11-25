CREATE TABLE cards_deleted 
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	sync_token BIGINT NOT NULL,
	card_uri VARCHAR(255) NOT NULL UNIQUE
);
CREATE INDEX idx01 ON cards_deleted (sync_token, addressbook_id);

CREATE TABLE cards_backend_map
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	card_uri VARCHAR(255) NOT NULL UNIQUE,
	card_uid VARCHAR(255) NOT NULL UNIQUE,
	backend_id VARCHAR(255) NOT NULL 
	
);
CREATE UNIQUE INDEX idx02 ON cards_backend_map (card_uri, backend_id);

CREATE TABLE cards_full_sync
(
	addressbook_id  VARCHAR(255) NOT NULL PRIMARY KEY,
	full_sync_ts BIGINT NOT NULL
);