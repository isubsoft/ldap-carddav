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
		if($this->carddavBackend->isAddressbookWritable($this->getName()) == false)
			return [
					[
					    'privilege' => '{DAV:}read',
					    'principal' => '{DAV:}owner',
					    'protected' => true,
					],
			];
			
			return parent::getChildACL();
  }
}
