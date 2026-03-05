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

// Set log level
error_reporting($GLOBALS['log_level']);

$GLOBALS['currentUserPrincipalUri'] = null;
$GLOBALS['currentUserPrincipalId'] = null;
$GLOBALS['currentUserPrincipalLdapConn'] = null;

// Backends
$authBackend = new ISubsoft\DAV\Auth\Backend\LDAP($config);
$principalBackend = new ISubsoft\DAV\DAVACL\PrincipalBackend\LDAP($config, $pdo);
$propStoreBackend = new Sabre\DAV\PropertyStorage\Backend\PDO($pdo);
$carddavBackend = new ISubsoft\DAV\CardDAV\Backend\LDAP($config, $pdo, $principalBackend);

// Setting up the directory tree //
$nodes = [
	new ISubsoft\DAV\DAVACL\PrincipalCollection($principalBackend),
	new ISubsoft\DAV\CardDAV\AddressBookRoot($principalBackend, $carddavBackend)
];

// Check cache before processing the request
$entityBackend = [
	'principal' => $principalBackend, 
	'card' => $carddavBackend
];

foreach($entityBackend as $key => $value) {
	if($value->cacheResetRequired() && !$value->resetCache()) {
		trigger_error("Cache could not be reset for object type '$key'.", E_USER_WARNING);
		http_response_code(503);
		exit(1);
	}
}

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);

// Setting the base uri
$server->setBaseUri($GLOBALS['base_uri']);

//// Plugins ////
// Add authentication plugin
$server->addPlugin(new ISubsoft\DAV\Auth\Plugin($authBackend));

// Add ACL plugin
$aclPlugin = new Sabre\DAVACL\Plugin();
$aclPlugin->allowUnauthenticatedAccess = false;
$aclPlugin->allowAccessToNodesWithoutACL = false;
$aclPlugin->hideNodesFromListings = true;

$server->addPlugin($aclPlugin);

// Add carddav plugin
$cardDavPlugin = new ISubsoft\DAV\CardDAV\Plugin($carddavBackend);

if($GLOBALS['max_payload_size'] != null)
	$cardDavPlugin->setResourceSize($GLOBALS['max_payload_size']);

$server->addPlugin($cardDavPlugin);

// Add property storage plugin
$server->addPlugin(new ISubsoft\DAV\PropertyStorage\Plugin($propStoreBackend));

// Add webdav sync plugin
if($GLOBALS['enable_incremental_sync'])
	$server->addPlugin(new Sabre\DAV\Sync\Plugin());

// Add browser plugin
if($GLOBALS['environment'] != 'prod')
	$server->addPlugin(new Sabre\DAV\Browser\Plugin());


// And off we go!
$server->exec();
