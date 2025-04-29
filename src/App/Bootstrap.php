<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

function replacePlaceholder (string $placeholder, string $replacement, string $subject)
{
	$placeholderEscChar = substr($placeholder, 0, 1);
	$exprMatches = [];

	if(preg_match_all('#(' . $placeholderEscChar . '*)(' . $placeholder . ')#', $subject, $exprMatches, PREG_OFFSET_CAPTURE) > 0)
	{
		$strOffset = 0;
		$replacedStr = '';
		
		foreach($exprMatches[0] as $matchedNum => $exprMatch)
		{
			$tmpStr = substr($subject, $strOffset, $exprMatch[1] - $strOffset + strlen($exprMatch[0]));
			$strOffset += strlen($tmpStr);
			
			if(substr_count($exprMatch[0], $placeholderEscChar) % 2 === 1)
				$tmpStr = str_replace($exprMatch[0], str_replace($placeholder, $replacement, str_replace($placeholderEscChar . $placeholderEscChar, $placeholderEscChar, $exprMatch[0])), $tmpStr);
			else
				$tmpStr = str_replace($exprMatch[0], str_replace($placeholderEscChar . $placeholderEscChar, $placeholderEscChar, $exprMatch[0]), $tmpStr);
				
			$replacedStr .= $tmpStr;
		}
		
		return $replacedStr . substr($subject, $strOffset);
	}
	
	return $subject;
}


// Define constants
define('__BASE_DIR__', __DIR__ . '/../..');
define('__CONF_DIR__', __BASE_DIR__ . '/conf');

require __CONF_DIR__ . '/conf.php';

define('__DATA_DIR__', (isset($config['datadir']) && $config['datadir'] != '')?((preg_match('#^/#', $config['datadir']) == 1)?$config['datadir']:__BASE_DIR__ . '/' . $config['datadir']):__BASE_DIR__ . '/data');

$tmpDir = !isset($config['tmpdir'])?'':(string)$config['tmpdir'];

$tmpDir = replacePlaceholder('%systempdir', sys_get_temp_dir(), $tmpDir);

define('__TMP_DIR__', $tmpDir != ''?((preg_match('#^/#', $tmpDir) == 1)?$tmpDir:__BASE_DIR__ . '/' . $tmpDir):__BASE_DIR__ . '/tmp');

$GLOBALS['environment'] = (isset($config['app']['env']) && $config['app']['env'] != null)?$config['app']['env']:null;
$GLOBALS['enable_incremental_sync'] = (isset($config['app']['enable_incremental_sync']) && is_bool($config['app']['enable_incremental_sync']))?$config['app']['enable_incremental_sync']:false;
$GLOBALS['max_payload_size'] = (isset($config['app']['max_payload_size']) && is_int($config['app']['max_payload_size']))?$config['app']['max_payload_size']:null;
$GLOBALS['base_uri'] = (isset($config['app']['base_uri']) && $config['app']['base_uri'] != '')?((preg_match('#^/#', $config['app']['base_uri']) == 1)?$config['app']['base_uri']:'/' . $config['app']['base_uri']):'/server.php';

/* Database */

try {
    $pdo_foreign_keys_enabled = false;
    $pdo_dsn = !isset($config['sync_database']['dsn'])?null:(string)$config['sync_database']['dsn'];
    $pdo_username = !isset($config['sync_database']['username'])?null:$config['sync_database']['username'];
    $pdo_password = !isset($config['sync_database']['password'])?null:$config['sync_database']['password'];
    
    if($pdo_dsn == null)
    {
			error_log("Sync database connection not defined.");
			http_response_code(500);
			exit(1);
    }
    
    $pdo_scheme = parse_url($pdo_dsn, PHP_URL_SCHEME);
		$pdo_dsn = replacePlaceholder('%datadir', __DATA_DIR__, $pdo_dsn);
    $pdo = new PDO($pdo_dsn, $pdo_username, $pdo_password);
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
