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

    public static function replace_placeholders($string, $values = [])
    {
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

    public static function getVCardAttrParams($vCardKey, $params)
    {
        $vCardParamsInfo = [];

        foreach($params as $vCardParam)
        {
            if ($param = $vCardKey[$vCardParam]) {
                foreach($param as $value) {
                  $vCardParamsInfo[$vCardParam][] = strtoupper($value);
                }
            }
        }

        return $vCardParamsInfo;
    }

    public static function isVcardParamsMatch($ldapKey, $vCardParams)
    {
        foreach($ldapKey as $ldapKeyInfo)
        {
            $newLdapKey = $ldapKeyInfo['backend_attribute'];
            
            foreach($ldapKeyInfo['parameters'] as $ParamList)
            {
                if($ParamList == null)
                {
                    continue;
                }
                
                $ParamsArray = explode(";", $ParamList);
                $allParamsValuesMatch = true;
                
                foreach($ParamsArray as $paramStr)
                {
                    $paramStr = str_replace('"', '', $paramStr);
                    $paramInfo = explode("=", $paramStr);
                    if(! array_key_exists($paramInfo[0], $vCardParams))
                    {
                        $allParamsValuesMatch = false;
                        break;
                    }
                    
                    $paramValues = explode(',', $paramInfo[1]);
                    foreach($paramValues as $paramValue)
                    {
                        if(! in_array(strtoupper($paramValue), $vCardParams[$paramInfo[0]]))
                        {
                            $allParamsValuesMatch = false;
                        }
                    }
                    if($allParamsValuesMatch == false)
                    {
                        break;
                    }
                    
                }

                if($allParamsValuesMatch == true)
                {
                    return (['status' => true, 'backend_attribute' => $newLdapKey ]);
                }
            }
        }

        return (['status' => false, 'backend_attribute' => '' ]);
    }

    public static function reverse_map_vCard_params($params, $backendMapIndex)
    {
        $vCardParams = [];
        if($backendMapIndex !== '')
        {
            $paramList = $params[$backendMapIndex];
            $paramsArr = explode(";", $paramList);
            
            foreach($paramsArr as $paramStr)
            {
                $paramStr = str_replace('"', '', $paramStr);
                $paramInfo = explode("=", $paramStr);
                $paramValues = explode(',', $paramInfo[1]);
                
                if(! isset($paramValues[1]))
                {
                    $vCardParams[strtolower($paramInfo[0])] = $paramValues[0];
                }
                else
                {
                    foreach($paramValues as $paramValue)
                    {
                        $vCardParams[strtolower($paramInfo[0])][] = $paramValue;
                    }
                }
            }
        }

        return $vCardParams;
    }

}