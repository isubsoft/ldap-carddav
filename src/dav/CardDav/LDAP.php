<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

namespace isubsoft\dav\CardDav;

use isubsoft\dav\Utility\LDAP as Utility;
use isubsoft\dav\Rules\LDAP as Rules;
use isubsoft\Vobject\Reader as Reader;
use \Sabre\DAV\Exception as SabreDAVException;

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
    private $addressBooksTableName = 'cards_addressbook';
    
    private $systemUsersTableName = 'cards_system_user';
    
    private $backendMapTableName = 'cards_backend_map';
    
    private $deletedCardsTableName = 'cards_deleted';

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
     * System user
     *
     * @var string
     */    
    public $systemUser = null;

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
        $this->principalUser = basename($principalUri);
        $addressBooks = [];
        
				try 
				{
			    $query = 'SELECT user_id FROM '. $this->systemUsersTableName . ' LIMIT 1';
			    $stmt = $this->pdo->prepare($query);
			    $stmt->execute([]);
			    
			    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
			    
			    if($row !== false)
			    	$this->systemUser = $row['user_id'];
			    
			  } catch (\Throwable $th) {
			        error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
			  }
        
        foreach ($this->config['card']['addressbook']['ldap'] as $addressBookId => $addressBookConfig) {
					try 
					{
				    $query = 'SELECT addressbook_id, user_specific, writable FROM '. $this->addressBooksTableName . ' WHERE addressbook_id =? LIMIT 1';
				    $stmt = $this->pdo->prepare($query);
				    $stmt->execute([$addressBookId]);
				    
				    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
				    
				    if($row === false)
				    	continue;
				    	
				    $addressBookConfig['user_specific'] = $row['user_specific'];
				    $addressBookConfig['writable'] = $row['writable'];
				    
				  } catch (\Throwable $th) {
				        error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
				        return [];
				  }
		    
            $addressBookDn = $addressBookConfig['base_dn'];
            $addressBookSyncToken = time();
               
            $addressBooks[] = [
                'id'                                                          => $addressBookId,
                'uri'                                                         => $addressBookId,
                'principaluri'                                                => $principalUri,
                '{DAV:}displayname'                                           => isset($addressBookConfig['name']) ? $addressBookConfig['name'] : '',
                '{' . \Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description'  => isset($addressBookConfig['description']) ? $addressBookConfig['description'] : '',
                '{http://calendarserver.org/ns/}getctag' 											=> (!$addressBookSyncToken == null) ? $addressBookSyncToken : time(),
                '{http://sabredav.org/ns}sync-token'                          => (!$addressBookSyncToken == null) ? $addressBookSyncToken : 0
            ];
            
						if(isset($addressBookConfig['bind_dn']) && $addressBookConfig['bind_dn'] != '')
            {
            	if(isset($addressBookConfig['bind_pass']) && $addressBookConfig['bind_pass'] != '')
		          {
		              $this->addressbook[$addressBookId]['LdapConnection'] = Utility::LdapBindConnection(['bindDn' => $addressBookConfig['bind_dn'], 'bindPass' => $addressBookConfig['bind_pass']], $addressBookConfig);
		          }
		          else
		          {
		              $this->addressbook[$addressBookId]['LdapConnection'] = Utility::LdapBindConnection(['bindDn' => $addressBookConfig['bind_dn'], 'bindPass' => null], $addressBookConfig);
		          }
            }
            else
            {
            	if($addressBookConfig['user_specific'] == true)
            		$addressBookDn = Utility::replacePlaceholders($addressBookConfig['base_dn'], ['%u' => $this->principalUser ]);
            		
              $this->addressbook[$addressBookId]['LdapConnection'] = $this->authBackend->userLdapConn;
            }

            $this->addressbook[$addressBookId]['config'] = $addressBookConfig;
            $this->addressbook[$addressBookId]['addressbookDn'] = $addressBookDn;
            $this->addressbook[$addressBookId]['syncToken'] = $addressBookSyncToken;
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
                
                $row = [    'id' => $data[$i]['card_uid'],
                            'uri' => $data[$i]['card_uri'],
                            'lastmodified' => $data[$i]['modified_timestamp']
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
				$cardUID = null;
				
				$addressBookConfig = $this->addressbook[$addressBookId]['config'];
				$dbUser = ($addressBookConfig['user_specific'])?$this->principalUser:$this->systemUser;
        
				try 
				{
		      $query = 'SELECT card_uid FROM '.$this->backendMapTableName.' WHERE user_id = ? AND addressbook_id = ? AND card_uri = ?';
		      $stmt = $this->pdo->prepare($query);
		      $stmt->execute([$dbUser, $addressBookId, $cardUri]);
		      
		      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
		          $cardUID = $row['card_uid'];
		      }
		    } catch (\Throwable $th) {
		          error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
		    }

				if($cardUID == null)
					return false;
            
        $data = $this->fetchLdapContactData($addressBookId, $cardUri, ['*', 'modifyTimestamp']);
        
        if(empty($data))
					throw new SabreDAVException\ServiceUnavailable();

        if(!$data['count'] > 0)
        	return false;
        	
        if(!isset($data[0]['modifytimestamp'][0]))
        {
					error_log("Read access to some operational attributes in LDAP not present. ".__METHOD__." at line no ".__LINE__);
        }
        	
        $cardData = $this->generateVcard($data[0], $addressBookId, $cardUri);
        
        if($cardData == null || $cardData == '')
        	return [];
         
        $result = [
            'id'            => $cardUID,
            'carddata'      => $cardData,
            'uri'           => $cardUri,
            'lastmodified'  => strtotime($data[0]['modifytimestamp'][0])
        ];
                    
        return $result;
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
     * Creates a new card or updates an existing one.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressBooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag is for the
     * created/updated resource, and must be enclosed with double quotes (that
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
     * @param string $operation
     * @return string|null
     */
    private function createUpdateCard($addressBookId, $cardUri, $cardData, $operation = 'CREATE')
    {
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        
        if(!$addressBookConfig['writable'])
					throw new SabreDAVException\Forbidden("Not allowed");
        
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];        
				$dbUser = ($addressBookConfig['user_specific'])?$this->principalUser:$this->systemUser;
				$vcard = (Reader::read($cardData))->convert(\Sabre\VObject\Document::VCARD40);
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
                        $memberCardUID = Reader::memberValue($values, $vCardKey);
                        if($memberCardUID != '')
                        {
                            $backendId = null;

                            try {
                                $query = 'SELECT backend_id FROM '.$this->backendMapTableName.' WHERE addressbook_id = ? and card_uid = ? and user_id = ?';
                                $stmt = $this->pdo->prepare($query);
                                $stmt->execute([$addressBookId, $memberCardUID, $dbUser]);
                                
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

                if($multiAllowedStatus['status'])
                {
                    foreach ($vcard->$vCardKey as $values) 
                    {
                        $ldapBackendInfo = Rules::mapVcardProperty($vCardKey, $ldapKey, $values);
                        if(!empty($ldapBackendInfo))
                        {
                            foreach ($ldapBackendInfo as $ldapAttr => $ldapBackendValue) {
                                $ldapInfo[$ldapAttr][] = $ldapBackendValue;
                            }
                        }
                    }
                }
                else
                {
                    $ldapBackendInfo = Rules::mapVcardProperty($vCardKey, $ldapKey, $vcard->$vCardKey);
                    if($ldapBackendInfo)
                    {
                        foreach ($ldapBackendInfo as $ldapAttr => $ldapBackendValue) {
                            $ldapInfo[$ldapAttr] = $ldapBackendValue;
                        }
                    }
                }
            }    
        }
         
        foreach ($requiredFields as $key) {
            if(! array_key_exists($key, $ldapInfo))
							throw new SabreDAVException\BadRequest("Required fields not present");
        }

        if(! array_key_exists($rdn, $ldapInfo))
					throw new SabreDAVException\BadRequest("Identity field not present");
					
				if($operation == 'UPDATE')
				{
					$oldLdapInfo = $this->fetchLdapContactData($addressBookId, $cardUri, ['*']);

					if(empty($oldLdapInfo))
						throw new SabreDAVException\Conflict();

					$oldLdapTree = $oldLdapInfo[0]['dn'];
					$componentOldLdapTree = ldap_explode_dn($oldLdapTree, 0);

					if(!$componentOldLdapTree)
					{
						error_log("Unknown error in " . __METHOD__ . " at line " . __LINE__);
						throw new SabreDAVException\ServiceUnavailable();
					}

					$parentOldLdapTree = "";

					for($dnComponentIndex=1; $dnComponentIndex<$componentOldLdapTree['count']; $dnComponentIndex++)
						$parentOldLdapTree = $parentOldLdapTree . (empty($parentOldLdapTree)?"":",") . $componentOldLdapTree[$dnComponentIndex];
						
					$mappedBackendAttributes = [];

					foreach($fieldMap as $vCardKey => $backendMapArr)
					{
						if(! isset($backendMapArr['backend_attribute']))
						{
							foreach($backendMapArr as $backendMap)
							{
								if(isset($backendMap['backend_attribute']) && is_array($backendMap['backend_attribute']))
								{
									foreach($backendMap['backend_attribute'] as $compositeBackendMapKey => $compositeBackendMapValue)
									{
										$mappedBackendAttributes[] = strtolower($compositeBackendMapValue);
									}
								}
								else
									$mappedBackendAttributes[] = strtolower($backendMap['backend_attribute']);
							}
						}
						else
						{
							if(is_array($backendMapArr['backend_attribute']))
								foreach($backendMapArr['backend_attribute'] as $compositeBackendMapKey => $compositeBackendMapValue)
									$mappedBackendAttributes[] = strtolower($compositeBackendMapValue);
							else
								 $mappedBackendAttributes[] = strtolower($backendMapArr['backend_attribute']);
						}
					}

					foreach($oldLdapInfo[0] as $oldLdapAttrName => $oldLdapAttrValue) 
					{ 
						if(isset($addressBookConfig['backend_data_update_policy']) && $addressBookConfig['backend_data_update_policy'] == 'replace')
						{
							if(! isset($ldapInfo[$oldLdapAttrName]))
							{
								if(is_array($oldLdapAttrValue))
									$ldapInfo[$oldLdapAttrName] = [];
							}
						}
						else
						{
							if(! isset($ldapInfo[$oldLdapAttrName]))
							{
								if(is_array($oldLdapAttrValue) && in_array($oldLdapAttrName, $mappedBackendAttributes))
									$ldapInfo[$oldLdapAttrName] = [];
							}
						}
					}

					$newLdapRdn = $rdn . '=' . ldap_escape(is_array($ldapInfo[$rdn])?$ldapInfo[$rdn][0]:$ldapInfo[$rdn], "", LDAP_ESCAPE_DN);

							if(! ldap_rename($ldapConn, $oldLdapTree, $newLdapRdn, null, false))
								throw new SabreDAVException\BadRequest();

							if(! ldap_mod_replace($ldapConn, $newLdapRdn . ',' . $parentOldLdapTree, $ldapInfo))
								throw new SabreDAVException\BadRequest();				
				}
				else
				{
	        $UID = (empty($vcard->UID))?$this->guidv4():$vcard->UID;
		      $ldapTree = $rdn. '='. ldap_escape(is_array($ldapInfo[$rdn])?$ldapInfo[$rdn][0]:$ldapInfo[$rdn], "", LDAP_ESCAPE_DN) . ',' .$addressBookDn;

		      if(!ldap_add($ldapConn, $ldapTree, $ldapInfo))
						throw new SabreDAVException\BadRequest();

		      $data = Utility::LdapQuery($ldapConn, $ldapTree, $addressBookConfig['filter'], ['entryuuid'], 'base');
		      
		      if(!empty($data) && $data['count'] > 0)
		      {
				    try {
				        $query = "INSERT INTO `".$this->backendMapTableName."` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
				        $sql = $this->pdo->prepare($query);
				        $sql->execute([$cardUri, $UID, $addressBookId, $data[0]['entryuuid'][0], $dbUser]);
				    } catch (\Throwable $th) {
				        error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
				    }
		      }						
				}
			
			return null;
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
			return $this->createUpdateCard($addressBookId, $cardUri, $cardData);
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
	    return $this->createUpdateCard($addressBookId, $cardUri, $cardData, 'UPDATE');
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
        
        $data = $this->fetchLdapContactData($addressBookId, $cardUri, ['dn', 'entryUUID']);
        
        if(empty($data))
        	return false;
        
        $ldapTree = $data[0]['dn'];

        try {
            $ldapDelete = ldap_delete($ldapConn, $ldapTree);
            
            if(!$ldapDelete)
            {
                return false;
            }
        } catch (\Throwable $th) {
            error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
            throw new SabreDAVException\ServiceUnavailable();
        }

        $this->addChange($addressBookId, $cardUri);
        return true;
    }


    /**
     * Generate Serialize Data of Vcard
     *
     * @param array $data
     * @param array $addressBookId
     * @return null or vcard data
     */
    protected function generateVcard($data, $addressBookId, $cardUri)
    { 
        if (empty ($data)) {
            return null;
        }
        
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        $fieldMap = $addressBookConfig['fieldmap'];
				$dbUser = ($addressBookConfig['user_specific'])?$this->principalUser:$this->systemUser;
        $UID = null;

        try {
            $query = 'SELECT card_uid FROM '.$this->backendMapTableName.' WHERE addressbook_id = ? and card_uri = ? and user_id = ?';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$addressBookId, $cardUri, $dbUser]);
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $UID = $row['card_uid'];
            }
        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }
        
        
        // build the Vcard
        $vcard = (new \Sabre\VObject\Component\VCard(['UID' => $UID]))->convert(\Sabre\VObject\Document::VCARD40);

        if($data['objectclass'][0] == $addressBookConfig['group_LDAP_Object_Classes'][0])
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
                                    $query = 'SELECT card_uid FROM '.$this->backendMapTableName.' WHERE addressbook_id = ? and backend_id = ? and user_id = ?';
                                    $stmt = $this->pdo->prepare($query);
                                    $stmt->execute([$addressBookId, $memberData[0]['entryuuid'][0], $dbUser]);
                                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                        $clientUID = $row['card_uid'];
                                    }
                                } catch (\Throwable $th) {
                                    error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
                                }
                                
                                $memberValue = Reader::memberValueConversion($clientUID, $vCardKey);
                                $memberValue ? $vcard->add($vCardKey, $memberValue): '';               
                            }                  
                        }
                    }
                }
            }
        }
      

        foreach ($fieldMap as $vCardKey => $ldapKey) {
            
            $multiAllowedStatus = Reader::multiAllowedStatus($vCardKey);
            $compositeAttrStatus = Reader::compositeAttrStatus($vCardKey);
            $iterativeArr = Utility::isMultidimensional($ldapKey);

            if($multiAllowedStatus['status'] && !$compositeAttrStatus['status'] && !$iterativeArr)
            {
                $newLdapKey = strtolower($ldapKey['backend_attribute']);
                if(isset($data[$newLdapKey]))
                {
                    foreach($data[$newLdapKey] as $key => $value)
                    {
                        if($key === 'count')
                        continue;

                        $vCardParams = !empty($ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']]) ? $ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']] : [];
                        $valueInfo = Reader::backendValueConversion($value, $ldapKey['backend_data_format']);
                        $vCardParams = array_merge($vCardParams, $valueInfo['params']);

                        !empty($vCardParams) ? $vcard->add($vCardKey, $valueInfo['cardData'], $vCardParams) : $vcard->add($vCardKey, $valueInfo['cardData']);
                    }
                }
            }
            else if($compositeAttrStatus['status'] && !$iterativeArr)  
            {
                if(!is_array($ldapKey['backend_attribute']) && isset($ldapKey['map_component_separator']))
                {
                    $newLdapKey = strtolower($ldapKey['backend_attribute']);
                    if(isset($data[$newLdapKey]))
                    {
                        if($multiAllowedStatus['status'])
                        {
                            foreach($data[$newLdapKey] as $key => $attrValue)
                            {
                                if($key === 'count')
                                continue;

                                $elementArr = explode($ldapKey['map_component_separator'], $attrValue);
                                
                                $vCardParams = !empty($ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']]) ? $ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']] : [];
                                
                                !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
                            }
                        }
                        else
                        {
                            $elementArr = explode($ldapKey['map_component_separator'], $data[$newLdapKey][0]);

                            $vCardParams = !empty($ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']]) ? $ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']] : [];
                            
                            !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
                        }
                    }
                }
                else
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
                        if($multiAllowedStatus['status'] && $count > 0)
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
                                        else
                                        {
                                            $elementArr[] = '';
                                        }
                                    }
                                    else
                                    {
                                        $elementArr[] = '';
                                    }
                                }
    
                                $vCardParams = !empty($ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']]) ? $ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']] : [];
                                
                                !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
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
                                    else
                                    {
                                        $elementArr[] = '';
                                    }
                                }
                                else
                                {
                                    $elementArr[] = '';
                                }
                            }
    
                            $vCardParams = !empty($ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']]) ? $ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']] : [];
                            
                            !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
                        }                                               
                    }  
                }                  
            }
            else if($iterativeArr)
            {
                foreach($ldapKey as $ldapKeyInfo)
                {
                    if($compositeAttrStatus['status'])
                    {
                        if(!is_array($ldapKeyInfo['backend_attribute']) && isset($ldapKeyInfo['map_component_separator']))
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

                                        $elementArr = explode($ldapKeyInfo['map_component_separator'], $attrValue);

                                        $vCardParams = !empty($ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']]) ? $ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']] : [];
                                        
                                        !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
                                    }
                                }
                                else
                                {
                                    $elementArr = explode($ldapKeyInfo['map_component_separator'], $data[$newLdapKey][0]);

                                    $vCardParams = !empty($ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']]) ? $ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']] : [];
                                    
                                    !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
                                }
                            }
                        }
                        else
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
                                if($multiAllowedStatus['status'] && $count > 0)
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
                                                else
                                                {
                                                    $elementArr[] = '';
                                                }
                                            }
                                            else
                                            {
                                                $elementArr[] = '';
                                            }
                                        }
                                        
                                        $vCardParams = !empty($ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']]) ? $ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']] : [];
                                        
                                        !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
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
                                            else
                                            {
                                                $elementArr[] = '';
                                            }
                                        }
                                        else
                                        {
                                            $elementArr[] = '';
                                        }
                                    }
                                
                                    $vCardParams = !empty($ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']]) ? $ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']] : [];
                                    
                                    !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
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
                                    
                                    $vCardParams = !empty($ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']]) ? $ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']] : [];
                                    $valueInfo = Reader::backendValueConversion($attrValue, $ldapKeyInfo['backend_data_format']);
                                    $vCardParams = array_merge($vCardParams, $valueInfo['params']);

                                    !empty($vCardParams) ? $vcard->add($vCardKey, $valueInfo['cardData'], $vCardParams) : $vcard->add($vCardKey, $valueInfo['cardData']);
                                }
                            }
                            else
                            {
                                $vCardParams = !empty($ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']]) ? $ldapKeyInfo['parameters'][$ldapKeyInfo['reverse_map_parameter_index']] : [];
                                $valueInfo = Reader::backendValueConversion($data[$newLdapKey][0], $ldapKeyInfo['backend_data_format']);
                                $vCardParams = array_merge($vCardParams, $valueInfo['params']);

                                !empty($vCardParams) ? $vcard->add($vCardKey, $valueInfo['cardData'], $vCardParams) : $vcard->add($vCardKey, $valueInfo['cardData']);
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
                    $vCardParams = !empty($ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']]) ? $ldapKey['parameters'][$ldapKey['reverse_map_parameter_index']] : [];
                    $valueInfo = Reader::backendValueConversion($data[$newLdapKey][0], $ldapKey['backend_data_format']);
                    $vCardParams = array_merge($vCardParams, $valueInfo['params']);

                    !empty($vCardParams) ? $vcard->add($vCardKey, $valueInfo['cardData'], $vCardParams) : $vcard->add($vCardKey, $valueInfo['cardData']);                                       
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
			$addressBookConfig = $this->addressbook[$addressBookId]['config'];
			$addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
			$addressBookSyncToken = $this->addressbook[$addressBookId]['syncToken'];
			$ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
			$dbUser = ($addressBookConfig['user_specific'])?$this->principalUser:$this->systemUser;

			$resultTmpError = [
					'syncToken' => $syncToken,
					'added'     => [],
					'modified'  => [],
					'deleted'   => [],
			];

			$result = [
					'syncToken' => $addressBookSyncToken,
					'added'     => [],
					'modified'  => [],
					'deleted'   => [],
			];

			if($syncToken >= $addressBookSyncToken)
			{
				return $result;
			}

			if(isset($addressBookConfig['sync_bind_dn']) && $addressBookConfig['sync_bind_dn'] != '')
			{
				$syncBindDn = $addressBookConfig['sync_bind_dn'];
				$syncBindPass = (!isset($addressBookConfig['sync_bind_pw']))?null:$addressBookConfig['sync_bind_pw'];
				$ldapConn = Utility::LdapBindConnection(['bindDn' => $syncBindDn, 'bindPass' => $syncBindPass], $addressBookConfig);
			}

			if($ldapConn === false)
			{
				return $resultTmpError;
			}

			// Perform initial sync
			if($syncToken == null)
			{
				$data = $this->fullSyncOperation($addressBookId);
				
				if(! empty($data))
				{
					for ($i=0; $i < count($data); $i++) {
							$result['added'][] = $data[$i]['card_uri'];
					}

					return $result;
				}
				
				return null;
			}
			
			$filter = '(&' . $addressBookConfig['filter'] . '(createtimestamp>=' . gmdate('YmdHis', $syncToken) . 'Z)(!(createtimestamp>=' . gmdate('YmdHis', $addressBookSyncToken) . 'Z)))';
			$data = Utility::LdapIterativeQuery($ldapConn, $addressBookDn, $filter, ['entryuuid'], strtolower($addressBookConfig['scope']));

			if($data === false)
			{
				return $resultTmpError;
			}

			while($data['entryIns'])
			{
				if(!isset($data['data']['entryUUID'][0]))
				{
					error_log("Read access to required operational attributes in LDAP not present. Cannot continue. Quitting. ".__METHOD__." at line no ".__LINE__);
					return $resultTmpError;
				}
				
				$cardUri = null;
				
				try {
						$query = 'SELECT card_uri FROM '.$this->backendMapTableName.' WHERE addressbook_id = ? and backend_id = ? and user_id = ?';
						$stmt = $this->pdo->prepare($query);
						$stmt->execute([$addressBookId, $data['data']['entryUUID'][0], $dbUser]);
						
						while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
								$cardUri = $row['card_uri'];
						}

						if($cardUri == null)
						{
								$cardUID = $this->guidv4();
								$cardUri = $cardUID .'.vcf';
								
								$query = "INSERT INTO `".$this->backendMapTableName."` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
								$sql = $this->pdo->prepare($query);
								$sql->execute([$cardUri, $cardUID, $addressBookId, $data['data']['entryUUID'][0], $dbUser]); 
						}
				} catch (\Throwable $th) {
						error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
						return $resultTmpError;
				}
				
				$result['added'][] = $cardUri;
				$data = Utility::LdapIterativeQuery($ldapConn, $data['entryIns']);
			}
				
			$filter = '(&' . $addressBookConfig['filter'] . '(!(createtimestamp>=' . gmdate('YmdHis', $syncToken) . 'Z))(modifytimestamp>=' . gmdate('YmdHis', $syncToken) . 'Z)(!(modifytimestamp>=' . gmdate('YmdHis', $addressBookSyncToken) . 'Z)))';
			$data = Utility::LdapIterativeQuery($ldapConn, $addressBookDn, $filter, ['entryuuid'], strtolower($addressBookConfig['scope']));

			if($data === false)
			{
				return $resultTmpError;
			}
				
			while($data['entryIns'])
			{
				if(!isset($data['data']['entryUUID'][0]))
				{
					error_log("Read access to required operational attributes in LDAP not present. Cannot continue. Quitting. ".__METHOD__." at line no ".__LINE__);
					return $resultTmpError;
				}
				
				$cardUri = null;
				
				try {
						$query = 'SELECT card_uri FROM '.$this->backendMapTableName.' WHERE addressbook_id = ? and backend_id = ? and user_id = ?';
						$stmt = $this->pdo->prepare($query);
						$stmt->execute([$addressBookId, $data['data']['entryUUID'][0], $dbUser]);
						
						while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
								$cardUri = $row['card_uri'];
						}
						
						if($cardUri == null)
						{
								$cardUID = $this->guidv4();
								$cardUri = $cardUID .'.vcf';

								$query = "INSERT INTO `".$this->backendMapTableName."` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
								$sql = $this->pdo->prepare($query);
								$sql->execute([$cardUri, $cardUID, $addressBookId, $data['data']['entryUUID'][0], $dbUser]);
								
								$result['added'][] = $cardUri;
								
								continue;
						}

						$result['modified'][] = $cardUri;
				} catch (\Throwable $th) {
						error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
						return $resultTmpError;
				}
					
				$data = Utility::LdapIterativeQuery($ldapConn, $data['entryIns']);
			}

			//DELETED CARDS
			$cardUri = null;
			
			try {
				// Fetch contacts from deleted table
				$query = 'SELECT card_uri FROM '.$this->deletedCardsTableName.' WHERE user_id = ? AND addressbook_id = ? AND sync_token >= ? AND sync_token < ?';
				$stmt = $this->pdo->prepare($query);
				$stmt->execute([$dbUser, $addressBookId, $syncToken, $addressBookSyncToken]);
					
				while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
				{
					$cardUri = $row['card_uri'];
					$result['deleted'][] = $cardUri;
				}
			} catch (\Throwable $th) {
					error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
					return $resultTmpError;
			}

			return $result;
		}


    /**
     * Adds a change record
     *
     * @param mixed  $addressBookId
     * @param string $objectUri
     * @param string $operation
     * @return bool
     */
    protected function addChange($addressBookId, $objectUri, $operation = 'DELETE')
    {
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
     		$dbUser = ($addressBookConfig['user_specific'])?$this->principalUser:$this->systemUser;
            
        if($operation == 'DELETE')
        {
		      try {
		          $this->pdo->beginTransaction();

		          $query = "DELETE FROM `".$this->backendMapTableName."` WHERE addressbook_id = ? AND card_uri = ? AND user_id = ?"; 
		          $sql = $this->pdo->prepare($query);
		          $sql->execute([$addressBookId, $objectUri, $dbUser]);


		          $query = "INSERT INTO `".$this->deletedCardsTableName."` (`sync_token` ,`addressbook_id` ,`card_uri`, `user_id`) VALUES (?, ?, ?, ?)"; 
		          $sql = $this->pdo->prepare($query);
		          $sql->execute([time(), $addressBookId, $objectUri, $dbUser]);

		          $this->pdo->commit();
		      } catch (\Throwable $th) {
		          error_log("Database query could not be executed: " . __METHOD__ . " at line no " . __LINE__ . ", " . $th->getMessage());
		          $this->pdo->rollback();
		          return false;
		      }
        }
        
        return true;
    }


    /**
     * Get contact using cards backend map table and ldap directory database.
     *
     * @param string  $addressBookId
     * @param string  $cardUri
     * @param array 	$attributes
     * @return array
     */
    function fetchLdapContactData($addressBookId, $cardUri, $attributes = [])
    {
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
				$dbUser = ($addressBookConfig['user_specific'])?$this->principalUser:$this->systemUser;
        $result = null;
        $backendId = null;
        
        if($ldapConn === false)
        {
					return null;
        }
        
        try {
            $query = 'SELECT backend_id FROM '.$this->backendMapTableName.' WHERE addressbook_id = ? and card_uri = ? and user_id = ?';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$addressBookId, $cardUri, $dbUser]);
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $backendId = $row['backend_id'];
            }
            
            if(empty($backendId))
            	return null;
            
        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }
        
  
        $filter = '(&'.$addressBookConfig['filter']. '(entryuuid=' .$backendId. '))'; 
        
        $result = Utility::LdapQuery($ldapConn, $addressBookDn, $filter, empty($attributes)?['dn', 'createTimestamp', 'modifyTimestamp']:$attributes, strtolower($addressBookConfig['scope']));      
        return $result;
    }


    /**
     * Full synchronize operation using Ldap database and cards backend map table.
     *
     * @param string  $addressBookId
     * @return array
     */
    function fullSyncOperation($addressBookId)
    {
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $addressBookSyncToken = $this->addressbook[$addressBookId]['syncToken'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        $dbUser = ($addressBookConfig['user_specific'])?$this->principalUser:$this->systemUser;
        
        if(isset($addressBookConfig['sync_bind_dn']) && $addressBookConfig['sync_bind_dn'] != '')
        {
        	$syncBindDn = $addressBookConfig['sync_bind_dn'];
        	$syncBindPass = (!isset($addressBookConfig['sync_bind_pw']))?null:$addressBookConfig['sync_bind_pw'];
        	$ldapConn = Utility::LdapBindConnection(['bindDn' => $syncBindDn, 'bindPass' => $syncBindPass], $addressBookConfig);
        }
        
        if($ldapConn === false)
        {
					return [];
        }
        
        $backendContacts = [];
				$filter = '(&' . $addressBookConfig['filter'] . '(!(createtimestamp>=' . gmdate('YmdHis', $addressBookSyncToken) . 'Z)))';
        $data = Utility::LdapIterativeQuery($ldapConn, $addressBookDn, $filter, ['entryuuid','modifytimestamp'], strtolower($addressBookConfig['scope']));
        
        if($data === false)
        {
					return [];
        }
        
        try 
        {
          while($data['entryIns']) 
					{
						if(!isset($data['data']['entryUUID'][0]) || !isset($data['data']['modifyTimestamp'][0]))
						{
							error_log("Read access to required operational attributes in LDAP not present. Cannot continue. Quitting. ".__METHOD__." at line no ".__LINE__);
							return [];
						}
						
            $contactData = null;
          
            $query = 'SELECT card_uri, card_uid FROM '.$this->backendMapTableName.' WHERE user_id = ? AND addressbook_id = ? AND backend_id = ?';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$dbUser, $addressBookId, $data['data']['entryUUID'][0]]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
          
            if (!empty($row)) 
            {
              $contactData = [	'card_uri' => $row['card_uri'], 
				          							'card_uid' => $row['card_uid']
				            				 ];
            }
            else
            {
          		// Adding contacts present in LDAP with no reference here
              $cardUID = $this->guidv4();
              $cardUri = $cardUID .'.vcf';
              
              $query = "INSERT INTO `" . $this->backendMapTableName."` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
              $sql = $this->pdo->prepare($query);
              $sql->execute([$cardUri, $cardUID, $addressBookId, $data['data']['entryUUID'][0], $dbUser]);

              $contactData = [    'card_uri' => $cardUri, 
              						'card_uid' => $cardUID
                				];
            }
          
            $contactData['backend_id'] = $data['data']['entryUUID'][0];
            $contactData['modified_timestamp'] = strtotime($data['data']['modifyTimestamp'][0]);
        
            $backendContacts[] = $contactData;
            $data = Utility::LdapIterativeQuery($ldapConn, $data['entryIns']);
					}
			
					// Fetch all mapped contacts
					$query = 'SELECT card_uri FROM '.$this->backendMapTableName.' WHERE user_id = ? AND addressbook_id = ?';
					$stmt = $this->pdo->prepare($query);
					$stmt->execute([$dbUser, $addressBookId]);
						
					while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
					{
						$cardUri = $row['card_uri'];
				    $data = $this->fetchLdapContactData($addressBookId, $cardUri, ['entryUUID']);
				    
				    if(empty($data))
				    	return [];
				    	
				    if(!$data['count'] > 0)
				    {
							$this->addChange($addressBookId, $cardUri);
				    }
					}

        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
            return [];
        }

        return $backendContacts;
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
