<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

namespace isubsoft\dav\Utility;

use \Sabre\DAV\Exception\ServiceUnavailable;

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

        try {
            // Connect to ldap server
            $ldapUri = ($config['use_tls'] ? 'ldaps://' : 'ldap://') . $config['host'] . ':' . $config['port'];
            $ldapConn = ldap_connect($ldapUri);

            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $config['ldap_version']);
            ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $config['network_timeout']);

            // using ldap bind
            $bindDn  = $credentials['bindDn'];     // ldap rdn or dn
            $bindPass = $credentials['bindPass'];  // associated password
            
            if (!$ldapConn) 
            {
                return false;
            }

            // binding to ldap server
            $ldapBind = ldap_bind($ldapConn, $bindDn, $bindPass);
        
            // verify binding
            if (!$ldapBind) 
            {
                return false;
            }     

        } catch (\Throwable $th) {  
            error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage()); 
            throw new ServiceUnavailable($th->getMessage());
        }        

        return $ldapConn;
    }

    public static function LdapQuery($ldapConn, $base, $filter, $attributes = [], $scope)
    {
        $data = null;
        
        try {
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

            if(!$result)
            {
                return null; 
            }

            $data = ldap_get_entries($ldapConn, $result);
            if(!$data)
            {
                return null;
            }

        } catch (\Throwable $th) {
            error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
            throw new ServiceUnavailable($th->getMessage());
        }    

        return $data;
    }

    public static function __callStatic($funcName, $args)
    {
        if($funcName == 'LdapIterativeQuery')
        {
            $data = null;

            switch(count($args)){                
                case 2:       
                    try {    
                        $data['entryIns'] = ldap_next_entry($args[0], $args[1]);
                        if(!$data['entryIns'])
                        {
                            return false;
                        }
                        $data['data'] = ldap_get_attributes($args[0], $data['entryIns']);
                       
                    } catch (\Throwable $th) {
                        error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
                        throw new ServiceUnavailable($th->getMessage());
                    }                
                    return $data;

                case 5:        
                    try {
                        if($args[4] == 'base')
                        {
                            $result = ldap_read($args[0], $args[1], $args[2], $args[3]);
                        }
                        else if($args[4] == 'list')
                        {
                            $result = ldap_list($args[0], $args[1], $args[2], $args[3]);
                        }
                        else
                        {
                            $result = ldap_search($args[0], $args[1], $args[2], $args[3]);
                        }
            
                        if(!$result)
                        {
                            return false;
                        }
                        
                        $data['entryIns'] = ldap_first_entry($args[0], $result);
                        if(!$data['entryIns'])
                        {
                            return false;
                        }
                        $data['data'] = ldap_get_attributes($args[0], $data['entryIns']);
                        
                    } catch (\Throwable $th) {
                        error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
                        throw new ServiceUnavailable($th->getMessage());
                    }
                    return $data;

                default:
                    return false;
            }              
        }
    }


    public static function replacePlaceholders($string, $values = [])
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
                    return (['status' => true, 'ldapArrMap' => $ldapKeyInfo ]);
                }
            }
        }

        return (['status' => false, 'ldapArrMap' => [] ]);
    }

    public static function reverseMapVCardParams($params, $backendMapIndex)
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
                    $vCardParams[strtoupper($paramInfo[0])] = strtoupper($paramValues[0]);
                }
                else
                {
                    foreach($paramValues as $paramValue)
                    {
                        $vCardParams[strtoupper($paramInfo[0])][] = strtoupper($paramValue);
                    }
                }
            }
        }

        return $vCardParams;
    }
    
    public static function decodeHexInString($string) {
    return
        preg_replace_callback(
            "/\\\\[0-9a-zA-Z]{2}/",
            function ($matches) {
                $match = array_shift($matches);
                return hex2bin(substr($match, 1));
            },
            $string
        );
		}
}
