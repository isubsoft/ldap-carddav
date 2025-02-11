<?php

namespace ISubsoft\DAV\CardDAV;

class AddressBookRoot extends \Sabre\CardDAV\AddressBookRoot
{
    public function getChildForPrincipal(array $principal)
    {
        return new AddressBookHome($this->carddavBackend, $principal['uri']);
    }
}
