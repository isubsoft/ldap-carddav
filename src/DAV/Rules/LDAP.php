<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\Rules;

use ISubsoft\VObject\Reader as Reader;
use ISubsoft\DAV\Utility\LDAP as Utility;

class LDAP {

    private static $file_uri_schemes = [
        'embedded' => ['data'],
        'remote' => ['http', 'https', 'ftp', 'ftps']
    ];

    




    public static function mapVcardProperty($vCardAttr, $mappLdapConfig, $vObj)
    {
        $compositeAttrStatus = Reader::compositeAttrStatus($vCardAttr);

        $iterativeArr = Utility::isMultidimensional($mappLdapConfig);
        $vCardParams = Utility::getVCardAttrParams($vObj, Reader::getDefaultParams($vCardAttr));
        $vCardParamListsMatch = self::isVcardParamsMatch($mappLdapConfig, $vCardParams, $iterativeArr);
        $ldapBackendMap = [];

        if($iterativeArr)
        {
            foreach($mappLdapConfig as $configIndex => $ldapConfigInfo)
            {
                if(empty($vCardParams) || ($vCardParamListsMatch['status'] == true))
                {
                    $backendDataFormat = strtoupper($ldapConfigInfo['backend_data_format']);

                    if(empty($vCardParams) && $backendDataFormat == 'BINARY' && isset($ldapConfigInfo['backend_data_mediatype']) && !empty($ldapConfigInfo['backend_data_mediatype']))
                    {                    
                        $ldapBackendMap = self::getvCardPropertyMap($vObj, $ldapConfigInfo, $compositeAttrStatus['status']);
                    }
                    else if($configIndex === $vCardParamListsMatch['configIndex'])
                    {   
                        $ldapBackendMap = self::getvCardPropertyMap($vObj, $ldapConfigInfo, $compositeAttrStatus['status']);
                    }
                }
            }
        }
        else
        {
            if(empty($vCardParams) || ($vCardParamListsMatch['status'] == true))
            {
                $ldapBackendMap = self::getvCardPropertyMap($vObj, $mappLdapConfig, $compositeAttrStatus['status']);
            }
        }

        return $ldapBackendMap;
    }

    public static function isVcardParamsMatch($ldapKey, $vCardParams, $iterativeArr)
    {
        if($iterativeArr)
        {
            foreach($ldapKey as $Index => $ldapKeyInfo)
            {            
                foreach($ldapKeyInfo['parameters'] as $ParamList)
                {
                    if($ParamList == null)
                    {
                        continue;
                    }
                    
                    $allParamsValuesMatch = true;
                    
                    foreach($ParamList as $paramListKey => $paramListValue)
                    {
                        if(! array_key_exists($paramListKey, $vCardParams))
                        {
                            $allParamsValuesMatch = false;
                            break;
                        }
                        
                        $paramValues = explode(',', $paramListValue);
                        foreach($paramValues as $paramValue)
                        {
                            if(! in_array(strtoupper($paramValue), $vCardParams[$paramListKey]))
                            {
                                $allParamsValuesMatch = false;
                            }
                        }
                        if($allParamsValuesMatch == false)
                        {
                            break;
                        }
                        
                    }
    
                    if($allParamsValuesMatch == true)
                    {
                        return (['status' => true, 'configIndex' => $Index ]);
                    }
                }
            }

            foreach($ldapKey as $Index => $ldapKeyInfo)
            {
                if(in_array(null, $ldapKeyInfo['parameters']))
                {                           
                    return (['status' => true, 'configIndex' => $Index ]);
                }
            }
        }
        else
        {
            foreach($ldapKey['parameters'] as $ParamList)
            {
                if($ParamList == null)
                {
                    continue;
                }
                
                $allParamsValuesMatch = true;
                
                foreach($ParamList as $paramListKey => $paramListValue)
                {
                    if(! array_key_exists($paramListKey, $vCardParams))
                    {
                        $allParamsValuesMatch = false;
                        break;
                    }
                    
                    $paramValues = explode(',', $paramListValue);
                    foreach($paramValues as $paramValue)
                    {
                        if(! in_array(strtoupper($paramValue), $vCardParams[$paramListKey]))
                        {
                            $allParamsValuesMatch = false;
                        }
                    }
                    if($allParamsValuesMatch == false)
                    {
                        break;
                    }
                    
                }

                if($allParamsValuesMatch == true)
                {
                    return (['status' => true, 'configIndex' => '' ]);
                }
            }
        }

        return (['status' => false, 'configIndex' => '' ]);
    } 

    public static function compositeLdapBackendValue($vObj, $ldapKey, $mapCompositeAttr)
    {
        $vCardPropValueArr = $vObj->getParts();
        $ldapBackendValueMap = [];

        if(is_array($ldapKey['backend_attribute']))
        {
            foreach($ldapKey['backend_attribute'] as $propKey => $backendAttr)
            {
                $propIndex = array_search($propKey, $mapCompositeAttr);
                if($propIndex !== false)
                {
                    if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                    {
                        $ldapBackendValueMap[strtolower($backendAttr)] = $vCardPropValueArr[$propIndex];
                    }
                }
            }
        }
        else
        {
            $newLdapKey = strtolower($ldapKey['backend_attribute']);
            $ldapAttrValueArr = [];

            if(isset($ldapKey['map_component_separator']) && $ldapKey['map_component_separator'] != '')
            {
                foreach ($mapCompositeAttr as $propIndex => $propKey) 
                {
                    if(isset($vCardPropValueArr[$propIndex]) && $vCardPropValueArr[$propIndex] != '')
                    {
                        $ldapAttrValueArr[] = $vCardPropValueArr[$propIndex];
                    }
                    else
                    {
                        $ldapAttrValueArr[] = '';
                    }
                }
                $ldapBackendValueMap[$newLdapKey] = implode($ldapKey['map_component_separator'], $ldapAttrValueArr);
            }            
        }
        return $ldapBackendValueMap;
    }

    public static function getvCardPropertyMap($vObj, $mappLdapConfig, $mapCompositeAttr)
    {
        $vCardDataFormat = strtoupper($vObj->getValueType());
        $backendDataFormat = strtoupper($mappLdapConfig['backend_data_format']);
        $valueComponent = parse_url($vObj);
        $ldapBackendMap = [];

        if($vCardDataFormat == 'TEXT')
        {
            if($backendDataFormat == 'TEXT' || $backendDataFormat == 'BINARY')
            {
                if($mapCompositeAttr)
                {
                    $ldapBackendMap = self::compositeLdapBackendValue($vObj, $mappLdapConfig, $mapCompositeAttr);
                }
                else
                {
                    $newLdapKey = strtolower($mappLdapConfig['backend_attribute']);
                    $backendvalue = (string)$vObj;
                    $ldapBackendMap = [$newLdapKey => $backendvalue];
                }                                             
            }
        }
        else if($vCardDataFormat == 'URI')
        {
            if($backendDataFormat == 'TEXT')
            {                
                $vCardMetaData = Reader::vCardMetaData();
                $vCardInfo = $vCardMetaData[$vcardAttr];
                $newLdapKey = strtolower($mappLdapConfig['backend_attribute']);
            
                if(isset($valueComponent['scheme']) && (isset($vCardInfo['uri_schemes']['embedded'])) && (in_array($valueComponent['scheme'], $vCardInfo['uri_schemes']['embedded'])))
                {
                    $backendvalue = $valueComponent['path'];
                    $ldapBackendMap = [$newLdapKey => $backendvalue];
                }
                else if(isset($valueComponent['scheme']) && (in_array($valueComponent['scheme'], self::$file_uri_schemes['embedded']) || in_array($valueComponent['scheme'], self::$file_uri_schemes['remote'])))
                {
                    $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME), file_get_contents((string)$vObj));
                    $mimeType = explode(';', $mimeType)[0];

                    if($mimeType == 'text/plain')
                    {
                        $backendvalue = file_get_contents((string)$vObj);
                        $ldapBackendMap = [$newLdapKey => $backendvalue];
                    }
                }
            }
            else if($backendDataFormat == 'BINARY')
            {    
                if(isset($valueComponent['scheme']) && (in_array($valueComponent['scheme'], self::$file_uri_schemes['embedded']) || in_array($valueComponent['scheme'], self::$file_uri_schemes['remote'])))
                {
                    $isMapp = false;
                    if(isset($mappLdapConfig['backend_data_mediatype']) && !empty($mappLdapConfig['backend_data_mediatype']))
                    {
                        $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME), file_get_contents((string)$vObj));
                        $mimeType = explode(';', $mimeType)[0];
                        
                        if(in_array($mimeType, $mappLdapConfig['backend_data_mediatype']))
                        {
                            $isMapp = true;
                        }
                    }
                    else
                    {
                        $isMapp = true;
                    }

                    if($isMapp === true)
                    {
                        $newLdapKey = strtolower($mappLdapConfig['backend_attribute']);
                        $backendvalue = file_get_contents((string)$vObj);
                        $ldapBackendMap = [$newLdapKey => $backendvalue];
                    }                        
                }
                else
                {
                    $isMapp = false;
                    if(isset($mappLdapConfig['backend_data_mediatype']) && !empty($mappLdapConfig['backend_data_mediatype']))
                    {
                        $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME), (string)$vObj);
                        $mimeType = explode(';', $mimeType)[0];

                        if(in_array($mimeType, $mappLdapConfig['backend_data_mediatype']))
                        {
                            $isMapp = true;
                        }
                    }
                    else
                    {
                        $isMapp = true;
                    }

                    if($isMapp === true)
                    {
                        if($mapCompositeAttr)
                        {
                            $ldapBackendMap = self::compositeLdapBackendValue($vObj, $mappLdapConfig, $mapCompositeAttr);
                        }
                        else
                        {
                            $newLdapKey = strtolower($mappLdapConfig['backend_attribute']);
                            $backendvalue = (string)$vObj;
                            $ldapBackendMap = [$newLdapKey => $backendvalue];
                        } 
                    }                         
                }
            }
            else if($backendDataFormat == 'URI')
            {
                if(isset($valueComponent['scheme']) && in_array($valueComponent['scheme'], $uriSchema['remote']))
                {
                    if($mapCompositeAttr)
                    {
                        $ldapBackendMap = self::compositeLdapBackendValue($vObj, $mappLdapConfig, $mapCompositeAttr);
                    }
                    else
                    {
                        $newLdapKey = strtolower($mappLdapConfig['backend_attribute']);
                        $backendvalue = (string)$vObj;
                        $ldapBackendMap = [$newLdapKey => $backendvalue];
                    }
                }
            }
        }
        else if($vCardDataFormat == 'BINARY')
        {
            if($backendDataFormat == 'BINARY')
            {
                $isMapp = false;
                if(isset($mappLdapConfig['backend_data_mediatype']) && !empty($mappLdapConfig['backend_data_mediatype']))
                {
                    $mimeType = finfo_buffer(finfo_open(FILEINFO_MIME), (string)$vObj);
                    $mimeType = explode(';', $mimeType)[0];

                    if(in_array($mimeType, $mappLdapConfig['backend_data_mediatype']))
                    {
                        $isMapp = true;
                    }
                }
                else
                {
                    $isMapp = true;
                }

                if($isMapp === true)
                {
                    if($mapCompositeAttr)
                    {
                        $ldapBackendMap = self::compositeLdapBackendValue($vObj, $mappLdapConfig, $mapCompositeAttr);
                    }
                    else
                    {
                        $newLdapKey = strtolower($mappLdapConfig['backend_attribute']);
                        $backendvalue = (string)$vObj;
                        $ldapBackendMap = [$newLdapKey => $backendvalue];
                    }
                }                     
            }
        }

        return $ldapBackendMap;
    }
}
