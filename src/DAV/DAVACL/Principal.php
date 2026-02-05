<?php

namespace ISubsoft\DAV\DAVACL;

class Principal extends \Sabre\DAVACL\Principal
{
	public function getACL()
	{
		return [
		  [
		      'privilege' => '{DAV:}read',
		      'principal' => '{DAV:}owner',
		      'protected' => true,
		  ],
		];
	}
}
