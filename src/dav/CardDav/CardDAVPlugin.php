<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

namespace isubsoft\dav\CardDav;

class CardDAVPlugin extends \Sabre\CardDAV\Plugin {

    function getAddressBookHomeForPrincipal($principal) {

        if (!substr($principal, 0, strlen('principal/') !== 'principal/')) {
            throw new \LogicException('This is not supposed to happen');
        }
        return 'addressbooks/' . substr($principal,strlen('principal/')+1);

    }

}

?>
