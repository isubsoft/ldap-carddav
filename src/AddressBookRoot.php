<?php

namespace isubsoft\dav;

class AddressBookRoot extends \Sabre\CardDAV\AddressBookRoot {

    function getName() {

        // Grabbing all the components of the principal path.
        $parts = explode('/', $this->principalPrefix);

        // We are only interested in the second part.
        return $parts[1];

    }

}

?>