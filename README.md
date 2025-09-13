# ldap-carddav - A CardDAV server with LDAP as authentication and contacts backend
![A CardDAV interface to LDAP](https://github.com/user-attachments/assets/e7d0f02a-bfd1-489f-b8a8-e0aef168c035)

## Features
1. Store contacts directly in LDAP directory.
2. Authenticate CardDAV users against LDAP directory.
3. Multiple address book support.
4. Global, shared and private address book support.
5. Read, write and rename contacts in LDAP directory using CardDAV protocol.
6. Bidirectional sync between LDAP directory and CardDAV clients. Contacts deleted directly in LDAP are deleted in CardDAV clients asynchronously.
7. Supports WebDAV sync to get changes from LDAP incrementally.
8. Fully compatible with LDAP address book applications since contacts are stored directly in LDAP directory.
9. Extensive and customizable configuration option to map vCard properties to LDAP directory attributes including multi-value and composite value properties and attributes.
10. Contact group support.
11. Media like profile picture support for contacts.

## Limitations
1. Does not support anonymous access to the server.
2. Same LDAP directory must be used for authentication as well as for address books.

## Planned features
1. Compatibility to as many vCard data types as possible.
2. Custom vCard property support.
3. Caching of backend contacts.

## Installation
Check INSTALL file.

## Wiki
Checkout the wiki here - [https://github.com/isubsoft/ldap-carddav/wiki](https://github.com/isubsoft/ldap-carddav/wiki) for more useful information and resources regarding this application.
