<?php

$config = [];

$config['database'] = 'data/sync/pdo/sqlite/deleted_cards.db';

$config['auth']['ldap'] = [
  'host'         => 'dev-db.intranet.isubsoft.com',
  'port'          => 389,
  'use_tls'       => false,
  'ldap_version'  => 3,       // using LDAPv3
  'network_timeout' => 10,    // The timeout (in seconds) for connect + bind attempts. This is only supported in PHP >= 5.3.0 with OpenLDAP 2.x
  'base_dn'       => 'ou=People,dc=example,dc=com',
  'bind_dn'       => '',
  'bind_pass'     => '',
  // It's possible to bind for an individual address book
  // The login name is used to search for the DN to bind with
  'search_base_dn' => '',
  'search_filter'  => '(&(objectclass=inetOrgPerson)(uid=%u))',   // e.g. '(&(objectClass=posixAccount)(uid=%u))'
  // DN and password to bind as before searching for bind DN, if anonymous search is not allowed
  'search_bind_dn' => 'cn=authclient,ou=People,dc=example,dc=com',
  'search_bind_pw' => 'authclient123',
  'scope' => 'list' // search mode: sub|base|list
];

$config['principal']['ldap'] = [
  'host'         => 'dev-db.intranet.isubsoft.com',
  'port'          => 389,
  'use_tls'       => false,
  'ldap_version'  => 3,       // using LDAPv3
  'network_timeout' => 10,    // The timeout (in seconds) for connect + bind attempts. This is only supported in PHP >= 5.3.0 with OpenLDAP 2.x
  'base_dn'       => 'ou=People,dc=example,dc=com',
  'bind_dn'       => '',
  'bind_pass'     => '',
  // It's possible to bind for an individual address book
  // The login name is used to search for the DN to bind with
  'search_base_dn' => '',
  'search_filter'  => '(&(objectclass=inetOrgPerson)(uid=%u))',   // e.g. '(&(objectClass=posixAccount)(uid=%u))'
  // DN and password to bind as before searching for bind DN, if anonymous search is not allowed
  'search_bind_dn' => 'cn=authclient,ou=People,dc=example,dc=com',
  'search_bind_pw' => 'authclient123',
  'scope' => 'list' // search mode: sub|base|list
];

$config['card']['addressbook']['ldap']['private'] = [
	'name'          	=> 'Personal Address Book',
  'description'     => 'New Book',
	'host'         		=> 'dev-db.intranet.isubsoft.com',
	'port'          	=> 389,
	'use_tls'	   			=> false,
  'ldap_version'		=> 3,       // using LDAPv3
	'network_timeout' => 15,
	'user_specific' 	=> true,
	'base_dn'       	=> 'ou=Address Book,%dn',
	'bind_dn'       	=> '',
	//    'bind_pass'     => '',
	'filter'        	=> '(objectClass=inetOrgPerson)',
	'writable'     	 	=> true,
	// If writable is true then these fields need to be populated:
	// LDAP_Object_Classes, required_fields, LDAP_rdn
	'LDAP_Object_Classes' => ['inetOrgPerson'],
	'required_fields'     => ['cn', 'sn'],
	'LDAP_rdn'      			=> 'uid',
	'search_fields' 			=> ['cn', 'mail'],
	'fieldmap'      => [
		// vCard    => LDAP
    'FN'            => 'cn',
    'N'							=> ['attr' => [ 0 => 'sn', 1 => 'givenName', 2 => '', 3 => 'title', 4 => '' ]],
		'EMAIL'         => 'mail:*',
		'ORG'         	=> 'o',
    'NICKNAME'      => 'displayName',
		'PHOTO'         => 'jpegphoto',
    'NOTE'        	=> 'description',
    'TEL'						=> ['type' => [
                                    'voice' => ['home' => 'homePhone',
                                                'work' => 'telephoneNumber',
                                                'default' => 'telephoneNumber',
                                                ], 
                                    'cell' => [
                                                'home' => 'mobile',
                                                'default' => 'mobile',
                                                ]
                                  ]
                        ],
	],
	'sort'          => 'cn',    // The field to sort the listing by.
	'scope'         => 'list',   // search mode: sub|base|list
	'fuzzy_search'  => true,     // server allows wildcard search
];
