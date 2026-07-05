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
	'major'    => 1,
	'minor'    => 1,
	'revision' => 0
];

$upgradeCompatibleSyncDbVersion = [
	'major'    => null,
	'minor'    => null,
	'revision' => null
];

$currentSyncDbVersion = [];

try {
	$pdo->exec("SELECT 1 FROM schema_version");
}
catch (\Throwable $th) {
	$currentSyncDbVersion = [
		'major'    => null,
		'minor'    => null,
		'revision' => null
	];
}

if($currentSyncDbVersion == []) {
	try {
		$query = 'SELECT key_name, key_value FROM schema_version';
		$stmt = $pdo->prepare($query);
		$stmt->execute();
		
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row)
			$currentSyncDbVersion[$row['key_name']] = $row['key_value'];
	}
	catch (\Throwable $th)
	{
		trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
		exit(1);
	}
}

// No upgrades are necessary if current syncdb schema version (major and minor) is equal to upgraded application syncdb schema version
if (isset($currentSyncDbVersion['major']) && $currentSyncDbVersion['major'] = $syncDbVersion['major']) {
	if (isset($currentSyncDbVersion['minor']) && $currentSyncDbVersion['minor'] = $syncDbVersion['minor']) {
		echo "[INFO] Sync database schema is up to date with current application version. No upgrade performed." . PHP_EOL;
		exit;
	}
}
elseif (isset($currentSyncDbVersion['major']) && ($currentSyncDbVersion['major'] > $syncDbVersion['major'] || ($currentSyncDbVersion['major'] = $syncDbVersion['major'] && isset($currentSyncDbVersion['minor']) && $currentSyncDbVersion['minor'] > $syncDbVersion['minor'] ))) {
	error_log("[ERROR] Sync database schema is incompatible with current application version. No upgrade will be performed.");
	exit(1);
}

$upgradeDbSqlFile = [];

if($pdo_scheme == 'mysql') {
	$upgradeDbSqlFile = [
		__BASE_DIR__ . "/upgrade/sql/" . $pdo_scheme . "/10_ddl.sql",
		__BASE_DIR__ . "/sql/" . $pdo_scheme . "/30_trigger_ddl.sql",
		__BASE_DIR__ . "/sql/" . $pdo_scheme . "/90_data_seed_dml.sql"
	];
}
elseif($pdo_scheme == 'pgsql') {
	$upgradeDbSqlFile = [
		__BASE_DIR__ . "/upgrade/sql/" . $pdo_scheme . "/10_ddl.sql",
		__BASE_DIR__ . "/sql/" . $pdo_scheme . "/30_trigger_ddl.sql",
		__BASE_DIR__ . "/sql/" . $pdo_scheme . "/90_data_seed_dml.sql"
	];
}
elseif($pdo_scheme == 'sqlite') {
	$upgradeDbSqlFile = [
		__BASE_DIR__ . "/upgrade/sql/" . $pdo_scheme . "/10_stage_1_ddl.sql",
		__BASE_DIR__ . "/sql/" . $pdo_scheme . "/30_trigger_ddl.sql",
		__BASE_DIR__ . "/upgrade/sql/" . $pdo_scheme . "/10_stage_2_ddl.sql",
		__BASE_DIR__ . "/sql/" . $pdo_scheme . "/90_data_seed_dml.sql"
	];
}

echo "[INFO] Performing sync database schema version upgrade from " . (json_encode($currentSyncDbVersion)) . " => " .  (json_encode($syncDbVersion)) . PHP_EOL;

echo "[INFO] Sync database upgrade can be a long running process so, kindly standby." . PHP_EOL;

try {
	foreach ($upgradeDbSqlFile as $sqlFile) {
		echo "[INFO] Executing SQL statements from file - '$sqlFile'" . PHP_EOL;
		$pdo->exec(file_get_contents($sqlFile));
	}
} 
catch (\Throwable $th) {
	trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
	exit(1);
}

echo "[INFO] Upgrade complete." . PHP_EOL;

exit;
