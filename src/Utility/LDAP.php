<?php

namespace isubsoft\dav\Utility;

class LDAP {

    public static function LdapConnection($credentials, $config, $encryptionconfig)
    {
        $ldapConn = null;

        // Connect to ldap server
        $ldapUri = ($config['use_tls'] ? 'ldaps://' : 'ldap://') . $config['host'] . ':' . $config['port'];
        $ldapConn = ldap_connect($ldapUri);

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $config['ldap_version']);
        ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $config['network_timeout']);

        // using ldap bind
        $bindDn  = $credentials['dn'];     // ldap rdn or dn
        $bindPass = self::decrypt($credentials['password'], $encryptionconfig);  // associated password
        
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

    public static function encrypt($string, $config)
    {            
        // Use openssl_encrypt() function to encrypt the data
        $encryptedString = openssl_encrypt($string, $config['cipher-method'],
                            $config['key'], $config['options'], $config['iv']);

        return $encryptedString;    
    }

    public function decrypt($string, $config)
    {
        // Use openssl_decrypt() function to decrypt the data
        $decryptedString = openssl_decrypt($string, $config['cipher-method'],
                            $config['key'], $config['options'], $config['iv']);
        
        return $decryptedString;
    }
}