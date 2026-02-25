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
		foreach($this->carddavBackend->getAddressBooksForUser($GLOBALS['currentUserPrincipalUri']) as $addressbook) {
			if($this->carddavBackend->isAddressbookDirectory($addressbook['id']))
				$this->directories[] = $this->getAddressbookHomeForPrincipal($GLOBALS['currentUserPrincipalUri']) . '/' . $addressbook['id'];
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
