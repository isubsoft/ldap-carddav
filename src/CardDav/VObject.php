<?php

namespace isubsoft\dav\CardDav;

class VObject extends \Sabre\VObject\Reader{

    /**
     * Vcard
     *
     * @var array
     */
    private static $VCard_attr_info = [

        'FN' => ['multi_allowed' => false,
                    'composite_attr'=> false,
                    'parameter_dependent' => false,
                    'parameter' => ''],
        
        'N' => ['multi_allowed' => false,
                    'composite_attr'=> true,
                    'parameter_dependent' => false,
                    'parameter' => ''],

        'EMAIL' => ['multi_allowed' => true,
                        'composite_attr'=> false,
                        'parameter_dependent' => false,
                        'parameter' => ''],

        'ORG' => ['multi_allowed' => false,
                    'composite_attr'=> true,
                    'parameter_dependent' => false,
                    'parameter' => ''],

        'TITLE' => ['multi_allowed' => false,
                        'composite_attr'=> false,
                        'parameter_dependent' => false,
                        'parameter' => ''],

        'NICKNAME' => ['multi_allowed' => false,
                            'composite_attr'=> false,
                            'parameter_dependent' => false,
                            'parameter' => ''],

        'PHOTO' => ['multi_allowed' => false,
                        'composite_attr'=> false,
                        'parameter_dependent' => false,
                        'parameter' => ''],

        'NOTE' => ['multi_allowed' => false,
                        'composite_attr'=> false,
                        'parameter_dependent' => false,
                        'parameter' => ''],

        'TEL' => ['multi_allowed' => true,
                    'composite_attr'=> false,
                    'parameter_dependent' => true,
                    'parameter' => 'TYPE']
                    
          
    ];

    function multi_allowed_status($vCard_attr){
        
        foreach(self::$VCard_attr_info as $attr => $info){

            if($attr == $vCard_attr)
            {
                return (['status' => $info['multi_allowed']]);
            }
        }

        return false;
    }

    function composite_attr_status($vCard_attr){
        
        foreach(self::$VCard_attr_info as $attr => $info){

            if($attr == $vCard_attr)
            {
                return (['status' => $info['composite_attr']]);
            }
        }

        return false;
    }

    function parameter_dependency_status($vCard_attr){
        
        foreach(self::$VCard_attr_info as $attr => $info){

            if($attr == $vCard_attr)
            {
                return (['status' => $info['parameter_dependent'], 'parameter' => $info['parameter']]);
            }
        }

        return false;
    }

}

?>