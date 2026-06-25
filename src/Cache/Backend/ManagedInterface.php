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
