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
     * Ldap Connection.
     *
     * @var string
     */
    public $userLdapConn = null;

    public $username = null;




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
        $this->username = $username;
        
        if(($this->config['auth']['ldap']['bind_dn'] != '') && ($this->config['auth']['ldap']['bind_pass'] != ''))
        {
            $bindDn = Utility::replace_placeholders($this->config['auth']['ldap']['bind_dn'], ['%u' => $username]);
            $bindPass = Utility::replace_placeholders($this->config['auth']['ldap']['bind_pass'], ['%p' => $password]);

            // binding to ldap server
            $ldapBindConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['auth']['ldap']);

            if($ldapBindConn)
            {
                $this->userLdapConn = $ldapBindConn;
                return true;
            }
        }
        else if(($this->config['auth']['ldap']['search_bind_dn'] != '') && ($this->config['auth']['ldap']['search_bind_pw'] != ''))
        {
            $bindDn = $this->config['auth']['ldap']['search_bind_dn'];
            $bindPass = $this->config['auth']['ldap']['search_bind_pw'];

            // binding to ldap server
            $ldapBindConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['auth']['ldap']);

            // verify binding
            if ($ldapBindConn) {
            
                $ldaptree = ($this->config['auth']['ldap']['search_base_dn'] !== '') ? $this->config['auth']['ldap']['search_base_dn'] : $this->config['auth']['ldap']['base_dn'];  
                $filter = Utility::replace_placeholders($this->config['auth']['ldap']['search_filter'], ['%u' => $username]);

                $data = Utility::LdapQuery($ldapBindConn, $ldaptree, $filter, [], strtolower($this->config['auth']['ldap']['scope']));

                if($data['count'] == 1)
                {
                    $ldapDn = $data[0]['dn'];
                    $ldapUserBind = ldap_bind($ldapBindConn, $ldapDn, $password);  

                    if($ldapUserBind)
                    {
                        $this->userLdapConn = $ldapBindConn;
                        return true;
                    }
                }      
            }
        }   

        return false;
    }
}

?>