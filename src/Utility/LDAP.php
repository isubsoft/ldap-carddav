<?php

namespace isubsoft\dav\Utility;

class LDAP {

    public static function LdapBindConnection($credentials, $config)
    {
        $ldapConn = null;

        // Connect to ldap server
        $ldapUri = ($config['use_tls'] ? 'ldaps://' : 'ldap://') . $config['host'] . ':' . $config['port'];
        $ldapConn = ldap_connect($ldapUri);

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $config['ldap_version']);
        ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $config['network_timeout']);

        // using ldap bind
        $bindDn  = $credentials['bindDn'];     // ldap rdn or dn
        $bindPass = $credentials['bindPass'];  // associated password
        
        if ($ldapConn) {
            
            // binding to ldap server
            $ldapBind = ldap_bind($ldapConn, $bindDn, $bindPass);

            // verify binding
            if ($ldapBind) {
                
                return $ldapConn;
            }
        }

        return $ldapConn;
    }

    public static function LdapQuery($ldapConn, $base, $filter, $attributes = [], $scope)
    {
        $data = null;

        if($scope == 'base')
        {
            $result = ldap_read($ldapConn, $base, $filter, $attributes);
        }
        else if($scope == 'list')
        {
            $result = ldap_list($ldapConn, $base, $filter, $attributes);
        }
        else
        {
            $result = ldap_search($ldapConn, $base, $filter, $attributes);
        }

        $data = ldap_get_entries($ldapConn, $result);

        return $data;
    }

}