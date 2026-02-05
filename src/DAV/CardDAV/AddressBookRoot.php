<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\CardDAV;

use Sabre\DAVACL;

class AddressBookRoot extends \Sabre\CardDAV\AddressBookRoot implements DAVACL\IACL
{
	use DAVACL\ACLTrait;
	
	public function getChildForPrincipal(array $principal)
	{
		  return new AddressBookHome($this->carddavBackend, $principal['uri']);
	}
	
  public function getACL()
  {
		return [
		  [
		      'privilege' => '{DAV:}read',
				  'principal' => '{DAV:}authenticated',
		      'protected' => true,
		  ],
		];
  }
}
