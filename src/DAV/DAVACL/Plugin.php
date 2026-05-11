<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\DAVACL;

use Sabre\DAV\Server;
use Sabre\DAV\Exception as SabreDAVException;

class Plugin extends \Sabre\DAVACL\Plugin
{
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
}
