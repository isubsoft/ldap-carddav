<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

namespace ISubsoft\VObject;
use ISubsoft\DAV\Utility\LDAP as Utility;
use \Sabre\VObject\DateTimeParser as DateTimeParser;

class Reader extends \Sabre\VObject\Reader{

    private static $encoding_format = 'base64';
    /**
     * Vcard
     *
     * @var array
     */

    public static function vCardMetaData(){

        $json = file_get_contents(__CONF_DIR__ . '/vcard_metadata.json'); 

        if ($json === false) {
            return null;
        }

        $jsonData = json_decode($json, true); 

        if ($jsonData === null) {
            return null;
        }

        return $jsonData;
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

    function getDefaultParams($vCard_attr){        

        $VCard_attr_info = self::vCardMetaData();
        if(isset($VCard_attr_info[$vCard_attr]))
        {
            return ($VCard_attr_info[$vCard_attr]['parameter']);
        }

        return [];
    }


    function backendValueConversion($vCardAttr, $value, $backendDataFormat)
    {
        $backendDataFormat = strtoupper($backendDataFormat);
        $cardData = '';
        $params = [];

        if($backendDataFormat == 'TEXT')
        {
            $vCardMetaData = self::vCardMetaData();
            $vCardAttrInfo = $vCardMetaData[$vCardAttr];
            
            if(isset($vCardAttrInfo['date_time']) && $vCardAttrInfo['date_time'] === true)
            {
                $dateTime = DateTimeParser::parseVCardDateTime($value);
            
                if(Utility::hasNotValue([$dateTime['date'], $dateTime['month'], $dateTime['year'], $dateTime['hour'], $dateTime['minute'], $dateTime['second']]) == false)
                {
                    $cardData = $dateTime['year'].'-'.$dateTime['month'].'-'.$dateTime['date'].'T'.$dateTime['hour'].':'.$dateTime['minute'].':'.$dateTime['second'];
                    $params = ['value' => 'DATE-TIME'];
                }
                else if((Utility::hasNotValue([$dateTime['date'], $dateTime['month'], $dateTime['year']]) == false) && (Utility::hasNotValue([$dateTime['hour'], $dateTime['minute'], $dateTime['second']]) == true))
                {
                    $cardData = $dateTime['year'].'-'.$dateTime['month'].'-'.$dateTime['date'];
                    $params = ['value' => 'DATE'];
                }
                else if((Utility::hasNotValue([$dateTime['date'], $dateTime['month'], $dateTime['year']]) == true) && (Utility::hasNotValue([$dateTime['hour'], $dateTime['minute'], $dateTime['second']]) == false))
                {
                    $cardData = $dateTime['hour'].':'.$dateTime['minute'].':'.$dateTime['second'];
                    $params = ['value' => 'TIME'];
                }
            }
            else
            {
                $cardData = $value;
                $params = ['value' => 'text'];
            }     
        }
        else if($backendDataFormat == 'URI')
        {
            $cardData = $value;
            $params = ['value' => 'uri'];
        }
        else if($backendDataFormat == 'BINARY')
        {
            if(self::$encoding_format == 'base64')
            {
                $cardData = 'data:' . finfo_buffer(finfo_open(FILEINFO_MIME), $value) . ';' . self::$encoding_format . ',' . base64_encode($value);
            }         
            $params = ['value' => 'uri'];
        }
        else if($backendDataFormat == 'TIMESTAMP')
        {
            $dateTime = DateTimeParser::parseVCardDateTime($value);

            $cardData = $dateTime['year'].'-'.$dateTime['month'].'-'.$dateTime['date'].'T'.$dateTime['hour'].':'.$dateTime['minute'].':'.$dateTime['second'];
            $params = ['value' => 'DATE-TIME'];
        }

        return  ['cardData' => $cardData, 'params' => $params];
    }

    function memberValue($value, $vCardAttr)
    {
        $valueComponent = parse_url($value);
        $vCardMetaData = self::vCardMetaData();
        $vCardInfo = $vCardMetaData[$vCardAttr];
        $memberValue = '';

        if(isset($valueComponent['scheme']) && (isset($vCardInfo['uri_schemes']['embedded'])) && (in_array($valueComponent['scheme'], $vCardInfo['uri_schemes']['embedded'])))
        {
            $pathComponent = explode(':', $valueComponent['path']);
            if($pathComponent[0] == 'uuid')
            {
                $memberValue = $pathComponent[1];
            }
        } 
        return $memberValue;
    }

    function memberValueConversion($value, $vCardAttr)
    {
        $vCardMetaData = self::vCardMetaData();
        $vCardInfo = $vCardMetaData[$vCardAttr];
        $memberValue = '';

        if(isset($vCardInfo['uri_schemes']['embedded']) && $value != '' && $value != null)
        {
            $schema = $vCardInfo['uri_schemes']['embedded'][0];
            $path = 'uuid:'. $value;

            $memberValue = $schema . ':' . $path;
        }
        return $memberValue;
    }

}
