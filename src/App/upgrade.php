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
* This script is used to manage sync database.
**/

function printHelp($argv)
{
	error_log("Usage: " . $argv[0] . " action [parameters]");
	error_log("");
	error_log("-- Actions");
	error_log("help:             Print this help and exit.");
	error_log("init:             Initialize sync database.");
	error_log("manage (default): Manage objects in sync database.");
	error_log("housekeeping:     Physically delete logically deleted records.");
	error_log("");
	error_log("-- Parameter(s) for action manage. Omitting any optional parameter below may turn on interactive mode to obtain it.");
	error_log("  user: (optional) Manage a user. Currently this parameter also implies the below 'delete' parameter.");
	error_log("    delete: (optional) Delete a user.");
	error_log("      <user_id>: (optional) entryUUID of the user from backend. WARNING: If this parameter is provided no confirmation will be taken before execution. So use this parameter only in a non-interactive or batch process.");
	error_log("");
	error_log("  addressbook: (optional) Manage an address book.");
	error_log("    list:   (optional) List address book(s) present in sync database.");
	error_log("    add:    (optional) Add an address book.");
	error_log("    rename: (optional) Rename an address book.");
	error_log("    delete: (optional) Delete an address book.");
	error_log("");
	error_log("-- Parameter(s) for action housekeeping");
	error_log("  <batch_size>: (optional, integer) Restrict action to maximum of these many items. Should be >= 1, defaults to 1000. Since this action can be time consuming set this parameter to a value in range 1000 to 10000 to be efficient. Avoid setting this to a very small or very large value as it may cause performance issues.");
	
	return;
}

/*import database connection*/
require_once __DIR__ . '/include/bootstrap.php';

$syncDbVersion = [
	'major' => 2,
	'minor' => 0,
	'dot'   => 0
];

$upgradeCompatibleSyncDbVersion = [
	'major' => null,
	'minor' => null,
	'dot'   => null
];

function mysqlFileGetContents($filename)
{
	$fileContents = null;
	
	foreach(file($filename) as $line) {
		if(preg_match('#^\s*DELIMITER\s+.*$#i', $line))
			continue;
			
		$fileContents = $fileContents . preg_replace('#(\s+)END\s*//|^(\s*)END\s*//#i', '$1END;', $line);
	}
	
	return $fileContents;
}

$upgradeDbStatements = [
	'mysql' => [ 
		"ALTER TABLE cards_addressbook MODIFY COLUMN user_specific CHAR(1) NOT NULL DEFAULT '1', MODIFY COLUMN writable CHAR(1) NOT NULL DEFAULT '1'",
		"ALTER TABLE propertystorage MODIFY COLUMN path TEXT NOT NULL, MODIFY COLUMN name TEXT NOT NULL, MODIFY COLUMN valuetype INTEGER",
		mysqlFileGetContents(__BASE_DIR__ . "/sql/mysql/30_trigger_ddl.sql"),
	],
	'pgsql' => [
		"ALTER TABLE cards_addressbook ALTER COLUMN user_specific TYPE CHAR(1) USING (user_specific::INTEGER), ALTER COLUMN user_specific SET DEFAULT '1', ALTER COLUMN writable TYPE CHAR(1) USING (writable::INTEGER), ALTER COLUMN writable SET DEFAULT '1'",
		"ALTER TABLE propertystorage ALTER COLUMN path TYPE TEXT, ALTER COLUMN name TYPE TEXT, ALTER COLUMN valuetype TYPE INTEGER",
		file_get_contents(__BASE_DIR__ . "/sql/pgsql/30_trigger_ddl.sql"),
	],
	'sqlite' => [
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
} 
catch (\Throwable $th) {
	trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
	exit(1);
}

exit;
