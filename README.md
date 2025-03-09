# ldap-carddav
Extension of SabreDAV to add LDAP as authentication and CardDAV backend.

-- The following features are available
1. Multiple address book support.
2. Full contact read-write support to LDAP with contact renaming.
3. Realtime bidirectional sync between LDAP and CardDAV clients for new contacts, modified contacts and contacts deleted via CardDAV protocol (contacts deleted directly in LDAP can be obtained by CardDAV clients asynchronously).
4. Extensive and customizable configuration option to map vCard properties to LDAP attributes including multi-value and composite value properties/attributes.
5. Global/shared and private address book support.
6. Contact group support (vCard v3.0 and v4.0).

-- Limitations
1. Does not support anonymous access to the server.
2. Same LDAP server must be used for both authentication and address books.
3. Does not support any date and/or time vCard property.

-- Planned features
1. Compatibility to as many vCard data types as possible.

-- Installation
1. Check INSTALL file.
