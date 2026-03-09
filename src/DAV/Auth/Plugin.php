<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\Auth;

use Sabre\DAV\Server;

class Plugin extends \Sabre\DAV\Auth\Plugin
{
	function initialize(Server $server){
		parent::initialize($server);
		$server->on('beforeMethod:*', [$this, 'beforeMethod'], 10);
		$server->on('beforeMethod:*', [$this, 'appBeforeMethod'], 15);
	}

	public function appBeforeMethod()
	{
		$GLOBALS['currentUserPrincipalUri'] = $this->getCurrentPrincipal();
		$GLOBALS['currentUserPrincipalId'] = basename($this->getCurrentPrincipal());
		
		return;
	}
}
