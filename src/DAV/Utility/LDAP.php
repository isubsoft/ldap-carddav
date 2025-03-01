<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\Utility;

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
            if(isset($config['host']) && isset($config['port']))
            {
		          // Connect to ldap server
		          $ldapUri = ((isset($config['connection_security']) && $config['connection_security'] == 'secure') ? 'ldaps://' : 'ldap://') . $config['host'] . ':' . $config['port'];
		          $ldapConn = ldap_connect($ldapUri);
		          
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
            }
            else
            {
            	error_log("Mandatory server connection parameters not present " . __METHOD__ . " at line " . __LINE__);
            	return false;
            }
            				            
            if(!$ldapConn) 
              return false;

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
            if ($param = $vCardKey[$vCardParam]) {
                foreach($param as $value) {
                  $vCardParamsInfo[$vCardParam][] = strtoupper($value);
                }
            }
        }

        return $vCardParamsInfo;
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

    public static function isMultidimensional(array $array) {
        foreach ($array as $value) {
            if (!is_array($value)) {
                return false;
            }
        }
        return true;
    }
}
