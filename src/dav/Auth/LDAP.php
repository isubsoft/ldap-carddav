<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

namespace isubsoft\dav\Auth;

use isubsoft\dav\Utility\LDAP as Utility;
use \Sabre\DAV\Exception\ServiceUnavailable;

class LDAP extends \Sabre\DAV\Auth\Backend\AbstractBasic {


    /**
     * Store ldap directory access credentials
     *
     * @var array
     */
    public $config;

    /**
     * Store PDO resource
     *
     * @var array
     */
    public $pdo;
    
    private $systemUsersTableName = 'cards_system_user';

    /**
     * Ldap Connection.
     *
     * @var string
     */
    public $userLdapConn = null;

    public $username = null;




    /**
     * Creates the backend.
     *
     * configuration array must be provided
     * to access initial directory.
     *
     * @param array $config
     * @return void
     */
    function __construct(array $config, $pdo) {
        $this->config = $config;
        $this->pdo = $pdo;
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
        	
				try 
				{
			    $query = 'SELECT user_id FROM '. $this->systemUsersTableName;
			    $stmt = $this->pdo->prepare($query);
			    $stmt->execute([]);
			    
		      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
		          if(strtolower($username) == strtolower($row['user_id']))
							{
								error_log("A reserved username was used to authenticate. Rejected.");
								return false;
							}

							continue;
		      }
			    
			  } catch (\Throwable $th) {
			        error_log("Database query could not be executed: ".__METHOD__." at line no ".__LINE__.", ".$th->getMessage());
			  }
        
        $this->username = $username;
        
        if(($this->config['auth']['ldap']['search_bind_dn'] != '') && ($this->config['auth']['ldap']['search_bind_pw'] != ''))
        {
            $bindDn = $this->config['auth']['ldap']['search_bind_dn'];
            $bindPass = $this->config['auth']['ldap']['search_bind_pw'];

            // binding to ldap server
            $ldapBindConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['auth']['ldap']);
            
            // verify binding
            if ($ldapBindConn) {
                $ldaptree = ($this->config['auth']['ldap']['search_base_dn'] !== '') ? $this->config['auth']['ldap']['search_base_dn'] : Utility::replacePlaceholders($this->config['auth']['ldap']['base_dn'], ['%u' => $username]);
                $filter = Utility::replacePlaceholders($this->config['auth']['ldap']['search_filter'], ['%u' => $username]);

                $data = Utility::LdapQuery($ldapBindConn, $ldaptree, $filter, [], strtolower($this->config['auth']['ldap']['scope']));
                
                if($data['count'] == 1)
                {
                    $bindDn = Utility::replacePlaceholders($this->config['auth']['ldap']['bind_dn'], ['%dn' => $data[0]['dn'], '%u' => $username]);

                    try {
                        $ldapUserBind = ldap_bind($ldapBindConn, $bindDn, $password);
                        if(!$ldapUserBind)
                        {
                            return false;
                        }
                    } catch (\Throwable $th) {
                        error_log("Unknown LDAP error: ".__METHOD__.", ".$th->getMessage());
                        throw new ServiceUnavailable($th->getMessage());
                    }

                    $this->userLdapConn = $ldapBindConn;
                    return true;
                }      
            }
        }
        else
        {
            $bindDn = Utility::replacePlaceholders($this->config['auth']['ldap']['bind_dn'], ['%u' => $username]);
            $bindPass = Utility::replacePlaceholders($this->config['auth']['ldap']['bind_pass'], ['%p' => $password]);

            // binding to ldap server
            $ldapBindConn = Utility::LdapBindConnection(['bindDn' => $bindDn, 'bindPass' => $bindPass], $this->config['auth']['ldap']);

            if($ldapBindConn)
            {
                $this->userLdapConn = $ldapBindConn;
                return true;
            }
        }

        return false;
    }
}

?>
