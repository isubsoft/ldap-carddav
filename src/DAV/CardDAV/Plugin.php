<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

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
		$path = parse_url($this->server->getRequestUri())['path'];
		$properties = $this->server->getProperties($path, ['{DAV:}owner']);

		if (isset($properties['{DAV:}owner'])) {
			$principalPath = $properties['{DAV:}owner']->getHref();
				
			if($principalPath != '/' && $principalPath != '')
				foreach($this->carddavBackend->getAddressBooksForUser($principalPath) as $addressbook) {
					if($this->carddavBackend->isAddressbookDirectory($addressbook['id']))
						$this->directories[] = $this->getAddressbookHomeForPrincipal($principalPath) . '/' . $addressbook['id'] . '/';
				}
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
