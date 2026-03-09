<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\PropertyStorage;

use Sabre\DAV\Server;

class Plugin extends \Sabre\DAV\PropertyStorage\Plugin
{
	/**
	 * Server class.
	 *
	 * @var DAV\Server
	 */
	protected $server;
    
	public function initialize(Server $server)
	{
		parent::initialize($server);

		$this->server = $server;
		
		$this->pathFilter = function($path) {
			$addressbookPathRegexp = '#^' . $this->server->getPlugin('carddav')->publicGetAddressbookHomeForPrincipal($GLOBALS['currentUserPrincipalUri']) . '$|^' . $GLOBALS['currentUserPrincipalUri'] . '$#';
			
			if (preg_match($addressbookPathRegexp, $path) === 1)
				return true;

			return false;
		};
	}
}
