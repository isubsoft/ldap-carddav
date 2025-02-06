<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

namespace isubsoft\App;


class Bootstrap{

	private $pdo;

	// Custom init method to initialize the connection
	public function init($config)
	{
		// Create a new database connection using PDO
		try {
			$pdo_foreign_keys_enabled = false;
			$pdo_scheme = parse_url($config['database'])['scheme'];
			
			$this->pdo = new \PDO($config['database']);
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			
			if($pdo_scheme == 'sqlite')
			{
				  // Enabling foreign keys
				  $this->pdo->exec('PRAGMA foreign_keys = ON');
				  $pdoStmt = $this->pdo->query('PRAGMA foreign_keys');
				  $pdoStmt->setFetchMode(\PDO::FETCH_NUM);
				  
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
			
			return $this->pdo;

		} catch (\Throwable $th) {
			error_log('Could not create database connection: '. $th->getMessage());
			http_response_code(500);
				exit(1);
		}
	}
}
?>