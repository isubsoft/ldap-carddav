<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\Cache;

use Sabre\Cache as SabreCacheBackend;
use Sabre\DAV\Exception as SabreDAVException;

class Master
{
	public static $allowedBackends = ['memory', 'apcu', 'local_fs', 'memcached'];

	private static function getKey(array $key)
	{
		return implode(".", $key);
	}	                          

	private static function getBackend(string $backend, $backendConfig)
	{
		$backendObj = new Backend\Dummy();
		
		if(!in_array($backend, self::$allowedBackends)) {
			error_log("Caching is disabled as '$backend' is not a valid caching backend. Check your configuration.  ".__METHOD__." at line no ".__LINE__);
			return $backendObj;
		}
		
		if($backend == 'memcached') {
			$memcached = new \Memcached();
			
			if(!isset($backendConfig['servers']) || !$memcached->addServers($backendConfig['servers'])) {
				error_log("Caching is disbaled as object for 'memcached' cache backend could not be instantiated. Check your 'memcached' cache backend configuration.  ".__METHOD__." at line no ".__LINE__);
				return $backendObj;
			}
			
			$backendObj = new SabreCacheBackend\Memcached($memcached);
		}
		else if($backend == 'local_fs')
			$backendObj = new Backend\LocalFS(__CACHE_DIR__);
		else if($backend == 'apcu')
			$backendObj = new SabreCacheBackend\Apcu();
		else if($backend == 'memory')
			$backendObj = new SabreCacheBackend\Memory();
		
		return $backendObj;
	}

	public static function getCardBackend(array $cacheConfig)
	{
		$objClass = 'card';
		$backend = (isset($cacheConfig[$objClass]['backend']) && $cacheConfig[$objClass]['backend'] != '')?$cacheConfig[$objClass]['backend']:null;

		return self::getBackend($backend, $cacheConfig['backend'][$backend]);
	}
	
	public static function cardKey(string $syncDbUserId, string $addressBookId, string $uri)
	{
		$objClass = 'card';
		return strtolower(md5(self::getKey([$objClass, $syncDbUserId, $addressBookId, $uri])));
	}
	
  public static function encodeCard(array $values)
  {
		$cacheValues = [];			
		
		foreach($values as $key => $value)
			$cacheValues[$key] = base64_encode($value);
		
		return json_encode($cacheValues);
  }
  
  public static function decodeCard($value)
  {
		$cardValues = [];
		
		if(!is_string($value))
			return $cardValues;
		
		foreach(json_decode($value, true) as $key => $value)
			$cardValues[$key] = base64_decode($value);
  
  	return $cardValues;
  }
}
