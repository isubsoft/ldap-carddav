<?php
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

namespace isubsoft\Vobject;
use isubsoft\dav\Utility\LDAP as Utility;

class Reader extends \Sabre\VObject\Reader{


    private static $file_uri_schemes = [
                                            'embedded' => ['data'],
                                            'remote' => ['http', 'https', 'ftp', 'ftps']
                                        ];

    private static $encoding_format = 'base64';
    /**
     * Vcard
     *
     * @var array
     */

    private static function vCardMetaData(){

        $json = file_get_contents($GLOBALS['__CONF_DIR__'].'/vcard_metadata.json'); 

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

    function backendValue($value, $vcardAttr, $backendDataFormat)
    {
        $vCardDataFormat = strtoupper($value->getValueType());
        $backendDataFormat = strtoupper($backendDataFormat);
        $backendvalue = '';
        

        if($vCardDataFormat == 'TEXT')
        {
            if($backendDataFormat == 'TEXT' || $backendDataFormat == 'BINARY')
            {
                $backendvalue = (string)$value;
            }
        }
        else if($vCardDataFormat == 'URI')
        {
            if($backendDataFormat == 'TEXT')
            {
                $valueComponent = parse_url($value);
                $vCardMetaData = self::vCardMetaData();
                $vCardInfo = $vCardMetaData[$vcardAttr];

                if(isset($valueComponent['scheme']) && (isset($vCardInfo['uri_schemes']['embedded'])) && (in_array($valueComponent['scheme'], $vCardInfo['uri_schemes']['embedded'])))
                {
                    $backendvalue = $valueComponent['path'];
                }
                else if(isset($valueComponent['scheme']) && (in_array($valueComponent['scheme'], self::$file_uri_schemes['embedded']) || in_array($valueComponent['scheme'], self::$file_uri_schemes['remote'])))
                {
                    $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME), file_get_contents((string)$value));
                    $mimeType = explode(';', $mimeType)[0];
                    
                    if($mimeType == 'text/plain')
                    {
                        $backendvalue = file_get_contents((string)$value);
                    }
                }
            }
            else if($backendDataFormat == 'BINARY')
            {
                $valueComponent = parse_url($value);             
                if(isset($valueComponent['scheme']) && (in_array($valueComponent['scheme'], self::$file_uri_schemes['embedded']) || in_array($valueComponent['scheme'], self::$file_uri_schemes['remote'])))
                {
                    $backendvalue = file_get_contents((string)$value);
                }
                else
                {
                    $backendvalue = (string)$value;
                }
            }
            else if($backendDataFormat == 'URI')
            {
                $valueComponent = parse_url($value);
                if(isset($valueComponent['scheme']) && in_array($valueComponent['scheme'], $uriSchema['remote']))
                {
                    $backendvalue = (string)$value;
                }
            }
        }
        else if($vCardDataFormat == 'BINARY')
        {
            if($backendDataFormat == 'BINARY')
            {
                $backendvalue = (string)$value;
            }
        }

        return  $backendvalue;
    }

    function backendValueConversion($value, $backendDataFormat)
    {
        $backendDataFormat = strtoupper($backendDataFormat);
        $cardData = '';
        $params = [];

        if($backendDataFormat == 'TEXT')
        {
            $cardData = $value;
            $params = ['value' => 'text'];
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
                $cardData = base64_encode($value);
            }
            
            $params = ['value' => 'BINARY', 'mediatype' => finfo_buffer(finfo_open(FILEINFO_MIME), $value), 'encoding' => 'B'];
        }

        return  ['cardData' => $cardData, 'params' => $params];
    }

    function memberValue($value, $vcardAttr)
    {
        $valueComponent = parse_url($value);
        $vCardMetaData = self::vCardMetaData();
        $vCardInfo = $vCardMetaData[$vcardAttr];
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

    function memberValueConversion($value, $vcardAttr)
    {
        $vCardMetaData = self::vCardMetaData();
        $vCardInfo = $vCardMetaData[$vcardAttr];
        $memberValue = '';

        if(isset($vCardInfo['uri_schemes']['embedded']))
        {
            $schema = $vCardInfo['uri_schemes']['embedded'][0];
            $path = 'uuid:'. $value;

            $memberValue = $schema.$path;
        }
        return $memberValue;
    }

}

?>
