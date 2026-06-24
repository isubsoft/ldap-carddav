<?php 

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

/**
* This script is used to upgrade sync database.
**/

/*import database connection*/
require_once __DIR__ . '/include/bootstrap.php';

$syncDbVersion = [
	'major'    => 2,
	'minor'    => 0,
	'revision' => 0
];

$upgradeCompatibleSyncDbVersion = [
	'major'    => null,
	'minor'    => null,
	'revision' => null
];

function mysqlFileGetContents($filename)
{
	$fileContents = null;
	
	foreach(file($filename) as $line) {
		if(preg_match('#^\\s*DELIMITER\\s+.*$#i', $line))
			continue;
			
		$fileContents = $fileContents . preg_replace('#(\\s+)END\\s*//|^(\\s*)END\\s*//#i', '$1END;', $line);
	}
	
	return $fileContents;
}

$createDbVersionTableStmt = "CREATE TABLE schema_version (key_name VARCHAR(32) NOT NULL, key_value INTEGER NOT NULL DEFAULT 0)";

foreach($syncDbVersion as $key_name => $key_value)
	$updateDbVersionStmt[] = "INSERT INTO schema_version (key_name, key_value) VALUES ('" . $key_name . "', " . $key_value . ")";

$upgradeDbStatements = [
	'mysql' => [
		$createDbVersionTableStmt,
		"ALTER TABLE cards_addressbook MODIFY COLUMN user_specific CHAR(1) NOT NULL DEFAULT '1', MODIFY COLUMN writable CHAR(1) NOT NULL DEFAULT '1'",
		"ALTER TABLE propertystorage MODIFY COLUMN path TEXT NOT NULL, MODIFY COLUMN name TEXT NOT NULL, MODIFY COLUMN valuetype INTEGER",
		"ALTER TABLE propertystorage DROP INDEX path_property, ADD UNIQUE INDEX path_property (path(600), name(100))",
		mysqlFileGetContents(__BASE_DIR__ . "/sql/mysql/30_trigger_ddl.sql"),
	],
	'pgsql' => [
		$createDbVersionTableStmt,
		"ALTER TABLE cards_addressbook ALTER COLUMN user_specific TYPE CHAR(1) USING (user_specific::INTEGER), ALTER COLUMN user_specific SET DEFAULT '1', ALTER COLUMN writable TYPE CHAR(1) USING (writable::INTEGER), ALTER COLUMN writable SET DEFAULT '1'",
		"ALTER TABLE propertystorage ALTER COLUMN path TYPE TEXT, ALTER COLUMN name TYPE TEXT, ALTER COLUMN valuetype TYPE INTEGER",
		file_get_contents(__BASE_DIR__ . "/sql/pgsql/30_trigger_ddl.sql"),
	],
	'sqlite' => [
		$createDbVersionTableStmt,
		"ALTER TABLE cards_addressbook RENAME COLUMN user_specific TO old_user_specific",
		"ALTER TABLE cards_addressbook RENAME COLUMN writable TO old_writable",
		"ALTER TABLE cards_addressbook ADD COLUMN user_specific CHAR(1) NOT NULL DEFAULT '1'",
		"ALTER TABLE cards_addressbook ADD COLUMN writable CHAR(1) NOT NULL DEFAULT '1'",
		"UPDATE cards_addressbook SET user_specific = old_user_specific, writable = old_writable",
		"ALTER TABLE propertystorage RENAME COLUMN valuetype TO old_valuetype",
		"ALTER TABLE propertystorage RENAME COLUMN value TO old_value",
		"ALTER TABLE propertystorage ADD COLUMN valuetype INTEGER",
		"ALTER TABLE propertystorage ADD COLUMN value BLOB",
		"UPDATE propertystorage SET value = old_value",
		file_get_contents(__BASE_DIR__ . "/sql/sqlite/30_trigger_ddl.sql"),
		"ALTER TABLE cards_addressbook DROP COLUMN old_user_specific",
		"ALTER TABLE cards_addressbook DROP COLUMN old_writable",
		"ALTER TABLE propertystorage DROP COLUMN old_valuetype",
		"ALTER TABLE propertystorage DROP COLUMN old_value"
	]
];

$pdoDriverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if (!isset($upgradeDbStatements[$pdoDriverName]) || !is_array($upgradeDbStatements[$pdoDriverName])) {
	echo "[INFO] No upgrade steps defined for '$pdoDriverName' database product.";
	exit(1);
}

try {
	foreach ($upgradeDbStatements[$pdoDriverName] as $stmt)
		$pdo->exec($stmt);
		
	foreach ($updateDbVersionStmt as $stmt)
		$pdo->exec($stmt);
} 
catch (\Throwable $th) {
	trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
	exit(1);
}

exit;
