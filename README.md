# ldap-carddav - A CardDAV server with LDAP as authentication and contacts backend
![A CardDAV interface to LDAP](https://github.com/user-attachments/assets/e7d0f02a-bfd1-489f-b8a8-e0aef168c035)

## Features
1. Create, edit, rename and delete contacts directly in LDAP directory using CardDAV protocol.
2. Authenticate CardDAV users against LDAP directory.
3. Multiple address book support.
4. Global, shared and private address book support.
5. Bidirectional sync between LDAP directory and CardDAV clients (contacts deleted directly in LDAP are deleted in CardDAV clients asynchronously).
6. Supports WebDAV sync to get changes from LDAP incrementally.
7. Fully compatible (and can coexist) with LDAP address book applications.
8. Extensive and customizable configuration option to map vCard properties to LDAP directory attributes including multi-value and composite value properties and attributes.
9. Contact group support.
10. Media like profile picture support for contacts.
11. Caching support using popular backends like Memcached and APCu.

## Limitations
1. Does not support anonymous access to the server.
2. Same LDAP directory must be used for authentication as well as for address books.

## Planned features
1. Compatibility to as many vCard data types as possible.
2. Custom vCard property support.

## Installation
Check INSTALL file.

## Wiki
Checkout the wiki here - [https://github.com/isubsoft/ldap-carddav/wiki](https://github.com/isubsoft/ldap-carddav/wiki) for more useful information and resources regarding this application.
