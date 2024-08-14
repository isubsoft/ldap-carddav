<?php

namespace isubsoft\dav\Utility;

class LDAP {

    /**
     * allowed placeholders for configuration
     *
     * @var array
     */
    private static $allowed_placeholders =  [
                                            '%u' => 'User name',
                                            '%p' => 'User password',
                                            '%dn' => 'User DN in LDAP backend'
                                        ];

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

    public static function replace_placeholders($string, $values = []){

        foreach(self::$allowed_placeholders as $placeholder => $value)
        {
            preg_match('/('.$placeholder.')/', $string, $matches, PREG_OFFSET_CAPTURE);
            
            if(!empty($matches))
            {
                if(array_key_exists($placeholder, $values))
                {
                    $string = str_replace($placeholder, $values[$placeholder], $string);
                }
                else{
                    $string = str_replace($placeholder, '', $string);
                }
            }
        }
        
        return $string;
    }

}