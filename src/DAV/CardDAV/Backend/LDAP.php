<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\CardDAV\Backend;

use ISubsoft\DAV\Utility\LDAP as Utility;
use ISubsoft\DAV\Rules\LDAP as Rules;
use ISubsoft\DAV\Exception as ISubsoftDAVException;
use ISubsoft\VObject\Reader as Reader;
use Sabre\DAV\Exception as SabreDAVException;
use ISubsoft\Cache\Master as CacheMaster;

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
     * Cache object.
     *
     * @var cache
     */
    private $cache;

    /**
     * PDO table name.
     *
     * @var string
     */
    private static $addressBooksTableName = 'cards_addressbook';
    
    /**
     * PDO table name.
     *
     * @var string
     */
    private static $systemUsersTableName = 'cards_system_user';
    
    /**
     * PDO table name.
     *
     * @var string
     */
    private static $backendMapTableName = 'cards_backend_map';
    
    /**
     * PDO table name.
     *
     * @var string
     */
    private static $deletedCardsTableName = 'cards_deleted';
    
    /**
     * PDO table name.
     *
     * @var string
     */
    private static $fullRefreshTableName = 'cards_full_refresh';

    /**
     * PDO table name.
     *
     * @var string
     */
    private static $fullSyncTableName = 'cards_full_sync';
    
    private static $defaultContactMaxSize = 16384;

    private static $defaultBackendDataUpdatePolicy = 'merge';
    
    private static $defaultFieldAclEval = 'r';
    
    private $defaultVcardVersion = \Sabre\VObject\Document::VCARD40;
    
		private $defaultFrontendVcardVersion = \Sabre\VObject\Document::VCARD30;
    
		private static $defaultFullRefreshInterval = 14400;

    private static $defaultForceFullSyncInterval = 86400;
    
		/**
     * User agent (UA) identification
     *
     * @var array
     */
		private static $uaIdentifier = [
			'moz_tb' => [
				'name' => 'Mozilla Thunderbird',
				'ua_regexp' => '#Mozilla.*\s+Thunderbird/(\S+)#i',
				'capture_group' => [1 => 'version']
			]
		];

    /**
     * Address books
     *
     * @var array
     */    
    private $addressbook = [];
    
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
      	$this->cache = CacheMaster::getCardBackend($config['cache']);
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
  		$principalId = basename($principalUri);
      $currentUserPrincipalId = $GLOBALS['currentUserPrincipalId'];
      $currentUserPrincipalBackendId = $GLOBALS['currentUserPrincipalBackendId'];
  		
  		if(strtolower($principalId) != strtolower($currentUserPrincipalId))
  			throw new SabreDAVException\Forbidden("Not allowed");
        			
      $addressBooks = [];
      
			try 
			{
		    $query = 'SELECT user_id FROM ' . self::$systemUsersTableName . ' LIMIT 1';
		    $stmt = $this->pdo->prepare($query);
		    $stmt->execute([]);
		    
		    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
		    
		    if($row !== false)
		    	$systemUser = $row['user_id'];
		    
		  } catch (\Throwable $th) {
		        error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
		  }
      
      foreach ($this->config['card']['addressbook']['ldap'] as $addressBookId => $addressBookConfig) {
				try 
				{
			    $query = 'SELECT addressbook_id, user_specific, writable FROM ' . self::$addressBooksTableName . ' WHERE addressbook_id =? LIMIT 1';
			    $stmt = $this->pdo->prepare($query);
			    $stmt->execute([$addressBookId]);
			    
			    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
			    
			    if($row === false)
			    	continue;
			    	
					if($addressBookConfig['user_specific'] != $row['user_specific'] || $addressBookConfig['writable'] != $row['writable'])
					{
						error_log("Configuration properties do not match that of sync database for address book '$addressBookId'. Excluded.");
						continue;
					}
					
			    $addressBookConfig['user_specific'] = $row['user_specific'];
			    $addressBookConfig['writable'] = $row['writable'];
			    
			  } catch (\Throwable $th) {
			        error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
			        return [];
			  }

      	$addressBookDn = $addressBookConfig['base_dn'];
        $addressBookSyncToken = time();
           
				if(isset($addressBookConfig['bind_dn']) && $addressBookConfig['bind_dn'] != '')
        {
        	$this->addressbook[$addressBookId]['LdapConnection'] = Utility::LdapBindConnection(['bindDn' => $addressBookConfig['bind_dn'], 'bindPass' => isset($addressBookConfig['bind_pass'])?$addressBookConfig['bind_pass']:null], $this->config['server']['ldap']);
        }
        else if($addressBookConfig['user_specific'] == true)
          $this->addressbook[$addressBookId]['LdapConnection'] = $GLOBALS['currentUserPrincipalLdapConn'];
        else
        {
		      error_log("No available connections to backend for address book - '" . $addressBookId . "'." . __METHOD__ . " at line no " . __LINE__ );
		      continue;
        }
          
      	if($addressBookConfig['user_specific'] == true)
      	{
          if(isset($addressBookConfig['search_base_dn']) && $addressBookConfig['search_base_dn'] != '' && isset($addressBookConfig['search_filter']) && $addressBookConfig['search_filter'] != '')
          {
          	$filter = Utility::replacePlaceholders($addressBookConfig['search_filter'], ['%u' => ldap_escape($principalId, "", LDAP_ESCAPE_FILTER)]);
           	$data = Utility::LdapQuery($this->addressbook[$addressBookId]['LdapConnection'], $addressBookConfig['search_base_dn'], $filter, ['dn'], strtolower($addressBookConfig['search_scope']));
          	
            if(!empty($data) && $data['count'] === 1)
            {
              $addressBookDn = Utility::replacePlaceholders($addressBookConfig['base_dn'], ['%u' => ldap_escape($principalId, "", LDAP_ESCAPE_DN), '%dn' => $data[0]['dn']]);
            }
            else
            {
				      error_log("Address book search returned no result or more than one result " . __METHOD__ . " at line no " . __LINE__ . " for address book id - " . $addressBookId);
				      continue;
            }
          }
          else
          	$addressBookDn = Utility::replacePlaceholders($addressBookConfig['base_dn'], ['%u' => ldap_escape($principalId, "", LDAP_ESCAPE_DN)]);
      	}
      	
        $this->addressbook[$addressBookId]['config'] = $addressBookConfig;
        $this->addressbook[$addressBookId]['addressbookDn'] = $addressBookDn;
        $this->addressbook[$addressBookId]['syncToken'] = $addressBookSyncToken;
        $this->addressbook[$addressBookId]['syncDbUserId'] =  ($addressBookConfig['user_specific'])?$currentUserPrincipalBackendId:$systemUser;
        $this->addressbook[$addressBookId]['contactMaxSize'] = ((isset($addressBookConfig['max_size']) && is_int($addressBookConfig['max_size']) && $addressBookConfig['max_size'] > 0)?$addressBookConfig['max_size']:self::$defaultContactMaxSize);
        
        $addressBooks[] = [
            'id'                                                          => $addressBookId,
            'uri'                                                         => $addressBookId,
            'principaluri'                                                => $principalUri,
            '{DAV:}displayname'                                           => isset($addressBookConfig['name']) ? $addressBookConfig['name'] : '',
            '{' . \Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description'  => isset($addressBookConfig['description']) ? $addressBookConfig['description'] : '',
            '{http://calendarserver.org/ns/}getctag' 											=> (!$addressBookSyncToken == null) ? $addressBookSyncToken : time(),
            '{http://sabredav.org/ns}sync-token'                          => (!$addressBookSyncToken == null) ? $addressBookSyncToken : 0
        ];
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
			throw new SabreDAVException\MethodNotAllowed("Operation not supported");
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
			throw new SabreDAVException\MethodNotAllowed("Operation not supported");
    }

    /**
     * Deletes an entire addressbook and all its contents
     *
     * @param mixed $addressBookId
     * @return void
     */
    function deleteAddressBook($addressBookId)
    {
			throw new SabreDAVException\MethodNotAllowed("Operation not supported");
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
				$addressBookConfig = $this->addressbook[$addressBookId]['config'];
				$syncDbUserId = $this->addressbook[$addressBookId]['syncDbUserId'];
        $addressBookSyncToken = $this->addressbook[$addressBookId]['syncToken'];
        $result = [];
        
        $data = $this->fullSyncOperation($addressBookId);   
        
        if(!empty($data))
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
				$syncDbUserId = $this->addressbook[$addressBookId]['syncDbUserId'];
        $cache = $this->cache;
        
				try 
				{
		      $query = 'SELECT card_uid, backend_id FROM ' . self::$backendMapTableName . ' WHERE user_id = ? AND addressbook_id = ? AND card_uri = ?';
		      $stmt = $this->pdo->prepare($query);
		      $stmt->execute([$syncDbUserId, $addressBookId, $cardUri]);
		      
					$row = $stmt->fetch(\PDO::FETCH_ASSOC);
					
		    	if($row === false)
						return false;
		    	
		      	$cardUID = $row['card_uid'];
		      	$backendId = $row['backend_id'];
		    } catch (\Throwable $th) {
		      	error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
						throw new SabreDAVException\ServiceUnavailable();
		    }
            
				$cacheInvalid = false; // If true cache need to be refreshed
       	$result = CacheMaster::decodeCard($cache->get(CacheMaster::cardKey($syncDbUserId, $addressBookId, $cardUri), null));
        	
        if(!isset($result['carddata']) || !isset($result['lastmodified']) || Reader::read($result['carddata'])->convert($this->defaultVcardVersion)->UID != $cardUID)
        {
        	$cacheInvalid = true;
					$cardModifiedTimestamp = null;
		      $cardData = null;
		      $data = $this->fetchLdapContactDataById($addressBookId, $backendId, ['*', 'modifyTimestamp']);
		      
		      if(empty($data))
						throw new SabreDAVException\ServiceUnavailable();
						
		      if(!$data['count'] > 0)
		      	return false;
		      	
		      if(!isset($data[0]['modifytimestamp'][0]))
		      {
						error_log("Read access to some operational attributes in LDAP not present. ".__METHOD__." at line no ".__LINE__);
						throw new SabreDAVException\ServiceUnavailable();
		      }
		      
					$cardModifiedTimestamp = strtotime($data[0]['modifytimestamp'][0]);
        	$cardData = $this->generateVcard($data[0], $addressBookId, $cardUID);
        	
					if(empty($cardData))
						throw new SabreDAVException\ServiceUnavailable();
						
					$result = [
            'carddata'      => $cardData,
            'lastmodified'  => $cardModifiedTimestamp,
            'etag'          => '"' . md5($cardData) . '"',
            'size'          => strlen($cardData)
					];
        }
        
        if(!isset($result['etag'])) {
	       	$cacheInvalid = true;
        	$result['etag'] = '"' . md5($result['carddata']) . '"';
        }
        	
        if(!isset($result['size'])) {
	       	$cacheInvalid = true;
        	$result['size'] = strlen($result['carddata']);
        }
					
				if($cacheInvalid) {
					if(!$cache->set(CacheMaster::cardKey($syncDbUserId, $addressBookId, $cardUri), CacheMaster::encodeCard($result))) {
				    error_log("Could not set cache data: " . __METHOD__ . " at line no " . __LINE__ . ", " . $th->getMessage());
						throw new SabreDAVException\ServiceUnavailable();
					}
				}
					
        $result['id'] = $cardUID;
        $result['uri'] = $cardUri;
				
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
    protected function createUpdateCard($addressBookId, $cardUri, $cardData, $operation = 'CREATE')
    {
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $syncDbUserId = $this->addressbook[$addressBookId]['syncDbUserId'];
        $writableAddressBook = (!isset($addressBookConfig['writable']))?true:$addressBookConfig['writable'];
        $maxContactSize = $this->addressbook[$addressBookId]['contactMaxSize'];
        $cache = $this->cache;
        
        if(!$writableAddressBook)
					throw new SabreDAVException\Forbidden("Not allowed");
					
				if(strlen($cardData) > $maxContactSize)
					throw new ISubsoftDAVException\ContentTooLarge();
					
				if(!$cache->delete(CacheMaster::cardKey($syncDbUserId, $addressBookId, $cardUri)))
				{
		      error_log("Could not delete cached data: " . __METHOD__ . " at line no " . __LINE__ . ", " . $th->getMessage());
					throw new SabreDAVException\ServiceUnavailable();
				}
					
				$vcard = Reader::read($cardData);
				
				foreach($vcard->validate() as $validationError)
					if($validationError['level'] >= 3)
						throw new SabreDAVException\BadRequest("Validation error for card property '" . ($validationError['node'])->name . "'. Make sure card version is mentioned in the card and all data in the card is formatted according to the version mentioned in the card.");
					
				$vcard = $vcard->convert($this->defaultVcardVersion);
	      $UID = (!isset($vcard->UID) || $vcard->UID == null || $vcard->UID == '')?null:$vcard->UID;
				
        if($operation == 'CREATE')
        {
		      $cardExists = false;
		      
		      try {
		          $query = 'SELECT 1 FROM ' . self::$backendMapTableName . ' WHERE user_id = ? AND addressbook_id = ? AND card_uid = ?';
		          $stmt = $this->pdo->prepare($query);
		          $stmt->execute([$syncDbUserId, $addressBookId, $UID]);
		          
		          if($stmt->fetch(\PDO::FETCH_ASSOC) !== false)
		          	$cardExists = true;
		      } catch (\Throwable $th) {
		          error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
							throw new SabreDAVException\ServiceUnavailable();
		      }
		      
		      if($cardExists)
						throw new SabreDAVException\BadRequest("Card with same identity exist");
        }
        
        if($operation == 'UPDATE')
        {
		      $cardIdMatch = true;
		      
		      try {
		          $query = 'SELECT 1 FROM ' . self::$backendMapTableName . ' WHERE user_id = ? AND addressbook_id = ? AND card_uri = ? AND card_uid <> ?';
		          $stmt = $this->pdo->prepare($query);
		          $stmt->execute([$syncDbUserId, $addressBookId, $cardUri, $UID]);
		          
		          if($stmt->fetch(\PDO::FETCH_ASSOC) !== false)
		          	$cardIdMatch = false;
		      } catch (\Throwable $th) {
		          error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
							throw new SabreDAVException\ServiceUnavailable();
		      }
		      
		      if(!$cardIdMatch)
						throw new SabreDAVException\BadRequest("Card identity does not match");
        }
        
        $isContactGroup = false;
        
        if(isset($vcard->KIND) && (strtolower((string)$vcard->KIND) === 'group'))
					$isContactGroup = true;
        
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        $requiredFields = [];
        $rdnField = null;
        $fieldAclEval = 'r';
        $fieldAclList = [];
        $readOnlyFields = [];
				$backendDataUpdatePolicy = (!isset($addressBookConfig['backend_data_update_policy']))?(self::$defaultBackendDataUpdatePolicy):$addressBookConfig['backend_data_update_policy'];
        $fieldMap = [];
        $ldapInfo = [];
        
        if($isContactGroup)
        {
          $ldapInfo['objectclass'] = (!isset($addressBookConfig['group_LDAP_Object_Classes']) || !is_array($addressBookConfig['group_LDAP_Object_Classes']))?[]:$addressBookConfig['group_LDAP_Object_Classes'];
          $fieldMap = (!isset($addressBookConfig['group_fieldmap']) || !is_array($addressBookConfig['group_fieldmap']))?[]:$addressBookConfig['group_fieldmap'];
          
          foreach((!isset($addressBookConfig['group_required_fields']) || !is_array($addressBookConfig['group_required_fields']))?[]:$addressBookConfig['group_required_fields'] as $field)
          {
						$requiredFields[] = strtolower($field);
          }
          
          $rdnField = (!isset($addressBookConfig['group_LDAP_rdn']))?null:strtolower($addressBookConfig['group_LDAP_rdn']);
          $fieldAclEval = (!isset($addressBookConfig['group_field_acl']['eval']))?(self::$defaultFieldAclEval):strtolower($addressBookConfig['group_field_acl']['eval']);
          
          foreach((!isset($addressBookConfig['group_field_acl']['list']) || !is_array($addressBookConfig['group_field_acl']['list']))?[]:$addressBookConfig['group_field_acl']['list'] as $field)
          {
						$fieldAclList[] = strtolower($field);
          }
        }
        else
        {
          $ldapInfo['objectclass'] = (!isset($addressBookConfig['LDAP_Object_Classes']) || !is_array($addressBookConfig['LDAP_Object_Classes']))?[]:$addressBookConfig['LDAP_Object_Classes'];
          $fieldMap = (!isset($addressBookConfig['fieldmap']) || !is_array($addressBookConfig['fieldmap']))?[]:$addressBookConfig['fieldmap'];
          
          foreach((!isset($addressBookConfig['required_fields']) || !is_array($addressBookConfig['required_fields']))?[]:$addressBookConfig['required_fields'] as $field)
          {
						$requiredFields[] = strtolower($field);
          }
          
          $rdnField = (!isset($addressBookConfig['LDAP_rdn']))?null:strtolower($addressBookConfig['LDAP_rdn']);
          $fieldAclEval = (!isset($addressBookConfig['field_acl']['eval']))?(self::$defaultFieldAclEval):strtolower($addressBookConfig['field_acl']['eval']);
          
          foreach((!isset($addressBookConfig['field_acl']['list']) || !is_array($addressBookConfig['field_acl']['list']))?[]:$addressBookConfig['field_acl']['list'] as $field)
          {
						$fieldAclList[] = strtolower($field);
          }
        }
        
				if($operation == 'CREATE' || ($operation == 'UPDATE' && $backendDataUpdatePolicy == 'replace'))
				{
		      if($rdnField == null || $ldapInfo['objectclass'] == [])
						throw new SabreDAVException\ServiceUnavailable();
				}
        
        if($isContactGroup)
        {
            foreach($addressBookConfig['group_member_map'] as $vCardKey => $ldapKey) 
            {
                $multiAllowedStatus = Reader::multiAllowedStatus($vCardKey);
                $compositeAttrStatus = Reader::compositeAttrStatus($vCardKey);

                if(isset($vcard->$vCardKey) && $multiAllowedStatus['status'] && !$compositeAttrStatus['status'] )
                {
                    $newLdapKey = strtolower($ldapKey['field_name']);
                    foreach($vcard->$vCardKey as $values)
                    {                 
                        $memberCardUID = Reader::memberValue($values, $vCardKey);
                        if($memberCardUID != '')
                        {
                            $backendId = null;

                            try {
                                $query = 'SELECT backend_id FROM ' . self::$backendMapTableName . ' WHERE addressbook_id = ? and card_uid = ? and user_id = ?';
                                $stmt = $this->pdo->prepare($query);
                                $stmt->execute([$addressBookId, $memberCardUID, $syncDbUserId]);
                                
                                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                    $backendId = $row['backend_id'];
                                }
                            } catch (\Throwable $th) {
                                error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
                            }
                            
                            if(isset($backendId) && $backendId != null)
                            {
                                $filter = '(&'.$addressBookConfig['filter']. '(entryuuid=' . ldap_escape($backendId, "", LDAP_ESCAPE_FILTER) . '))'; 
                        
                                $data = Utility::LdapQuery($ldapConn, $addressBookDn, $filter, ['dn'], strtolower($addressBookConfig['scope']));
                                
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

        unset($vcard);
        
				if(strlen(serialize($ldapInfo)) > $maxContactSize)
					throw new ISubsoftDAVException\ContentTooLarge();
					
				$mappedBackendAttributes = Utility::getMappedBackendAttributes($fieldMap);
				
				if($fieldAclEval == 'w')
				{
					foreach($mappedBackendAttributes as $field)
					{
						if(!in_array($field, $fieldAclList))
							$readOnlyFields[] = $field;
					}
				}
				else
					$readOnlyFields = $fieldAclList;
					
				if($operation == 'UPDATE')
				{
					$newLdapRdn = null;
					
					$oldLdapInfo = $this->fetchLdapContactDataByUri($addressBookId, $cardUri, ['*'], 1);
					
					if(empty($oldLdapInfo))
						throw new SabreDAVException\Conflict();
						
					if($fieldAclEval == 'w')
					{
						for($i=0; $i<$oldLdapInfo[0]['count']; $i++)
						{
							$field = $oldLdapInfo[0][$i];
							
							if(!in_array($field, $fieldAclList) && !in_array($field, $readOnlyFields))
								$readOnlyFields[] = $field;
						}
					}
					
					if($backendDataUpdatePolicy == 'replace')
					{
						foreach($oldLdapInfo[0] as $oldLdapAttrName => $oldLdapAttrValue)
						{
							if(!isset($ldapInfo[$oldLdapAttrName]))
							{
								if(is_array($oldLdapAttrValue))
									$ldapInfo[$oldLdapAttrName] = [];
							}
						}
					}
					else
					{
						foreach($mappedBackendAttributes as $attr)
						{
							if(!isset($ldapInfo[$attr]))
								$ldapInfo[$attr] = [];
						}
					}
					
					if($backendDataUpdatePolicy == 'replace')
					{
						foreach($readOnlyFields as $key)
						{
							if(array_key_exists($key, $ldapInfo))
								unset($ldapInfo[$key]);
						}
					
					  foreach ($requiredFields as $key) {
					      if(!array_key_exists($key, $ldapInfo))
									throw new SabreDAVException\BadRequest("Required fields not present or do not have write access");
					  }

					  if(!array_key_exists($rdnField, $ldapInfo))
							throw new SabreDAVException\BadRequest("Identity field not present or do not have write access");

						$newLdapRdn = $rdnField . '=' . ldap_escape(is_array($ldapInfo[$rdnField])?$ldapInfo[$rdnField][0]:$ldapInfo[$rdnField], "", LDAP_ESCAPE_DN);
					}
					else
					{
						foreach($readOnlyFields as $key)
						{
							if(array_key_exists($key, $ldapInfo))
								unset($ldapInfo[$key]);
						}
						
				    foreach ($requiredFields as $key) {
				      if(!in_array($key, $readOnlyFields) && !array_key_exists($key, $ldapInfo))
								throw new SabreDAVException\BadRequest("Required fields not present or do not have write access");
				    }
				    
				    	if(array_key_exists($rdnField, $ldapInfo))
								$newLdapRdn = $rdnField . '=' . ldap_escape(is_array($ldapInfo[$rdnField])?$ldapInfo[$rdnField][0]:$ldapInfo[$rdnField], "", LDAP_ESCAPE_DN);
					}

					$oldLdapTree = $oldLdapInfo[0]['dn'];
					$componentOldLdapTree = ldap_explode_dn($oldLdapTree, 0);

					if(!$componentOldLdapTree)
					{
						error_log("Unknown error in " . __METHOD__ . " at line " . __LINE__);
						throw new SabreDAVException\ServiceUnavailable();
					}
					
					$oldLdapRdn = $componentOldLdapTree[0];
					$parentOldLdapTree = "";

					for($dnComponentIndex=1; $dnComponentIndex<$componentOldLdapTree['count']; $dnComponentIndex++)
						$parentOldLdapTree = $parentOldLdapTree . (empty($parentOldLdapTree)?"":",") . $componentOldLdapTree[$dnComponentIndex];
					
					$ldapTree = $oldLdapTree;
					
					if($newLdapRdn == null)
						$newLdapRdn = $oldLdapRdn;

					if($newLdapRdn != $oldLdapRdn)
					{
						if(!ldap_rename($ldapConn, $oldLdapTree, $newLdapRdn, null, false))
							throw new SabreDAVException\BadRequest("Card with same name may already exist");
							
						$ldapTree = $newLdapRdn . ',' . $parentOldLdapTree;
					}

					if(!ldap_mod_replace($ldapConn, $ldapTree, $ldapInfo))
						throw new SabreDAVException\BadRequest("Card data may be incompatible");
				}
				else
				{
					foreach($readOnlyFields as $key)
					{
						if(array_key_exists($key, $ldapInfo))
							unset($ldapInfo[$key]);
					}
				
			    foreach ($requiredFields as $key) {
			        if(!array_key_exists($key, $ldapInfo))
								throw new SabreDAVException\BadRequest("Required fields not present or do not have write access");
			    }

			    if(!array_key_exists($rdnField, $ldapInfo))
						throw new SabreDAVException\BadRequest("Identity field not present or do not have write access");
						
		      $ldapTree = $rdnField. '='. ldap_escape(is_array($ldapInfo[$rdnField])?$ldapInfo[$rdnField][0]:$ldapInfo[$rdnField], "", LDAP_ESCAPE_DN) . ',' .$addressBookDn;

		      if(!ldap_add($ldapConn, $ldapTree, $ldapInfo))
						throw new SabreDAVException\BadRequest("Card with same name may already exist");

		      $data = Utility::LdapQuery($ldapConn, $ldapTree, $addressBookConfig['filter'], ['entryuuid'], 'base');
		      
		      if(!empty($data) && $data['count'] > 0)
		      {
				    try {
				        $query = "INSERT INTO `" . self::$backendMapTableName . "` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
				        $sql = $this->pdo->prepare($query);
				        $sql->execute([$cardUri, ($UID == null)?$this->guidv4():$UID, $addressBookId, $data[0]['entryuuid'][0], $syncDbUserId]);
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
        $syncDbUserId = $this->addressbook[$addressBookId]['syncDbUserId'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        $writableAddressBook = (!isset($addressBookConfig['writable']))?true:$addressBookConfig['writable'];
        $cache = $this->cache;
        
        if(!$writableAddressBook)
        	return false;
        	
				if(!$cache->delete(CacheMaster::cardKey($syncDbUserId, $addressBookId, $cardUri)))
				{
		      error_log("Could not delete cached data: " . __METHOD__ . " at line no " . __LINE__ . ", " . $th->getMessage());
					throw new SabreDAVException\ServiceUnavailable();
				}
        
        $data = $this->fetchLdapContactDataByUri($addressBookId, $cardUri, ['dn', 'entryUUID']);
        
        if(empty($data))
        	return false;
        
        $ldapTree = $data[0]['dn'];

        try {
            $ldapDelete = ldap_delete($ldapConn, $ldapTree);
            
            if(!$ldapDelete)
	            return false;
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
    protected function generateVcard($data, $addressBookId, $cardUID)
    { 
        if (empty ($data) || empty($addressBookId) || empty($cardUID))
            return null;
        
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $syncDbUserId = $this->addressbook[$addressBookId]['syncDbUserId'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        $fieldMap = $addressBookConfig['fieldmap'];
        
        // build the Vcard
        $vcard = (new \Sabre\VObject\Component\VCard(['UID' => $cardUID]))->convert($this->defaultVcardVersion);
        
        $isContactGroup = false;
        $contactGroupMemberFieldName = $addressBookConfig['group_member_map']['MEMBER']['field_name'];
        
        if(isset($data[$contactGroupMemberFieldName]) && is_array($data[$contactGroupMemberFieldName]))
					$isContactGroup = true;

        if($isContactGroup)
        {
            $vcard->add('KIND', 'group');
            $fieldMap = $addressBookConfig['group_fieldmap'];      

            foreach ($addressBookConfig['group_member_map'] as $vCardKey => $ldapKey) 
            {
                $multiAllowedStatus = Reader::multiAllowedStatus($vCardKey);
                $compositeAttrStatus = Reader::compositeAttrStatus($vCardKey);

                if($multiAllowedStatus['status'] && !$compositeAttrStatus['status'] )
                {
                    $newLdapKey = strtolower($ldapKey['field_name']);
                    if(isset($data[$newLdapKey]))
                    {
                        foreach($data[$newLdapKey] as $key => $value)
                        {
                            if($key === 'count')
                            continue;

                            $memberData = Utility::LdapQuery($ldapConn, $value, $addressBookConfig['filter'], ['entryuuid'], 'base');
                     
                            if(! empty($memberData) && $memberData['count'] > 0)
                            { 
                                $memberCardUID = null;

                                try {
                                    $query = 'SELECT card_uid FROM ' . self::$backendMapTableName . ' WHERE addressbook_id = ? and backend_id = ? and user_id = ?';
                                    $stmt = $this->pdo->prepare($query);
                                    $stmt->execute([$addressBookId, $memberData[0]['entryuuid'][0], $syncDbUserId]);
                                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                        $memberCardUID = $row['card_uid'];
                                    }
                                } catch (\Throwable $th) {
                                    error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
                                }
                                
                                $memberValue = Reader::memberValueConversion($memberCardUID, $vCardKey);
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
                $newLdapKey = strtolower($ldapKey['field_name']);
                if(isset($data[$newLdapKey]))
                {
                    foreach($data[$newLdapKey] as $key => $value)
                    {
                        if($key === 'count')
                        continue;

                        $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKey['parameters']) ? $ldapKey['parameters'] : []), (isset($ldapKey['reverse_map_parameter_index']) ? $ldapKey['reverse_map_parameter_index'] : 0));
                        $ldapValueConversionInfo = Reader::backendValueConversion($vCardKey, $value, (!isset($ldapKey['field_data_format']))?'text':$ldapKey['field_data_format']);
                        $vCardParams = array_merge($vCardParams, $ldapValueConversionInfo['params']);

                        !empty($vCardParams) ? $vcard->add($vCardKey, $ldapValueConversionInfo['cardData'], $vCardParams) : $vcard->add($vCardKey, $ldapValueConversionInfo['cardData']);
                    }
                }
            }
            else if($compositeAttrStatus['status'] && !$iterativeArr)  
            {
                if(!is_array($ldapKey['field_name']) && isset($ldapKey['map_component_separator']))
                {
                    $newLdapKey = strtolower($ldapKey['field_name']);
                    if(isset($data[$newLdapKey]))
                    {
                        if($multiAllowedStatus['status'])
                        {
                            foreach($data[$newLdapKey] as $key => $attrValue)
                            {
                                if($key === 'count')
                                continue;

                                $ldapValueArr = explode($ldapKey['map_component_separator'], $attrValue);
                                $ldapCompositeValueConversion = Utility::getCompositebackendValueConversion($ldapValueArr, $vCardKey, $ldapKey);
                                $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKey['parameters']) ? $ldapKey['parameters'] : []), (isset($ldapKey['reverse_map_parameter_index']) ? $ldapKey['reverse_map_parameter_index'] : 0));

                                $vCardParams = !empty($ldapCompositeValueConversion['params']) ? array_merge($vCardParams, $ldapCompositeValueConversion['params']) : $vCardParams;
                                
                                !empty($vCardParams) ? $vcard->add($vCardKey, $ldapCompositeValueConversion['ldapValueArray'], $vCardParams) : $vcard->add($vCardKey, $ldapCompositeValueConversion['ldapValueArray']);
                            }
                        }
                        else
                        {
                            $ldapValueArr = explode($ldapKey['map_component_separator'], $data[$newLdapKey][0]);
                            $ldapCompositeValueConversion = Utility::getCompositebackendValueConversion($ldapValueArr, $vCardKey, $ldapKey);
                            $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKey['parameters']) ? $ldapKey['parameters'] : []), (isset($ldapKey['reverse_map_parameter_index']) ? $ldapKey['reverse_map_parameter_index'] : 0));

                            $vCardParams = !empty($ldapCompositeValueConversion['params']) ? array_merge($vCardParams, $ldapCompositeValueConversion['params']) : $vCardParams; 
                            
                            !empty($vCardParams) ? $vcard->add($vCardKey, $ldapCompositeValueConversion['ldapValueArray'], $vCardParams) : $vcard->add($vCardKey, $ldapCompositeValueConversion['ldapValueArray']);
                        }
                    }
                }
                else
                {
                    $isLdapKeyExists = false;
                    $count = 0;
    
                    foreach($ldapKey['field_name'] as $backendAttr)
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
                                $backendAttrParams = [];
                                $elementArr = [];

                                foreach($compositeAttrStatus['status'] as $propValue)
                                {
                                    if(isset($ldapKey['field_name'][$propValue]))
                                    {
                                        $newLdapKey = strtolower($ldapKey['field_name'][$propValue]);
                                        if(isset($data[$newLdapKey]) && isset($data[$newLdapKey][$i]) && $data[$newLdapKey][$i] != '')
                                        {
                                            $ldapValueConversionInfo = Reader::backendValueConversion($vCardKey, $data[$newLdapKey][$i], (!isset($ldapKey['field_data_format']))?'text':$ldapKey['field_data_format']);
                                            $backendAttrParams = $ldapValueConversionInfo['params'];
                                            $elementArr[] = $ldapValueConversionInfo['cardData'];
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
    
                                $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKey['parameters']) ? $ldapKey['parameters'] : []), (isset($ldapKey['reverse_map_parameter_index']) ? $ldapKey['reverse_map_parameter_index'] : 0));
                                $vCardParams = !empty($backendAttrParams) ? array_merge($vCardParams, $backendAttrParams) : $vCardParams;
                                !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
                            }
                        }
                        else
                        {
                            $backendAttrParams = [];
                            $elementArr = [];

                            foreach($compositeAttrStatus['status'] as $propValue)
                            {
                                if(isset($ldapKey['field_name'][$propValue]))
                                {
                                    $newLdapKey = strtolower($ldapKey['field_name'][$propValue]);
                                    if(isset($data[$newLdapKey]))
                                    {
                                        $ldapValueConversionInfo = Reader::backendValueConversion($vCardKey, $data[$newLdapKey][0], (!isset($ldapKey['field_data_format']))?'text':$ldapKey['field_data_format']);
                                        $backendAttrParams = $ldapValueConversionInfo['params'];
                                        $elementArr[] = $ldapValueConversionInfo['cardData'];
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
    
                            $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKey['parameters']) ? $ldapKey['parameters'] : []), (isset($ldapKey['reverse_map_parameter_index']) ? $ldapKey['reverse_map_parameter_index'] : 0));
                            $vCardParams = !empty($backendAttrParams) ? array_merge($vCardParams, $backendAttrParams) : $vCardParams;
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
                        if(!is_array($ldapKeyInfo['field_name']) && isset($ldapKeyInfo['map_component_separator']))
                        {
                            $newLdapKey = strtolower($ldapKeyInfo['field_name']);

                            if(isset($data[$newLdapKey]))
                            {
                                if($multiAllowedStatus['status'])
                                {
                                    foreach($data[$newLdapKey] as $key => $attrValue)
                                    {
                                        if($key === 'count')
                                        continue;

                                        $ldapValueArr = explode($ldapKeyInfo['map_component_separator'], $attrValue);
                                        $ldapCompositeValueConversion = Utility::getCompositebackendValueConversion($ldapValueArr, $vCardKey, $ldapKeyInfo);
                                        $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKeyInfo['parameters']) ? $ldapKeyInfo['parameters'] : []), (isset($ldapKeyInfo['reverse_map_parameter_index']) ? $ldapKeyInfo['reverse_map_parameter_index'] : 0));
        
                                        $vCardParams = !empty($ldapCompositeValueConversion['params']) ? array_merge($vCardParams, $ldapCompositeValueConversion['params']) : $vCardParams;
                                        
                                        !empty($vCardParams) ? $vcard->add($vCardKey, $ldapCompositeValueConversion['ldapValueArray'], $vCardParams) : $vcard->add($vCardKey, $ldapCompositeValueConversion['ldapValueArray']);
                                    }
                                }
                                else
                                {

                                    $ldapValueArr = explode($ldapKeyInfo['map_component_separator'], $data[$newLdapKey][0]);
                                    $ldapCompositeValueConversion = Utility::getCompositebackendValueConversion($ldapValueArr, $vCardKey, $ldapKeyInfo);
                                    $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKeyInfo['parameters']) ? $ldapKeyInfo['parameters'] : []), (isset($ldapKeyInfo['reverse_map_parameter_index']) ? $ldapKeyInfo['reverse_map_parameter_index'] : 0));
    
                                    $vCardParams = !empty($ldapCompositeValueConversion['params']) ? array_merge($vCardParams, $ldapCompositeValueConversion['params']) : $vCardParams;
                                    
                                    !empty($vCardParams) ? $vcard->add($vCardKey, $ldapCompositeValueConversion['ldapValueArray'], $vCardParams) : $vcard->add($vCardKey, $ldapCompositeValueConversion['ldapValueArray']);
                                }
                            }
                        }
                        else
                        {
                            $isLdapKeyExists = false;
                        
                            $count = 0;
    
                            foreach($ldapKeyInfo['field_name'] as $backendAttr)
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
                                        $backendAttrParams = [];
                                        $elementArr = [];
    
                                        foreach($compositeAttrStatus['status'] as $propValue)
                                        {
                                            if(isset($ldapKeyInfo['field_name'][$propValue]))
                                            {
                                                $newLdapKey = strtolower($ldapKeyInfo['field_name'][$propValue]);
                                                if(isset($data[$newLdapKey]) && isset($data[$newLdapKey][$i]))
                                                {
                                                    $ldapValueConversionInfo = Reader::backendValueConversion($vCardKey, $data[$newLdapKey][$i], (!isset($ldapKeyInfo['field_data_format']))?'text':$ldapKeyInfo['field_data_format']);
                                                    $backendAttrParams = $ldapValueConversionInfo['params'];
                                                    $elementArr[] = $ldapValueConversionInfo['cardData'];
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

                                        $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKeyInfo['parameters']) ? $ldapKeyInfo['parameters'] : []), (isset($ldapKeyInfo['reverse_map_parameter_index']) ? $ldapKeyInfo['reverse_map_parameter_index'] : 0));
                                        $vCardParams = !empty($backendAttrParams) ? array_merge($vCardParams, $backendAttrParams) : $vCardParams;
                                        !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
                                    }
                                }
                                else
                                {
                                    $backendAttrParams = [];
                                    $elementArr = [];
                                    
                                    foreach($compositeAttrStatus['status'] as $propValue)
                                    {
                                        if(isset($ldapKeyInfo['field_name'][$propValue]))
                                        {
                                            $newLdapKey = strtolower($ldapKeyInfo['field_name'][$propValue]);
                                            if(isset($data[$newLdapKey]))
                                            {
                                                $ldapValueConversionInfo = Reader::backendValueConversion($vCardKey, $data[$newLdapKey][0], (!isset($ldapKeyInfo['field_data_format']))?'text':$ldapKeyInfo['field_data_format']);
                                                $backendAttrParams = $ldapValueConversionInfo['params'];
                                                $elementArr[] = $ldapValueConversionInfo['cardData'];
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

                                    $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKeyInfo['parameters']) ? $ldapKeyInfo['parameters'] : []), (isset($ldapKeyInfo['reverse_map_parameter_index']) ? $ldapKeyInfo['reverse_map_parameter_index'] : 0));
                                    $vCardParams = !empty($backendAttrParams) ? array_merge($vCardParams, $backendAttrParams) : $vCardParams;
                                    !empty($vCardParams) ? $vcard->add($vCardKey, $elementArr, $vCardParams) : $vcard->add($vCardKey, $elementArr);
                                }                                               
                            }
                        }                 
                    }
                    else
                    {
                        $newLdapKey = strtolower($ldapKeyInfo['field_name']);
                        
                        if(isset($data[$newLdapKey]))
                        {                        
                            if($multiAllowedStatus['status'])
                            {
                                foreach($data[$newLdapKey] as $key => $attrValue)
                                {
                                    if($key === 'count')
                                    continue;
                                    
                                    $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKeyInfo['parameters']) ? $ldapKeyInfo['parameters'] : []), (isset($ldapKeyInfo['reverse_map_parameter_index']) ? $ldapKeyInfo['reverse_map_parameter_index'] : 0));
                                    $valueInfo = Reader::backendValueConversion($vCardKey, $attrValue, (!isset($ldapKeyInfo['field_data_format']))?'text':$ldapKeyInfo['field_data_format']);
                                    $vCardParams = array_merge($vCardParams, $valueInfo['params']);

                                    !empty($vCardParams) ? $vcard->add($vCardKey, $valueInfo['cardData'], $vCardParams) : $vcard->add($vCardKey, $valueInfo['cardData']);
                                }
                            }
                            else
                            {
                                $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKeyInfo['parameters']) ? $ldapKeyInfo['parameters'] : []), (isset($ldapKeyInfo['reverse_map_parameter_index']) ? $ldapKeyInfo['reverse_map_parameter_index'] : 0));
                                $valueInfo = Reader::backendValueConversion($vCardKey, $data[$newLdapKey][0], (!isset($ldapKeyInfo['field_data_format']))?'text':$ldapKeyInfo['field_data_format']);
                                $vCardParams = array_merge($vCardParams, $valueInfo['params']);

                                !empty($vCardParams) ? $vcard->add($vCardKey, $valueInfo['cardData'], $vCardParams) : $vcard->add($vCardKey, $valueInfo['cardData']);
                            }
                        }
                    }           
                }    
            }
            else
            {
                $newLdapKey = strtolower($ldapKey['field_name']);
            
                if(isset($data[$newLdapKey]))
                {    
                    $vCardParams = Utility::getMappedVCardAttrParams((isset($ldapKey['parameters']) ? $ldapKey['parameters'] : []), (isset($ldapKey['reverse_map_parameter_index']) ? $ldapKey['reverse_map_parameter_index'] : 0));
                    $valueInfo = Reader::backendValueConversion($vCardKey, $data[$newLdapKey][0], (!isset($ldapKey['field_data_format']))?'text':$ldapKey['field_data_format']);
                    $vCardParams = array_merge($vCardParams, $valueInfo['params']);

                    !empty($vCardParams) ? $vcard->add($vCardKey, $valueInfo['cardData'], $vCardParams) : $vcard->add($vCardKey, $valueInfo['cardData']);                                       
                }
            }
        }
       
			// convert to default frontend version and send the vcard
			return $vcard->convert($this->defaultFrontendVcardVersion)->serialize();
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
			$syncDbUserId = $this->addressbook[$addressBookId]['syncDbUserId'];
			$ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
      $cache = $this->cache;

			$result = [
					'syncToken' => $addressBookSyncToken,
					'added'     => [],
					'modified'  => [],
					'deleted'   => [],
			];
			
			$forceInitialSyncInterval = (isset($addressBookConfig['force_full_sync_interval']) && is_int($addressBookConfig['force_full_sync_interval']) && $addressBookConfig['force_full_sync_interval'] > 0)?$addressBookConfig['force_full_sync_interval']:self::$defaultForceFullSyncInterval;
			
			$fullSyncToken = null;
			
			try {
					$query = 'SELECT sync_token FROM ' . self::$fullSyncTableName . ' WHERE addressbook_id = ? AND user_id = ?';
					$stmt = $this->pdo->prepare($query);
					$stmt->execute([$addressBookId, $syncDbUserId]);
					
					$row = $stmt->fetch(\PDO::FETCH_ASSOC);
					
					if($row !== false)
						$fullSyncToken = (int)$row['sync_token'];
					else
					{
						$query = "INSERT INTO `" . self::$fullSyncTableName . "` (`user_id`, `addressbook_id`, `sync_token`) VALUES (?, ?, ?)"; 
						$stmt = $this->pdo->prepare($query);
						$stmt->execute([$syncDbUserId, $addressBookId, $addressBookSyncToken]);
					}

			} catch (\Throwable $th) {
					error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
			}
			
			if($fullSyncToken != null && $addressBookSyncToken > ($fullSyncToken + $forceInitialSyncInterval))
			{
		    try {
					$query = "UPDATE `" . self::$fullSyncTableName . "` SET sync_token = ? WHERE user_id = ? AND addressbook_id = ?"; 
					$stmt = $this->pdo->prepare($query);
					$stmt->execute([$addressBookSyncToken, $syncDbUserId, $addressBookId]);
					
					$fullSyncToken = $addressBookSyncToken;
					
					$query = "DELETE FROM `" . self::$deletedCardsTableName . "` WHERE user_id = ? AND addressbook_id = ? AND sync_token < ?"; 
					$stmt = $this->pdo->prepare($query);
					$stmt->execute([$syncDbUserId, $addressBookId, $fullSyncToken]);
				} catch (\Throwable $th) {
						error_log("Database query could not be executed: " . __METHOD__ . " at line no " . __LINE__ . ", " . $th->getMessage());
				}
			}

			// Perform initial sync
			if($syncToken == null)
			{
				$data = $this->fullSyncOperation($addressBookId);
				
				if(!empty($data))
					for ($i=0; $i < count($data); $i++) {
							$result['added'][] = $data[$i]['card_uri'];
					}
				
				return $result;
			}
			
			$userAgent = $_SERVER['HTTP_USER_AGENT'];
			$uaValues = ['id' => '', 'initial_sync_response_code' => null];

			foreach(self::$uaIdentifier as $id => $properties)
			{
				$matches = [];
				
				if(preg_match($properties['ua_regexp'], $userAgent, $matches) === 1)
				{
					$uaValues['id'] = $id;
					
					foreach($properties['capture_group'] as $captureGroupIndex => $captureGroupName)
						if(isset($matches[$captureGroupIndex]))
							$uaValues['properties'][$captureGroupName] = $matches[$captureGroupIndex];
							
					break;
				}
			}

			if($uaValues['id'] == 'moz_tb')
				$uaValues['initial_sync_response_code'] = 400;
			
			// Invalid sync token
			if($syncToken != null && (!settype($syncToken, 'integer') || $syncToken >= $addressBookSyncToken))
			{
				if($uaValues['initial_sync_response_code'] != null)
					throw Utility::responseCodeException($uaValues['initial_sync_response_code'], 'Sync token is invalid (response workaround applied for user agent id - ' . $uaValues['id'] . ')');
				
				return null;
			}
			
			// Sync token expiry
			if((settype($syncToken, 'integer') && ($addressBookSyncToken - $syncToken) > $forceInitialSyncInterval) || (settype($syncToken, 'integer') && $fullSyncToken != null && $syncToken < $fullSyncToken))
			{
				if($uaValues['initial_sync_response_code'] != null)
					throw Utility::responseCodeException($uaValues['initial_sync_response_code'], 'Sync token has expired (response workaround applied for user agent id - ' . $uaValues['id'] . ')');
					
				return null;
			}
				
			if(isset($addressBookConfig['sync_bind_dn']) && $addressBookConfig['sync_bind_dn'] != '')
			{
				$syncBindDn = $addressBookConfig['sync_bind_dn'];
				$syncBindPass = (!isset($addressBookConfig['sync_bind_pw']))?null:$addressBookConfig['sync_bind_pw'];
				$ldapConn = Utility::LdapBindConnection(['bindDn' => $syncBindDn, 'bindPass' => $syncBindPass], $this->config['server']['ldap']);
			}

			if($ldapConn === false)
      	throw new SabreDAVException\ServiceUnavailable();
			
			$filter = '(&' . $addressBookConfig['filter'] . '(createtimestamp>=' . gmdate('YmdHis', $syncToken) . 'Z)(!(createtimestamp>=' . gmdate('YmdHis', $addressBookSyncToken) . 'Z)))';
			$data = Utility::LdapIterativeQuery($ldapConn, $addressBookDn, $filter, ['entryuuid'], strtolower($addressBookConfig['scope']));

			if($data === false)
      	throw new SabreDAVException\ServiceUnavailable();

			while($data['entryIns'])
			{
				if(!isset($data['data']['entryUUID'][0]))
				{
					error_log("Read access to required operational attributes in LDAP not present. Cannot continue. Quitting. ".__METHOD__." at line no ".__LINE__);
					throw new SabreDAVException\ServiceUnavailable();
				}
				
				$cardUri = null;
				
				try {
						$query = 'SELECT card_uri FROM ' . self::$backendMapTableName . ' WHERE addressbook_id = ? and backend_id = ? and user_id = ?';
						$stmt = $this->pdo->prepare($query);
						$stmt->execute([$addressBookId, $data['data']['entryUUID'][0], $syncDbUserId]);
						
						while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
								$cardUri = $row['card_uri'];
						}

						if($cardUri == null)
						{
								$cardUID = $this->guidv4();
								$cardUri = $cardUID .'.vcf';
								
								$query = "INSERT INTO `" . self::$backendMapTableName . "` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
								$sql = $this->pdo->prepare($query);
								$sql->execute([$cardUri, $cardUID, $addressBookId, $data['data']['entryUUID'][0], $syncDbUserId]); 
						}
				} catch (\Throwable $th) {
						error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
						throw new SabreDAVException\ServiceUnavailable();
				}
				
				$result['added'][] = $cardUri;
				$data = Utility::LdapIterativeQuery($ldapConn, $data['entryIns']);
			}
				
			$filter = '(&' . $addressBookConfig['filter'] . '(!(createtimestamp>=' . gmdate('YmdHis', $syncToken) . 'Z))(modifytimestamp>=' . gmdate('YmdHis', $syncToken) . 'Z)(!(modifytimestamp>=' . gmdate('YmdHis', $addressBookSyncToken) . 'Z)))';
			$data = Utility::LdapIterativeQuery($ldapConn, $addressBookDn, $filter, ['entryuuid'], strtolower($addressBookConfig['scope']));

			if($data === false)
				throw new SabreDAVException\ServiceUnavailable();
				
			while($data['entryIns'])
			{
				if(!isset($data['data']['entryUUID'][0]))
				{
					error_log("Read access to required operational attributes in LDAP not present. Cannot continue. Quitting. ".__METHOD__." at line no ".__LINE__);
					throw new SabreDAVException\ServiceUnavailable();
				}
				
				$cardUri = null;
				
				try {
						$query = 'SELECT card_uri FROM ' . self::$backendMapTableName . ' WHERE addressbook_id = ? and backend_id = ? and user_id = ?';
						$stmt = $this->pdo->prepare($query);
						$stmt->execute([$addressBookId, $data['data']['entryUUID'][0], $syncDbUserId]);
						
						while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
								$cardUri = $row['card_uri'];
						}
						
						if($cardUri == null)
						{
								$cardUID = $this->guidv4();
								$cardUri = $cardUID .'.vcf';

								$query = "INSERT INTO `" . self::$backendMapTableName . "` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
								$sql = $this->pdo->prepare($query);
								$sql->execute([$cardUri, $cardUID, $addressBookId, $data['data']['entryUUID'][0], $syncDbUserId]);
								
								$result['added'][] = $cardUri;
								
								continue;
						}
						
						if(!$cache->delete(CacheMaster::cardKey($syncDbUserId, $addressBookId, $cardUri)))
						{
						  error_log("Could not delete cached data: " . __METHOD__ . " at line no " . __LINE__ . ", " . $th->getMessage());
							throw new SabreDAVException\ServiceUnavailable();
						}

						$result['modified'][] = $cardUri;
				} catch (\Throwable $th) {
						error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
						throw new SabreDAVException\ServiceUnavailable();
				}
					
				$data = Utility::LdapIterativeQuery($ldapConn, $data['entryIns']);
			}

			//DELETED CARDS
			$cardUri = null;
			
			try {
				// Fetch contacts from deleted table
				$query = 'SELECT card_uri FROM ' . self::$deletedCardsTableName . ' WHERE user_id = ? AND addressbook_id = ? AND sync_token >= ? AND sync_token < ?';
				$stmt = $this->pdo->prepare($query);
				$stmt->execute([$syncDbUserId, $addressBookId, $syncToken, $addressBookSyncToken]);
					
				while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
				{
					$cardUri = $row['card_uri'];
					$result['deleted'][] = $cardUri;
				}
			} catch (\Throwable $th) {
					error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
					throw new SabreDAVException\ServiceUnavailable();
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
        $syncDbUserId = $this->addressbook[$addressBookId]['syncDbUserId'];
            
        if($operation == 'DELETE')
        {
		      try {
		          $this->pdo->beginTransaction();

		          $query = "DELETE FROM `" . self::$backendMapTableName . "` WHERE addressbook_id = ? AND card_uri = ? AND user_id = ?"; 
		          $sql = $this->pdo->prepare($query);
		          $sql->execute([$addressBookId, $objectUri, $syncDbUserId]);


		          $query = "INSERT INTO `" . self::$deletedCardsTableName . "` (`sync_token` ,`addressbook_id` ,`card_uri`, `user_id`) VALUES (?, ?, ?, ?)"; 
		          $sql = $this->pdo->prepare($query);
		          $sql->execute([time(), $addressBookId, $objectUri, $syncDbUserId]);

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
     * @param string  $backendId
     * @param array 	$attributes
     * @param int 		$attributesOnly
     * @return array
     */
    function fetchLdapContactDataById($addressBookId, $backendId, $attributes = [], int $attributesOnly = 0)
    {
        $addressBookConfig = $this->addressbook[$addressBookId]['config'];
        $addressBookDn = $this->addressbook[$addressBookId]['addressbookDn'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        
        if($ldapConn === false || $backendId === null)
					return null;
        
        $filter = '(&'.$addressBookConfig['filter']. '(entryuuid=' . ldap_escape($backendId, "", LDAP_ESCAPE_FILTER) . '))';
              
        return Utility::LdapQuery($ldapConn, $addressBookDn, $filter, empty($attributes)?['dn', 'createTimestamp', 'modifyTimestamp']:$attributes, strtolower($addressBookConfig['scope']), $attributesOnly);
    }
    

    /**
     * Get contact using cards backend map table and ldap directory database.
     *
     * @param string  $addressBookId
     * @param string  $cardUri
     * @param array 	$attributes
     * @param int 		$attributesOnly
     * @return array
     */
    function fetchLdapContactDataByUri($addressBookId, $cardUri, $attributes = [], int $attributesOnly = 0)
    {
        $syncDbUserId = $this->addressbook[$addressBookId]['syncDbUserId'];
        $backendId = null;
        
        try {
            $query = 'SELECT backend_id FROM ' . self::$backendMapTableName . ' WHERE addressbook_id = ? and card_uri = ? and user_id = ?';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$addressBookId, $cardUri, $syncDbUserId]);
            
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if($row === false)
            	return null;
            	
            $backendId = $row['backend_id'];
        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        }
             
        return $this->fetchLdapContactDataById($addressBookId, $backendId, $attributes, $attributesOnly);
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
        $syncDbUserId = $this->addressbook[$addressBookId]['syncDbUserId'];
        $ldapConn = $this->addressbook[$addressBookId]['LdapConnection'];
        $cache = $this->cache;
				$backendContacts = [];
				
				$fullRefreshSyncToken = null;
				
				try {
						$query = 'SELECT sync_token FROM ' . self::$fullRefreshTableName . ' WHERE addressbook_id = ? AND user_id = ?';
						$stmt = $this->pdo->prepare($query);
						$stmt->execute([$addressBookId, $syncDbUserId]);
						
						$row = $stmt->fetch(\PDO::FETCH_ASSOC);
						
						if($row !== false)
							$fullRefreshSyncToken = (int)$row['sync_token'];

				} catch (\Throwable $th) {
						error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
				}
				
				$fullRefreshInterval = (isset($addressBookConfig['full_refresh_interval']) && is_int($addressBookConfig['full_refresh_interval']) && $addressBookConfig['full_refresh_interval'] > 0)?$addressBookConfig['full_refresh_interval']:self::$defaultFullRefreshInterval;
				
				if($fullRefreshSyncToken != null && $addressBookSyncToken < ($fullRefreshSyncToken + $fullRefreshInterval))
				{
					try {
						$query = 'SELECT card_uid, card_uri, backend_id FROM ' . self::$backendMapTableName . ' WHERE user_id = ? AND addressbook_id = ?';
						$stmt = $this->pdo->prepare($query);
						$stmt->execute([$syncDbUserId, $addressBookId]);
						
						while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
							$cacheValues = null;
							$cardModifiedTimestamp = null;
						  
					  	$cacheValues = CacheMaster::decodeCard($cache->get(CacheMaster::cardKey($syncDbUserId, $addressBookId, $row['card_uri']), null));
					  	$cardModifiedTimestamp = !isset($cacheValues['lastmodified'])?null:$cacheValues['lastmodified'];
						  	
						  if($cardModifiedTimestamp == null)
						  {
								$data = $this->fetchLdapContactDataById($addressBookId, $row['backend_id'], ['modifyTimestamp']);
								
								if(empty($data))
									throw new SabreDAVException\ServiceUnavailable();
									
								if(!$data['count'] > 0)
									continue;
								
								if(!isset($data[0]['modifytimestamp'][0])) {
									error_log("Read access to some operational attributes in LDAP not present. ".__METHOD__." at line no ".__LINE__);
									throw new SabreDAVException\ServiceUnavailable();
								}
								
								$cardModifiedTimestamp = $data[0]['modifytimestamp'][0];
							}
							
							$backendContacts[] = [
                'card_uri' => $row['card_uri'],
								'card_uid' => $row['card_uid'],
                'backend_id' => $row['backend_id'],
                'modified_timestamp' => $cardModifiedTimestamp
              ];
						}
					} catch (\Throwable $th) {
							error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
							throw new SabreDAVException\ServiceUnavailable();
					}
					
					return $backendContacts;
				}
				
        if(isset($addressBookConfig['sync_bind_dn']) && $addressBookConfig['sync_bind_dn'] != '')
        {
        	$syncBindDn = $addressBookConfig['sync_bind_dn'];
        	$syncBindPass = (!isset($addressBookConfig['sync_bind_pw']))?null:$addressBookConfig['sync_bind_pw'];
        	$ldapConn = Utility::LdapBindConnection(['bindDn' => $syncBindDn, 'bindPass' => $syncBindPass], $this->config['server']['ldap']);
        }
        
        if($ldapConn === false)
        	throw new SabreDAVException\ServiceUnavailable();
        
				$filter = '(&' . $addressBookConfig['filter'] . '(!(createtimestamp>=' . gmdate('YmdHis', $addressBookSyncToken) . 'Z)))';
        $data = Utility::LdapIterativeQuery($ldapConn, $addressBookDn, $filter, ['entryuuid','modifytimestamp'], strtolower($addressBookConfig['scope']));
        
        if($data === false)
         throw new SabreDAVException\ServiceUnavailable();
        
        try 
        {
          while($data['entryIns']) 
					{
						if(!isset($data['data']['entryUUID'][0]) || !isset($data['data']['modifyTimestamp'][0]))
						{
							error_log("Read access to required operational attributes in LDAP not present. Cannot continue. Quitting. ".__METHOD__." at line no ".__LINE__);
        			throw new SabreDAVException\ServiceUnavailable();
						}
						
            $contactData = null;
          
            $query = 'SELECT card_uri, card_uid FROM ' . self::$backendMapTableName . ' WHERE user_id = ? AND addressbook_id = ? AND backend_id = ?';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$syncDbUserId, $addressBookId, $data['data']['entryUUID'][0]]);
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
              
              $query = "INSERT INTO `" . self::$backendMapTableName . "` (`card_uri`, `card_uid`, `addressbook_id`, `backend_id`, `user_id`)  VALUES (?, ?, ?, ?, ?)";
              $sql = $this->pdo->prepare($query);
              $sql->execute([$cardUri, $cardUID, $addressBookId, $data['data']['entryUUID'][0], $syncDbUserId]);

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
					$query = 'SELECT card_uri, backend_id FROM ' . self::$backendMapTableName . ' WHERE user_id = ? AND addressbook_id = ?';
					$stmt = $this->pdo->prepare($query);
					$stmt->execute([$syncDbUserId, $addressBookId]);
						
					while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
					{
						$cardUri = $row['card_uri'];
						$backendId = $row['backend_id'];
						$backendContactExist = false;
						
						if(!$cache->delete(CacheMaster::cardKey($syncDbUserId, $addressBookId, $cardUri))) {
		      		error_log("Could not delete cached data: " . __METHOD__ . " at line no " . __LINE__ . ", " . $th->getMessage());
							throw new SabreDAVException\ServiceUnavailable();
						}

						foreach($backendContacts as $backendContact)
						{
							if($backendId == $backendContact['backend_id'])
							{
								$backendContactExist = true;
								break;
							}
						}
						
						if(!$backendContactExist)
							$this->addChange($addressBookId, $cardUri);
					}

        } catch (\Throwable $th) {
            error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
        		throw new SabreDAVException\ServiceUnavailable();
        }
        
        try {
					$query = "UPDATE `" . self::$fullRefreshTableName . "` SET sync_token = ? WHERE user_id = ? AND addressbook_id = ?"; 
					$sql = $this->pdo->prepare($query);
					$sql->execute([$addressBookSyncToken, $syncDbUserId, $addressBookId]);
					
					if(!$sql->rowCount() > 0)
					{
						$query = "INSERT INTO `" . self::$fullRefreshTableName . "` (`user_id`, `addressbook_id`, `sync_token`) VALUES (?, ?, ?)"; 
						$sql = $this->pdo->prepare($query);
						$sql->execute([$syncDbUserId, $addressBookId, $addressBookSyncToken]);
					}
				} catch (\Throwable $th) {
							error_log("Database query could not be executed: " . __METHOD__ . " at line no " . __LINE__ . ", " . $th->getMessage());
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
    
    function isAddressbookWritable($addressBookId)
    {
			try 
			{
		    $query = 'SELECT writable FROM ' . self::$addressBooksTableName . ' WHERE addressbook_id =? LIMIT 1';
		    $stmt = $this->pdo->prepare($query);
		    $stmt->execute([$addressBookId]);
		    
		    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
		    
		    if($row === false)
        	throw new SabreDAVException\ServiceUnavailable();
		    	
		    return $row['writable'];
		  } 
		  catch (\Throwable $th) {
	      error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
		  }
		  
      throw new SabreDAVException\ServiceUnavailable();
    }
}
