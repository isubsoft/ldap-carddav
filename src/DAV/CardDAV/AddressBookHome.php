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

class AddressBookHome extends \Sabre\CardDAV\AddressBookHome
{
  public function getChildren()
  {
		$addressbooks = $this->carddavBackend->getAddressBooksForUser($this->principalUri);
		$objs = [];
		
		foreach ($addressbooks as $addressbook) {
			if($this->carddavBackend->isAddressbookDirectory($addressbook['id']))
				$objs[] = new AddressBookDirectory($this->carddavBackend, $addressbook);
			else
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
