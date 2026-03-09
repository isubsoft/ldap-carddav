<?php

namespace ISubsoft\DAV\DAVACL;

class PrincipalCollection extends \Sabre\DAVACL\PrincipalCollection
{
	public function getChildForPrincipal(array $principal)
	{
		return new Principal($this->principalBackend, $principal);
	}
	
	public function getACL()
	{
		return [
		  [
		      'privilege' => '{DAV:}read',
		      'principal' => '{DAV:}authenticated',
		      'protected' => true,
		  ],
		];
	}
	
	public function getChildACL()
	{
		return [
		  [
		      'privilege' => '{DAV:}read',
		      'principal' => '{DAV:}authenticated',
		      'protected' => true,
		  ],
		  [
		      'privilege' => '{DAV:}all',
		      'principal' => '{DAV:}owner',
		      'protected' => true,
		  ],
		];
	}
}
