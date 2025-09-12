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

	private static function getKey(array $key)
	{
		return implode(".", $key);
	}	                          

	public static function getCardBackend(array $cacheConfig)
	{
		$objClass = 'card';
		$backend = (isset($cacheConfig[$objClass]['backend']) && $cacheConfig[$objClass]['backend'] != '')?$cacheConfig[$objClass]['backend']:null;
		$backendObj = new Backend\Dummy();
		
		if(!in_array($backend, self::$allowedBackends[$objClass]))
			return $backendObj;
			
		if($backend == 'local_fs')
			$backendObj = new Backend\LocalFS(__CACHE_DIR__);
		
		return $backendObj;
	}
	
	public static function generateCardKey(string $syncDbUserId, string $addressBookId, string $uri)
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
