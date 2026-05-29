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
* This script is used to manage application cache.
**/

function printHelp($argv)
{
	error_log("Usage: " . $argv[0] . " action [parameters]");
	error_log("");
	error_log("-- Actions");
	error_log("help:           Print this help and exit.");
	error_log("info (default): Print cache information.");
	error_log("clear:          Clear cache. WARNING: This will delete/invalidate all items in the cache including the ones set by other application(s).");
	error_log("housekeeping:   Evict stale cache from managed caches.");
	error_log("");
	error_log("-- Parameter(s) for housekeeping");
	error_log("  batch size: (optional, integer) Restrict action to maximum of these many items. Should be >= 0, 0 (default) means no limit. Since this action can be time consuming set this parameter to a small value like 1000 to finish early. Useful when used from a scheduler.");
	
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
elseif(!isset($argv[1]) || $argv[1] == 'info')
{
	if(count($cachedBackendEntity) < 1) {
		echo "[INFO] No cache backend is active. Quitting." . PHP_EOL;
		exit;
	}

	echo "-- Cache info [ backend => object(s) cached ] --" . PHP_EOL;
	
	foreach($cachedBackendEntity as $backendId => $entityList)
		echo $backendId . " => " . json_encode($entityList, JSON_NUMERIC_CHECK) . PHP_EOL;
		
	exit;
}
elseif(isset($argv[1]) && $argv[1] == 'clear')
{
	if(count($cachedBackendEntity) < 1) {
		echo "[INFO] No cache backend is active. Quitting." . PHP_EOL;
		exit;
	}
	
	echo "-- Cache info [ backend => object(s) cached ] --" . PHP_EOL;
	
	foreach($cachedBackendEntity as $backendId => $entityList)
		if(!in_array($backendId, ISubsoft\Cache\Master::$noPersistenceBackends))
			echo $backendId . " => " . json_encode($entityList, JSON_NUMERIC_CHECK) . PHP_EOL;
		
	echo PHP_EOL;
		
  $cachedBackend = readline("Enter the backend you want to clear: ");
  
  if($cachedBackend == '' || !isset($cacheMaster->cache[$cachedBackend]) || in_array($cachedBackend, ISubsoft\Cache\Master::$noPersistenceBackends)) {
		error_log("[ERROR] Invalid cache backend provided.");
		exit(1);
  }
  
  echo "WARNING: This will delete/invalidate all items in the cache including the ones set by other application(s).";
  
  $option = readline(" Are you sure you want to proceed (y/N): ");
  
  if($option == '' || ($option != 'Y' && $option != 'y'))
  	exit;
  
  if(!$cacheMaster->cache[$cachedBackend]->clear()) {
		error_log("[ERROR] Cache backend '$cacheBackendId' could not be cleared.");
		exit(1);
  }
  
	echo "Complete." . PHP_EOL;
}
elseif(isset($argv[1]) && $argv[1] == 'housekeeping')
{
	$exitCode = 0;
	$batchSize = 0;
	
	if(isset($argv[2]))
		$batchSize = $argv[2];
			
	if(!settype($batchSize, 'integer') || $batchSize < 0) {
		error_log("[ERROR] Invalid batch size provided. Cannot continue. Quitting.");
		error_log("Check help information using: " . $argv[0] . " help");
		exit(1);
	}
			
	echo "Housekeeping cache ..." . PHP_EOL;
	
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
		echo "Complete" . PHP_EOL;
		
	exit($exitCode);
}
else
{
	error_log("[ERROR] Not a valid action. Quitting.");
	error_log("Check help information using: " . $argv[0] . " help");
	exit(1);
}

exit;
