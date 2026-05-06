<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

namespace ISubsoft\VObject;
use ISubsoft\DAV\Utility\LDAP as Utility;
use \Sabre\VObject\DateTimeParser as DateTimeParser;

date_default_timezone_set('UTC');

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

    public static function multiAllowedStatus($vCard_attr){    

        $VCard_attr_info = self::vCardMetaData();
        if(isset($VCard_attr_info[$vCard_attr]))
        {
            return (['status' => $VCard_attr_info[$vCard_attr]['multi_allowed']]);
        }

        return false;
    }

    public static function compositeAttrStatus($vCard_attr){

        $VCard_attr_info = self::vCardMetaData();
        if(isset($VCard_attr_info[$vCard_attr]))
        {
            return (['status' => $VCard_attr_info[$vCard_attr]['composite_attr']]);
        }

        return false;
    }

    public static function getDefaultParams($vCard_attr){        

        $VCard_attr_info = self::vCardMetaData();
        if(isset($VCard_attr_info[$vCard_attr]))
        {
            return ($VCard_attr_info[$vCard_attr]['parameter']);
        }

        return [];
    }


    public static function backendValueConversion($vCardAttr, $value, $backendDataFormat)
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
            
                if(Utility::notHasValue([$dateTime['date'], $dateTime['month'], $dateTime['year'], $dateTime['hour']]) == false)
                {
                    if(Utility::notHasValue([$dateTime['minute'], $dateTime['second']]) == false)
                        $cardData = $dateTime['year'] . $dateTime['month'] . $dateTime['date'] .'T'. $dateTime['hour'] . $dateTime['minute'] . $dateTime['second'] . 'Z';
                    else
                        $cardData = $dateTime['year'] . $dateTime['month'] . $dateTime['date'] .'T'. $dateTime['hour'] . 'Z';

                    $params = ['value' => 'DATE-TIME'];
                }
                elseif((Utility::notHasValue([$dateTime['date'], $dateTime['month'], $dateTime['year']]) == false) && (Utility::notHasValue([$dateTime['hour'], $dateTime['minute'], $dateTime['second']]) == true))
                {
                    $cardData = $dateTime['year'] . $dateTime['month'] . $dateTime['date'];
                    $params = ['value' => 'DATE'];
                }
                elseif((Utility::notHasValue([$dateTime['date'], $dateTime['month'], $dateTime['year']]) == true) && (Utility::notHasValue([$dateTime['hour'], $dateTime['minute'], $dateTime['second']]) == false))
                {
                    $cardData = $dateTime['hour'] . $dateTime['minute'] . $dateTime['second'] ;
                    $params = ['value' => 'DATE-AND-OR-TIME'];
                }
            }
            else
            {
                $cardData = $value;
                $params = ['value' => 'text'];
            }     
        }
        elseif($backendDataFormat == 'URI')
        {
            $cardData = $value;
            $params = ['value' => 'uri'];
        }
        elseif($backendDataFormat == 'BINARY')
        {
            if(self::$encoding_format == 'base64')
            {
                $cardData = 'data:' . finfo_buffer(finfo_open(FILEINFO_MIME), $value) . ';' . self::$encoding_format . ',' . base64_encode($value);
            }         
            $params = ['value' => 'uri'];
        }
        elseif($backendDataFormat == 'TIMESTAMP')
        {
            $dateComponent = substr($value, 0, 8);
            $timeComponent = substr($value, 8);
            
            $dateTime = DateTimeParser::parseVCardDateTime($dateComponent. 'T'. $timeComponent);

            if(Utility::notHasValue([$dateTime['date'], $dateTime['month'], $dateTime['year'], $dateTime['hour']]) == false)
            {
                if(Utility::notHasValue([$dateTime['minute'], $dateTime['second']]) == false)
                    $cardData = $dateTime['year'] . $dateTime['month'] . $dateTime['date'] .'T'. $dateTime['hour'] . $dateTime['minute'] . $dateTime['second'] . 'Z';
                else
                    $cardData = $dateTime['year'] . $dateTime['month'] . $dateTime['date'] .'T'. $dateTime['hour'] . 'Z';
            }
            
            $vCardMetaData = self::vCardMetaData();
            $vCardAttrInfo = $vCardMetaData[$vCardAttr];
            
            if(isset($vCardAttrInfo['date_time']) && $vCardAttrInfo['date_time'] === true)
                $params = ['value' => 'DATE-TIME'];
            else
                $params = ['value' => 'TEXT']; 
        }

        return  ['cardData' => $cardData, 'params' => $params];
    }

    public static function memberValue($value, $vCardAttr)
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

    public static function memberValueConversion($value, $vCardAttr)
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
