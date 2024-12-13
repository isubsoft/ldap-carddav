<?php

namespace isubsoft\Vobject;

class Reader extends \Sabre\VObject\Reader{

    /**
     * Vcard
     *
     * @var array
     */
    private static $VCard_attr_info = [

        'FN' => ['multi_allowed' => false,
                    'composite_attr'=> false,
                    'parameter' => []],
        
        'N' => ['multi_allowed' => false,
                    'composite_attr'=> [ 0 => 'last_name', 1 => 'first_name', 2 => 'middle_name', 3 => 'prefix', 4 => 'suffix' ],
                    'parameter' => []],

        'EMAIL' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => []],

        'ORG' => ['multi_allowed' => true,
                    'composite_attr'=> [ 0 => 'org_name', 1 => 'org_unit_name'],
                    'parameter' => []],

        'TITLE' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => []],

        'NICKNAME' => ['multi_allowed' => false,
                            'composite_attr'=> false,
                            'parameter' => []],

        'PHOTO' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => ['TYPE', 'VALUE', 'MEDIATYPE', 'ENCODING']],

        'NOTE' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => []],

        'TEL' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => ['TYPE', 'VALUE']],

        'ADR' => ['multi_allowed' => true,
                        'composite_attr'=> [ 0 => 'po_box', 1 => 'house_no', 2 => 'street', 3 => 'locality', 4 => 'province', 5 => 'postal_code', 6 => 'country'],
                        'parameter' => ['TYPE', 'VALUE']],

        'BDAY' => ['multi_allowed' => false,
                        'composite_attr'=> false,
                        'parameter' => []],

        'ANNIVERSARY' => ['multi_allowed' => false,
                                'composite_attr'=> false,
                                'parameter' => []],

        'GENDER' => ['multi_allowed' => false,
                            'composite_attr'=> [ 0 => 'sex', '1' => 'gender_identity'],
                            'parameter' => []],

        'IMPP' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => ['TYPE', 'VALUE']],

        'LANG' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => ['TYPE', 'VALUE']],

        'TZ' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => ['VALUE']],

        'GEO' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => []],
        
        'ROLE' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => []],

        'LOGO' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => []],

        'MEMBER' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => []],

        'RELATED' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => ['VALUE']],        

        'BIRTHPLACE' => ['multi_allowed' => false,
                            'composite_attr'=> false,
                            'parameter' => ['VALUE', 'LANGUAGE']],

        'DEATHPLACE' => ['multi_allowed' => false,
                            'composite_attr'=> false,
                            'parameter' => ['VALUE', 'LANGUAGE']],

        'DEATHDATE' => ['multi_allowed' => false,
                            'composite_attr'=> false,
                            'parameter' => ['VALUE', 'CALSCALE', 'LANGUAGE']],

        'EXPERTISE' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => ['LEVEL', 'INDEX']],

        'HOBBY' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => ['LEVEL', 'INDEX']],

        'INTEREST' => ['multi_allowed' => true,
                            'composite_attr'=> false,
                            'parameter' => ['LEVEL', 'INDEX']],

        'ORG-DIRECTORY' => ['multi_allowed' => true,
                                'composite_attr'=> false,
                                'parameter' => ['PREF', 'INDEX']],

        'CONTACT-URI' => ['multi_allowed' => true,
                                'composite_attr'=> false,
                                'parameter' => []],

        'GRAMGENDER' => ['multi_allowed' => true,
                                'composite_attr'=> false,
                                'parameter' => ['LANGUAGE']],

        'LANGUAGE' => ['multi_allowed' => false,
                                'composite_attr'=> false,
                                'parameter' => []],

        'PRONOUNS' => ['multi_allowed' => true,
                                'composite_attr'=> false,
                                'parameter' => ['LANGUAGE', 'PREF', 'TYPE', 'ALTID']],

        'SOCIALPROFILE' => ['multi_allowed' => true,
                                'composite_attr'=> false,
                                'parameter' => ['SERVICE-TYPE', 'VALUE']],

        'JSPROP' => ['multi_allowed' => true,
                                'composite_attr'=> false,
                                'parameter' => ['JSPTR']]
                    
          
    ];

    function multiAllowedStatus($vCard_attr){    

        if(isset(self::$VCard_attr_info[$vCard_attr]))
        {
            return (['status' => self::$VCard_attr_info[$vCard_attr]['multi_allowed']]);
        }

        return false;
    }

    function compositeAttrStatus($vCard_attr){

        if(isset(self::$VCard_attr_info[$vCard_attr]))
        {
            return (['status' => self::$VCard_attr_info[$vCard_attr]['composite_attr']]);
        }

        return false;
    }

    function parameterStatus($vCard_attr){        

        if(isset(self::$VCard_attr_info[$vCard_attr]))
        {
            return (['parameter' => self::$VCard_attr_info[$vCard_attr]['parameter']]);
        }

        return false;
    }

    function attributeType($attrParams){

        if(array_key_exists('ENCODING', $attrParams) && ( in_array('B', $attrParams['ENCODING']) || in_array('BASE64', $attrParams['ENCODING'])))
        {
            return 'BINARY';
        }
        else if(array_key_exists('VALUE', $attrParams) && ( in_array('URI', $attrParams['VALUE']) || in_array('URL', $attrParams['VALUE'])))
        {
            return 'FILE';
        }
        else
        {
            return 'TEXT';
        }
    }

}

?>