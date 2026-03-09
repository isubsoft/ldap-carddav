<?php 
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/**
* This script is used to manage application cache.
**/

function print_help($argv)
{
	echo "\n";
	echo "Usage: " . $argv[0] . " action [parameters]\n";
	echo "\n";
	echo "Parameters for action housekeeping.\n";
	echo "batch size (optional, integer >= 0, 0 means no limit, defaults to no limit): Restrict action to maximum of these many items. Since actions can be time consuming set this parameter to a small value like 1000 to finish early. Useful when used from a scheduler.\n";
}

/*import database connection*/
require_once __DIR__ . '/Bootstrap.php';

/* load classes */
require_once __BASE_DIR__ . '/vendor/autoload.php';

if(isset($argv[1]) && $argv[1] == 'housekeeping')
{
	$exitCode = 0;
	$batchSize = 0;
	
	if(isset($argv[2]))
		$batchSize = $argv[2];
			
	if(!settype($argv[2], 'integer') || $batchSize < 0) {
		error_log("Invalid batch size provided. Cannot continue. Quitting.");
		print_help($argv);
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
	error_log("No valid argument provided. Nothing to do. Quitting.");
	print_help($argv);
	exit;
}

exit;
