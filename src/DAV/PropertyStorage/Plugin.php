<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\PropertyStorage;

class Plugin extends \Sabre\DAV\PropertyStorage\Plugin
{
	/**
	 * Server class.
	 *
	 * @var DAV\Server
	 */
	protected $server;
    
	public function initialize(\Sabre\DAV\Server $server)
	{
		parent::initialize($server);

		$this->server = $server;
		
		$this->pathFilter = function($path) {
			$node = $this->server->tree->getNodeForPath($path);
			
			if($node instanceof \Sabre\CardDAV\AddressBookHome || $node instanceof \Sabre\DAVACL\Principal)
				return true;

			return false;
		};
	}
}
