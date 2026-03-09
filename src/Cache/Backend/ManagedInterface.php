<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\Cache\Backend;

/**
* Application managed cache backend interface.
*
* This interface need to added by cache backends which
* are managed by the application like filesystem cache.
**/
interface ManagedInterface
{
	/**
	* Implement this method to evict (delete) stale cache items.
	*
	* This can clean up space in cache store.
	*
  * @param integer $batchSize
  *
  * @return bool
	**/
	public function evictStale(int $batchSize = 0);
}
