# ldap-carddav - A CardDAV server with LDAP as authentication and contacts backend
![A CardDAV server for LDAP](https://github.com/user-attachments/assets/e7d0f02a-bfd1-489f-b8a8-e0aef168c035)

## Features
1. Authenticate CardDAV users against LDAP server.
2. Multiple address book support.
3. Global, shared and private address book support.
4. Read, write and rename contacts between CardDAV clients and LDAP server.
5. Bidirectional sync between LDAP server and CardDAV clients for new contacts, modified contacts and contacts deleted via CardDAV protocol. Contacts deleted directly in LDAP server are deleted in CardDAV clients asynchronously.
6. Supports WebDAV sync to get changes from LDAP server incrementally.
7. Fully compatible with LDAP address book applications. Contacts are stored in LDAP server as directory entries and not as vCards.
8. Extensive and customizable configuration option to map vCard properties to LDAP attributes including multi-value and composite value properties and attributes.
9. Contact group support.
10. Media like profile picture support.

## Limitations
1. Does not support anonymous access to the server.
2. Same LDAP server must be used for authentication as well as for address books.

## Planned features
1. Compatibility to as many vCard data types as possible.
2. Custom vCard property support.

## Installation
Check INSTALL file.

## Wiki
Checkout the wiki here - [https://github.com/isubsoft/ldap-carddav/wiki](https://github.com/isubsoft/ldap-carddav/wiki) for more useful information and resources regarding this project.

## Support
Email: [admin@isubsoft.com](mailto:admin@isubsoft.com?subject=Support%20request%20for%20your%20product%20ldap-carddav)
