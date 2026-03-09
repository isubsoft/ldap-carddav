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
  /**
   * Cache config
   *
   * @array
   */
	private $config;
	
  /**
   * PDO connection handle
   *
   * @var \PDO
   */
  private $pdo;
    
	private static $entityCacheTableName = 'entity_cache';
	
  /**
   * Dummy cache backend id
   *
   * @string
   */
	public static $dummyBackend = '__dummy__';
	
  /**
   * Allowed cache backends
   *
   * @array
   */
	public static $allowedBackends = ['memory', 'apcu', 'local_fs', 'memcached'];

  /**
   * Backend object indexed by backend id
   *
   * @array
   */	
	public $cache;
	
  public function __construct(array $config, \PDO $pdo) {
  	$this->config = $config['cache'];
  	$this->pdo = $pdo;
  	$this->cache = [];
  }
	
	public function cacheResetRequired($entityId, $setBackendId)
	{
		$backendId = ($setBackendId == self::$dummyBackend)?null:$setBackendId;
		
		try {
				$query = 'SELECT backend_id FROM ' . self::$entityCacheTableName . ' WHERE entity_id = ?';
				$stmt = $this->pdo->prepare($query);
				$stmt->execute([$entityId]);
				
				$row = $stmt->fetch(\PDO::FETCH_ASSOC);
				
				if($row === false || $backendId != $row['backend_id'])
					return true;
		} 
		catch (\Throwable $th) {
			trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
		}
		
		return false;
	}
	
	public function setActiveBackend($entityId, $setBackendId)
	{
		$backendId = ($setBackendId == self::$dummyBackend)?null:$setBackendId;
		
		try {
				$query = 'UPDATE ' . self::$entityCacheTableName . ' SET backend_id = ? WHERE entity_id = ?';
				$stmt = $this->pdo->prepare($query);
				$stmt->execute([$backendId, $entityId]);
				
				if(!$stmt->rowCount() > 0) {
					$query = 'INSERT INTO ' . self::$entityCacheTableName . ' (entity_id, backend_id) VALUES (?, ?)';
					$stmt = $this->pdo->prepare($query);
					$stmt->execute([$entityId, $backendId]);
				}
		}
		catch (\Throwable $th) {
			trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);

			return false;
		}
		
		return true;
	}

	public function getBackendId($entityId)
	{
		$configuredBackendId = (isset($this->config[$entityId]['backend']) && is_string($this->config[$entityId]['backend']) && $this->config[$entityId]['backend'] != '')?$this->config[$entityId]['backend']:null;
		
		if($configuredBackendId != null && !in_array($configuredBackendId, self::$allowedBackends)) {
			trigger_error("Caching is disabled as '$configuredBackendId' is not a valid caching backend. Check configuration.", E_USER_WARNING);
			
			$backendId = self::$dummyBackend;
			
			if(!isset($this->cache[$backendId]))
				$this->cache[$backendId] = new Backend\Dummy();
			
			return $backendId;
		}
		
		$backendConfig = !isset($this->config['backend'][$configuredBackendId])?null:$this->config['backend'][$configuredBackendId];
		
		if($configuredBackendId == null) {
			$backendId = self::$dummyBackend;
			
			if(!isset($this->cache[$backendId]))
				$this->cache[$backendId] = new Backend\Dummy();
				
				return $backendId;		
		}
		else if($configuredBackendId == 'memcached') {
			$memcached = new \Memcached();
			
			if(!isset($backendConfig['servers']) || !$memcached->addServers($backendConfig['servers'])) {
				trigger_error("Caching is disbaled as '$configuredBackendId' cache backend could not be initialized. Check '$configuredBackendId' cache backend configuration.", E_USER_WARNING);
				
				$backendId = self::$dummyBackend;
				
				if(!isset($this->cache[$backendId]))
					$this->cache[$backendId] = new Backend\Dummy();
				
				return $backendId;
			}
			
			if(!isset($this->cache[$configuredBackendId]))
				$this->cache[$configuredBackendId] = new SabreCacheBackend\Memcached($memcached);
		}
		else if($configuredBackendId == 'local_fs') {
			if(!file_exists(__CACHE_DIR__)) {
				trigger_error("Caching is disbaled as '$configuredBackendId' cache backend could not be initialized. Check your '$configuredBackendId' cache backend configuration.", E_USER_WARNING);

				$backendId = self::$dummyBackend;
				
				if(!isset($this->cache[$backendId]))
					$this->cache[$backendId] = new Backend\Dummy();
				
				return $backendId;
			}
			
			if(!isset($this->cache[$configuredBackendId]))
				$this->cache[$configuredBackendId] = new Backend\LocalFS(__CACHE_DIR__);
		}
		else if($configuredBackendId == 'apcu') {
			if(!isset($this->cache[$configuredBackendId]))
				$this->cache[$configuredBackendId] = new SabreCacheBackend\Apcu();
		}
		else if($configuredBackendId == 'memory') {
			if(!isset($this->cache[$configuredBackendId]))
				$this->cache[$configuredBackendId] = new SabreCacheBackend\Memory();
		}
		
		return $configuredBackendId;
	}
	
	public static function getKey(array $key)
	{
		return strtolower(md5(implode("/", $key)));
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
