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

namespace ISubsoft\DAV\CardDAV;

use Sabre\DAV\Server;

class Plugin extends \Sabre\CardDAV\Plugin
{
	protected $carddavBackend;
	
	function __construct($carddavBackend) {
		$this->carddavBackend = $carddavBackend;
	}

	function initialize(Server $server){
		parent::initialize($server);
		$server->on('beforeMethod:*', [$this, 'beforeMethodSetDirectory'], 900);
	}
	
  /**
   * Mark address book(s) as CardDAV directory address book if configured to be one.
   *
   * @param object  $request
   * @param object  $response
   */
	public function beforeMethodSetDirectory(\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response)
	{
		if(!in_array(strtolower($request->getMethod()), ['propfind', 'get'])) // GET method is needed for browser plugin
			return;
			
		$principalUri = $this->server->getPlugin('acl')->getRequestUrlOwner();
		
		if($principalUri === null)
			return;
		
		foreach($this->carddavBackend->getAddressBooksForUser($principalUri) as $addressbook) {
			if($this->carddavBackend->isAddressbookDirectory($addressbook['id']))
				$this->directories[] = $this->getAddressbookHomeForPrincipal($principalUri) . '/' . $addressbook['id'] . '/';
		}
		
		return;
	}
	
	public function setResourceSize(int $sizeInBytes)
	{
		if($sizeInBytes > 0)
			$this->maxResourceSize = $sizeInBytes;
			
		return;
	}
}
