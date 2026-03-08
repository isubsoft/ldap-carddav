<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\DAVACL\PrincipalBackend;

use ISubsoft\DAV\Utility\LDAP as Utility;
use \Sabre\DAV\Exception as SabreDAVException;
use ISubsoft\Cache\Master as CacheMaster;

class LDAP extends \Sabre\DAVACL\PrincipalBackend\AbstractBackend {


    /**
     * Store ldap directory access credentials
     *
     * @var array
     */
    public $config;

    /**
     * LDAP connection
     *
     * @var array
     */    
    private $ldapConn = false;
    
    /**
     * PDO connection handle
     *
     * @var \PDO
     */
    public $pdo;
    
    /**
     * Cache entity name.
     *
     * @var string
     */
    public static $cacheEntityId = 'principal';
    
    /**
     * Cache object.
     *
     * @var cache
     */
    private $cache;

    /**
     * A list of additional fields to support
     *
     * @var array
     */
    protected $fieldMap = [

        /**
         * This property can be used to display the users' real name.
         */
			'{DAV:}displayname' => 'display_name',

        /**
         * This is the users' primary email-address.
         */
			'{http://sabredav.org/ns}email-address' => 'mail_primary',

			'{http://calendarserver.org/ns/}email-address-set' => [
				'{http://calendarserver.org/ns/}email-address' => 'mail'
			]
		];

    /**
     * A list of properties which are mandatory
     *
     * @var array
     */    
    private static $mandatoryProperties = ['id'];
    
    private $userTableName = 'cards_user';
    
    private static $cacheTtl = 86400;

      /**
     * Creates the backend.
     *
     * configuration array must be provided
     * to access initial directory.
     *
     * @param array $this->config
     * @return void
     */
    public function __construct(array $config, \PDO $pdo, $cache) { 
        $this->config = $config;
        $this->pdo = $pdo;
				$this->cache = $cache;
    }
    
    private function setPrincipalBackendProperties()
    {
    	if($this->ldapConn !== false)
    		return;
    		
		  $bindDn = $this->config['principal']['ldap']['search_bind_dn'];
		  $bindPass = (isset($this->config['principal']['ldap']['search_bind_pw']))?$this->config['principal']['ldap']['search_bind_pw']:null;
		  $ldapConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['server']['ldap']);
		  
		  if($ldapConn !== false)
				$this->ldapConn = $ldapConn;
    	
    	return;
    }
    
    private static function getCacheKey($principalId) {
    	return [self::$cacheEntityId, $principalId];
    }
    
    /**
     * Returns a list of principals based on a prefix.
     *
     * This prefix will often contain something like 'principals'. You are only
     * expected to return principals that are in this base path.
     *
     * You are expected to return at least a 'uri' for every user, you can
     * return any additional properties if you wish so. Common properties are:
     *   {DAV:}displayname
     *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
     *     field that's actually injected in a number of other properties. If
     *     you have an email address, use this property.
     *
     * @param string $prefixPath
     * @return array
     */
    function getPrincipalsByPrefix($prefixPath)
    {
    		$currentUserPrincipalId = $GLOBALS['currentUserPrincipalId'];
    		$principals = [];
    		
        if(!isset($this->config['principal']['ldap']['search_bind_dn']) || $this->config['principal']['ldap']['search_bind_dn'] == '')
        {  
            $principals[] = ['uri' => $prefixPath. '/' . $currentUserPrincipalId];
            return $principals;
        }
        
        $configFieldMap = (isset($this->config['principal']['ldap']['fieldmap']) && is_array($this->config['principal']['ldap']['fieldmap']))?$this->config['principal']['ldap']['fieldmap']:[];
        
    		foreach(self::$mandatoryProperties as $value) {
    			if(!isset($configFieldMap[$value]) || !is_string($configFieldMap[$value]) || $configFieldMap[$value] === '') {
    				trigger_error("Mandatory property '$value' for principals not mapped. Check configuration.", E_USER_WARNING);
        		throw new SabreDAVException\ServiceUnavailable();
    			}
    		}

				$this->setPrincipalBackendProperties();
				$ldapConn = $this->ldapConn;
        
        if($ldapConn === false)
        	throw new SabreDAVException\ServiceUnavailable();
  
        $ldaptree = ($this->config['principal']['ldap']['search_base_dn'] !== '') ? $this->config['principal']['ldap']['search_base_dn'] : $this->config['principal']['ldap']['base_dn'];
        $filter = Utility::replacePlaceholders($this->config['principal']['ldap']['search_filter'], ['%u' => ldap_escape($currentUserPrincipalId, "", LDAP_ESCAPE_FILTER)]);
        
        $tmp = [];
				$attributes = [];
				
    		foreach(self::$mandatoryProperties as $value)
					$attributes[] = $configFieldMap[$value];
				
        foreach(Utility::getLeafValues($this->fieldMap, $tmp) as $value) {
        	if(isset($configFieldMap[$value]) && is_string($configFieldMap[$value]) && $configFieldMap[$value] !== '')
						$attributes[] = $configFieldMap[$value];
				}
        
        $data = Utility::LdapQuery($ldapConn, $ldaptree, $filter, $attributes, strtolower($this->config['principal']['ldap']['scope']));
                    
        if($data['count'] > 0)
        {
            for ($i=0; $i < $data['count']; $i++) {
            		$principalId = $data[$i][$configFieldMap['id']][0];
               	$principal = Utility::setPrincipalProperty(null, $this->fieldMap, $configFieldMap, $data[$i]);
				        $principal['id'] = $principalId;
				        $principal['uri'] = $prefixPath. '/' . $principalId;
                $principals[] = $principal;
            }
        }
                    
        return $principals;
    }

    /**
     * Returns a specific principal, specified by it's path.
     * The returned structure should be the exact same as from
     * getPrincipalsByPrefix.
     *
     * @param string $path
     * @return array
     */
    function getPrincipalByPath($path)
    {
        $principalId = basename($path);
        $currentUserPrincipalId = $GLOBALS['currentUserPrincipalId'];
        $principal = [];

        if(strtolower($principalId) != strtolower($currentUserPrincipalId))
  				throw new SabreDAVException\Forbidden("User does not have access to this path");
        
			  if(!isset($this->config['principal']['ldap']['search_bind_dn']) || $this->config['principal']['ldap']['search_bind_dn'] == '') {
			  	$principal = [ 'id'=> $principalId, 'uri' => $path];
			    return $principal;
			  }
        
				$cacheValid = true; // If false then cache need to be refreshed
				$principal = CacheMaster::decode($this->cache->get(CacheMaster::getKey(self::getCacheKey($principalId)), null));
				
       	if($principal == [] || $principal == null)
					$cacheValid = false;
					
				if($cacheValid) {
					$principal['id'] = $principalId;
        	$principal['uri'] = $path;
        	
					return $principal;
				}
				
				$principal == [];
				
        $configFieldMap = (isset($this->config['principal']['ldap']['fieldmap']) && is_array($this->config['principal']['ldap']['fieldmap']))?$this->config['principal']['ldap']['fieldmap']:[];
        
    		foreach(self::$mandatoryProperties as $value) {
    			if(!isset($configFieldMap[$value]) || !is_string($configFieldMap[$value]) || $configFieldMap[$value] === '') {
    				trigger_error("Mandatory property '$value' for principals not mapped. Check configuration.", E_USER_WARNING);
        		throw new SabreDAVException\ServiceUnavailable();
    			}
    		}

				$this->setPrincipalBackendProperties();
				$ldapConn = $this->ldapConn;
        
        if($ldapConn === false)
        	throw new SabreDAVException\ServiceUnavailable();
          
        $ldaptree = ($this->config['principal']['ldap']['search_base_dn'] !== '') ? $this->config['principal']['ldap']['search_base_dn'] : $this->config['principal']['ldap']['base_dn'];
        $principalIdAttribute = $configFieldMap['id'];
        $filter = Utility::replacePlaceholders('(&' . $this->config['principal']['ldap']['search_filter'] . '(' . $principalIdAttribute . '=' . '%u' . '))', ['%u' => ldap_escape($principalId, "", LDAP_ESCAPE_FILTER)]);
        
        $tmp = [];
				$attributes = [];
				
    		foreach(self::$mandatoryProperties as $value)
					$attributes[] = $configFieldMap[$value];
				
        foreach(Utility::getLeafValues($this->fieldMap, $tmp) as $value) {
        	if(isset($configFieldMap[$value]) && is_string($configFieldMap[$value]) && $configFieldMap[$value] !== '')
						$attributes[] = $configFieldMap[$value];
				}
        
        $attributes[] = 'entryuuid';

        $data = Utility::LdapQuery($ldapConn, $ldaptree, $filter, $attributes, strtolower($this->config['principal']['ldap']['scope']));
                    
        if(!empty($data) && $data['count'] === 1)
        {
		   			if(!isset($data[0]['entryuuid'][0]))
		   			{
							trigger_error("Could not obtain backend id for principal '$principalId'. Check access privileges in backend.", E_USER_WARNING);
		   				throw new SabreDAVException\ServiceUnavailable();
		   			}
		   			
	        	$principal = Utility::setPrincipalProperty(null, $this->fieldMap, $configFieldMap, $data[0]);
            $principal['__backend_id'] = $data[0]['entryuuid'][0];
            
						if(!$this->cache->set(CacheMaster::getKey(self::getCacheKey($principalId)), CacheMaster::encode($principal), (isset($this->config['cache']['principal']['ttl']) && is_int($this->config['cache']['principal']['ttl']) && $this->config['cache']['principal']['ttl'] > 0 && $this->config['cache']['principal']['ttl'] <= 2592000)?$this->config['cache']['principal']['ttl']:self::$cacheTtl))
						  trigger_error("Could not set cache", E_USER_WARNING);
            
            $principal['id'] = $principalId;
            $principal['uri'] = $path;
            
            return $principal;
        }

        return [];
    }

    /**
     * Updates one ore more webdav properties on a principal.
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
     * @param string $path
     * @param \Sabre\DAV\PropPatch $propPatch
     * @return void
     */
    function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch)
    {
			return;
    }

    /**
     * This method is used to search for principals matching a set of
     * properties.
     *
     * This search is specifically used by RFC3744's principal-property-search
     * REPORT.
     *
     * The actual search should be a unicode-non-case-sensitive search. The
     * keys in searchProperties are the WebDAV property names, while the values
     * are the property values to search on.
     *
     * By default, if multiple properties are submitted to this method, the
     * various properties should be combined with 'AND'. If $test is set to
     * 'anyof', it should be combined using 'OR'.
     *
     * This method should simply return an array with full principal uri's.
     *
     * If somebody attempted to search on a property the backend does not
     * support, you should simply return 0 results.
     *
     * You can also just return 0 results if you choose to not support
     * searching at all, but keep in mind that this may stop certain features
     * from working.
     *
     * @param string $prefixPath
     * @param array $searchProperties
     * @param string $test
     * @return array
     */
    function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof')
    {
        return [];
    }

    /**
     * Finds a principal by its URI.
     *
     * This method may receive any type of uri, but mailto: addresses will be
     * the most common.
     *
     * Implementation of this API is optional. It is currently used by the
     * CalDAV system to find principals based on their email addresses. If this
     * API is not implemented, some features may not work correctly.
     *
     * This method must return a relative principal path, or null, if the
     * principal was not found or you refuse to find it.
     *
     * @param string $uri
     * @param string $principalPrefix
     * @return string
     */
    function findByUri($uri, $principalPrefix)
    {
        return '';
    }

    /**
     * Returns the list of members for a group-principal
     *
     * @param string $principal
     * @return array
     */
    function getGroupMemberSet($principal)
    {
        return [];
    }

    /**
     * Returns the list of groups a principal is a member of
     *
     * @param string $principal
     * @return array
     */
    function getGroupMembership($principal)
    {
        return [];
    }

    /**
     * Updates the list of group members for a group principal.
     *
     * The principals should be passed as a list of uri's.
     *
     * @param string $principal
     * @param array $members
     * @return void
     */
    function setGroupMemberSet($principal, array $members)
    {
        return null;
    }
}
