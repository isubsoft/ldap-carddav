<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

namespace isubsoft\dav\DAVACL\PrincipalBackend;

use isubsoft\dav\Utility\LDAP as Utility;

class LDAP extends \Sabre\DAVACL\PrincipalBackend\AbstractBackend {


    /**
     * Store ldap directory access credentials
     *
     * @var array
     */
    public $config;

    /**
     * A list of additional fields to support
     *
     * @var array
     */
    protected $fieldMap = [

        /**
         * This property can be used to display the users' real name.
         */
        '{DAV:}displayname' => [
            'dbField' => 'displayname',
        ],

        /**
         * This is the users' primary email-address.
         */
        '{http://sabredav.org/ns}email-address' => [
            'dbField' => 'mail',
        ]
    ];

    /**
     * Auth Backend Object class.
     *
     * @var string
     */
    public $authBackend = null;

      /**
     * Creates the backend.
     *
     * configuration array must be provided
     * to access initial directory.
     *
     * @param array $this->config
     * @return void
     */
    public function __construct(array $config, $authBackend) { 
        $this->config = $config;
        $this->authBackend = $authBackend;
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
    		$principals = [];
    		
        if($this->config['principal']['ldap']['search_bind_dn'] == '' && $this->config['principal']['ldap']['search_bind_pw'] == '')
        {  
            $principals[] = ['uri' => $prefixPath. '/' . $this->authBackend->username];
            return $principals;
        }

        $bindDn = $this->config['principal']['ldap']['search_bind_dn'];
        $bindPass = $this->config['principal']['ldap']['search_bind_pw'];
        $ldapConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['principal']['ldap']);
  
        $ldaptree = ($this->config['principal']['ldap']['search_base_dn'] !== '') ? $this->config['principal']['ldap']['search_base_dn'] : $this->config['principal']['ldap']['base_dn'];
        $filter = Utility::replacePlaceholders($this->config['principal']['ldap']['search_filter'], ['%u' => $this->authBackend->username]);
        
        foreach($this->config['principal']['ldap']['fieldMap'] as $key => $value)
        {
					$attributes[] = $value;
        }

        $data = Utility::LdapQuery($ldapConn, $ldaptree, $filter, $attributes, strtolower($this->config['principal']['ldap']['scope']));
                    
        if($data['count'] > 0)
        {
            for ($i=0; $i < $data['count']; $i++) {
            		$principalId = $data[$i][$this->config['principal']['ldap']['fieldMap']['id']][0];
            		
                $principal = [
                    'uri' => $prefixPath. '/' . $principalId
                ];
                
                foreach ($this->fieldMap as $key => $value) {
                    if ( isset($data[$i][$value['dbField']])) {
                        $principal[$key] = $data[$i][$value['dbField']][0];
                    }
                }
                
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

        if($this->config['principal']['ldap']['search_bind_dn'] == '' && $this->config['principal']['ldap']['search_bind_pw'] == '')
        {  
            $principal = [ 'id'=> $principalId, 'uri' => $path];
            return $principal;
        }

        $bindDn = $this->config['principal']['ldap']['search_bind_dn'];
        $bindPass = $this->config['principal']['ldap']['search_bind_pw'];
        $ldapConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['principal']['ldap']);
          
        $ldaptree = ($this->config['principal']['ldap']['search_base_dn'] !== '') ? $this->config['principal']['ldap']['search_base_dn'] : $this->config['principal']['ldap']['base_dn'];
        $filter = Utility::replacePlaceholders($this->config['principal']['ldap']['search_filter'], ['%u' => $principalId]); // single filter
        $principalIdAttribute = $this->config['principal']['ldap']['fieldMap']['id'];
        $filter = '(&' . $filter . '(' . $principalIdAttribute . '=' . $principalId . '))';
        $attributes = ['displayName','mail'];

        $data = Utility::LdapQuery($ldapConn, $ldaptree, $filter, $attributes, strtolower($this->config['principal']['ldap']['scope']));
                    
        if($data['count'] == 1)
        { 
            $principal = [
                'id'  => $principalId,
                'uri' => $path
            ];

            foreach ($this->fieldMap as $key => $value) {
                if ( isset($data[0][$value['dbField']])) {
                    $principal[$key] = $data[0][$value['dbField']][0];
                }
            }
            
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
        return null;
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
?>
