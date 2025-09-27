<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

declare(strict_types=1);

namespace ISubsoft\Cache\Backend;

use Psr\SimpleCache\CacheInterface;

class LocalFS implements CacheInterface
{
    public $basePath = null;
    private static $noTtlDefault = 86400;
    
    public function __construct(string $basePath)
    {
    	$this->basePath = $basePath;
    	
   		if(!file_exists($this->basePath))
    		mkdir($this->basePath, 0755, true);
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
    	$cacheFile = $this->basePath . '/' . $key;
    	$ttlFile = $this->basePath . '/' . $key . ".ttl";
    	
   		$ttl = file_get_contents($ttlFile);
			$cacheData = file_get_contents($cacheFile);
   		
			if($ttl == false || $cacheData == false || time() - filemtime($cacheFile) > (int)$ttl)
				return $default;
				
    	return $cacheData;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store. Must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
    	$cacheFile = $this->basePath . '/' . $key;
    	$ttlFile = $this->basePath . '/' . $key . ".ttl";
    	
	  	if(file_put_contents($ttlFile, (!is_int($ttl) || $ttl === 0 || $ttl > 2592000)?self::$noTtlDefault:$ttl) == false || file_put_contents($cacheFile, $value) == false) {
    		$this->delete($key);
	  		return false;
	  	}
    		
    	return true;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
    	$cacheFile = $this->basePath . '/' . $key;
    	$ttlFile = $this->basePath . '/' . $key . ".ttl";
    	
			return unlink($ttlFile) && unlink($cacheFile);
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
    	$filePattern = $this->basePath . '/' . '*';
    	
    	if(file_exists($this->basePath))
		  	foreach(glob($filePattern) as $file)
		  		if(!unlink($file))
		  			return false;
    			
    	return true;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
    	$valueList = [];
    	
    	foreach($keys as $key)
    		$valueList[$key] = $this->get($key);
    	
    	return $valueList;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
    	foreach($values as $key => $value)
    		if(!$this->set($key, $value))
    			return false;
    		
    	return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
    	foreach($keys as $key)
    		if(!$this->delete($key))
    			return false;
    		
    	return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it, making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
    	$cacheFile = $this->basePath . '/' . $key;
    	
			return file_exists($cacheFile);
    }
}
