<?php

namespace isubsoft\Vobject;

class Reader extends \Sabre\VObject\Reader{

    /**
     * Vcard
     *
     * @var array
     */

    private static function vCardMetaData(){

        $json = file_get_contents(__DIR__.'/../../conf/vcard_metadata.json'); 

        if ($json === false) {
            return null;
        }

        $json_data = json_decode($json, true); 

        if ($json_data === null) {
            return null;
        }

        return $json_data;
    }

    function multiAllowedStatus($vCard_attr){    

        $VCard_attr_info = self::vCardMetaData();
        if(isset($VCard_attr_info[$vCard_attr]))
        {
            return (['status' => $VCard_attr_info[$vCard_attr]['multi_allowed']]);
        }

        return false;
    }

    function compositeAttrStatus($vCard_attr){

        $VCard_attr_info = self::vCardMetaData();
        if(isset($VCard_attr_info[$vCard_attr]))
        {
            return (['status' => $VCard_attr_info[$vCard_attr]['composite_attr']]);
        }

        return false;
    }

    function parameterStatus($vCard_attr){        

        $VCard_attr_info = self::vCardMetaData();
        if(isset($VCard_attr_info[$vCard_attr]))
        {
            return (['parameter' => $VCard_attr_info[$vCard_attr]['parameter']]);
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