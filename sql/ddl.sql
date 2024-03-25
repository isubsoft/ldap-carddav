CREATE TABLE cards_deleted 
(
	sync_token BIGINT NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	user_id VARCHAR(255) NOT NULL,
	card_uri VARCHAR(255) NOT NULL
);
CREATE INDEX idx ON cards_deleted (sync_token, addressbook_id);

CREATE TABLE cards_backend_map
(
	card_uri VARCHAR(255) NOT NULL,
	backend_id VARCHAR(255) NOT NULL UNIQUE,
	addressbook_id  VARCHAR(255) NOT NULL,
	user_id VARCHAR(255) NOT NULL
);
CREATE INDEX idx2 ON cards_backend_map (card_uri, backend_id);

CREATE TABLE full_sync
(
	addressbook_id  VARCHAR(255) NOT NULL,
	full_sync_timestamp BIGINT NOT NULL
);