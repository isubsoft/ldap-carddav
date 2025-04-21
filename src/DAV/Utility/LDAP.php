<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\Utility;

use \Sabre\DAV\Exception\ServiceUnavailable;
use ISubsoft\VObject\Reader as Reader;

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

    private static $allowed_vCard_params = ['TYPE', 'PREF'];



    public static function LdapBindConnection($credentials, $config)
    {
        $ldapConn = null;

        try {
            if(isset($config['host']))
            {
		          $connPort = (isset($config['port']) && $config['port'] != null && $config['port'] != '')?$config['port']:null;
		          $ldapUri = ((isset($config['connection_security']) && $config['connection_security'] == 'secure') ? 'ldaps://' : 'ldap://') . (string)$config['host'] . (string)(($connPort != null)?':' . $connPort:'');
		          
 		          // Connect to URI
	          	$ldapConn = ldap_connect($ldapUri);
		        }
		        else
		        {
		        	// Connect to default URI
	          	$ldapConn = ldap_connect();
		        }
            				            
            if(!$ldapConn) 
              return false;
              
	          if(isset($config['connection_security']) && $config['connection_security'] == 'starttls')
	          {
	          	if(!ldap_start_tls($ldapConn))
	          	{
          			error_log("Start TLS connection security could not be established in " . __METHOD__ . " at line " . __LINE__);
          			
	          		if(!ldap_close($ldapConn))
          				error_log("Server connection could not be closed in " . __METHOD__ . " at line " . __LINE__);
	          		
	          		return false;
	          	}
	          }

						$ldapVersion = (isset($config['ldap_version']))?$config['ldap_version']:3;
						
						if($ldapVersion >= 3)
            	ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $ldapVersion);
            else
            {
		    			error_log("LDAP version less than 3 is not supported " . __METHOD__ . " at line " . __LINE__);
		    			
		      		if(!ldap_close($ldapConn))
		      		{
		    				error_log("Server connection could not be closed in " . __METHOD__ . " at line " . __LINE__);
		      		}
		      		
		      		return false;
            }

						if(isset($config['network_timeout']))
            	ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, $config['network_timeout']);

	          // using ldap bind
	          $bindDn = (isset($credentials['bindDn']) && $credentials['bindDn'] != '')?$credentials['bindDn']:null;     // ldap rdn or dn
          	$bindPass = (isset($bindDn) && $bindDn != '' && isset($credentials['bindPass']) && $credentials['bindPass'] != '')?$credentials['bindPass']:null;  // associated password

            // binding to ldap server
            if(!ldap_bind($ldapConn, $bindDn, $bindPass))
            	return false;

        } catch (\Throwable $th) {  
            error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage()); 
            throw new ServiceUnavailable($th->getMessage());
        }        

        return $ldapConn;
    }

    public static function LdapQuery($ldapConn, $base, $filter, $attributes = [], $scope, int $attributesOnly = 0)
    {
        $data = null;
        
        try {
            if($scope == 'base')
            {
                $result = ldap_read($ldapConn, $base, $filter, $attributes, $attributesOnly);
            }
            else if($scope == 'list')
            {
                $result = ldap_list($ldapConn, $base, $filter, $attributes, $attributesOnly);
            }
            else
            {
                $result = ldap_search($ldapConn, $base, $filter, $attributes, $attributesOnly);
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
                        
                        if($data['entryIns'] === false)
                        {
                            return $data;
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
            
                        if($result === false)
                        {
                            return false;
                        }
                        
                        $data['entryIns'] = ldap_first_entry($args[0], $result);
                        
                        if($data['entryIns'] === false)
                        {
                            return $data;
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
            if (($param = $vCardKey[$vCardParam]) && in_array(strtoupper($vCardParam), self::$allowed_vCard_params)) {
                foreach($param as $value) {
                  $vCardParamsInfo[$vCardParam][] = strtoupper($value);
                }
            }
        }

        return $vCardParamsInfo;
    }

    public static function getMappedVCardAttrParams($paramList, $MappIndex)
    {
        $vCardParams = [];

        if(empty($paramList))
            return [];

        if(self::isMultidimensional($paramList, true) && isset($paramList[$MappIndex]) && $paramList[$MappIndex] != null)
        	$vCardParams = $paramList[$MappIndex];
        elseif(!self::isMultidimensional($paramList, true))
        	$vCardParams = $paramList;

        foreach($vCardParams as $param => $value)
        {
            if(!in_array(strtoupper($param), self::$allowed_vCard_params))
            {
                unset($vCardParams[$param]);
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
                $conValue = hex2bin(substr($match, 1));
                return ($conValue === false)?$match:$conValue;
            },
            $string
        );
	}

    public static function encodeStringToHex($string, array $char = []) {
    		if(count($char) > 8)
    		{
    			error_log("Number of characters to be encoded exceeds limit in " . __METHOD__ . '. No encodings performed.');
    			return $string;
    		}
    		
    		$searchExpr = '';
    		
    		foreach($char as $token)
    			if(mb_strlen($token) === 1)
    			{
						if($token == '\\' || $token == '-' || $token == '^' || $token == ']')
    					$searchExpr = $searchExpr . '\\' . $token;
    				else
    					$searchExpr = $searchExpr . $token;
    			}
    			
        if($searchExpr == '')
        	return $string;
        	
        return
            preg_replace_callback(
                '/(['.$searchExpr.'])/',
                function ($matches) {
                    return '\\' . bin2hex($matches[1]);
                },
                $string
            );
        }

    public static function isMultidimensional(array $array, bool $isNullValueOk = false) {
        $notSingleArray = false;
        
        foreach ($array as $key => $value) {
            if (!is_int($key) || (!$isNullValueOk && !is_array($value)) || ($isNullValueOk && !is_array($value) && $value != null)) {
                return false;
            }
            
        	$notSingleArray = true;
        }
        
        if($notSingleArray)
        	return true;

        return false;
    }

    
    public static function getMappedBackendAttributes($fieldMap)
    {
    	$mappedBackendAttributes = [];
    	
			foreach($fieldMap as $vCardKey => $backendMapArr)
			{
				if(self::isMultidimensional($backendMapArr))
				{
					foreach($backendMapArr as $backendMap)
					{
						if(isset($backendMap['field_name']) && is_array($backendMap['field_name']))
						{
							foreach($backendMap['field_name'] as $compositeBackendMapKey => $compositeBackendMapValue)
							{
								$mappedBackendAttributes[] = strtolower($compositeBackendMapValue);
							}
						}
						else
							$mappedBackendAttributes[] = strtolower($backendMap['field_name']);
					}
				}
				else
				{
					if(is_array($backendMapArr['field_name']))
						foreach($backendMapArr['field_name'] as $compositeBackendMapKey => $compositeBackendMapValue)
							$mappedBackendAttributes[] = strtolower($compositeBackendMapValue);
					else
						 $mappedBackendAttributes[] = strtolower($backendMapArr['field_name']);
				}
			}
			
			return $mappedBackendAttributes;
    }

    public static function getCompositebackendValueConversion($ldapValueArr, $vCardKey, $ldapKey)
    {
        $elementArr = [];
        $params = [];

        foreach($ldapValueArr as $ldapValueComponent)
        {
            if($ldapValueComponent != '' && $ldapValueComponent != null)
            {
                $ldapValueConversionInfo = Reader::backendValueConversion($vCardKey, self::decodeHexInString($ldapValueComponent), (!isset($ldapKey['field_data_format']))?'text':$ldapKey['field_data_format']);
                $elementArr[] = $ldapValueConversionInfo['cardData'];
                $params = $ldapValueConversionInfo['params'];
            }
            else
            {
                $elementArr[] = '';
            }
        }

        return ['ldapValueArray' => $elementArr, 'params' => $params];
    }
}