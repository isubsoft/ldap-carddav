CREATE TABLE deleted_cards 
(
	sync_token BIGINT NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	uri VARCHAR(255) NOT NULL
);
CREATE INDEX idx ON deleted_cards (sync_token, addressbook_id);