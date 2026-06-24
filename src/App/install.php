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

$installDbDdlFiles = [];

foreach(glob(__BASE_DIR__ . "/sql/" . $$pdo_scheme . "/*_ddl.sql", GLOB_ERR) as $ddlSqlFile)
	$installDbDdlFiles[] = $ddlSqlFile;

if($installDbDdlFiles == []) {
	echo "[INFO] No install steps defined for '$pdo_scheme' database product.";
	exit(1);
}

try {
	foreach ($installDbDdlFiles as $ddlSqlFile)
		echo "[INFO] Executing DDL statements from file - '$ddlSqlFile'";
		$pdo->exec(file_get_contents($ddlSqlFile));
} 
catch (\Throwable $th) {
	trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
	exit(1);
}

exit;
