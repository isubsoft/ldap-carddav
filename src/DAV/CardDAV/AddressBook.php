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

namespace ISubsoft\DAV\CardDAV;

class AddressBook extends \Sabre\CardDAV\AddressBook
{
	public function getACL()
	{
		$acl = [];
		$acl[] = [
	    'privilege' => '{DAV:}read',
	    'principal' => '{DAV:}owner',
	    'protected' => true
	  ];
	  
	  if($this->carddavBackend->isAddressbookWritable($this->getName()) == true) {
			$writeAclDeny = [];
			
			$writeAclDeny = $this->carddavBackend->getWriteAclDenyList($this->getName());
			
			if(!in_array('create', $writeAclDeny)) {
				$acl[] = [
					'privilege' => '{DAV:}bind',
					'principal' => '{DAV:}owner',
					'protected' => true
				];
			}
			
			if(!in_array('delete', $writeAclDeny)) {
				$acl[] = [
					'privilege' => '{DAV:}unbind',
					'principal' => '{DAV:}owner',
					'protected' => true
				];
			}
	  }
			
		if($this->carddavBackend->isAddressbookUserSpecific($this->getName()) == true)
			$acl[] = [
				'privilege' => '{DAV:}write-properties',
				'principal' => '{DAV:}owner',
				'protected' => true
			];
			
		// Due to lack of proper ACL support for collections in clients all privileges are
		// given to a writable address book. This can be removed in future (as above rules
		// are the actual privileges which need to be sent to the client) when proper ACL support
		// for collections is available in clients.
	  if($this->carddavBackend->isAddressbookWritable($this->getName()) == true) {
			$acl = [];
			$acl[] = [
			  'privilege' => '{DAV:}all',
			  'principal' => '{DAV:}owner',
			  'protected' => true
			];
		}
			
		return $acl;
	}
	
  public function getChildACL()
  {
		$acl = [];
		$acl[] = [
			'privilege' => '{DAV:}read',
			'principal' => '{DAV:}owner',
			'protected' => true
		];
		
		return $acl;
  }
}
