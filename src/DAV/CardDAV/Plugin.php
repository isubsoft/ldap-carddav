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
	private $carddavBackend;
	
	function __construct($carddavBackend) {
		$this->carddavBackend = $carddavBackend;
	}

	function initialize(Server $server){
		parent::initialize($server);
		$server->on('beforeMethod:*', [$this, 'appBeforeMethod']);
	}
	
	public function appBeforeMethod()
	{
		foreach($this->carddavBackend->getAddressBooksForUser($GLOBALS['currentUserPrincipalUri']) as $addressbook) {
			if($this->carddavBackend->isAddressbookDirectory($addressbook['id']))
				$this->directories[] = $this->getAddressbookHomeForPrincipal($GLOBALS['currentUserPrincipalUri']) . '/' . $addressbook['id'] . '/';
		}
		
		return;
	}
	
	public function setResourceSize(int $sizeInBytes)
	{
		if($sizeInBytes > 0)
			$this->maxResourceSize = $sizeInBytes;
			
		return;
	}
	
	public function publicGetAddressbookHomeForPrincipal($principal)
	{
		return $this->getAddressbookHomeForPrincipal($principal);
	}
}
