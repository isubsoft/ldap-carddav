<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\Utility;

use Sabre\DAV\Exception as SabreDAVException;
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

    private static $allowed_vCard_params = ['TYPE'];



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
          			trigger_error("Start TLS connection security could not be established in " . __METHOD__ . " at line " . __LINE__, E_USER_WARNING);
          			
	          		if(!ldap_close($ldapConn))
          				trigger_error("Server connection could not be closed in " . __METHOD__ . " at line " . __LINE__, E_USER_WARNING);
	          		
	          		return false;
	          	}
	          }

						$ldapVersion = (isset($config['ldap_version']))?$config['ldap_version']:3;
						
						if($ldapVersion >= 3)
            	ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, $ldapVersion);
            else
            {
		    			trigger_error("LDAP version less than 3 is not supported " . __METHOD__ . " at line " . __LINE__, E_USER_WARNING);
		    			
		      		if(!ldap_close($ldapConn))
		      		{
		    				trigger_error("Server connection could not be closed in " . __METHOD__ . " at line " . __LINE__, E_USER_WARNING);
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
					trigger_error("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage(), E_USER_WARNING); 
					throw new SabreDAVException\ServiceUnavailable();
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
            trigger_error("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage(), E_USER_WARNING);
            throw new SabreDAVException\ServiceUnavailable();
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
		                  trigger_error("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage(), E_USER_WARNING);
		                  throw new SabreDAVException\ServiceUnavailable();
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
											trigger_error("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage(), E_USER_WARNING);
											throw new SabreDAVException\ServiceUnavailable();
                    }
                    
                    return $data;

                default:
                    return false;
            }              
        }
    }


    public static function replacePlaceholders($subject, $values = [])
    {
        $replacedSubject = $subject;
        
        foreach(self::$allowed_placeholders as $placeholder => $desc)
                if(array_key_exists($placeholder, $values))
                    $replacedSubject = replacePlaceholder($placeholder, $values[$placeholder], $replacedSubject);
        
        return $replacedSubject;
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
    			trigger_error("Number of characters to be encoded exceeds limit in " . __METHOD__ . '. No encodings performed.', E_USER_WARNING);
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

		/********
			Returns true if any of the array values is a scalar and not an empty string
		********/
    public static function hasValue(array $array) :bool
    {
        $flag = false;

        foreach($array as $value)
        {
            if(is_scalar($value) && ((is_string($value) && trim($value) !== '') || !is_string($value)))
                $flag = true;
        }

        return $flag;
    }

		/********
			Returns true if any of the array values is either not a scalar or an empty string
		********/
    public static function notHasValue(array $array) :bool
    {
        $flag = false;

        foreach($array as $value)
        {
            if(!is_scalar($value) || (is_string($value) && trim($value) === ''))
                $flag = true;
        }

        return $flag;
    }
    
		public static function responseCodeException($responseCode, $message)
		{
			if($responseCode == 400)
				return new SabreDAVException\BadRequest($message);
				
			return new SabreDAVException\ServiceUnavailable();
		}
		
		/**
		* Get leaf values as a list for an array
		*
		* @param array $tree
		* @param array &$result
    * @return array
		**/
    public static function getLeafValues(array $tree, array &$result)
    {
  		foreach($tree as $value) {
				if(is_array($value))
  				self::getLeafValues($value, $result);
				else
					$result[] = $value;
  		}
    		
   		return $result;
    }
    
		/**
		* Set property array from property definition array and backend values
		*
		* @param array $propNs
		* @param array $propDef
		* @param array &$configFieldMap
		* @param array &$backendData
    * @return array
		**/
    public static function setPrincipalProperty($propNs, array $propDef, &$configFieldMap, &$backendData)
    {
			$principalPropValue = null;
			
				foreach($propDef as $key => $value) {
					if(is_string($value) && $value !== '') {
						if(isset($configFieldMap[$value]) && is_string($configFieldMap[$value]) && $configFieldMap[$value] !== '') {
							$principalPropValue[$key] = [];
							$backendAttr = $configFieldMap[$value];
							
							if(isset($backendData[$backendAttr])) {
								if($propNs == null)
									$principalPropValue[$key] = $backendData[$backendAttr][0];
								else
									for($index=0; $index<$backendData[$backendAttr]['count']; $index++)
										$principalPropValue[$key][][$propNs] = $backendData[$backendAttr][$index];
							}
						}
					}
					elseif(is_array($value))
						$principalPropValue[$key] = self::setPrincipalProperty($key, $value, $configFieldMap, $backendData);
				}
					
			return $principalPropValue;
    }
}
