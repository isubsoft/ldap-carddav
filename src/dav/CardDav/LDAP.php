<?php

namespace isubsoft\dav\CardDav;

use isubsoft\dav\Utility\LDAP as Utility;
use isubsoft\Vobject\Reader as Reader;
use \Sabre\DAV\Exception\ServiceUnavailable;

class LDAP extends \Sabre\CardDAV\Backend\AbstractBackend implements \Sabre\CardDAV\Backend\SyncSupport {

    /**
     * Store ldap directory access credentials
     *
     * @var array
     */
    public $config;

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
    private $deletedCardsTableName = 'cards_deleted';

    private $ldapMapTableName = 'cards_backend_map';

    private $fullSyncTable = 'cards_full_sync';

    /**
     * sync Token.
     *
     * @var string
     */
    private $syncToken = null;

    /**
     * Auth Backend Object class.
     *
     * @var string
     */
    public $authBackend = null;

    /**
     * Principal user
     *
     * @var string
     */    
    public $principalUser = null;

    private $addressbook = null;

    /**
     * Ldap Connection.
     *
     * @var string
     */
    public $userLdapConn = null;

    /**
     * Creates the backend.
     *
     * configuration array must be provided
     * to access initial directory.
     *
     * @param array $config
     * @return void
     */
    function __construct(array $config, \PDO $pdo, $authBackend) {
        $this->config = $config;
        $this->pdo = $pdo;
        $this->authBackend = $authBackend;
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

        $this->principalUser = basename($principalUri);
       
        foreach ($this->config['card']['addressbook']['ldap'] as $addressbookId => $configParams) {
 
                $addressBookDn = Utility::replacePlaceholders($configParams['base_dn'], ['%u' => $this->principalUser ]);
                   
                $addressBooks[] = [
                    'id'                                                          => $addressbookId,
                    'uri'                                                         => $addressbookId,
                    'principaluri'                                                => $principalUri,
                    '{DAV:}displayname'                                           => isset($configParams['name']) ? $configParams['name'] : '',
                    '{' . CardDAVPlugin::NS_CARDDAV . '}addressbook-description'  => isset($configParams['description']) ? $configParams['description'] : '',
                    '{http://sabredav.org/ns}sync-token'                          => isset($this->syncToken) ? $this->syncToken : 0,
                ];
                
                if($configParams['bind_dn'] == '')
                {
                    $this->addressbook[$addressbookId]['LdapConnection'] = $this->authBackend->userLdapConn;
                }
                else{
                    if(isset($configParams['bind_pass']) && $configParams['bind_pass'] != '')
                    {
                        $this->addressbook[$addressbookId]['LdapConnection'] = Utility::LdapBindConnection(['bindDn' => $configParams['bind_dn'], 'bindPass' => $configParams['bind_pass']], $configParams);
                    }
                    else{
                        $this->addressbook[$addressbookId]['LdapConnection'] = Utility::LdapBindConnection(['bindDn' => $configParams['bind_dn'], 'bindPass' => null], $configParams);
                    }
                }

                $this->addressbook[$addressbookId]['config'] = $configParams;
                $this->addressbook[$addressbookId]['addressbookDn'] = $addressBookDn;
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
     * @param mixed $addressBookId
     * @return array
     */
    function getCards($addressBookId)
    {
        $result = [];     

        $data = $this->fullSyncOperation($addressBookId);   
        
        if( !empty($data))
        {
            for ($i=0; $i < count($data); $i++) { 
                
                $row = [    'id' => $data[$i]['backend_id'],
                            'uri' => $data[$i]['card_uri'],
                            'lastmodified' => $data[$i]['modified_timestamp'],
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
        $result = [];
        
        $data = $this->fetchContactData($addressBookId, $cardUri);

        if( !empty($data) && $data['count'] > 0)
        {           
            $cardData = $this->generateVcard($data[0], $addressBookId, $cardUri);
             
            $result = [
                'id'            => $data[0]['entryuuid'][0],
                'carddata'      => $cardData,
                'uri'           => $cardUri,
                'lastmodified'  => strtotime($data[0]['modifytimestamp'][0]),
            ];
                        
            return $result;         
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
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];

        if(!$addressBookConfig['writable'])
        {
            return false;
        }

        $vcard = Reader::read($cardData);
        $UID = $vcard->UID;
         
        $ldapInfo = [];


        if( isset($vcard->KIND) && (strtolower((string)$vcard->KIND) === 'group'))
        {
            $ldapInfo['objectclass'] = $addressBookConfig['group_LDAP_Object_Classes'];
            $fieldMap = $addressBookConfig['group_fieldmap'];
            $requiredFields = $addressBookConfig['group_required_fields'];
            $rdn = $addressBookConfig['group_LDAP_rdn'];

            foreach($addressBookConfig['group_member_map'] as $vCardKey => $ldapKey) 
            {
                $multiAllowedStatus = Reader::multiAllowedStatus($vCardKey);
                $compositeAttrStatus = Reader::compositeAttrStatus($vCardKey);

                if(isset($vcard->$vCardKey) && $multiAllowedStatus['status'] && !$compositeAttrStatus['status'] )
                {
                    $newLdapKey = strtolower($ldapKey['backend_attribute']);
                    foreach($vcard->$vCardKey as $values)
                    {
                        $memberUriArr = explode(':', (string)$values);
                        if(strtolower($memberUriArr[0]) == 'urn' && strtolower($memberUriArr[1]) == 'uuid')
                        {
                            $memberCardUID = $memberUriArr[2];
                            $backendId = null;

                            try {
                                $query = 'SELECT backend_id FROM '.$this->ldapMapTableName.' WHERE addressbook_id = ? and card_uid = ? and user_id = ?';
                                $stmt = $this->pdo->prepare($query);
                                $stmt->execute([$addressBookId, $memberCardUID, $this->principalUser]);
                                
                                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                    $backendId = $row['backend_id'];
                                }
                            } catch (\Throwable $th) {
                                error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
                            }
                            
                            if(isset($backendId) && $backendId != null)
                            {
                                $filter = '(&'.$addressBookConfig['filter']. '(entryuuid=' .$backendId. '))'; 
                        
                                $data = Utility::LdapQuery($ldapConn, $addressBookDn, $filter, [], strtolower($addressBookConfig['scope']));
                                if( !empty($data) && $data['count'] > 0)
                                {
                                    $ldapInfo[$newLdapKey][] = $data[0]['dn'];
                                }
                            }   
                        }             
                    }
                }
            }

        }
        else
        {
            $ldapInfo['objectclass'] = $addressBookConfig['LDAP_Object_Classes'];
            $fieldMap = $addressBookConfig['fieldmap'];
            $requiredFields = $addressBookConfig['required_fields'];
            $rdn = $addressBookConfig['LDAP_rdn'];
        }
        
        //Fetch from VCard associative array with respect to vcard to ldap field map 
        foreach($fieldMap as $vCardKey => $ldapKey)
        {
            if( isset($vcard->$vCardKey))
            {
                $multiAllowedStatus = Reader::multiAllowedStatus($vCardKey);
                $compositeAttrStatus = Reader::compositeAttrStatus($vCardKey);
                $parameterStatus = Reader::parameterStatus($vCardKey);

                if($multiAllowedStatus['status'] && !$compositeAttrStatus['status'] && !$parameterStatus['parameter'])
                {
                    $newLdapKey = strtolower($ldapKey['backend_attribute']);
                    foreach($vcard->$vCardKey as $values)
                    {
                        $ldapInfo[$newLdapKey][] = (string)$values;             
                    }
                }
                else if($compositeAttrStatus['status']  && !$parameterStatus['parameter'])  
                {
                    if($multiAllowedStatus)
                    {
                        foreach($vcard->$vCardKey as $values)
                        {
                            $vCardPropValueArr = $values->getParts();

                            foreach($ldapKey['backend_attribute'] as $propKey => $backendAttr)
                            {
                                $propIndex = array_search($propKey, $compositeAttrStatus['status']);

                                if($propIndex !== false)
                                {
                                    if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                                    {
                                        $ldapInfo[strtolower($backendAttr)][] = $vCardPropValueArr[$propIndex];
                                    }
                                }
                            }
                        }
                    }
                    else
                    {
                        $vCardPropValueArr = $vcard->$vCardKey->getParts();

                        foreach($ldapKey['backend_attribute'] as $propKey => $backendAttr)
                        {
                            $propIndex = array_search($propKey, $compositeAttrStatus['status']);
                            
                            if($propIndex !== false)
                            {
                                if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                                {
                                    $ldapInfo[strtolower($backendAttr)] = $vCardPropValueArr[$propIndex];
                                }
                            }
                        }
                    }          
                }
                else if(! empty($parameterStatus['parameter']))
                {
                    if($multiAllowedStatus['status'])
                    {
                        foreach($vcard->$vCardKey as $values) 
                        {
                            $inputParamsInfo = Utility::getVCardAttrParams($values, $parameterStatus['parameter']);                       
                            $vCardParamListsMatch = Utility::isVcardParamsMatch($ldapKey, $inputParamsInfo);
                            $backendAttrValue = '';
                            $decodeFile = false;

                            if($vCardParamListsMatch['status'] === true)
                            {
                                $backendAttrValue = $vCardParamListsMatch['ldapArrMap']['backend_attribute'];
                                if(isset($vCardParamListsMatch['ldapArrMap']['decode_file']))
                                {
                                    $decodeFile = $vCardParamListsMatch['ldapArrMap']['decode_file'];
                                }
                            }
                            else
                            {
                                foreach($ldapKey as $ldapKeyInfo)
                                {
                                    if(in_array(null, $ldapKeyInfo['parameters']))
                                    {                           
                                        $backendAttrValue = $ldapKeyInfo['backend_attribute'];
                                        if(isset($ldapKeyInfo['decode_file']))
                                        {
                                            $decodeFile = $ldapKeyInfo['decode_file'];
                                        }
                                        break;
                                    }
                                }
                            }

                            if($compositeAttrStatus['status'] && $backendAttrValue !== '')
                            {
                                $vCardPropValueArr = $values->getParts();

                                foreach($backendAttrValue as $propKey => $backendAttr)
                                {
                                    $propIndex = array_search($propKey, $compositeAttrStatus['status']);
    
                                    if($propIndex !== false)
                                    {
                                        if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                                        {
                                            $ldapInfo[strtolower($backendAttr)][] = $vCardPropValueArr[$propIndex];
                                        }
                                    }
                                }
                            }
                            else if($backendAttrValue !== '')
                            {
                                $attrType = Reader::attributeType($inputParamsInfo);
                                $newLdapKey = strtolower($backendAttrValue);

                                if($attrType == 'FILE' && $decodeFile == true)
                                {
                                    $file = file_get_contents((string)$values);
                                    if ($file !== false)
                                    $ldapInfo[$newLdapKey] = $file;
                                }
                                else
                                {
                                    $ldapInfo[$newLdapKey][] = (string)$values;
                                }
                            }
                               
                        }
                    }
                    else
                    {
                        $inputParamsInfo = Utility::getVCardAttrParams($vcard->$vCardKey, $parameterStatus['parameter']);                        
                        $vCardParamListsMatch = Utility::isVcardParamsMatch($ldapKey, $inputParamsInfo);
                        $backendAttrValue = '';
                        $decodeFile = false;

                        if($vCardParamListsMatch['status'] === true)
                        {
                            $backendAttrValue = $vCardParamListsMatch['ldapArrMap']['backend_attribute'];
                            if(isset($vCardParamListsMatch['ldapArrMap']['decode_file']))
                            {
                                $decodeFile = $vCardParamListsMatch['ldapArrMap']['decode_file'];
                            }
                        }
                        else
                        {
                            foreach($ldapKey as $ldapKeyInfo)
                            {
                                if(in_array(null, $ldapKeyInfo['parameters']))
                                {
                                    $backendAttrValue = $ldapKeyInfo['backend_attribute'];
                                    if(isset($ldapKeyInfo['decode_file']))
                                    {
                                        $decodeFile = $ldapKeyInfo['decode_file'];
                                    }
                                    break;
                                }
                            }
                        }

                        
                        if($compositeAttrStatus['status'] && $backendAttrValue !== '')
                        {
                            $vCardPropValueArr = $vcard->$vCardKey->getParts();

                            foreach($backendAttrValue as $propKey => $backendAttr)
                            {
                                $propIndex = array_search($propKey, $compositeAttrStatus['status']);

                                if($propIndex !== false)
                                {
                                    if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                                    {
                                        $ldapInfo[strtolower($backendAttr)] = $vCardPropValueArr[$propIndex];
                                    }
                                }
                            }
                        }
                        else if($backendAttrValue !== '')
                        {
                            $attrType = Reader::attributeType($inputParamsInfo);
                            $newLdapKey = strtolower($backendAttrValue);

                            if($attrType == 'FILE' && $decodeFile == true)
                            {
                                $file = file_get_contents((string)$vcard->$vCardKey);
                                if ($file !== false)
                                $ldapInfo[$newLdapKey] = $file;
                            }
                            else
                            {
                                $ldapInfo[$newLdapKey][] = (string)$vcard->$vCardKey;
                            }
                        }
                    }
                }
                else
                {
                    $newLdapKey = strtolower($ldapKey['backend_attribute']);
                    $ldapInfo[$newLdapKey] = (string)$vcard->$vCardKey;            
                }
            }    
        }

         
        foreach ($requiredFields as $key) {
            if(! array_key_exists($key, $ldapInfo))
            return false;
        }

        if(! array_key_exists($rdn, $ldapInfo))
        {
            return false;
        }
        
        $ldapTree = $rdn. '='. $ldapInfo[$rdn]. ',' .$addressBookDn;

        try {
            $ldapResponse = ldap_add($ldapConn, $ldapTree, $ldapInfo);
            if(!$ldapResponse)
            {
                return false;
            }
        } catch (\Throwable $th) {
            error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
            throw new ServiceUnavailable($th->getMessage());
        }

        $data = Utility::LdapQuery($ldapConn, $ldapTree, $addressBookConfig['filter'], ['entryuuid'], 'base');
        
        try {
            $query = "INSERT INTO `".$this->ldapMapTableName."` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
            $sql = $this->pdo->prepare($query);
            $sql->execute([$cardUri, $UID, $addressBookId, $data[0]['entryuuid'][0], $this->principalUser]);
        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }       
                
        return null;
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
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];

        if(!$addressBookConfig['writable'])
        {
            return false;
        }

       
        $vcard = Reader::read($cardData);

        $ldapInfo = [];
        
        if( isset($vcard->KIND) && (strtolower((string)$vcard->KIND) === 'group'))
        {
            $ldapInfo['objectclass'] = $addressBookConfig['group_LDAP_Object_Classes'];
            $fieldMap = $addressBookConfig['group_fieldmap'];
            $requiredFields = $addressBookConfig['group_required_fields'];
            $rdn = $addressBookConfig['group_LDAP_rdn'];

            foreach($addressBookConfig['group_member_map'] as $vCardKey => $ldapKey) 
            {
                $multiAllowedStatus = Reader::multiAllowedStatus($vCardKey);
                $compositeAttrStatus = Reader::compositeAttrStatus($vCardKey);

                if(isset($vcard->$vCardKey) && $multiAllowedStatus['status'] && !$compositeAttrStatus['status'] )
                {
                    $newLdapKey = strtolower($ldapKey['backend_attribute']);
                    foreach($vcard->$vCardKey as $values)
                    {
                        $memberUriArr = explode(':', (string)$values);
                        if(strtolower($memberUriArr[0]) == 'urn' && strtolower($memberUriArr[1]) == 'uuid')
                        {
                            $memberCardUID = $memberUriArr[2];
                            $backendId = null;

                            try {
                                $query = 'SELECT backend_id FROM '.$this->ldapMapTableName.' WHERE addressbook_id = ? and card_uid = ? and user_id = ?';
                                $stmt = $this->pdo->prepare($query);
                                $stmt->execute([$addressBookId, $memberCardUID, $this->principalUser]);
                                
                                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                    $backendId = $row['backend_id'];
                                }
                            } catch (\Throwable $th) {
                                error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
                            }
                            
                            if(isset($backendId) && $backendId != null)
                            {
                                $filter = '(&'.$addressBookConfig['filter']. '(entryuuid=' .$backendId. '))'; 
                        
                                $data = Utility::LdapQuery($ldapConn, $addressBookDn, $filter, [], strtolower($addressBookConfig['scope']));
                                if( !empty($data) && $data['count'] > 0)
                                {
                                    $ldapInfo[$newLdapKey][] = $data[0]['dn'];
                                }
                            }
                        }             
                    }
                }
            }

        }
        else
        {
            $ldapInfo['objectclass'] = $addressBookConfig['LDAP_Object_Classes'];
            $fieldMap = $addressBookConfig['fieldmap'];
            $requiredFields = $addressBookConfig['required_fields'];
            $rdn = $addressBookConfig['LDAP_rdn'];
        }

        //Fetch from VCard associative array with respect to vcard to ldap field map 
        
        foreach($fieldMap as $vCardKey => $ldapKey)
        {
            if( isset($vcard->$vCardKey))
            {
                $multiAllowedStatus = Reader::multiAllowedStatus($vCardKey);
                $compositeAttrStatus = Reader::compositeAttrStatus($vCardKey);
                $parameterStatus = Reader::parameterStatus($vCardKey);
                
                if($multiAllowedStatus['status'] && !$compositeAttrStatus['status'] && !$parameterStatus['parameter'])
                {
                    $newLdapKey = strtolower($ldapKey['backend_attribute']);
                    foreach($vcard->$vCardKey as $values)
                    {
                        $ldapInfo[$newLdapKey][] = (string)$values;
                    }
                }
                else if($compositeAttrStatus['status']  && !$parameterStatus['parameter'])  
                {
                    if($multiAllowedStatus)
                    {
                        foreach($vcard->$vCardKey as $values)
                        {
                            $vCardPropValueArr = $values->getParts();

                            foreach($ldapKey['backend_attribute'] as $propKey => $backendAttr)
                            {
                                $propIndex = array_search($propKey, $compositeAttrStatus['status']);

                                if($propIndex !== false)
                                {
                                    if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                                    {
                                        $ldapInfo[strtolower($backendAttr)][] = $vCardPropValueArr[$propIndex];
                                    }
                                }
                            }
                        }
                    }
                    else
                    {
                        $vCardPropValueArr = $vcard->$vCardKey->getParts();

                        foreach($ldapKey['backend_attribute'] as $propKey => $backendAttr)
                        {
                            $propIndex = array_search($propKey, $compositeAttrStatus['status']);
                            
                            if($propIndex !== false)
                            {
                                if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                                {
                                    $ldapInfo[strtolower($backendAttr)] = $vCardPropValueArr[$propIndex];
                                }
                            }
                        }
                    }          
                }
                else if(! empty($parameterStatus['parameter']))
                {
                    if($multiAllowedStatus['status'])
                    {
                        foreach($vcard->$vCardKey as $values) 
                        {
                            $inputParamsInfo = Utility::getVCardAttrParams($values, $parameterStatus['parameter']);                       
                            $vCardParamListsMatch = Utility::isVcardParamsMatch($ldapKey, $inputParamsInfo);
                            
                            $backendAttrValue = '';
                            $decodeFile = false;

                            if($vCardParamListsMatch['status'] === true)
                            {
                                $backendAttrValue = $vCardParamListsMatch['ldapArrMap']['backend_attribute'];
                                if(isset($vCardParamListsMatch['ldapArrMap']['decode_file']))
                                {
                                    $decodeFile = $vCardParamListsMatch['ldapArrMap']['decode_file'];
                                }
                            }
                            else
                            {
                                foreach($ldapKey as $ldapKeyInfo)
                                {
                                    if(in_array(null, $ldapKeyInfo['parameters']))
                                    {                           
                                        $backendAttrValue = $ldapKeyInfo['backend_attribute'];
                                        if(isset($ldapKeyInfo['decode_file']))
                                        {
                                            $decodeFile = $ldapKeyInfo['decode_file'];
                                        }
                                        break;
                                    }
                                }
                            }

                            if($compositeAttrStatus['status'] && $backendAttrValue !== '')
                            {
                                $vCardPropValueArr = $values->getParts();

                                foreach($backendAttrValue as $propKey => $backendAttr)
                                {
                                    $propIndex = array_search($propKey, $compositeAttrStatus['status']);
    
                                    if($propIndex !== false)
                                    {
                                        if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                                        {
                                            $ldapInfo[strtolower($backendAttr)][] = $vCardPropValueArr[$propIndex];
                                        }
                                    }
                                }
                            }
                            else if($backendAttrValue !== '')
                            {
                                $attrType = Reader::attributeType($inputParamsInfo);
                                $newLdapKey = strtolower($backendAttrValue);

                                if($attrType == 'FILE' && $decodeFile == true)
                                {
                                    $file = file_get_contents((string)$values);
                                    if ($file !== false)
                                    $ldapInfo[$newLdapKey] = $file;
                                }
                                else
                                {
                                    $ldapInfo[$newLdapKey][] = (string)$values;
                                }
                            }
                               
                        }
                    }
                    else
                    {
                        $inputParamsInfo = Utility::getVCardAttrParams($vcard->$vCardKey, $parameterStatus['parameter']);                        
                        $vCardParamListsMatch = Utility::isVcardParamsMatch($ldapKey, $inputParamsInfo);
                        $backendAttrValue = '';
                        $decodeFile = false;

                        if($vCardParamListsMatch['status'] === true)
                        {
                            $backendAttrValue = $vCardParamListsMatch['ldapArrMap']['backend_attribute'];
                            if(isset($vCardParamListsMatch['ldapArrMap']['decode_file']))
                            {
                                $decodeFile = $vCardParamListsMatch['ldapArrMap']['decode_file'];
                            }
                        }
                        else
                        {
                            foreach($ldapKey as $ldapKeyInfo)
                            {
                                if(in_array(null, $ldapKeyInfo['parameters']))
                                {
                                    $backendAttrValue = $ldapKeyInfo['backend_attribute'];
                                    if(isset($ldapKeyInfo['decode_file']))
                                    {
                                        $decodeFile = $ldapKeyInfo['decode_file'];
                                    }
                                    break;
                                }
                            }
                        }

                        
                        if($compositeAttrStatus['status'] && $backendAttrValue !== '')
                        {
                            $vCardPropValueArr = $vcard->$vCardKey->getParts();

                            foreach($backendAttrValue as $propKey => $backendAttr)
                            {
                                $propIndex = array_search($propKey, $compositeAttrStatus['status']);

                                if($propIndex !== false)
                                {
                                    if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                                    {
                                        $ldapInfo[strtolower($backendAttr)] = $vCardPropValueArr[$propIndex];
                                    }
                                }
                            }
                        }
                        else if($backendAttrValue !== '')
                        {
                            $attrType = Reader::attributeType($inputParamsInfo);
                            $newLdapKey = strtolower($backendAttrValue);

                            if($attrType == 'FILE' && $decodeFile == true)
                            {
                                $file = file_get_contents((string)$vcard->$vCardKey);
                                if ($file !== false)
                                $ldapInfo[$newLdapKey] = $file;
                            }
                            else
                            {
                                $ldapInfo[$newLdapKey][] = (string)$vcard->$vCardKey;
                            }
                        }
                    }
                }
                else
                {
                    $newLdapKey = strtolower($ldapKey['backend_attribute']); 
                    $ldapInfo[$newLdapKey] = (string)$vcard->$vCardKey;    
                }
            }    
        }
    

        foreach ($requiredFields as $key) {
            if(! array_key_exists($key, $ldapInfo))
            return false;
        }

        if(! array_key_exists($rdn, $ldapInfo))
        {
            return false;
        }

        $data = $this->fetchContactData($addressBookId, $cardUri);
                    
        $ldapRdnValues = null;
        foreach($data[0] as $key => $value) { 
            if(is_array($value) && $key!= 'entryuuid' && $key!= 'modifytimestamp')
            {
                if(! isset($ldapInfo[$key]))
                $ldapInfo[$key] = [];

                if($key == $rdn)
                {
                    for ($i=0; $i < $value['count']; $i++) { 
                        $ldapRdnValues[] = $value[$i];
                    }
                    $newLdapRdnValue = $ldapInfo[$key];
                    $ldapInfo[$key] = $ldapRdnValues;
                }
            }
        }
        
        $ldapTree = $data[0]['dn'];

        try {
            $ldapResponse = ldap_mod_replace($ldapConn, $ldapTree, $ldapInfo);
            if(!$ldapResponse)
            {
                return false;
            }

            $parsr = ldap_explode_dn($ldapTree, 0);
            if(!$parsr)
            {
                return false;
            }
        } catch (\Throwable $th) {
            error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
            throw new ServiceUnavailable($th->getMessage());
        }
        

        $newLdapRdn = $rdn. '='. $newLdapRdnValue;    
        if($parsr[0] == $newLdapRdn)
        {
            return null;
        }
        else
        {
            try {
                if(in_array($newLdapRdnValue, $ldapRdnValues))
                {
                    $ldapRename = ldap_rename($ldapConn, $ldapTree, $newLdapRdn, $addressBookDn, false);
                }
                else{
                    $ldapRename = ldap_rename($ldapConn, $ldapTree, $newLdapRdn, $addressBookDn, true);
                }

                if(!$ldapRename)
                {
                    return false;
                }           
            } catch (\Throwable $th) {
                error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
                throw new ServiceUnavailable($th->getMessage());
            }
            
            return null;
        } 

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
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        
        if($addressBookConfig['writable'] == false)
        {
            return false;
        }
        
        $data = $this->fetchContactData($addressBookId, $cardUri);
        $ldapTree = $data[0]['dn'];

        try {
            $ldapDelete = ldap_delete($ldapConn, $ldapTree);
            if(!$ldapDelete)
            {
                return false;
            }
        } catch (\Throwable $th) {
            error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
            throw new ServiceUnavailable($th->getMessage());
        }

        $this->addChange($addressBookId, $cardUri, $data[0]['entryuuid'][0]);
        return true;
    }


    /**
     * Generate Serialize Data of Vcard
     *
     * @param array $data
     * @param array $addressBookId
     * @return bool or vcard data
     */
    protected function generateVcard($data, $addressBookId, $cardUri)
    { 
        if (empty ($data)) {
            return false;
        }

        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        $fieldMap = $addressBookConfig['fieldmap'];
        $UID = null;

        try {
            $query = 'SELECT card_uid FROM '.$this->ldapMapTableName.' WHERE addressbook_id = ? and card_uri = ? and user_id = ?';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$addressBookId, $cardUri, $this->principalUser]);
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $UID = $row['card_uid'];
            }
        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }
        
        
        // build the Vcard
        $vcard = new \Sabre\VObject\Component\VCard(['UID' => $UID]);

        if($data['objectclass'][0] === $addressBookConfig['group_LDAP_Object_Classes'][0])
        {
            $vcard->add('KIND', 'group');
            $fieldMap = $addressBookConfig['group_fieldmap'];      

            foreach ($addressBookConfig['group_member_map'] as $vCardKey => $ldapKey) 
            {
                $multiAllowedStatus = Reader::multiAllowedStatus($vCardKey);
                $compositeAttrStatus = Reader::compositeAttrStatus($vCardKey);

                if($multiAllowedStatus['status'] && !$compositeAttrStatus['status'] )
                {
                    $newLdapKey = strtolower($ldapKey['backend_attribute']);
                    if(isset($data[$newLdapKey]))
                    {
                        foreach($data[$newLdapKey] as $key => $value)
                        {
                            if($key === 'count')
                            continue;

                            $memberData = Utility::LdapQuery($ldapConn, $value, $addressBookConfig['filter'], ['entryuuid'], 'base');
                     
                            if(! empty($memberData) && $memberData['count'] > 0)
                            { 
                                $clientUID = null;

                                try {
                                    $query = 'SELECT card_uid FROM '.$this->ldapMapTableName.' WHERE addressbook_id = ? and backend_id = ? and user_id = ?';
                                    $stmt = $this->pdo->prepare($query);
                                    $stmt->execute([$addressBookId, $memberData[0]['entryuuid'][0], $this->principalUser]);
                                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                        $clientUID = $row['card_uid'];
                                    }
                                } catch (\Throwable $th) {
                                    error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
                                }
                                
                                $vcard->add($vCardKey, 'urn:uuid:'.$clientUID);
                            }                  
                        }
                    }
                }
            }
        }
      

        foreach ($fieldMap as $vCardKey => $ldapKey) {
            
            $multiAllowedStatus = Reader::multiAllowedStatus($vCardKey);
            $compositeAttrStatus = Reader::compositeAttrStatus($vCardKey);
            $parameterStatus = Reader::parameterStatus($vCardKey);

            if($multiAllowedStatus['status'] && !$compositeAttrStatus['status'] && !$parameterStatus['parameter'])
            {
                $newLdapKey = strtolower($ldapKey['backend_attribute']);
                if(isset($data[$newLdapKey]))
                {
                    foreach($data[$newLdapKey] as $key => $value)
                    {
                        if($key === 'count')
                        continue;

                        $vCardParams = Utility::reverseMapVCardParams($ldapKey['parameters'], $ldapKey['reverse_map_parameter_index']);
                        if(!empty($vCardParams))
                        {   
                            $vcard->add($vCardKey, $value, $vCardParams);
                        }
                        else
                        {
                            $vcard->add($vCardKey, $value); 
                        }
                    }
                }
            }
            else if($compositeAttrStatus['status'] && !$parameterStatus['parameter'])  
            {
                $isLdapKeyExists = false;
                $elementArr = [];
                $count = 0;

                foreach($ldapKey['backend_attribute'] as $backendAttr)
                {
                    if(isset($data[strtolower($backendAttr)]))
                    {
                        if($data[strtolower($backendAttr)]['count'] > $count)
                        $count = $data[strtolower($backendAttr)]['count'];

                        $isLdapKeyExists = true;
                    }
                }

                if($isLdapKeyExists == true)
                {
                    if($multiAllowedStatus['status'] && $count > 1)
                    {
                        for($i = 0; $i < $count; $i++)
                        {
                            foreach($compositeAttrStatus['status'] as $propValue)
                            {
                                if(isset($ldapKey['backend_attribute'][$propValue]))
                                {
                                    $newLdapKey = strtolower($ldapKey['backend_attribute'][$propValue]);
                                    if(isset($data[$newLdapKey]) && isset($data[$newLdapKey][$i]))
                                    {
                                        $elementArr[] = $data[$newLdapKey][$i];
                                    }
                                }
                                else
                                {
                                    $elementArr[] = '';
                                }
                            }

                            $vCardParams = Utility::reverseMapVCardParams($ldapKey['parameters'], $ldapKey['reverse_map_parameter_index']);
                            if(!empty($vCardParams))
                            {
                                $vcard->add($vCardKey, $elementArr, $vCardParams);
                            }
                            else
                            {
                                $vcard->add($vCardKey, $elementArr); 
                            }
                        }
                    }
                    else
                    {
                        foreach($compositeAttrStatus['status'] as $propValue)
                        {
                            if(isset($ldapKey['backend_attribute'][$propValue]))
                            {
                                $newLdapKey = strtolower($ldapKey['backend_attribute'][$propValue]);
                                if(isset($data[$newLdapKey]))
                                {
                                    $elementArr[] = $data[$newLdapKey][0];
                                }
                            }
                            else
                            {
                                $elementArr[] = '';
                            }
                        }

                        $vCardParams = Utility::reverseMapVCardParams($ldapKey['parameters'], $ldapKey['reverse_map_parameter_index']);
                        if(!empty($vCardParams))
                        {
                            $vcard->add($vCardKey, $elementArr, $vCardParams);
                        }
                        else
                        {
                            $vcard->add($vCardKey, $elementArr); 
                        }
                    }                                               
                }               
            }
            else if(! empty($parameterStatus['parameter']))
            {
                foreach($ldapKey as $ldapKeyInfo)
                {
                    if($compositeAttrStatus['status'])
                    {
                        $isLdapKeyExists = false;
                        
                        $count = 0;

                        foreach($ldapKeyInfo['backend_attribute'] as $backendAttr)
                        {
                            if(isset($data[strtolower($backendAttr)]))
                            {
                                if($data[strtolower($backendAttr)]['count'] > $count)
                                $count = $data[strtolower($backendAttr)]['count'];
                            
                                $isLdapKeyExists = true;
                            }
                        }
                    
                        if($isLdapKeyExists == true)
                        {
                            if($multiAllowedStatus['status'] && $count > 1)
                            {
                                for($i = 0; $i < $count; $i++)
                                {
                                    $elementArr = [];

                                    foreach($compositeAttrStatus['status'] as $propValue)
                                    {
                                        if(isset($ldapKeyInfo['backend_attribute'][$propValue]))
                                        {
                                            $newLdapKey = strtolower($ldapKeyInfo['backend_attribute'][$propValue]);
                                            if(isset($data[$newLdapKey]) && isset($data[$newLdapKey][$i]))
                                            {
                                                $elementArr[] = $data[$newLdapKey][$i];
                                            }
                                        }
                                        else
                                        {
                                            $elementArr[] = '';
                                        }
                                    }
                                
                                    $vCardParams = Utility::reverseMapVCardParams($ldapKeyInfo['parameters'], $ldapKeyInfo['reverse_map_parameter_index']);
                                    if(!empty($vCardParams))
                                    {
                                        $vcard->add($vCardKey, $elementArr, $vCardParams);
                                    }
                                    else
                                    {
                                        $vcard->add($vCardKey, $elementArr); 
                                    }
                                }
                            }
                            else
                            {
                                $elementArr = [];
                                
                                foreach($compositeAttrStatus['status'] as $propValue)
                                {
                                    if(isset($ldapKeyInfo['backend_attribute'][$propValue]))
                                    {
                                        $newLdapKey = strtolower($ldapKeyInfo['backend_attribute'][$propValue]);
                                        if(isset($data[$newLdapKey]))
                                        {
                                            $elementArr[] = $data[$newLdapKey][0];
                                        }
                                    }
                                    else
                                    {
                                        $elementArr[] = '';
                                    }
                                }
                            
                                $vCardParams = Utility::reverseMapVCardParams($ldapKeyInfo['parameters'], $ldapKeyInfo['reverse_map_parameter_index']);
                                if(!empty($vCardParams))
                                {
                                    $vcard->add($vCardKey, $elementArr, $vCardParams);
                                }
                                else
                                {
                                    $vcard->add($vCardKey, $elementArr); 
                                }
                            }                                               
                        }
                    }
                    else
                    {
                        $newLdapKey = strtolower($ldapKeyInfo['backend_attribute']);
                        
                        if(isset($data[$newLdapKey]))
                        {                        
                            if($multiAllowedStatus['status'])
                            {
                                foreach($data[$newLdapKey] as $key => $attrValue)
                                {
                                    if($key === 'count')
                                    continue;
                                    
                                    $vCardParams = Utility::reverseMapVCardParams($ldapKeyInfo['parameters'], $ldapKeyInfo['reverse_map_parameter_index']);
                                    
                                    if(!empty($vCardParams))
                                    {
                                        if(isset($ldapKeyInfo['decode_file']) && $ldapKeyInfo['decode_file'] == true)
                                        {
                                            $vCardParams = array_merge($vCardParams, ['value' => 'BINARY']);
                                            $vcard->add($vCardKey, base64_encode($attrValue), $vCardParams);
                                        }
                                        else
                                        {
                                            $vcard->add($vCardKey, $attrValue, $vCardParams);
                                        }              
                                    }
                                    else
                                    {
                                        $vcard->add($vCardKey, $attrValue); 
                                    }
                                }
                            }
                            else
                            {
                                $vCardParams = Utility::reverseMapVCardParams($ldapKeyInfo['parameters'], $ldapKeyInfo['reverse_map_parameter_index']);
                                
                                if(!empty($vCardParams))
                                {
                                    if(isset($ldapKeyInfo['decode_file']) && $ldapKeyInfo['decode_file'] == true)
                                    {
                                        $vCardParams = array_merge($vCardParams, ['value' => 'BINARY']);
                                        $vcard->add($vCardKey, base64_encode($data[$newLdapKey][0]), $vCardParams);
                                    }
                                    else
                                    {
                                        $vcard->add($vCardKey, $data[$newLdapKey][0], $vCardParams);
                                    } 
                                }
                                else
                                {
                                    $vcard->add($vCardKey, $data[$newLdapKey][0]); 
                                }
                            }
                        }
                    }           
                }    
            }
            else
            {
                $newLdapKey = strtolower($ldapKey['backend_attribute']);
                if(isset($data[$newLdapKey]))
                {                   
                    $vcard->add($vCardKey, $data[$newLdapKey][0]);                                         
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
        $result = [
            'syncToken' => $this->syncToken,
            'added'     => [],
            'modified'  => [],
            'deleted'   => [],
        ];

        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];

        if($ldapConn === false)
        {
            return false;
        }

        //Full sync Operation
        if($syncToken == null)
        {
            $data = $this->fullSyncOperation($addressBookId);
            
            if(! empty($data))
            {
		          for ($i=0; $i < count($data); $i++) {
		              $result['added'][] = $data[$i]['card_uri'];
		          }
            }
            return $result;
        } 

        $fullSyncTimestamp = null;
        try {
            $query = 'SELECT full_sync_ts FROM '.$this->fullSyncTable.' WHERE addressbook_id = ? ';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$addressBookId]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $fullSyncTimestamp = $row['full_sync_ts'];
                }
        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }
         
        if( ($syncToken < $fullSyncTimestamp) &&  ($this->syncToken >= $fullSyncTimestamp))
        {           
            return null;
        }    
        

        //ADDED CARDS
        $filter = '(&' .$addressBookConfig['filter']. '(createtimestamp<=' .gmdate('YmdHis', $this->syncToken). 'Z)(!(|(createtimestamp<='.gmdate('YmdHis', $syncToken).'Z)(createtimestamp='.gmdate('YmdHis', $syncToken).'Z))))'; 
        $data = Utility::LdapIterativeQuery($ldapConn, $addressBookDn, $filter, ['entryuuid'], strtolower($addressBookConfig['scope']));      
        
        while($data)
        {
            $cardUri = null;
            try {
                $query = 'SELECT card_uri FROM '.$this->ldapMapTableName.' WHERE addressbook_id = ? and backend_id = ? and user_id = ?';
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$addressBookId, $data['data']['entryUUID'][0], $this->principalUser]);
                
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $cardUri = $row['card_uri'];
                }

                if($cardUri == null)
                {
                    $cardUri = $this->guidv4().'.vcf';
                    $cardUID = $this->guidv4();
                    $query = "INSERT INTO `".$this->ldapMapTableName."` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
                    $sql = $this->pdo->prepare($query);
                    $sql->execute([$cardUri, $cardUID, $addressBookId, $data['data']['entryUUID'][0], $this->principalUser]); 
                }
            } catch (\Throwable $th) {
                error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
            }     
            $result['added'][] = $cardUri;
       
            $data = Utility::LdapIterativeQuery($ldapConn, $data['entryIns']);
        }
        
        

        //MODIFIED CARDS
        $filter = '(&' .$addressBookConfig['filter']. '(createtimestamp<=' .gmdate('YmdHis', $this->syncToken). 'Z)(!(|(modifytimestamp<='.gmdate('YmdHis', $syncToken).'Z)(modifytimestamp='.gmdate('YmdHis', $syncToken).'Z))))';  
        $data = Utility::LdapIterativeQuery($ldapConn, $addressBookDn, $filter, ['entryuuid'], strtolower($addressBookConfig['scope']));
        
        while($data)
        {
            $cardUri = null;
            try {
                $query = 'SELECT card_uri FROM '.$this->ldapMapTableName.' WHERE addressbook_id = ? and backend_id = ? and user_id = ?';
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$addressBookId, $data['data']['entryUUID'][0], $this->principalUser]);
                
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $cardUri = $row['card_uri'];
                }
                
                if($cardUri == null)
                {
                    $cardUri = $this->guidv4().'.vcf';
                    $cardUID = $this->guidv4();
                    $query = "INSERT INTO `".$this->ldapMapTableName."` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
                    $sql = $this->pdo->prepare($query);
                    $sql->execute([$cardUri, $cardUID, $addressBookId, $data['data']['entryUUID'][0], $this->principalUser]);  
                    $result['added'][] = $cardUri;
                }
                else{
                    $result['modified'][] = $cardUri;
                }
            } catch (\Throwable $th) {
                error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
            }   
            
            $data = Utility::LdapIterativeQuery($ldapConn, $data['entryIns']);
        }
        


        //DELETED CARDS
        try {
            $query = 'SELECT card_uri FROM '.$this->deletedCardsTableName.' WHERE addressbook_id = ? and ( sync_token <= ? ) and ( sync_token > ? ) and user_id = ? ';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$addressBookId, $this->syncToken, $syncToken, $this->principalUser]);
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $result['deleted'][] = $row['card_uri'];
            }
        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }  
        
        return $result;
    }


    /**
     * Adds a change record to the addressbookchanges table.
     *
     * @param mixed  $addressBookId
     * @param string $objectUri
     */
    protected function addChange($addressBookId, $objectUri, $backendId)
    {
        try {
            $this->pdo->beginTransaction();

            $query = "DELETE FROM `".$this->ldapMapTableName."` WHERE addressbook_id = ? AND backend_id = ? AND user_id = ?"; 
            $sql = $this->pdo->prepare($query);
            $sql->execute([$addressBookId, $backendId, $this->principalUser]);


            $query = "INSERT INTO `".$this->deletedCardsTableName."` (`sync_token` ,`addressbook_id` ,`card_uri`, `user_id`) VALUES (?, ?, ?, ?)"; 
            $sql = $this->pdo->prepare($query);
            $sql->execute([time(), $addressBookId, $objectUri, $this->principalUser]);

            $this->pdo->commit();
        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }    
    }


    /**
     * Get contact using cards backend map table and ldap directory database.
     *
     * @param string  $addressBookDn
     * @param string  $addressBookId
     * @param string  $cardUri
     * @param array $config
     */
    function fetchContactData($addressBookId, $cardUri)
    {
        $config = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        $result = null;
        $backendId = null;
        
        try {
            $query = 'SELECT backend_id FROM '.$this->ldapMapTableName.' WHERE addressbook_id = ? and card_uri = ? and user_id = ?';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$addressBookId, $cardUri, $this->principalUser]);
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $backendId = $row['backend_id'];
            }
            
        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }
        
  
        $filter = '(&'.$config['filter']. '(entryuuid=' .$backendId. '))'; 
        $attributes = ['*', 'entryuuid', 'modifytimestamp'];
        
        $result = Utility::LdapQuery($ldapConn, $addressBookDn, $filter, $attributes, strtolower($config['scope']));      
        return $result;
    }


    /**
     * Full synchronize operation using Ldap database and cards backend map table.
     *
     * @param string  $addressBookId
     */
    function fullSyncOperation($addressBookId)
    {
        $config = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        $mappedContacts = [];
        $backendIds = [];
        $mappedBackendIds = [];

        $filter = '(&'.$config['filter']. '(createtimestamp<=' . gmdate('YmdHis', $this->syncToken) . 'Z))';     
        $attributes = ['entryuuid','modifytimestamp'];
        
        $data = Utility::LdapIterativeQuery($ldapConn, $addressBookDn, $filter, $attributes, strtolower($config['scope']));
        
        try 
        {
			$this->pdo->beginTransaction();
           
            while($data) 
			{
	            $contactData = null;
	          
	            $query = 'SELECT card_uri, card_uid FROM '.$this->ldapMapTableName.' WHERE user_id = ? AND addressbook_id = ? AND backend_id = ?';
	            $stmt = $this->pdo->prepare($query);
	            $stmt->execute([$this->principalUser, $addressBookId, $data['data']['entryUUID'][0]]);
	            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
	          
	            if (!empty($row)) 
                {
	                $contactData = [    'card_uri' => $row['card_uri'], 
                  						'card_uid' => $row['card_uid']
	                  				];
	            }
	            else
                {
                		// Adding contacts present in LDAP with no reference here
                    $cardUri = $this->guidv4().'.vcf';
                    $cardUID = $this->guidv4();
                    $query = "INSERT INTO `".$this->ldapMapTableName."` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
                    $sql = $this->pdo->prepare($query);
                    $sql->execute([$cardUri, $cardUID, $addressBookId, $data['data']['entryUUID'][0], $this->principalUser]);

	                $contactData = [    'card_uri' => $cardUri, 
                  						'card_uid' => $cardUID
	                  				];
                }
            
                $contactData['backend_id'] = $data['data']['entryUUID'][0];
                $contactData['modified_timestamp'] = strtotime($data['data']['modifyTimestamp'][0]);
            
                $mappedContacts[] = $contactData;
                $backendIds[] = $data['data']['entryUUID'][0];

                $data = Utility::LdapIterativeQuery($ldapConn, $data['entryIns']);
			}
				
				// Fetch all mapped backend ids
		    $query = 'SELECT backend_id FROM '.$this->ldapMapTableName.' WHERE user_id = ? AND addressbook_id = ?';
		    $stmt = $this->pdo->prepare($query);
		    $stmt->execute([$this->principalUser, $addressBookId]);
					
		    while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
			{
				$mappedBackendIds[] = $row['backend_id'];
			}
            
		    foreach($mappedBackendIds as $mappedBackendId)
		    {
		        if( !in_array($mappedBackendId, $backendIds))
		        {
		          $query = "INSERT INTO `".$this->deletedCardsTableName."` (`addressbook_id`, `card_uri`, `user_id`, `sync_token`) SELECT addressbook_id, card_uri, user_id, ? FROM " . $this->ldapMapTableName . " WHERE user_id = ? AND addressbook_id = ? AND backend_id = ?"; 
	            $sql = $this->pdo->prepare($query);
	            $sql->execute([time(), $this->principalUser, $addressBookId, $mappedBackendId]);
	            
	            $query = "DELETE FROM `" . $this->ldapMapTableName . "` WHERE user_id = ? AND addressbook_id = ? AND backend_id = ?"; 
	            $sql = $this->pdo->prepare($query);
	            $sql->execute([$this->principalUser, $addressBookId, $mappedBackendId]);
		        }
		    }

            $this->pdo->commit();

        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }

        return $mappedContacts;
    }

    function guidv4($data = null) {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);
    
        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

?>
