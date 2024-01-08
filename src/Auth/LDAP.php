<?php

namespace isubsoft\dav\Auth;
require '../../conf/conf.php';

class LDAP extends \Sabre\DAV\Auth\Backend\AbstractBasic {

    function __construct() {
       
    }

    function validateUserPass($username, $password)
    {

        // connect to ldap server
        $ldapUri = ($config['auth']['ldap']['use_tls'] ? 'ldaps://' : 'ldap://') . $config['auth']['ldap']['host'] . ':' . $config['auth']['ldap']['port'];
        $ldapConn = ldap_connect($ldapUri);

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $config['auth']['ldap']['ldap_version']);
        ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $config['auth']['ldap']['network_timeout']);

        // using ldap bind
        $searchBindDn  = $config['auth']['ldap']['search_bind_dn'];     // ldap rdn or dn
        $searchBindPass = $config['auth']['ldap']['search_bind_pw'];  // associated password


        if ($ldapConn) {

            // binding to ldap server
            $ldapBind = ldap_bind($ldapConn, $searchBindDn, $searchBindPass);

            // verify binding
            if ($ldapBind) {
                
                $ldaptree = ($config['auth']['ldap']['search_base_dn'] !== '') ? $config['auth']['ldap']['search_base_dn'] : $config['auth']['ldap']['base_dn'];
                $filter = str_replace('%u', $username, $config['auth']['ldap']['search_filter']);  // single filter
                $attributes = ['dn'];

                $result = ldap_search($ldapConn,$ldaptree, $filter, $attributes) or die ("Error in search query: ".ldap_error($ldapConn));
                $data = ldap_get_entries($ldapConn, $result);
                
                if($data['count'] == 1)
                {
                    $ldapUserBind = ldap_bind($ldapConn, $ldapDn, $password);
                    
                    if($ldapUserBind)
                    {
                        return true;
                    }
                    else{
                        return false;
                    }
                }
                else
                {
                    return false;
                }

            } else {
                return false;
            }

        }

        return false;
    }
}

?>