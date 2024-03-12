<?php

/*

Addressbook/CardDAV server example

This server features CardDAV support

*/

// settings

// Make sure this setting is turned on and reflect the root url for your WebDAV server.
// This can be for example the root / or a complete path to your server script
$baseUri = '/';
$globalLdapConn = null;
$addressBookConfig = null;

// Autoloader
require_once 'vendor/autoload.php';
require 'conf/conf.php';


/* Database */
$pdo = new PDO('sqlite:'.$config['database']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Connect to ldap server
$ldapUri = ($config['auth']['ldap']['use_tls'] ? 'ldaps://' : 'ldap://') . $config['auth']['ldap']['host'] . ':' . $config['auth']['ldap']['port'];
$ldapConn = ldap_connect($ldapUri);

ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $config['auth']['ldap']['ldap_version']);
ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $config['auth']['ldap']['network_timeout']);

// using ldap bind
$searchBindDn  = $config['auth']['ldap']['search_bind_dn'];     // ldap rdn or dn
$searchBindPass = $config['auth']['ldap']['search_bind_pw'];  // associated password


if ($ldapConn) {

    // binding to ldap server
    $ldapBind = ldap_bind($ldapConn, $searchBindDn, $searchBindPass);

    // verify binding
    if ($ldapBind) {
        $globalLdapConn = $ldapConn;
    }
}


// Backends
$authBackend = new isubsoft\dav\Auth\LDAP($config);
$principalBackend = new isubsoft\dav\DAVACL\PrincipalBackend\LDAP($config);
$carddavBackend = new isubsoft\dav\CardDav\LDAP($config, $pdo, $principalBackend);


// Setting up the directory tree //
$nodes = [
    new Sabre\DAV\SimpleCollection('principals', [
        new Sabre\DAVACL\PrincipalCollection($principalBackend, 'principals/users')
    ]),
    new Sabre\DAV\SimpleCollection('addressbooks', [
        new isubsoft\dav\CardDav\AddressBookRoot($principalBackend, $carddavBackend, 'principals/users')
    ])
];


// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);
$server->setBaseUri($baseUri);

// Plugins
$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend));
$server->addPlugin(new Sabre\DAV\Browser\Plugin());
$server->addPlugin(new isubsoft\dav\CardDav\CardDAVPlugin());
$server->addPlugin(new Sabre\DAVACL\Plugin());
// $server->addPlugin(new Sabre\DAV\Sync\Plugin());

// And off we go!
$server->exec();