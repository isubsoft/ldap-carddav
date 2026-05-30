# ldap-carddav - A CardDAV server with a LDAP server as contacts and authentication backend
![ldap-carddav - A CardDAV server for LDAP](https://github.com/user-attachments/assets/e7d0f02a-bfd1-489f-b8a8-e0aef168c035)

## Features
1. Create, edit, rename and delete contacts directly in LDAP server using CardDAV protocol.
2. Authenticate CardDAV users against LDAP server.
3. User group support.
4. Multiple address book support.
5. Global, private and group (shared) address book support.
6. Bidirectional sync between LDAP server and CardDAV clients (contacts deleted directly in LDAP are deleted in CardDAV clients asynchronously).
7. Supports WebDAV sync.
8. Fully compatible (and can coexist) with LDAP address book applications.
9. Extensive and customizable configuration option to map vCard properties to LDAP attributes including multi-value and composite value properties and attributes.
10. Contact group support.
11. Media (like profile picture) support for contacts.
12. Caching support using popular backends like Memcached, APCu and file system.

## Limitations
1. Does not support anonymous access.
2. Same LDAP server must be used for authentication as well as for address books.
3. vCard properties not mapped in conf file are not stored.
4. Following vCard parameter(s) are not stored - PREF.

## Planned features
1. Support for more PDO databases.
2. Compatibility to as many vCard data types as possible.
3. Custom vCard property support.

## Wiki
Checkout the [wiki](https://github.com/isubsoft/ldap-carddav/wiki) for useful information and resources regarding this application.

## Installation
Check the INSTALL file.
