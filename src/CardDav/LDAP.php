<?php

namespace isubsoft\dav\CardDav;
// session_start();

class LDAP extends \Sabre\CardDAV\Backend\AbstractBackend {

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

    public $vCardKeyParts = ['ADR', 'N', 'ORG'];


    /**
     * Creates the backend.
     *
     * configuration array must be provided
     * to access initial directory.
     *
     * @param array $config
     * @return void
     */
    function __construct(array $config) {
        $this->config = $config;
        date_default_timezone_set("Asia/Calcutta");
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
        $ldapConn = $GLOBALS['globalLdapConn'];
        $searchUserId = str_replace($this->principalPrefix,'',$principalUri);

        $addressBooks = [];
        $searchDn = null;
                    
        $ldaptree = ($this->config['principal']['ldap']['search_base_dn'] !== '') ? $this->config['principal']['ldap']['search_base_dn'] : $this->config['principal']['ldap']['base_dn'];
        $filter = str_replace('%u', $searchUserId, $this->config['principal']['ldap']['search_filter']);  // single filter
        $attributes = ['dn'];

        if(strtolower($this->config['principal']['ldap']['scope']) == 'base')
        {
            $result = ldap_read($ldapConn, $ldaptree, $filter, $attributes);
        }
        else if(strtolower($this->config['principal']['ldap']['scope']) == 'list')
        {
            $result = ldap_list($ldapConn, $ldaptree, $filter, $attributes);
        }
        else
        {
            $result = ldap_search($ldapConn, $ldaptree, $filter, $attributes);
        }
                    
        $data = ldap_get_entries($ldapConn, $result);
                            
        if($data['count'] == 1)
        {
            $searchDn = $data[0]['dn'];
        }

        foreach ($this->config['card']['ldap'] as $addressBookName => $configParams) {

                $addressBookDn = str_replace('%dn', $searchDn, $configParams['base_dn']);

                $addressBooks[] = [
                    'id'                                                          => $addressBookDn,
                    'uri'                                                         => $addressBookName,
                    'principaluri'                                                => $principalUri,
                    '{DAV:}displayname'                                           => $configParams['name'],
                    '{' . CardDAVPlugin::NS_CARDDAV . '}addressbook-description'  => $configParams['description'],
                    '{http://calendarserver.org/ns/}getctag'                      => $configParams['synctoken'],
                    '{http://sabredav.org/ns}sync-token'                          => $configParams['synctoken'] ? $configParams['synctoken'] : '0',
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
        
        $filter = $addressBookConfig['filter']; 
        $attributes = ['cn','uid','entryuuid', 'modifytimestamp'];

        if(strtolower($addressBookConfig['scope']) == 'base')
        {
            $ldapResult = ldap_read($ldapConn, $addressBookDn, $filter, $attributes);
        }
        else if(strtolower($addressBookConfig['scope']) == 'list')
        {
            $ldapResult = ldap_list($ldapConn, $addressBookDn, $filter, $attributes);
        }
        else
        {
            $ldapResult = ldap_search($ldapConn, $addressBookDn, $filter, $attributes);
        }

        $data = ldap_get_entries($ldapConn, $ldapResult);
                    
        if($data['count'] > 0)
        {
            for ($i=0; $i < $data['count']; $i++) { 
                            
                $row = [    'id' => $data[$i]['entryuuid'][0],
                            'uri' => $data[$i]['uid'][0],
                            'lastmodified' => $this->showDateString($data[$i]['modifytimestamp'][0]),
                            'etag' => null,
                            'size' => strlen($data[$i]['cn'][0])
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
        $attributes = ['entryuuid', 'modifytimestamp'];
        
        $ldapResult = ldap_read($ldapConn, $ldapTree, $filter);

        if($ldapResult)
        {
            $ldapAdditionalResult = ldap_read($ldapConn, $ldapTree, $filter, $attributes);
                        
            $data = ldap_get_entries($ldapConn, $ldapResult);
            $additionalData = ldap_get_entries($ldapConn, $ldapAdditionalResult);
                    
            if($data['count'] > 0)
            {
                $cardInfo = [];
                $cardInfoArray = [];

                foreach($addressBookConfig['fieldmap'] as $vCardKey => $ldapKey)
                {
                    $ldapKey = strtolower($ldapKey);
                    if(strpos($ldapKey, ':*'))
                    {
                        $newLdapKey = str_replace(':*', '', $ldapKey);
                        if(isset($data[0][$newLdapKey]))
                        {
                            foreach($data[0][$newLdapKey] as $key => $values)
                            {
                                if($key === 'count')
                                continue;

                                $cardInfoArray[$vCardKey][] = $values;
                            }
                        }   
                    }
                    else if(isset($data[0][$ldapKey]))
                    {      
                        
                        if($ldapKey == 'jpegphoto') 
                        {
                            continue;                         
                        }
                        else
                        {
                            if(in_array($vCardKey, $this->vCardKeyParts))
                            {
                                $keyParts = explode(';', $data[0][$ldapKey][0]);

                                foreach($keyParts as $element)
                                {
                                    $cardInfo[$vCardKey][] = $element;
                                }
                            }
                            else
                            {
                                $cardInfo[$vCardKey] = $data[0][$ldapKey][0];
                            }                          
                        }    
                        
                    } 
                } 
                
            $vcard = new \Sabre\VObject\Component\VCard($cardInfo);

            if(! empty($cardInfoArray))
            {
                foreach($cardInfoArray as $vCardKey => $vCardValues)
                {
                    foreach ($vCardValues as $vCardValue) {
                        
                        $vcard->add($vCardKey, $vCardValue);
                    }
                }
            }
            
            if(isset($data[0]['jpegphoto']))
            {
                $b64vcard = base64_encode($data[0]['jpegphoto'][0]);
                $vcard->add('PHOTO', $b64vcard, array('type' => 'JPEG', 'encoding' => 'b', 'value' => 'BINARY'));
            }
            
            $result = [
                'id' => $additionalData[0]['entryuuid'][0],
                'carddata'  => $vcard->serialize(),
                'uri' => $cardUri,
                'lastmodified' => $this->showDateString($additionalData[0]['modifytimestamp'][0]),
                'etag' => null,
                'size' => strlen($data[0]['cn'][0])
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
                    else if($vCardKey == 'N')
                    {
                        $ldapInfo[$ldapKey] = (string)$vcard->$vCardKey;

                        $ldapInfo['sn'] = $vcard->$vCardKey->getParts()[0];
                    }
                    else
                    {
                        $ldapInfo[$ldapKey] = (string)$vcard->$vCardKey;
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
                    
        if ($ldapResponse) {

            $filter = $addressBookConfig['filter']; 
            $attributes = ['modifytimestamp'];

            $ldapResult = ldap_read($ldapConn, $ldapTree, $filter, $attributes);
                        
            $data = ldap_get_entries($ldapConn, $ldapResult);

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
                    else if($vCardKey == 'N')
                    {
                        $ldapInfo[$ldapKey] = (string)$vcard->$vCardKey;

                        $ldapInfo['sn'] = $vcard->$vCardKey->getParts()[0];
                    }
                    else
                    {
                        $ldapInfo[$ldapKey] = (string)$vcard->$vCardKey;
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
                    
        if ($ldapResponse) {

            $attributes = ['modifytimestamp'];
            $ldapResult = ldap_read($ldapConn, $ldapTree, $filter, $attributes);
            $data = ldap_get_entries($ldapConn, $ldapResult);

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
            return true;
        }

        return false;
    }

    /**
     * return date in specific format, given a timestamp.
     *
     * @param  timestamp  $datetime
     * @return string
     */
    function showDateString($timestamp)
    {
      if ($timestamp !== NULL) {

        $timestamp = strtotime($timestamp);  
        return new \DateTime( "@" . $timestamp );
      }
      return '';
    }

}

?>