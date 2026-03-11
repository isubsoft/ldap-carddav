<?php 
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/**
* This script is used to manage application cache.
**/

function printHelp($argv)
{
	error_log("Usage: " . $argv[0] . " action [parameters]");
	error_log("");
	error_log("-- Actions");
	error_log("help:         Print this help and exit.");
	error_log("clear:        Clear cache. WARNING: This will delete/invalidate all items in the cache including ones set by other applications.");
	error_log("housekeeping: Evict stale cache from managed caches.");
	error_log("");
	error_log("-- Parameter(s) for help");
	error_log("none");
	error_log("");
	error_log("-- Parameter(s) for clear");
	error_log("none");
	error_log("");
	error_log("-- Parameter(s) for housekeeping");
	error_log("batch size (optional, integer): Restrict action to maximum of these many items. Should be >= 0, 0 (default) means no limit. Since this action can be time consuming set this parameter to a small value like 1000 to finish early. Useful when used from a scheduler.");
	
	return;
}

/*import database connection*/
require_once __DIR__ . '/include/bootstrap.php';

/* load classes */
require_once __BASE_DIR__ . '/vendor/autoload.php';

// Create object for active cache backends
$cacheMaster = new ISubsoft\Cache\Master($config, $pdo);
$cachedBackendEntity = [];

foreach(CACHED_ENTITIES as $entityId) {
	$cacheBackendId = $cacheMaster->getBackendId($entityId);
	
	if($cacheBackendId != ISubsoft\Cache\Master::$dummyBackend)
		$cachedBackendEntity[$cacheBackendId][] = $entityId;
}

if(isset($argv[1]) && $argv[1] == 'help')
{
	printHelp($argv);
	exit;
}
else if(isset($argv[1]) && $argv[1] == 'clear')
{
	echo "-- Cache backend and entities cached in them --\n";
	
	foreach($cachedBackendEntity as $backendId => $entityList)
		echo $backendId . "\t" . json_encode($entityList, JSON_NUMERIC_CHECK) . "\n";
		
  echo "\n";
  
  $cachedBackend = readline("Enter the backend you want to clear: ");
  
  if($cachedBackend == '' || !isset($cachedBackendEntity[$cachedBackend])) {
		error_log("[ERROR] Invalid cache backend provided.");
		exit(1);
  }
  
  echo "WARNING: This will delete/invalidate all items in the cache including ones set by other applications.";
  
  $option = readline(" Are you sure you want to proceed (y/N): ");
  
  if($option == '' || ($option != 'Y' && $option != 'y'))
  	exit;
  
  if(!$cacheMaster->cache[$cachedBackend]->clear()) {
		error_log("[ERROR] Cache backend '$cacheBackendId' could not be cleared.");
		exit(1);
  }
  
	echo "Complete.\n";
}
else if(isset($argv[1]) && $argv[1] == 'housekeeping')
{
	$exitCode = 0;
	$batchSize = 0;
	
	if(isset($argv[2]))
		$batchSize = $argv[2];
			
	if(!settype($batchSize, 'integer') || $batchSize < 0) {
		error_log("[ERROR] Invalid batch size provided. Cannot continue. Quitting.");
  	error_log("");
		printHelp($argv);
		exit(1);
	}
			
	echo "Housekeeping cache ...\n";
	
	// Delete stale cache from each managed cache backend
	foreach($cacheMaster->cache as $backendId => $cache) {
		if($cache instanceof ISubsoft\Cache\Backend\ManagedInterface) {
			if(!$cache->evictStale($batchSize)) {
				$exitCode = 1;
				
				error_log("[ERROR] Could not complete eviction of stale items for backend '$backendId'.");
			}
		}
	}
	
	if($exitCode === 0)
		echo "Complete\n";
		
	exit($exitCode);
}
else
{
	error_log("[ERROR] '$argv[1]' is not a valid action. Quitting.");
 	error_log("");
	printHelp($argv);
	exit(1);
}

exit;
