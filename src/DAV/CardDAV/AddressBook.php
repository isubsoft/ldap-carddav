<?php

namespace ISubsoft\DAV\CardDAV;

class AddressBook extends \Sabre\CardDAV\AddressBook
{
	public function getACL()
	{
		  if(isset($this->carddavBackend->config['card']['addressbook']['ldap'][$this->getName()]) && $this->carddavBackend->config['card']['addressbook']['ldap'][$this->getName()]['writable'] == false) 
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
