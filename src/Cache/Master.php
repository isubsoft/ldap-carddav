<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

declare(strict_types=1);

namespace ISubsoft\Cache;

use Sabre\Cache as SabreCacheBackend;
use Sabre\DAV\Exception as SabreDAVException;

class Master
{
	public static $allowedBackends = [
		'principal' => ['memory', 'apcu', 'local_fs', 'memcached'],
		'card' => ['memory', 'apcu', 'local_fs', 'memcached']
	];

	private static function getKey(array $key)
	{
		return strtolower(md5(implode(".", $key)));
	}	                          

	private static function getBackend($backend, $backendConfig)
	{
		$backendObj = new Backend\Dummy();
		
		if($backend == 'memcached') {
			$memcached = new \Memcached();
			
			if(!isset($backendConfig['servers']) || !$memcached->addServers($backendConfig['servers'])) {
				trigger_error("Caching is disbaled as object for 'memcached' cache backend could not be instantiated. Check your 'memcached' cache backend configuration.  ".__METHOD__." at line no ".__LINE__, E_USER_WARNING);
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
	
	public static function getPrincipalBackend(array $cacheConfig)
	{
		$objClass = 'principal';
		$backend = (isset($cacheConfig[$objClass]['backend']) && $cacheConfig[$objClass]['backend'] != '')?$cacheConfig[$objClass]['backend']:null;
		
		if($backend != null && !in_array($backend, self::$allowedBackends[$objClass])) {
			trigger_error("Caching is disabled as '$backend' is not a valid caching backend for '$objClass'. Check your configuration.  ".__METHOD__." at line no ".__LINE__, E_USER_WARNING);
			$backend = null;
		}

		return self::getBackend($backend, !isset($cacheConfig['backend'][$backend])?null:$cacheConfig['backend'][$backend]);
	}

	public static function getCardBackend(array $cacheConfig)
	{
		$objClass = 'card';
		$backend = (isset($cacheConfig[$objClass]['backend']) && $cacheConfig[$objClass]['backend'] != '')?$cacheConfig[$objClass]['backend']:null;
		
		if($backend != null && !in_array($backend, self::$allowedBackends[$objClass])) {
			trigger_error("Caching is disabled as '$backend' is not a valid caching backend for '$objClass'. Check your configuration.  ".__METHOD__." at line no ".__LINE__, E_USER_WARNING);
			$backend = null;
		}

		return self::getBackend($backend, !isset($cacheConfig['backend'][$backend])?null:$cacheConfig['backend'][$backend]);
	}
	
	public static function principalKey(string $principalId)
	{
		$objClass = 'principal';
		return self::getKey([$objClass, $principalId]);
	}

	public static function cardKey(string $syncDbUserId, string $addressBookId, string $uri)
	{
		$objClass = 'card';
		return self::getKey([$objClass, $syncDbUserId, $addressBookId, $uri]);
	}
	
	private static function recursive_encode(array $values)
	{
		$cacheValues = [];			
		
		foreach($values as $key => $value)
		{
			if(is_scalar($value))
				$cacheValues[$key] = base64_encode((string)$value);
			elseif($value === null)
				$cacheValues[$key] = null;
			elseif(is_array($value))
				$cacheValues[$key] = self::recursive_encode($value);
			else
				throw new \UnexpectedValueException();
		}
		
		return $cacheValues;
	}
	
  public static function encode(array $values)
  {
		$cacheValues = '[]';

		try {
			$cacheValues = json_encode(self::recursive_encode($values));
		} catch(Exception $e) {
			trigger_error("Encountered an invalid value/datatype.", E_USER_WARNING);
			return null;
		}
		
		return $cacheValues;
  }
  
	private static function recursive_decode(array $values)
	{
		$cacheValues = [];			
		
		foreach($values as $key => $value)
		{
			if(is_scalar($value))
				$cacheValues[$key] = base64_decode($value);
			elseif($value === null)
				$cacheValues[$key] = null;
			elseif(is_array($value))
				$cacheValues[$key] = self::recursive_decode($value);
		}
		
		return $cacheValues;
	}
  
  public static function decode($value)
  {
		$cardValues = [];
		
		if(!is_string($value))
			return $cardValues;
		
		return self::recursive_decode(json_decode($value, true));
  }
}
