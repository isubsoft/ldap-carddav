<?php

namespace isubsoft\dav\Auth;

use isubsoft\dav\Utility\LDAP as Utility;

class LDAP extends \Sabre\DAV\Auth\Backend\AbstractBasic {


    /**
     * Store ldap directory access credentials
     *
     * @var array
     */
    public $config;

    /**
     * This is the prefix that will be used to generate principal urls.
     *
     * @var string
     */
    public $principalPrefix = 'principals/users/';


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
    }


    /**
     * Validates a username and password
     *
     * This method should return true or false depending on if login
     * succeeded.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    function validateUserPass($username, $password)
    {      
        $ldapConn =  $GLOBALS['globalLdapConn'];
         
        $ldaptree = ($this->config['auth']['ldap']['search_base_dn'] !== '') ? $this->config['auth']['ldap']['search_base_dn'] : $this->config['auth']['ldap']['base_dn'];
        $filter = str_replace('%u', $username, $this->config['auth']['ldap']['search_filter']);  
        $attributes = ['dn'];

        $data = Utility::LdapQuery($ldapConn, $ldaptree, $filter, $attributes, strtolower($this->config['auth']['ldap']['scope']));
        
        if($data['count'] == 1)
        {
            $ldapDn = $data[0]['dn'];
            $ldapUserBind = ldap_bind($ldapConn, $ldapDn, $password);  
            
            if($ldapUserBind)
            {
               return true;
            }
        }          

        return false;
    }
}

?>