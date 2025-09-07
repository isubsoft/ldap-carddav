<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\Cache;

class Master
{
	public static $allowedBackends = [
		'card' => ['memory', 'apcu', 'local_fs', 'memcached']
  ];
	                          
	public static function getCardBackend(array $cacheConfig, string $syncDbUserId, string $addressBookId)
	{
		$objClass = 'card';
		$backend = (isset($cacheConfig[$objClass]['backend']) && $cacheConfig[$objClass]['backend'] != '')?$cacheConfig[$objClass]['backend']:null;
		$backendObj = new Backend\Dummy();
		
		if(!in_array($backend, self::$allowedBackends[$objClass]))
			return $backendObj;
			
		if($backend == 'local_fs')
		{
			$basePath = __CACHE_DIR__ . '/' . ((isset($cacheConfig[$objClass][$backend]['base_path']) && $cacheConfig[$objClass][$backend]['base_path'] != '')?$cacheConfig[$objClass][$backend]['base_path']:$objClass) . '/' . $syncDbUserId . '/' . $addressBookId;
			$backendObj = new Backend\LocalFS($basePath);
		}
		
		return $backendObj;
	}
}
