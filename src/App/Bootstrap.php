<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

function replacePlaceholder(string $placeholder, string $replacement, string $subject)
{
	$regexpDelim = '/';
	$placeholderEscChar = substr($placeholder, 0, 1);
	
	if($regexpDelim == $placeholderEscChar)
		$regexpDelim = '#';
		
	$exprMatches = [];

	if(preg_match_all($regexpDelim . '(' . $placeholderEscChar . '*)(' . $placeholder . ')' . $regexpDelim, $subject, $exprMatches, PREG_OFFSET_CAPTURE) > 0)
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
$GLOBALS['enable_incremental_sync'] = (isset($config['app']['enable_incremental_sync']) && is_bool($config['app']['enable_incremental_sync']))?$config['app']['enable_incremental_sync']:true;
$GLOBALS['max_payload_size'] = (isset($config['app']['max_payload_size']) && is_int($config['app']['max_payload_size']))?$config['app']['max_payload_size']:null;
$GLOBALS['base_uri'] = (isset($config['app']['base_uri']) && $config['app']['base_uri'] != '')?((preg_match('#^/#', $config['app']['base_uri']) == 1)?$config['app']['base_uri']:'/' . $config['app']['base_uri']):'/server.php';

/* Database */

$configurablePdoAttributes = [PDO::ATTR_TIMEOUT, PDO::ATTR_PERSISTENT];

try {
    $pdo_dsn = !isset($config['sync_database']['dsn'])?null:(string)$config['sync_database']['dsn'];
    $pdo_username = !isset($config['sync_database']['username'])?null:(string)$config['sync_database']['username'];
    $pdo_password = !isset($config['sync_database']['password'])?null:(string)$config['sync_database']['password'];
    $pdo_options = [];
    
    foreach(!isset($config['sync_database']['options'])?[]:(array)$config['sync_database']['options'] as $pdoAttr => $value)
    	if(in_array($pdoAttr, $configurablePdoAttributes))
    		$pdo_options[$pdoAttr] = $value;
    
    if($pdo_dsn == null)
    {
			error_log("Sync database connection not defined.");
			http_response_code(500);
			exit(1);
    }
    
    $pdo_scheme = parse_url($pdo_dsn, PHP_URL_SCHEME);
		$pdo_dsn = replacePlaceholder('%datadir', __DATA_DIR__, $pdo_dsn);
    $pdo = new PDO($pdo_dsn, $pdo_username, $pdo_password, $pdo_options);
    
    // Setting mandatory database connection attributes
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db_init_commands = (isset($config['sync_database']['init_commands']) && is_array($config['sync_database']['init_commands']))?$config['sync_database']['init_commands']:[];
    $applicable_db_init_commands = [];
    
    if($pdo_scheme == 'sqlite')
    {
	  	foreach($db_init_commands as $stmt)
	  		if(preg_match('/^\\s*PRAGMA\\s+/i', $stmt))
	  			$applicable_db_init_commands[] = $stmt;
					
    	// Enforce foreign key constraints
			$applicable_db_init_commands[] = 'PRAGMA foreign_keys = ON';
    }
    elseif($pdo_scheme == 'mysql')
    {
	  	foreach($db_init_commands as $stmt)
	  		if(preg_match('/^\\s*SET\\s+/i', $stmt))
	  			$applicable_db_init_commands[] = $stmt;
	  			
    	// Enforce foreign key constraints
			$applicable_db_init_commands[] = 'SET foreign_key_checks = ON';
    }
    
    // Execute applicable init commands
    foreach($applicable_db_init_commands as $stmt)
			$pdo->exec($stmt);
} catch (\Throwable $th) {
    error_log('Could not create sync database connection or execute init commands correctly: '. $th->getMessage());
    http_response_code(500);
		exit(1);
}
