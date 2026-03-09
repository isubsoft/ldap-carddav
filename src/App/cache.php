<?php 
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/**
* This script is used to manage application cache.
**/

function printHelp($argv)
{
	error_log("");
	error_log("Usage: " . $argv[0] . " action [parameters]");
	error_log("");
	error_log("Parameters for action housekeeping.");
	error_log("batch size (optional, integer): Restrict action to maximum of these many items. Should be >= 0, 0 (default) means no limit. Since actions can be time consuming set this parameter to a small value like 1000 to finish early. Useful when used from a scheduler.");
	error_log("");
	
	return;
}

/*import database connection*/
require_once __DIR__ . '/Bootstrap.php';

/* load classes */
require_once __BASE_DIR__ . '/vendor/autoload.php';

if(isset($argv[1]) && $argv[1] == 'help')
{
	printHelp($argv);
	exit;
}
else if(isset($argv[1]) && $argv[1] == 'housekeeping')
{
	$exitCode = 0;
	$batchSize = 0;
	
	if(isset($argv[2]))
		$batchSize = $argv[2];
			
	if(!settype($batchSize, 'integer') || $batchSize < 0) {
		error_log("Invalid batch size provided. Cannot continue. Quitting.");
		printHelp($argv);
		exit(1);
	}
			
	echo "Housekeeping cache ...\n";
	
	// Cached entities
	$cachedEntities = ['principal', 'card'];
	$cacheMaster = new ISubsoft\Cache\Master($config, $pdo);

	// Create object for cache backends
	foreach($cachedEntities as $entityId)
		$cacheBackendId = $cacheMaster->getBackendId($entityId);
	
	// Delete stale cache from each managed cache backend
	foreach($cacheMaster->cache as $backendId => $cache) {
		if($cache instanceof ISubsoft\Cache\Backend\ManagedInterface) {
			if(!$cache->evictStale($batchSize)) {
				$exitCode = 1;
				
				error_log("Could not complete eviction of stale items for backend '$backendId'.");
			}
		}
	}
	
	if($exitCode === 0)
		echo "Complete\n";
		
	exit($exitCode);
}
else
{
	error_log("'$argv[1]' is not a valid action. Quitting.");
	printHelp($argv);
	exit(1);
}

exit;
