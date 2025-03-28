<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\CardDAV;

class AddressBook extends \Sabre\CardDAV\AddressBook
{
	public function getACL()
	{
		  if($this->carddavBackend->isAddressbookWritable($this->getName()) == false)
				return [
				    [
				        'privilege' => '{DAV:}read',
				        'principal' => '{DAV:}owner',
				        'protected' => true,
				    ],
				];
				
			return parent::getACL();
	}
	
  public function getChildACL()
  {
      return [
          [
              'privilege' => '{DAV:}all',
				      'principal' => '{DAV:}owner',
              'protected' => true,
          ],
      ];
  }
}
