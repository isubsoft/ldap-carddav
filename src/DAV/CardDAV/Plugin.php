<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

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
	
	public function publicGetAddressbookHomeForPrincipal($principal)
	{
		return $this->getAddressbookHomeForPrincipal($principal);
	}
}
