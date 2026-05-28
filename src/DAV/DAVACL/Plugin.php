<?php

/***************************************************************************
*
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
* 
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <https://www.gnu.org/licenses/>.
*
***************************************************************************/

namespace ISubsoft\DAV\DAVACL;

use Sabre\DAV\Exception as SabreDAVException;

class Plugin extends \Sabre\DAVACL\Plugin
{
	function initialize(\Sabre\DAV\Server $server)
	{
		parent::initialize($server);
		$server->on('beforeMethod:PROPFIND', [$this, 'beforeMethodPropFind'], 19);
		$server->on('beforeMethod:REPORT', [$this, 'beforeMethodReport'], 19);
		$server->on('beforeMethod:PROPPATCH', [$this, 'beforeMethodPropPatch'], 21);
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
    // If the node doesn't exists, none of these checks apply
    if(!$this->server->tree->nodeExists($this->server->getRequestUri()))
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
	
	public function beforeMethodPropPatch()
	{
    // If the node doesn't exists, none of these checks apply
		if(!$this->server->tree->nodeExists($this->server->getRequestUri()))
			return;
			
		$node = $this->server->tree->getNodeForPath($this->server->getRequestUri());
		
		if(!$node instanceof \Sabre\CardDAV\AddressBookHome && !$node instanceof \Sabre\DAVACL\Principal)
			throw new SabreDAVException\Forbidden("Properties are only allowed to be written to your own principal or address book home path");

		return;
	}
}
