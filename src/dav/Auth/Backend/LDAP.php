<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\Auth\Backend;

use ISubsoft\DAV\Utility\LDAP as Utility;
use \Sabre\DAV\Exception\ServiceUnavailable;

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
     * to access initial directory.
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
        if($username == '' || $password == null)
        	return false;
        	
        if(isset($this->config['auth']['ldap']['search_bind_dn']) && $this->config['auth']['ldap']['search_bind_dn'] != '')
        {
            $bindDn = $this->config['auth']['ldap']['search_bind_dn'];
            $bindPass = (isset($this->config['auth']['ldap']['search_bind_pw']))?$this->config['auth']['ldap']['search_bind_pw']:null;

            // binding to ldap server
            $ldapBindConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['server']['ldap']);
            
            // verify binding
            if ($ldapBindConn) {
                $ldaptree = (isset($this->config['auth']['ldap']['search_base_dn']) && $this->config['auth']['ldap']['search_base_dn'] !== '')?$this->config['auth']['ldap']['search_base_dn']:$this->config['auth']['ldap']['base_dn'];
                $filter = Utility::replacePlaceholders($this->config['auth']['ldap']['search_filter'], ['%u' => $username]);

                $data = Utility::LdapQuery($ldapBindConn, $ldaptree, $filter, ['dn'], strtolower($this->config['auth']['ldap']['scope']));

                if(empty($data))
                {
                	error_log("Could not execute backend search: ".__METHOD__.", ".$th->getMessage());
                	throw new ServiceUnavailable();
                }
                
                if($data['count'] == 1)
                {
                    $bindDn = Utility::replacePlaceholders($this->config['auth']['ldap']['bind_dn'], ['%dn' => $data[0]['dn'], '%u' => $username]);

                    try {
                        $ldapUserBind = ldap_bind($ldapBindConn, $bindDn, $password);
                        
                        if(!$ldapUserBind)
                        	return false;
                    } catch (\Throwable $th) {
                        error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
                        throw new ServiceUnavailable();
                    }
                    
                    $GLOBALS['currentUserPrincipalLdapConn'] = $ldapBindConn;
                    return true;
                }      
            }
        }
        else
        {
            $bindDn = Utility::replacePlaceholders($this->config['auth']['ldap']['bind_dn'], ['%u' => $username]);
            $bindPass = Utility::replacePlaceholders($this->config['auth']['ldap']['bind_pass'], ['%p' => $password]);

            // binding to ldap server
            $ldapBindConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['server']['ldap']);

            if($ldapBindConn)
            {
                $GLOBALS['currentUserPrincipalLdapConn'] = $ldapBindConn;
                return true;
            }
        }

        return false;
    }
}

?>
