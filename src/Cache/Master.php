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
   * PDO connection handle
   *
   * @var \PDO
   */
  private $pdo;
    
	private static $entityCacheTableName = 'entity_cache';
	private $config;
	
	public static $allowedBackends = [
		'principal' => ['memory', 'apcu', 'local_fs', 'memcached'],
		'card' => ['memory', 'apcu', 'local_fs', 'memcached']
	];
	
  public function __construct(array $config, \PDO $pdo) {
  	$this->config = $config['cache'];
  	$this->pdo = $pdo;
  }
	
	public function getLastBackendId($entityId)
	{
		$backendId = null;
		
		try {
				$query = 'SELECT backend_id FROM ' . self::$entityCacheTableName . ' WHERE backend_id IS NOT NULL AND entity_id = ?';
				$stmt = $this->pdo->prepare($query);
				$stmt->execute([$entityId]);
				
				$row = $stmt->fetch(\PDO::FETCH_ASSOC);
				
				if($row !== false)
					$backendId = $row['backend_id'];
		} 
		catch (\Throwable $th) {
			trigger_error("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage(), E_USER_WARNING);
		}
		
		return $backendId;
	}
	
	public function getBackendId($entityId)
	{
		return (isset($this->config[$entityId]['backend']) && is_string($this->config[$entityId]['backend']) && $this->config[$entityId]['backend'] != '')?$this->config[$entityId]['backend']:null;
	}
	
	public function setLastBackendId($entityId)
	{
		if($this->getBackendId($entityId) != $this->getLastBackendId($entityId)) {		
			try {
					$query = 'UPDATE ' . self::$entityCacheTableName . ' SET backend_id = ? WHERE entity_id = ?';
					$stmt = $this->pdo->prepare($query);
					$stmt->execute([$this->getBackendId($entityId), $entityId]);
					
					if(!$stmt->rowCount() > 0) {
						$query = 'INSERT INTO ' . self::$entityCacheTableName . ' (entity_id, backend_id) VALUES (?, ?)';
						$stmt = $this->pdo->prepare($query);
						$stmt->execute([$entityId, $this->getBackendId($entityId)]);
					}
			}
			catch (\Throwable $th) {
				trigger_error("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage(), E_USER_WARNING);

				return false;
			}
		}
		
		return true;
	}

	public function getBackend($entityId)
	{
		$backendObj = new Backend\Dummy();
		$backendId = $this->getBackendId($entityId);
		
		if($backendId != null && !in_array($backendId, self::$allowedBackends[$entityId])) {
			trigger_error("Caching is disabled as '$backendId' is not a valid caching backend for '$entityId'. Check your configuration.  ".__METHOD__." at line no ".__LINE__, E_USER_WARNING);
			$backendId = null;
		}
		
		$backendConfig = !isset($this->config['backend'][$backendId])?null:$this->config['backend'][$backendId];
		
		if($backendId == 'memcached') {
			$memcached = new \Memcached();
			
			if(!isset($backendConfig['servers']) || !$memcached->addServers($backendConfig['servers'])) {
				trigger_error("Caching is disbaled as object for 'memcached' cache backend could not be instantiated. Check your 'memcached' cache backend configuration.  ".__METHOD__." at line no ".__LINE__, E_USER_WARNING);
				return $backendObj;
			}
			
			$backendObj = new SabreCacheBackend\Memcached($memcached);
		}
		else if($backendId == 'local_fs')
			$backendObj = new Backend\LocalFS(__CACHE_DIR__);
		else if($backendId == 'apcu')
			$backendObj = new SabreCacheBackend\Apcu();
		else if($backendId == 'memory')
			$backendObj = new SabreCacheBackend\Memory();
		
		return $backendObj;
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
