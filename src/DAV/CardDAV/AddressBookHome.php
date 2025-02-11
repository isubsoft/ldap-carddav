<?php

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
}
