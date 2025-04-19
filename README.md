# ldap-carddav - A CardDAV server with LDAP as authentication and contacts backend
![banner_01_trimmed](https://github.com/user-attachments/assets/5deb0f55-3276-4fd3-9344-485352106f6e)  

## Features
1. Authenticate CardDAV users against LDAP.
2. Multiple address book support.
3. Global, shared and private address book support.
4. Full read-write support between CardDAV clients and LDAP with contact renaming.
5. Bidirectional sync between LDAP and CardDAV clients for new contacts, modified contacts and contacts deleted via CardDAV protocol (contacts deleted directly in LDAP are deleted in CardDAV clients asynchronously).
6. Fully compatible with LDAP address book applications since contacts are stored in LDAP server as normal directory entries and not as vCards.
7. Extensive and customizable configuration option to map vCard properties to LDAP attributes including multi-value and composite value properties and attributes.
8. Media like profile picture support.
9. Contact group support.

## Limitations
1. Does not support anonymous access to the server.
2. Same LDAP server must be used for authentication as well as address book.
3. Does not support any date and/or time vCard property.

## Planned features
1. Compatibility to as many vCard data types as possible.

## Installation
1. Check INSTALL file.

## Wiki
Check the Wiki here - [https://github.com/isubsoft/ldap-carddav/wiki](https://github.com/isubsoft/ldap-carddav/wiki) for more useful information and resources regarding this project.
