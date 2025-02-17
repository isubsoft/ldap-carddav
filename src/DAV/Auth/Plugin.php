<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\Auth;

class Plugin extends \Sabre\DAV\Auth\Plugin
{
	public function check(\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response)
	{
		$returnValue = parent::check($request, $response);
		$GLOBALS['currentUserPrincipal'] = basename($this->getCurrentPrincipal());
		return $returnValue;
	}
}
