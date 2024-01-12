<?php

namespace isubsoft\dav\Auth;

class LDAP extends \Sabre\DAV\Auth\Backend\AbstractBasic {


    /**
     * Store ldap directory access credentials
     *
     * @var array
     */
    public $config;


    /**
     * Creates the backend.
     *
     * configuration array must be provided
     * to access initial directory access.
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
        // connect to ldap server
        $ldapUri = ($this->config['auth']['ldap']['use_tls'] ? 'ldaps://' : 'ldap://') . $this->config['auth']['ldap']['host'] . ':' . $this->config['auth']['ldap']['port'];
        $ldapConn = ldap_connect($ldapUri);

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $this->config['auth']['ldap']['ldap_version']);
        ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $this->config['auth']['ldap']['network_timeout']);

        // using ldap bind
        $searchBindDn  = $this->config['auth']['ldap']['search_bind_dn'];     // ldap rdn or dn
        $searchBindPass = $this->config['auth']['ldap']['search_bind_pw'];  // associated password


        if ($ldapConn) {

            // binding to ldap server
            $ldapBind = ldap_bind($ldapConn, $searchBindDn, $searchBindPass);

            // verify binding
            if ($ldapBind) {
                
                $ldaptree = ($this->config['auth']['ldap']['search_base_dn'] !== '') ? $this->config['auth']['ldap']['search_base_dn'] : $this->config['auth']['ldap']['base_dn'];
                $filter = str_replace('%u', $username, $this->config['auth']['ldap']['search_filter']);  // single filter
                $attributes = ['dn'];

                $result = ldap_search($ldapConn,$ldaptree, $filter, $attributes); 
                $data = ldap_get_entries($ldapConn, $result);
                
                if($data['count'] == 1)
                {
                    $ldapDn = $data[0]['dn'];
                    $ldapUserBind = ldap_bind($ldapConn, $ldapDn, $password);
                    
                    if($ldapUserBind)
                    {
                        return true;
                    }
                }
                
            }

        }

        return false;
    }
}

?>