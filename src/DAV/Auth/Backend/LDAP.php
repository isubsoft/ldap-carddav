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
        $this->setRealm((isset($config['auth']['ldap']['realm']) && is_string($config['auth']['ldap']['realm']) && $config['auth']['ldap']['realm'] != '')?$config['auth']['ldap']['realm']:'isubsoft/ldap-carddav');
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
        if($username == null || $username == '' || $password == null)
        {
		    	trigger_error("Invalid credentials provided.", E_USER_NOTICE);
        	return false;
        }
        	
        if(isset($this->config['auth']['ldap']['search_bind_dn']) && $this->config['auth']['ldap']['search_bind_dn'] != '')
        {
            $bindDn = $this->config['auth']['ldap']['search_bind_dn'];
            $bindPass = (isset($this->config['auth']['ldap']['search_bind_pw']))?$this->config['auth']['ldap']['search_bind_pw']:null;

            // binding to ldap server
            $ldapBindConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['server']['ldap']);
            
						// verify binding
            if($ldapBindConn === false)
            {
		          trigger_error("Could not establish bind connection to backend server.", E_USER_WARNING);
		          throw new ServiceUnavailable();
            }
            
            $ldaptree = (isset($this->config['auth']['ldap']['search_base_dn']) && $this->config['auth']['ldap']['search_base_dn'] !== '')?$this->config['auth']['ldap']['search_base_dn']:$this->config['auth']['ldap']['base_dn'];
            $filter = Utility::replacePlaceholders($this->config['auth']['ldap']['search_filter'], ['%u' => ldap_escape($username, "", LDAP_ESCAPE_FILTER)]);

            $data = Utility::LdapQuery($ldapBindConn, $ldaptree, $filter, ['dn'], strtolower($this->config['auth']['ldap']['scope']));

            if(empty($data))
            {
            	trigger_error("Could not execute backend search.", E_USER_WARNING);
            	throw new ServiceUnavailable();
            }
            
            if($data['count'] !== 1)
        			return false;
            
            $bindDn = Utility::replacePlaceholders($this->config['auth']['ldap']['bind_dn'], ['%dn' => $data[0]['dn'], '%u' => ldap_escape($username, "", LDAP_ESCAPE_DN)]);

            try {
                $ldapUserBind = ldap_bind($ldapBindConn, $bindDn, $password);
                
                if(!$ldapUserBind)
                	return false;
            } catch (\Throwable $th) {
							trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
							throw new ServiceUnavailable();
            }
            
            $GLOBALS['currentUserPrincipalLdapConn'] = $ldapBindConn;
            return true;
        }
        else
        {
            $bindDn = Utility::replacePlaceholders($this->config['auth']['ldap']['bind_dn'], ['%u' => ldap_escape($username, "", LDAP_ESCAPE_DN)]);
            $bindPass = Utility::replacePlaceholders($this->config['auth']['ldap']['bind_pass'], ['%p' => $password]);

            // binding to ldap server
            $ldapBindConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['server']['ldap']);

            if($ldapBindConn === false)
            {
		          trigger_error("Could not establish bind connection to backend server.", E_USER_WARNING);
		          throw new ServiceUnavailable();
            }

            $GLOBALS['currentUserPrincipalLdapConn'] = $ldapBindConn;
            return true;
        }

        return false;
    }
}
