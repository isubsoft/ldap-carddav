CREATE TABLE schema_version
(
	key_name VARCHAR(32) NOT NULL,
	key_value INTEGER NOT NULL DEFAULT 0,
	PRIMARY KEY (key_name)
);

ALTER TABLE cards_addressbook RENAME COLUMN user_specific TO old_user_specific;
ALTER TABLE cards_addressbook RENAME COLUMN writable TO old_writable;
ALTER TABLE cards_addressbook ADD COLUMN user_specific CHAR(1) NOT NULL DEFAULT '1';
ALTER TABLE cards_addressbook ADD COLUMN writable CHAR(1) NOT NULL DEFAULT '1';
UPDATE cards_addressbook SET user_specific = old_user_specific, writable = old_writable;
ALTER TABLE propertystorage RENAME COLUMN valuetype TO old_valuetype;
ALTER TABLE propertystorage RENAME COLUMN value TO old_value;
ALTER TABLE propertystorage ADD COLUMN valuetype INTEGER;
ALTER TABLE propertystorage ADD COLUMN value BLOB;
UPDATE propertystorage SET valuetype = old_valuetype, value = old_value;
