<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

/*

Addressbook/CardDAV server

This server features CardDAV support for LDAP as contacts backend

*/

// settings

// Make sure this setting is turned on and reflect the root url for your WebDAV server.
// This can be for example the root / or a complete path to your server script
$baseUri = '/';

// Autoloader
require_once 'src/App/Bootstrap.php';
require_once 'vendor/autoload.php';

// Backends
$authBackend = new isubsoft\dav\Auth\LDAP($config, $pdo);
$principalBackend = new isubsoft\dav\DAVACL\PrincipalBackend\LDAP($config, $authBackend);
$carddavBackend = new isubsoft\dav\CardDav\LDAP($config, $pdo, $authBackend);

// We're assuming that the realm name is called 'SabreDAV'.
$authBackend->setRealm('SabreDAV');

// Setting up the directory tree //
$nodes = [
    new Sabre\DAVACL\PrincipalCollection($principalBackend),
    new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend)
];

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);
$server->setBaseUri($baseUri);

// Plugins
$aclPlugin = new Sabre\DAVACL\Plugin();
$aclPlugin->allowUnauthenticatedAccess = false;
$aclPlugin->hideNodesFromListings = true;

$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend));
$server->addPlugin($aclPlugin);
$server->addPlugin(new Sabre\DAV\Browser\Plugin());
$server->addPlugin(new Sabre\CardDAV\Plugin());
$server->addPlugin(new Sabre\DAV\Sync\Plugin());

// And off we go!
$server->exec();
