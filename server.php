<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

/********************************************************************************
*
* WebDAV server
*
* Provides CardDAV support for contacts stored in LDAP
*
*********************************************************************************/

// Initialize
require_once __DIR__ . '/src/App/Bootstrap.php';

// Loader
require_once __BASE_DIR__ . '/vendor/autoload.php';

$GLOBALS['currentUserPrincipalId'] = null;
$GLOBALS['currentUserPrincipalBackendId'] = null;
$GLOBALS['currentUserPrincipalLdapConn'] = null;

// Backends
$authBackend = new ISubsoft\DAV\Auth\Backend\LDAP($config);
$principalBackend = new ISubsoft\DAV\DAVACL\PrincipalBackend\LDAP($config, $pdo);
$carddavBackend = new ISubsoft\DAV\CardDAV\Backend\LDAP($config, $pdo, $principalBackend);

// Setting up the directory tree //
$nodes = [
    new Sabre\DAVACL\PrincipalCollection($principalBackend),
    new ISubsoft\DAV\CardDAV\AddressBookRoot($principalBackend, $carddavBackend)
];

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);

// Setting the base uri
$server->setBaseUri($GLOBALS['base_uri']);

// Plugins
$aclPlugin = new Sabre\DAVACL\Plugin();
$aclPlugin->allowUnauthenticatedAccess = false;
$aclPlugin->hideNodesFromListings = true;

$server->addPlugin(new ISubsoft\DAV\Auth\Plugin($authBackend));
$server->addPlugin($aclPlugin);

if($GLOBALS['environment'] != 'prod')
	$server->addPlugin(new Sabre\DAV\Browser\Plugin());

$cardDavPlugin = new ISubsoft\DAV\CardDAV\Plugin();

if($GLOBALS['max_payload_size'] != null)
	$cardDavPlugin->setResourceSize($GLOBALS['max_payload_size']);

$server->addPlugin($cardDavPlugin);

if($GLOBALS['enable_incremental_sync'])
	$server->addPlugin(new Sabre\DAV\Sync\Plugin());

// And off we go!
$server->exec();
