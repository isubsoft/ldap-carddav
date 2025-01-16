<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

/*

Addressbook/CardDAV server example

This server features CardDAV support

*/

// settings

// Make sure this setting is turned on and reflect the root url for your WebDAV server.
// This can be for example the root / or a complete path to your server script
$baseUri = '/';

//constants
$GLOBALS['__BASE_DIR__'] = __DIR__;
$GLOBALS['__DATA_DIR__'] = $GLOBALS['__BASE_DIR__'].'/data';
$GLOBALS['__CONF_DIR__'] = $GLOBALS['__BASE_DIR__'].'/conf';

require $GLOBALS['__CONF_DIR__'].'/conf.php';

/* Database */
try {
    $pdo = new PDO($config['database']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\Throwable $th) {
    error_log('Could not create database connection: '. $th->getMessage());
    http_response_code(500);
    exit;
}

// Autoloader
require_once 'vendor/autoload.php';


// Backends
$authBackend = new isubsoft\dav\Auth\LDAP($config);
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
$authPlugin = new Sabre\DAV\Auth\Plugin($authBackend);
$server->addPlugin($authPlugin);
$server->addPlugin(new Sabre\DAVACL\Plugin());
$server->addPlugin(new Sabre\DAV\Browser\Plugin());
$server->addPlugin(new isubsoft\dav\CardDav\CardDAVPlugin());
$server->addPlugin(new Sabre\DAV\Sync\Plugin());



// And off we go!
$authPlugin->autoRequireLogin = true;

$server->exec();
