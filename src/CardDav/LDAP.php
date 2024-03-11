<?php

namespace isubsoft\dav\CardDav;

use isubsoft\dav\Utility\LDAP as Utility;

class LDAP extends \Sabre\CardDAV\Backend\AbstractBackend implements \Sabre\CardDAV\Backend\SyncSupport {

    /**
     * Store ldap directory access credentials
     *
     * @var array
     */
    public $config;

    /**
     * prefix for ldap
     *
     * @var string
     */
    public $principalPrefix = 'principals/users/';

    public $vCardKeyParts = ['ADR', 'ORG'];

    public $vCardKeyMulti = ['TEL'];


    /**
     * PDO connection.
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * The table name that will be used for tracking changes in address books.
     *
     * @var string
     */
    public $deletedCardsTableName = 'deleted_cards';

    /**
     * sync Token.
     *
     * @var string
     */
    protected $syncToken = null;


    /**
     * Creates the backend.
     *
     * configuration array must be provided
     * to access initial directory.
     *
     * @param array $config
     * @return void
     */
    function __construct(array $config, \PDO $pdo) {
        $this->config = $config;
        $this->pdo = $pdo;
    }


    /**
     * Returns the list of addressbooks for a specific user.
     *
     * Every addressbook should have the following properties:
     *   id - an arbitrary unique id
     *   uri - the 'basename' part of the url
     *   principaluri - Same as the passed parameter
     *
     * Any additional clark-notation property may be passed besides this. Some
     * common ones are :
     *   {DAV:}displayname
     *   {urn:ietf:params:xml:ns:carddav}addressbook-description
     *   {http://calendarserver.org/ns/}getctag
     *
     * @param string $principalUri
     * @return array
     */
    function getAddressBooksForUser($principalUri)
    {      
        $this->syncToken = time();
        $ldapConn = $GLOBALS['globalLdapConn'];
        $searchUserId = basename($principalUri);

        $addressBooks = [];
        $principalUserDn = null;
                    
        $ldaptree = ($this->config['principal']['ldap']['search_base_dn'] !== '') ? $this->config['principal']['ldap']['search_base_dn'] : $this->config['principal']['ldap']['base_dn'];
        $filter = str_replace('%u', $searchUserId, $this->config['principal']['ldap']['search_filter']);  // single filter

        $data = Utility::LdapQuery($ldapConn, $ldaptree, $filter, [], strtolower($this->config['principal']['ldap']['scope']));
                            
		if($data['count'] === 1)
        {
            $principalUserDn = $data[0]['dn'];
        }
        else
        {
			return [];
        }

        foreach ($this->config['card']['addressbook']['ldap'] as $addressBookName => $configParams) {
 
               	$addressBookDn = str_replace('%dn', $principalUserDn, $configParams['base_dn']);
               	$addressBookDn = str_replace('%u', $searchUserId, $addressBookDn);

                $addressBooks[] = [
                    'id'                                                          => $addressBookDn,
                    'uri'                                                         => $addressBookName,
                    'principaluri'                                                => $principalUri,
                    '{DAV:}displayname'                                           => $configParams['name'],
                    '{' . CardDAVPlugin::NS_CARDDAV . '}addressbook-description'  => $configParams['description'],
                    '{http://sabredav.org/ns}sync-token'                          => isset($this->syncToken) ? $this->syncToken : 0,
                ];
                $GLOBALS['addressBookConfig'][$addressBookDn] = $configParams;
        }
        
        return $addressBooks;
    }

    /**
     * Updates properties for an address book.
     *
     * The list of mutations is stored in a Sabre\DAV\PropPatch object.
     * To do the actual updates, you must tell this object which properties
     * you're going to process with the handle() method.
     *
     * Calling the handle method is like telling the PropPatch object "I
     * promise I can handle updating this property".
     *
     * Read the PropPatch documentation for more info and examples.
     *
     * @param string $addressBookId
     * @param \Sabre\DAV\PropPatch $propPatch
     * @return void
     */
    function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch)
    {
        return false;
    }

    /**
     * Creates a new address book.
     *
     * This method should return the id of the new address book. The id can be
     * in any format, including ints, strings, arrays or objects.
     *
     * @param string $principalUri
     * @param string $url Just the 'basename' of the url.
     * @param array $properties
     * @return mixed
     */
    function createAddressBook($principalUri, $url, array $properties)
    {
        return false;
    }

    /**
     * Deletes an entire addressbook and all its contents
     *
     * @param mixed $addressBookId
     * @return void
     */
    function deleteAddressBook($addressBookId)
    {
        return false;
    }

    /**
     * Returns all cards for a specific addressbook id.
     *
     * This method should return the following properties for each card:
     *   * carddata - raw vcard data
     *   * uri - Some unique url
     *   * lastmodified - A unix timestamp
     *
     * It's recommended to also return the following properties:
     *   * etag - A unique etag. This must change every time the card changes.
     *   * size - The size of the card in bytes.
     *
     * If these last two properties are provided, less time will be spent
     * calculating them. If they are specified, you can also ommit carddata.
     * This may speed up certain requests, especially with large cards.
     *
     * @param mixed $addressbookId
     * @return array
     */
    function getCards($addressBookDn)
    {
        $result = [];     
        $ldapConn = $GLOBALS['globalLdapConn'];
        $addressBookConfig = $GLOBALS['addressBookConfig'][$addressBookDn];
        
        $filter = '(&'.$addressBookConfig['filter']. '(createtimestamp<=' . gmdate('YmdHis', $this->syncToken) . 'Z))'; 
        $attributes = ['cn','uid','entryuuid','modifytimestamp'];

        $data = Utility::LdapQuery($ldapConn, $addressBookDn, $filter, $attributes, strtolower($addressBookConfig['scope']));
                    
        if($data['count'] > 0)
        {
            for ($i=0; $i < $data['count']; $i++) { 
                   
                $row = [    'id' => $data[$i]['entryuuid'][0],
                            'uri' => $data[$i]['uid'][0],
                            'lastmodified' => strtotime($data[$i]['modifytimestamp'][0]),
                            ];

                $result[] = $row;
            }     
        }
        
        return $result;
    }

    /**
     * Returns a specfic card.
     *
     * The same set of properties must be returned as with getCards. The only
     * exception is that 'carddata' is absolutely required.
     *
     * If the card does not exist, you must return false.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @return array
     */
    function getCard($addressBookId, $cardUri)
    {
        $ldapConn = $GLOBALS['globalLdapConn'];
        $addressBookConfig = $GLOBALS['addressBookConfig'][$addressBookId];
        $result = [];
        
        
        $ldapTree = $addressBookConfig['LDAP_rdn']. '='. $cardUri. ',' .$addressBookId;
        $filter = $addressBookConfig['filter']; 
        $attributes = ['*', 'entryuuid', 'modifytimestamp'];
        
        $ldapResult = ldap_read($ldapConn, $ldapTree, $filter, $attributes);

        if($ldapResult)
        {
            $data = ldap_get_entries($ldapConn, $ldapResult);
                    
            if($data['count'] > 0)
            {
                $cardData = $this->generateVcard($data[0], $addressBookConfig['fieldmap'], $cardUri);
                 
                $result = [
                    'id'            => $data[0]['entryuuid'][0],
                    'carddata'      => $cardData,
                    'uri'           => $cardUri,
                    'lastmodified'  => strtotime($data[0]['modifytimestamp'][0]),
                ];
                        
                return $result;
            }
        }
        return false;
        
    }

    
    /**
     * Returns a list of cards.
     *
     * This method should work identical to getCard, but instead return all the
     * cards in the list as an array.
     *
     * If the backend supports this, it may allow for some speed-ups.
     *
     * @param mixed $addressBookId
     * @param array $uris
     * @return array
     */
    function getMultipleCards($addressBookId, array $uris)
    {
        $result = [];

        foreach($uris as $uri)
        {
            $result[] = $this->getCard($addressBookId, $uri);
        }

        return $result;
    }

    /**
     * Creates a new card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressBooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag is for the
     * newly created resource, and must be enclosed with double quotes (that
     * is, the string itself must contain the double quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @param string $cardData
     * @return string|null
     */
    function createCard($addressBookId, $cardUri, $cardData)
    {
        $ldapConn = $GLOBALS['globalLdapConn'];
        $addressBookConfig = $GLOBALS['addressBookConfig'][$addressBookId];

        $ldapInfo = [];
        $ldapInfo['objectclass'] = $addressBookConfig['LDAP_Object_Classes'];
            
        $vcard = \Sabre\VObject\Reader::read($cardData);
        
        foreach($addressBookConfig['fieldmap'] as $vCardKey => $ldapKey)
        {
            if(is_array($ldapKey))
            {
                if(array_key_exists('attr', $ldapKey))
                {
                    foreach($ldapKey['attr'] as $index => $ldapElement)
                    {
                        if($ldapElement != '' && $ldapElement != null && $vcard->$vCardKey->getParts()[$index] != '')
                        {
                            $ldapInfo[strtolower($ldapElement)] = $vcard->$vCardKey->getParts()[$index];                         
                        }
                    }
                }

                if(array_key_exists('type', $ldapKey))
                {
                    if(in_array($vCardKey, $this->vCardKeyMulti))
                    {                       
                        foreach($vcard->$vCardKey as $values) {
                            
                            $vCardType = [];

                            if ($param = $values['TYPE']) {
                                foreach($param as $value) {
                                  $vCardType[] = $value;
                                }
                            }
                            
                            if(!empty($vCardType))
                            {
                                $vCardElementsMatch = false;
                                foreach($ldapKey['type'] as $index => $vCardElements)
                                {                        
                                    if(in_array($index, $vCardType))
                                    { 
                                        $vCardElementsMatch = true;
                                        $vCardElementMatch = false;
                                        foreach($vCardElements as $vCardElement => $newLdapKey)
                                        {
                                            if(in_array($vCardElement, $vCardType))
                                            {
                                                $ldapInfo[strtolower($newLdapKey)][] = (string)$values;
                                                $vCardElementMatch = true;
                                            }
                                        }
        
                                        if($vCardElementMatch == false)
                                        {
                                            $ldapInfo[strtolower($vCardElements['default'])][] = (string)$values;
                                        }
                                    }
                                }
                                
                                if($vCardElementsMatch == false)
                                {
                                    $vCardElementMatch = false;
                                    $vCardDefaultElement = array_key_first($ldapKey['type']);
                                    foreach($ldapKey['type'][$vCardDefaultElement] as $vCardElement => $newLdapKey)
                                    {
                                        if(in_array($vCardElement, $vCardType))
                                        {
                                            $ldapInfo[strtolower($newLdapKey)][] = (string)$values;
                                            $vCardElementMatch = true;
                                        }
                                    }
        
                                    if($vCardElementMatch == false)
                                    {
                                        $ldapInfo[strtolower($ldapKey['type'][$vCardDefaultElement]['default'])][] = (string)$values;
                                    }
                                }
                            }
                            else
                            {
                                $vCardDefaultElement = array_key_first($ldapKey['type']);
                                $ldapInfo[strtolower($ldapKey['type'][$vCardDefaultElement]['default'])][] = (string)$values;
                            }
                        }   
                    }
                    else
                    {
                        $vCardType = [];

                        if ($param = $vcard->$vCardKey['TYPE']) {
                            foreach($param as $value) {
                              $vCardType[] = $value;
                            }
                        }
    
                        if(!empty($vCardType))
                        {
                            $vCardElementsMatch = false;
                            foreach($ldapKey['type'] as $index => $vCardElements)
                            {                        
                                if(in_array($index, $vCardType))
                                {
                                    $vCardElementsMatch = true;
                                    $vCardElementMatch = false;
                                    foreach($vCardElements as $vCardElement => $newLdapKey)
                                    {
                                        if(in_array($vCardElement, $vCardType))
                                        {
                                            $ldapInfo[strtolower($newLdapKey)] = (string)$vcard->$vCardKey;
                                            $vCardElementMatch = true;
                                        }
                                    }
    
                                    if($vCardElementMatch == false)
                                    {
                                        $ldapInfo[strtolower($vCardElements['default'])] = (string)$vcard->$vCardKey;
                                    }
                                }
                            }
                            
                            if($vCardElementsMatch == false)
                            {
                                $vCardElementMatch = false;
                                $vCardDefaultElement = array_key_first($ldapKey['type']);
                                foreach($ldapKey['type'][$vCardDefaultElement] as $vCardElement => $newLdapKey)
                                {
                                    if(in_array($vCardElement, $vCardType))
                                    {
                                        $ldapInfo[strtolower($newLdapKey)] = (string)$vcard->$vCardKey;
                                        $vCardElementMatch = true;
                                    }
                                }
    
                                if($vCardElementMatch == false)
                                {
                                    $ldapInfo[strtolower($ldapKey['type'][$vCardDefaultElement]['default'])] = (string)$vcard->$vCardKey;
                                }
                            }
                        }
                        else
                        {
                            $vCardDefaultElement = array_key_first($ldapKey['type']);
                            $ldapInfo[strtolower($ldapKey['type'][$vCardDefaultElement]['default'])] = (string)$vcard->$vCardKey;
                        }
                    }    
                }
            }
            else
            {
                $ldapKey = strtolower($ldapKey);
                if(isset($vcard->$vCardKey))
                {
                    if(strpos($ldapKey, ':*'))
                    {
                        $newLdapKey = str_replace(':*', '', $ldapKey);
                        foreach($vcard->$vCardKey as $values)
                        {
                            $ldapInfo[$newLdapKey][] = (string)$values;
                        }
                    }
                    else
                    { 
                        if($vCardKey == 'PHOTO')
                        {
                            if((string)$vcard->$vCardKey['ENCODING'] == 'B')
                            {
                                $ldapInfo[$ldapKey] = (string)$vcard->$vCardKey;
                            }
                            else
                            {
                                $image = file_get_contents((string)$vcard->$vCardKey);

                                if ($image !== false)
                                    $ldapInfo[$ldapKey] = $image;          
                            } 
                        }
                        else
                        {
                            $ldapInfo[$ldapKey] = (string)$vcard->$vCardKey;
                        }         
                        
                    }
                } 
            }    
        }
       
        if(! array_key_exists('sn', $ldapInfo))
        {
            if( isset($vcard->FN))
            {
                $ldapInfo['sn'] = (string)$vcard->FN;
            }
        }
        
        foreach ($addressBookConfig['required_fields'] as $key) {
            if(! array_key_exists($key, $ldapInfo))
            return false;
        }
        
        $ldapTree = $addressBookConfig['LDAP_rdn']. '='. $cardUri. ',' .$addressBookId;
        
        $ldapResponse = ldap_add($ldapConn, $ldapTree, $ldapInfo);
                    
        if ($ldapResponse) 
        {    
            return null;
        }

        return false;
    }

    /**
     * Updates a card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressBooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag should
     * match that of the updated resource, and must be enclosed with double
     * quotes (that is: the string itself must contain the actual quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @param string $cardData
     * @return string|null
     */
    function updateCard($addressBookId, $cardUri, $cardData)
    {
        $ldapConn = $GLOBALS['globalLdapConn'];
        $addressBookConfig = $GLOBALS['addressBookConfig'][$addressBookId];

        $ldapInfo = [];
        $ldapInfo['uid'] = $cardUri;
        $ldapInfo['objectclass'] = $addressBookConfig['LDAP_Object_Classes'];

        $vcard = \Sabre\VObject\Reader::read($cardData);
        
        foreach($addressBookConfig['fieldmap'] as $vCardKey => $ldapKey)
        {
            if(is_array($ldapKey))
            {
                if(array_key_exists('attr', $ldapKey))
                {
                    foreach($ldapKey['attr'] as $index => $ldapElement)
                    {
                        if($ldapElement != '' && $ldapElement != null && $vcard->$vCardKey->getParts()[$index] != '')
                        {
                            $ldapInfo[strtolower($ldapElement)] = $vcard->$vCardKey->getParts()[$index];                         
                        }
                    }
                }

                if(array_key_exists('type', $ldapKey))
                {
                    if(in_array($vCardKey, $this->vCardKeyMulti))
                    {                       
                        foreach($vcard->$vCardKey as $values) {
                            
                            $vCardType = [];

                            if ($param = $values['TYPE']) {
                                foreach($param as $value) {
                                  $vCardType[] = $value;
                                }
                            }
                            
                            if(!empty($vCardType))
                            {
                                $vCardElementsMatch = false;
                                foreach($ldapKey['type'] as $index => $vCardElements)
                                {                        
                                    if(in_array($index, $vCardType))
                                    { 
                                        $vCardElementsMatch = true;
                                        $vCardElementMatch = false;
                                        foreach($vCardElements as $vCardElement => $newLdapKey)
                                        {
                                            if(in_array($vCardElement, $vCardType))
                                            {
                                                $ldapInfo[strtolower($newLdapKey)][] = (string)$values;
                                                $vCardElementMatch = true;
                                            }
                                        }
        
                                        if($vCardElementMatch == false)
                                        {
                                            $ldapInfo[strtolower($vCardElements['default'])][] = (string)$values;
                                        }
                                    }
                                }
                                
                                if($vCardElementsMatch == false)
                                {
                                    $vCardElementMatch = false;
                                    $vCardDefaultElement = array_key_first($ldapKey['type']);
                                    foreach($ldapKey['type'][$vCardDefaultElement] as $vCardElement => $newLdapKey)
                                    {
                                        if(in_array($vCardElement, $vCardType))
                                        {
                                            $ldapInfo[strtolower($newLdapKey)][] = (string)$values;
                                            $vCardElementMatch = true;
                                        }
                                    }
        
                                    if($vCardElementMatch == false)
                                    {
                                        $ldapInfo[strtolower($ldapKey['type'][$vCardDefaultElement]['default'])][] = (string)$values;
                                    }
                                }
                            }
                            else
                            {
                                $vCardDefaultElement = array_key_first($ldapKey['type']);
                                $ldapInfo[strtolower($ldapKey['type'][$vCardDefaultElement]['default'])][] = (string)$values;
                            }
                        }   
                    }
                    else
                    {
                        $vCardType = [];

                        if ($param = $vcard->$vCardKey['TYPE']) {
                            foreach($param as $value) {
                              $vCardType[] = $value;
                            }
                        }
    
                        if(!empty($vCardType))
                        {
                            $vCardElementsMatch = false;
                            foreach($ldapKey['type'] as $index => $vCardElements)
                            {                        
                                if(in_array($index, $vCardType))
                                {
                                    $vCardElementsMatch = true;
                                    $vCardElementMatch = false;
                                    foreach($vCardElements as $vCardElement => $newLdapKey)
                                    {
                                        if(in_array($vCardElement, $vCardType))
                                        {
                                            $ldapInfo[strtolower($newLdapKey)] = (string)$vcard->$vCardKey;
                                            $vCardElementMatch = true;
                                        }
                                    }
    
                                    if($vCardElementMatch == false)
                                    {
                                        $ldapInfo[strtolower($vCardElements['default'])] = (string)$vcard->$vCardKey;
                                    }
                                }
                            }
                            
                            if($vCardElementsMatch == false)
                            {
                                $vCardElementMatch = false;
                                $vCardDefaultElement = array_key_first($ldapKey['type']);
                                foreach($ldapKey['type'][$vCardDefaultElement] as $vCardElement => $newLdapKey)
                                {
                                    if(in_array($vCardElement, $vCardType))
                                    {
                                        $ldapInfo[strtolower($newLdapKey)] = (string)$vcard->$vCardKey;
                                        $vCardElementMatch = true;
                                    }
                                }
    
                                if($vCardElementMatch == false)
                                {
                                    $ldapInfo[strtolower($ldapKey['type'][$vCardDefaultElement]['default'])] = (string)$vcard->$vCardKey;
                                }
                            }
                        }
                        else
                        {
                            $vCardDefaultElement = array_key_first($ldapKey['type']);
                            $ldapInfo[strtolower($ldapKey['type'][$vCardDefaultElement]['default'])] = (string)$vcard->$vCardKey;
                        }
                    }    
                }
            }
            else
            {
                $ldapKey = strtolower($ldapKey);
                if(isset($vcard->$vCardKey))
                {
                    if(strpos($ldapKey, ':*'))
                    {
                        $newLdapKey = str_replace(':*', '', $ldapKey);
                        foreach($vcard->$vCardKey as $values)
                        {
                            $ldapInfo[$newLdapKey][] = (string)$values;
                        }
                    }
                    else
                    { 
                        if($vCardKey == 'PHOTO')
                        {
                            if((string)$vcard->$vCardKey['ENCODING'] == 'B')
                            {
                                $ldapInfo[$ldapKey] = (string)$vcard->$vCardKey;
                            }
                            else
                            {
                                $image = file_get_contents((string)$vcard->$vCardKey);

                                if ($image !== false)
                                    $ldapInfo[$ldapKey] = $image;          
                            } 
                        }
                        else
                        {
                            $ldapInfo[$ldapKey] = (string)$vcard->$vCardKey;
                        }         
                        
                    }
                } 
            }    
        }
       
        if(! array_key_exists('sn', $ldapInfo))
        {
            if( isset($vcard->FN))
            {
                $ldapInfo['sn'] = (string)$vcard->FN;
            }
        }

        foreach ($addressBookConfig['required_fields'] as $key) {
            if(! array_key_exists($key, $ldapInfo))
            return false;
        } 

        $ldapTree = $addressBookConfig['LDAP_rdn']. '='. $cardUri. ',' .$addressBookId;
        $filter = $addressBookConfig['filter']; 
                    
        $ldapResult = ldap_read($ldapConn, $ldapTree, $filter);
        $data = ldap_get_entries($ldapConn, $ldapResult);
                    
        foreach($data[0] as $key => $value) { 
            if(is_array($value))
            {
                if(! isset($ldapInfo[$key]))
                $ldapInfo[$key] = [];
            }
        }
        
        $ldapResponse = ldap_mod_replace($ldapConn, $ldapTree, $ldapInfo);
                    
        if ($ldapResponse) 
        {
            return null;
        }

        return false;
    }

    /**
     * Deletes a card
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @return bool
     */
    function deleteCard($addressBookId, $cardUri)
    {
        $ldapConn = $GLOBALS['globalLdapConn'];
        $addressBookConfig = $GLOBALS['addressBookConfig'][$addressBookId];

        $ldapTree = $addressBookConfig['LDAP_rdn']. '='. $cardUri. ',' .$addressBookId;
                    
        if(ldap_delete($ldapConn, $ldapTree))
        { 
            $this->addChange($addressBookId,$cardUri);
            return true;
        }

        return false;
    }


    /**
     * Generate Serialize Data of Vcard
     *
     * @param array $data
     * @param array $fieldMap
     * @return bool or vcard data
     */
    protected function generateVcard($data, $fieldMap, $cardUri)
    { 
        if (empty ($data)) {
            return false;
        }
        // build the Vcard
        $vcard = new \Sabre\VObject\Component\VCard(['UID' => $cardUri]);

        foreach ($fieldMap as $vcardKey => $ldapKey) {
            if(!is_array($ldapKey))
            {
                $ldapKey = strtolower($ldapKey);
                if(strpos($ldapKey, ':*'))
                {
                    $newLdapKey = str_replace(':*', '', $ldapKey);
                    if(isset($data[$newLdapKey]))
                    {
                        foreach($data[$newLdapKey] as $key => $value)
                        {
                            if($key === 'count')
                            continue;
                            $vcard->add($vcardKey, $value);
                        }
                    }   
                }
                else if(isset($data[$ldapKey]))
                {                   
                    if($ldapKey == 'jpegphoto') 
                    {
                        $vcard->add('PHOTO', base64_encode($data['jpegphoto'][0]), ['type' => 'JPEG', 'encoding' => 'b', 'value' => 'BINARY']);                     
                    }
                    else
                    {
                        if(in_array($vcardKey, $this->vCardKeyParts))
                        {
                            $keyParts = explode(';', $data[$ldapKey][0]);
                            $vcard->add($vcardKey, $keyParts);
                        }
                        else
                        {
                            $vcard->add($vcardKey, $data[$ldapKey][0]);
                        }                          
                    }                      
                } 
                
            }else{

                if(array_key_exists('attr', $ldapKey))
                {
                    $elementArr = [];
                    foreach ($ldapKey['attr'] as $element) {
                        $elementArr[] = $data[strtolower($element)][0];
                    }
                    $vcard->add($vcardKey, $elementArr);
                }

                if(array_key_exists('type', $ldapKey))
                {
                    foreach ($ldapKey['type'] as $vcardIndex => $values) {
                    foreach ($values as $vcardType => $value) {                   
                        if(isset($data[strtolower($value)]) && $vcardType!= 'default')
                            if(in_array($vcardKey, $this->vCardKeyMulti))
                            {
                                foreach ($data[strtolower($value)] as $key => $ldapElement) {
                                    if($key === 'count')
                                    continue;
                                    $vcard->add($vcardKey, $ldapElement, ['type' => [$vcardType, $vcardIndex], 'value' => 'uri']);
                                }
                            }
                            else{
                                $vcard->add($vcardKey, $data[strtolower($value)][0], ['type' => [$vcardType, $vcardIndex], 'value' => 'uri']);
                            }  
                        }
                    }
                }
            }   
        }
        // send the  VCard
        $output = $vcard->serialize();
        return $output;
    }

    /**
     * The getChanges method returns all the changes that have happened, since
     * the specified syncToken in the specified address book.
     *
     * This function should return an array, such as the following:
     *
     * [
     *   'syncToken' => 'The current synctoken',
     *   'added'   => [
     *      'new.txt',
     *   ],
     *   'modified'   => [
     *      'modified.txt',
     *   ],
     *   'deleted' => [
     *      'foo.php.bak',
     *      'old.txt'
     *   ]
     * ];
     *
     * The returned syncToken property should reflect the *current* syncToken
     * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
     * property. This is needed here too, to ensure the operation is atomic.
     *
     * If the $syncToken argument is specified as null, this is an initial
     * sync, and all members should be reported.
     *
     * The modified property is an array of nodenames that have changed since
     * the last token.
     *
     * The deleted property is an array with nodenames, that have been deleted
     * from collection.
     *
     * The $syncLevel argument is basically the 'depth' of the report. If it's
     * 1, you only have to report changes that happened only directly in
     * immediate descendants. If it's 2, it should also include changes from
     * the nodes below the child collections. (grandchildren)
     *
     * The $limit argument allows a client to specify how many results should
     * be returned at most. If the limit is not specified, it should be treated
     * as infinite.
     *
     * If the limit (infinite or not) is higher than you're willing to return,
     * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
     *
     * If the syncToken is expired (due to data cleanup) or unknown, you must
     * return null.
     *
     * The limit is 'suggestive'. You are free to ignore it.
     *
     * @param string $addressBookId
     * @param string $syncToken
     * @param int $syncLevel
     * @param int $limit
     * @return array
     */
    function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null)
    {         
        return null;
        
        $result = [
            'syncToken' => $this->syncToken,
            'added'     => [],
            'modified'  => [],
            'deleted'   => [],
        ];

        $ldapConn = $GLOBALS['globalLdapConn'];
        $addressBookConfig = $GLOBALS['addressBookConfig'][$addressBookId];
        
        //ADDED CARDS
        $filter = '(&' .$addressBookConfig['filter']. '(createtimestamp<=' .gmdate('YmdHis', $this->syncToken). 'Z)(!(|(createtimestamp<='.gmdate('YmdHis', $syncToken).'Z)(createtimestamp='.gmdate('YmdHis', $syncToken).'Z))))'; 

        $data = Utility::LdapQuery($ldapConn, $addressBookId, $filter, [], strtolower($addressBookConfig['scope']));
                    
        if($data['count'] > 0)
        {
            for ($i=0; $i < $data['count']; $i++) { 
                $cardUri = $data[$i]['uid'][0];                
                    $result['added'][] = $cardUri;
                }       
        }


        //MODIFIED CARDS
        $filter = '(&' .$addressBookConfig['filter']. '(createtimestamp<=' .gmdate('YmdHis', $this->syncToken). 'Z)(!(|(modifytimestamp<='.gmdate('YmdHis', $syncToken).'Z)(modifytimestamp='.gmdate('YmdHis', $syncToken).'Z))))';

        $data = Utility::LdapQuery($ldapConn, $addressBookId, $filter, [], strtolower($addressBookConfig['scope']));
                    
        if($data['count'] > 0)
        {
            for ($i=0; $i < $data['count']; $i++) { 
                $cardUri = $data[$i]['uid'][0];
                    $result['modified'][] = $cardUri;
                } 
        }


        //DELETED CARDS
        $query = 'SELECT uri, sync_token FROM '.$this->deletedCardsTableName.' WHERE addressbook_id = ? and ( sync_token <= ? ) and ( sync_token > ? ) ';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$addressBookId, $this->syncToken, $syncToken]);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $result['deleted'][] = $row['uri'];
        }
       
        return $result;
    }

    /**
     * Adds a change record to the addressbookchanges table.
     *
     * @param mixed  $addressBookId
     * @param string $objectUri
     */
    protected function addChange($addressBookId, $objectUri)
    {
        $query = "INSERT INTO `".$this->deletedCardsTableName."` (`sync_token` ,`addressbook_id` ,`uri`) " 
            ." VALUES (?, ?, ?)";

        $sql = $this->pdo->prepare($query);
        $sql->execute([time(), $addressBookId, $objectUri]);
    }
}

?>
