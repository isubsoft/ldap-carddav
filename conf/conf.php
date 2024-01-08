<?php

$config = [];

$config['auth']['ldap'] = [
  'hosts'         => array('directory.example.com'),
  'port'          => 389,
  'use_tls'       => false,
  'ldap_version'  => 3,       // using LDAPv3
  'network_timeout' => 10,    // The timeout (in seconds) for connect + bind attempts. This is only supported in PHP >= 5.3.0 with OpenLDAP 2.x
  'base_dn'       => 'ou=People,dc=example,dc=com',
  'bind_dn'       => '',
  'bind_pass'     => '',
  // It's possible to bind for an individual address book
  // The login name is used to search for the DN to bind with
  'search_base_dn' => 'ou=People,dc=example,dc=com',
  'search_filter'  => '',   // e.g. '(&(objectClass=posixAccount)(uid=%u))'
  // DN and password to bind as before searching for bind DN, if anonymous search is not allowed
  'search_bind_dn' => 'cn=admin,dc=example,dc=com',
  'search_bind_pw' => '<password>',
  'scope' => ''
];

$config['card']['ldap']['<name>'] = []; // TBD