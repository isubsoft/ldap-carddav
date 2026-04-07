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
		$server->on('beforeMethod:*', [$this, 'beforeMethodSetDirectory'], 900);
	}
	
	public function beforeMethodSetDirectory(\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response)
	{
		$requestMethod = $request->getMethod();
		
		if(strtolower($requestMethod) != 'get' && strtolower($requestMethod) != 'propfind') // GET method is needed for browser plugin
			return;
		
		$requestUrlPath = parse_url($request->getPath(), PHP_URL_PATH);
		
		if($requestUrlPath === false || $requestUrlPath === null)
			return;
		
		$properties = $this->server->getProperties($requestUrlPath, ['{DAV:}owner']);

		if (isset($properties['{DAV:}owner'])) {
			$principalPath = $properties['{DAV:}owner']->getHref();
				
			if(preg_match('#^/+$#', $principalPath) === 0 && $principalPath != '')
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
