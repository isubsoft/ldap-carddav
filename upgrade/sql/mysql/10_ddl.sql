CREATE TABLE schema_version
(
	key_name VARCHAR(32) NOT NULL PRIMARY KEY,
	key_value INTEGER NOT NULL DEFAULT 0
);

ALTER TABLE cards_addressbook MODIFY COLUMN user_specific CHAR(1) NOT NULL DEFAULT '1', MODIFY COLUMN writable CHAR(1) NOT NULL DEFAULT '1';
ALTER TABLE propertystorage MODIFY COLUMN path TEXT NOT NULL, MODIFY COLUMN name TEXT NOT NULL, MODIFY COLUMN valuetype INTEGER;
ALTER TABLE propertystorage DROP INDEX path_property, ADD UNIQUE INDEX path_property (path(600), name(100));
