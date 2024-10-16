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
                    'composite_attr'=> [ 0 => 'last_name', 1 => 'first_name', 2 => 'middle_name', 3 => 'prefix', 4 => 'sufix' ],
                    'parameter' => []],

        'EMAIL' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter' => []],

        'ORG' => ['multi_allowed' => false,
                    'composite_attr'=> [ 0 => 'org_name', 1 => 'org_unit_name'],
                    'parameter' => []],

        'TITLE' => ['multi_allowed' => false,
                        'composite_attr'=> false,
                        'parameter' => []],

        'NICKNAME' => ['multi_allowed' => false,
                            'composite_attr'=> false,
                            'parameter' => []],

        'PHOTO' => ['multi_allowed' => false,
                        'composite_attr'=> false,
                        'parameter' => []],

        'NOTE' => ['multi_allowed' => false,
                        'composite_attr'=> false,
                        'parameter' => []],

        'TEL' => ['multi_allowed' => true,
                    'composite_attr'=> false,
                    'parameter' => ['TYPE', 'VALUE']]
                    
          
    ];

    function multi_allowed_status($vCard_attr){    

        if(isset(self::$VCard_attr_info[$vCard_attr]))
        {
            return (['status' => self::$VCard_attr_info[$vCard_attr]['multi_allowed']]);
        }

        return false;
    }

    function composite_attr_status($vCard_attr){

        if(isset(self::$VCard_attr_info[$vCard_attr]))
        {
            return (['status' => self::$VCard_attr_info[$vCard_attr]['composite_attr']]);
        }

        return false;
    }

    function parameter_status($vCard_attr){        

        if(isset(self::$VCard_attr_info[$vCard_attr]))
        {
            return (['parameter' => self::$VCard_attr_info[$vCard_attr]['parameter']]);
        }

        return false;
    }

}

?>