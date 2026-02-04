<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\CardDAV;

class AddressBookHome extends \Sabre\CardDAV\AddressBookHome
{
    public function getChildren()
    {
        $addressbooks = $this->carddavBackend->getAddressBooksForUser($this->principalUri);
        $objs = [];
        foreach ($addressbooks as $addressbook) {
            $objs[] = new AddressBook($this->carddavBackend, $addressbook);
        }

        return $objs;
    }
    
  public function getACL()
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
