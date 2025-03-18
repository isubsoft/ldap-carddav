<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/



//constants
define('__BASE_DIR__', __DIR__ . '/../..');
define('__DATA_DIR__', __BASE_DIR__ . '/data');
define('__CONF_DIR__', __BASE_DIR__ . '/conf');

require __CONF_DIR__ . '/conf.php';

/* Database */

try {
    $pdo_foreign_keys_enabled = false;
    $pdo_scheme = parse_url($config['sync_database'])['scheme'];
    
    $pdo = new PDO($config['sync_database']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if($pdo_scheme == 'sqlite')
    {
		  // Enabling foreign keys
		  $pdo->exec('PRAGMA foreign_keys = ON');
		  $pdoStmt = $pdo->query('PRAGMA foreign_keys');
		  $pdoStmt->setFetchMode(PDO::FETCH_NUM);
		  
		  foreach ($pdoStmt as $row) {
				if($row[0] == 1)
				{
					$pdo_foreign_keys_enabled = true;
				}
				else
				{
					$pdo_foreign_keys_enabled = false;
					break;
				}
			}
    }
    else if($pdo_scheme == 'mysql' || $pdo_scheme == 'pgsql')
    {
    	$pdo_foreign_keys_enabled = true;
    }
    
    if($pdo_foreign_keys_enabled == false)
    {
			error_log("Foreign key feature is not enabled or could not be enabled in the database (type '$pdo_scheme'). Cannot continue.");
			http_response_code(500);
			exit(1);
    }
    
} catch (\Throwable $th) {
    error_log('Could not create database connection: '. $th->getMessage());
    http_response_code(500);
		exit(1);
}
