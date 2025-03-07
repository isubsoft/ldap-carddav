<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\DAV\CardDAV;

class Plugin extends \Sabre\CardDAV\Plugin
{
	/**
	 * Restricting size of a card to be created/updated to 64 KiB which 
	 * should be sufficient for including commonly used properties.
	 */
	protected $maxResourceSize = 65536;
	
	public function setResourceSize(int $sizeInBytes)
	{
		if($sizeInBytes > 0)
			$this->maxResourceSize = $sizeInBytes;
			
		return;
	}
}
