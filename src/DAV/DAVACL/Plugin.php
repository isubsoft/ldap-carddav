<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\DAVACL;

use Sabre\DAV\Server;
use Sabre\DAV\Exception as SabreDAVException;

class Plugin extends \Sabre\DAVACL\Plugin
{
	protected $principalBackend;
	
	function __construct($principalBackend) {
		$this->principalBackend = $principalBackend;
	}
	
	function initialize(Server $server){
		parent::initialize($server);
		$server->on('beforeMethod:*', [$this, 'beforeMethodBlockPrincipal'], 21);
	}
	
  /**
   * Return owner principal url if it can be determined from the request path else 
   * return authenticated principal url.
   *
   * @param object $request
   * @return string|null
   */
	public function getRequestUrlOwner()
	{
		$properties = $this->server->getProperties($this->server->getRequestUri(), ['{DAV:}owner']);

		if(isset($properties['{DAV:}owner'])) {
			$ownerUrl = $properties['{DAV:}owner']->getHref();
			
			if(preg_match('#^/+$#', $ownerUrl) === 0 && $ownerUrl != '')
				return dirname($ownerUrl) . '/' . basename($ownerUrl);
		}
			
		return null;
	}
	
	public function beforeMethodBlockPrincipal(\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response)
	{
		$principalUri = $this->getRequestUrlOwner();
		
		if($principalUri === null)
			return;
		
		$principal = new Principal($this->principalBackend, ['uri' => $principalUri]);
				
		if(!in_array($GLOBALS['currentUserPrincipalUri'], $principal->getGroupMemberSet()) && $GLOBALS['currentUserPrincipalUri'] != $principalUri)
			throw new SabreDAVException\Forbidden("This current user is not allowed to access this path");
			
		return;
	}
}
