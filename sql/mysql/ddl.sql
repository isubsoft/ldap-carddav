/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

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
	user_specific BOOL NOT NULL DEFAULT true,
	writable BOOL NOT NULL DEFAULT true,
	CONSTRAINT cards_addressbook_pk PRIMARY KEY (addressbook_id)
);

CREATE TABLE cards_backend_map
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	card_uri VARCHAR(255) NOT NULL,
	card_uid VARCHAR(255) NOT NULL,
	backend_id VARCHAR(255) NOT NULL,
	CONSTRAINT cards_backend_map_pk PRIMARY KEY (user_id, addressbook_id, card_uri),
	CONSTRAINT cards_backend_map_fk01 FOREIGN KEY(user_id) REFERENCES cards_user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT cards_backend_map_fk02 FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook (addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE UNIQUE INDEX cards_backend_map_uk01 ON cards_backend_map (user_id, addressbook_id, card_uid);
CREATE UNIQUE INDEX cards_backend_map_uk02 ON cards_backend_map (user_id, addressbook_id, backend_id);

CREATE TABLE cards_deleted
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	card_uri VARCHAR(255) NOT NULL,
	sync_token BIGINT NOT NULL,
	CONSTRAINT cards_deleted_fk01 FOREIGN KEY(user_id) REFERENCES cards_user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT cards_deleted_fk02 FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook (addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE INDEX cards_deleted_idx01 ON cards_deleted (user_id, addressbook_id, sync_token);

CREATE TABLE cards_full_refresh
(
	user_id VARCHAR(255) NOT NULL,
	addressbook_id  VARCHAR(255) NOT NULL,
	sync_token BIGINT NOT NULL,
	CONSTRAINT cards_full_refresh_pk PRIMARY KEY (user_id, addressbook_id),
	CONSTRAINT cards_full_refresh_fk01 FOREIGN KEY(user_id) REFERENCES cards_user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT cards_full_refresh_fk02 FOREIGN KEY(addressbook_id) REFERENCES cards_addressbook (addressbook_id) ON DELETE CASCADE ON UPDATE CASCADE
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


/**************** Triggers ******************/

DROP TRIGGER IF EXISTS cards_addressbook_before;
DELIMITER //
CREATE TRIGGER cards_addressbook_before BEFORE INSERT ON cards_addressbook FOR EACH ROW 
BEGIN
	if (NOT NEW.user_specific AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = '__SYS_USER') AND NOT EXISTS (SELECT 1 FROM cards_system_user)) THEN 
             INSERT INTO cards_user (user_id) VALUES ('__SYS_USER');
			INSERT INTO cards_system_user (user_id) VALUES ('__SYS_USER');
	END IF;
END //
DELIMITER ;

DROP TRIGGER IF EXISTS cards_backend_map_before;
DELIMITER //
CREATE TRIGGER cards_backend_map_before BEFORE INSERT ON cards_backend_map FOR EACH ROW
BEGIN
	if (EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific) AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id)) THEN 
             INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
END //

DELIMITER ;

DROP TRIGGER IF EXISTS cards_full_refresh_before;
DELIMITER //
CREATE TRIGGER cards_full_refresh_before BEFORE INSERT ON cards_full_refresh FOR EACH ROW
BEGIN
	if (EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific) AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id)) THEN 
             INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
END //
DELIMITER ;

DROP TRIGGER IF EXISTS cards_full_sync_before;
DELIMITER //
CREATE TRIGGER cards_full_sync_before BEFORE INSERT ON cards_full_sync FOR EACH ROW
BEGIN
	if (EXISTS (SELECT 1 FROM cards_addressbook WHERE addressbook_id = NEW.addressbook_id AND user_specific) AND NOT EXISTS (SELECT 1 FROM cards_user WHERE user_id = NEW.user_id)) THEN 
             INSERT INTO cards_user (user_id) VALUES (NEW.user_id);
	END IF;
END //
DELIMITER ;
