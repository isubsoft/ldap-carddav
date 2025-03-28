/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/**************** Tables ******************/

CREATE TABLE cards_user
(
	user_id VARCHAR(255) NOT NULL,
	PRIMARY KEY (user_id)
);

CREATE TABLE cards_system_user
(
	user_id VARCHAR(255) NOT NULL,
	PRIMARY KEY (user_id),
	FOREIGN KEY(user_id) REFERENCES cards_user(user_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE cards_addressbook
(
	addressbook_id  VARCHAR(255) NOT NULL,
	user_specific BOOL NOT NULL DEFAULT true,
	writable BOOL NOT NULL DEFAULT true,
	PRIMARY KEY (addressbook_id)
);

CREATE TABLE cards_backend_map
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	card_uri VARCHAR(255) NOT NULL,
	card_uid VARCHAR(255) NOT NULL,
	backend_id VARCHAR(255) NOT NULL,
	PRIMARY KEY (user_id, addressbook_id, card_uri),
	FOREIGN KEY(user_id) REFERENCES cards_user(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook(addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE UNIQUE INDEX cards_backend_map_uk01 ON cards_backend_map (user_id, addressbook_id, card_uid);
CREATE UNIQUE INDEX cards_backend_map_uk02 ON cards_backend_map (user_id, addressbook_id, backend_id);

CREATE TABLE cards_deleted
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	card_uri VARCHAR(255) NOT NULL,
	sync_token BIGINT NOT NULL,
	FOREIGN KEY(user_id) REFERENCES cards_user(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook(addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX cards_deleted_idx01 ON cards_deleted (user_id, addressbook_id, sync_token);

CREATE TABLE cards_full_sync
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	sync_token BIGINT NOT NULL,
	PRIMARY KEY (user_id, addressbook_id),
	FOREIGN KEY(user_id) REFERENCES cards_user(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook(addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
);

/**************** Triggers ******************/

DROP TRIGGER IF EXISTS cards_addressbook_before;
CREATE TRIGGER cards_addressbook_before BEFORE INSERT ON cards_addressbook FOR EACH ROW WHEN NOT NEW.user_specific AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = '__SYS_USER') AND NOT EXISTS (SELECT 1 FROM cards_system_user)
BEGIN
	INSERT INTO cards_user (user_id) VALUES ('__SYS_USER');
	INSERT INTO cards_system_user (user_id) VALUES ('__SYS_USER');
END;

DROP TRIGGER IF EXISTS cards_backend_map_before;
CREATE TRIGGER cards_backend_map_before BEFORE INSERT ON cards_backend_map FOR EACH ROW WHEN EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific) AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id)
BEGIN
	INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
END;

DROP TRIGGER IF EXISTS cards_full_sync_before;
CREATE TRIGGER cards_full_sync_before BEFORE INSERT ON cards_full_sync FOR EACH ROW WHEN EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific) AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id)
BEGIN
	INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
END;
