<?php

namespace isubsoft\dav\Utility;

class LDAP {

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
}