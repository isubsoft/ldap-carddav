<?php

namespace isubsoft\dav\DAVACL\PrincipalBackend;
session_start();

class LDAP extends \Sabre\DAVACL\PrincipalBackend\AbstractBackend {


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
    public $prefix = 'principals/users';

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
        ],
    ];

      /**
     * Creates the backend.
     *
     * configuration array must be provided
     * to access initial directory.
     *
     * @param array $this->config
     * @return void
     */
    function __construct(array $config) {
        $this->config = $config;
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

        if( session_id() != null && isset($_SESSION['user-credentials']))
        {
            $userCredential = $this->getUsercredential($_SESSION['user-credentials']);
        
            // connect to ldap server
            $ldapUri = ($this->config['principal']['ldap']['use_tls'] ? 'ldaps://' : 'ldap://') . $this->config['principal']['ldap']['host'] . ':' . $this->config['principal']['ldap']['port'];
            $ldapConn = ldap_connect($ldapUri);
            
            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $this->config['principal']['ldap']['ldap_version']);
            ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $this->config['principal']['ldap']['network_timeout']);


            if ($ldapConn) {

                // binding to ldap server
                $ldapBind = ldap_bind($ldapConn, $userCredential['userDn'], $userCredential['userPw']);

                // verify binding
                if ($ldapBind) {
                    
                    $ldaptree = ($this->config['principal']['ldap']['search_base_dn'] !== '') ? $this->config['principal']['ldap']['search_base_dn'] : $this->config['principal']['ldap']['base_dn'];
                    $filter = str_replace('%u', '*', $this->config['principal']['ldap']['search_filter']); 
                    $attributes = ['displayName','mail'];

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
                    
                    if($data['count'] > 0)
                    {
                        for ($i=0; $i < $data['count']; $i++) { 

                            $principal = [
                                'uri' => $prefixPath. '/' . str_replace('uid=', '',explode(',',$data[$i]['dn'])[0]),
                            ];
                            foreach ($this->fieldMap as $key => $value) {
                                if ($data[$i][$value['dbField']]) {
                                    $principal[$key] = $data[$i][$value['dbField']][0];
                                }
                            }
                            $principals[] = $principal;
                        }
        
                    }
                }
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
        if( session_id() != null && isset($_SESSION['user-credentials']))
        {
            $userCredential = $this->getUsercredential($_SESSION['user-credentials']);
            $id = str_replace($this->prefix.'/','',$path);

            // connect to ldap server
            $ldapUri = ($this->config['principal']['ldap']['use_tls'] ? 'ldaps://' : 'ldap://') . $this->config['principal']['ldap']['host'] . ':' . $this->config['principal']['ldap']['port'];
            $ldapConn = ldap_connect($ldapUri);
            
            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $this->config['principal']['ldap']['ldap_version']);
            ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $this->config['principal']['ldap']['network_timeout']);

            // using ldap bind
            $searchBindDn  = $this->config['principal']['ldap']['search_bind_dn'];     // ldap rdn or dn
            $searchBindPass = $this->config['principal']['ldap']['search_bind_pw'];  // associated password


            if ($ldapConn) {

                // binding to ldap server
                $ldapBind = ldap_bind($ldapConn, $userCredential['userDn'], $userCredential['userPw']);

                // verify binding
                if ($ldapBind) {
                    
                    $ldaptree = ($this->config['principal']['ldap']['search_base_dn'] !== '') ? $this->config['principal']['ldap']['search_base_dn'] : $this->config['principal']['ldap']['base_dn'];
                    $filter = str_replace('%u', $id, $this->config['principal']['ldap']['search_filter']);  // single filter
                    $attributes = ['displayName','mail'];

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

                        $principal = [
                            'id'  => $id,
                            'uri' => $path
                        ];

                        foreach ($this->fieldMap as $key => $value) {
                            if ($data[0][$value['dbField']]) {
                                $principal[$key] = $data[0][$value['dbField']][0];
                            }
                        }
                        return $principal;
        
                    }
                }
            }
        }

        return ;
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
        echo 'd';
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
        echo 'd';
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
        echo 'd';
    }

    /**
     * Returns the list of members for a group-principal
     *
     * @param string $principal
     * @return array
     */
    function getGroupMemberSet($principal)
    {
        echo 'd';
    }

    /**
     * Returns the list of groups a principal is a member of
     *
     * @param string $principal
     * @return array
     */
    function getGroupMembership($principal)
    {
        echo 'd';
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
        echo 'd';
    }

    /*
    Get credentials stored in session
    */
    function getUsercredential($sessionData)
    {
        $userCredential = explode('|', $sessionData);

        return ['userDn' => str_replace('userdn=', '', $userCredential[0]),
                'userPw' => str_replace('pw=', '', $userCredential[1])];
    }
}
?>