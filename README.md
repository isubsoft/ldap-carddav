# ldap-carddav
A CardDAV server with LDAP as authentication and contacts backend.

-- The following features are available
1. Authenticate CardDAV users against LDAP.
2. Multiple LDAP address book support.
3. Full read-write support to LDAP with contact renaming.
4. Bidirectional sync between LDAP and CardDAV clients for new contacts, modified contacts and contacts deleted via CardDAV protocol (contacts deleted directly in LDAP are obtained by CardDAV clients asynchronously).
5. Extensive and customizable configuration option to map vCard properties to LDAP attributes including multi-value and composite value properties and attributes.
6. Global, shared and private address book support.
7. Contact group support.

-- Limitations
1. Does not support anonymous access to the server.
2. Same LDAP server must be used for authentication as well as address book.
3. Does not support any date and/or time vCard property.

-- Planned features
1. Compatibility to as many vCard data types as possible.

-- Installation
1. Check INSTALL file.
