<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\DAVACL;

class Plugin extends \Sabre\DAVACL\Plugin
{
	function initialize(\Sabre\DAV\Server $server)
	{
		parent::initialize($server);
		$server->on('beforeMethod:PROPFIND', [$this, 'beforeMethodPropFind'], 19);
		$server->on('beforeMethod:REPORT', [$this, 'beforeMethodReport'], 19);
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
		if(!$this->server->tree->nodeExists($this->server->getRequestUri()))
			return null;
			
		$properties = $this->server->getProperties($this->server->getRequestUri(), ['{DAV:}owner']);

		if(isset($properties['{DAV:}owner'])) {
			$ownerUrl = $properties['{DAV:}owner']->getHref();
			
			if(preg_match('#^/+$#', $ownerUrl) === 0 && $ownerUrl != '')
				return dirname($ownerUrl) . '/' . basename($ownerUrl);
		}
			
		return null;
	}
	
	private function checkReadAccess()
	{
		$exists = $this->server->tree->nodeExists($this->server->getRequestUri());

    // If the node doesn't exists, none of these checks apply
    if(!$exists)
			return;
        
		$this->checkPrivileges($this->server->getRequestUri(), '{DAV:}read');
		
		return;
	}
	
	public function beforeMethodPropFind()
	{
		$this->checkReadAccess();
		return;
	}
	
	public function beforeMethodReport()
	{
		$this->checkReadAccess();
		return;
	}
}
