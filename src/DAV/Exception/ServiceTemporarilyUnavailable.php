<?php

/***************************************************************************
*
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
* 
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <https://www.gnu.org/licenses/>.
*
***************************************************************************/

namespace ISubsoft\DAV\Exception;

class ServiceTemporarilyUnavailable extends \Sabre\DAV\Exception
{
		protected $httpResponseHeaders = [];
    /**
     * This method allows the exception to return any extra HTTP response headers.
     *
     * The headers must be returned as an array.
     *
     * @return array
     */
    public function setHTTPHeaders(array $headers)
    {
        $this->httpResponseHeaders = array_merge($this->httpResponseHeaders, $headers);
    }
    
    /**
     * Returns the HTTP statuscode for this exception.
     *
     * @return int
     */
    public function getHTTPCode()
    {
        return 503;
    }
    
    /**
     * This method allows the exception to return any extra HTTP response headers.
     *
     * The headers must be returned as an array.
     *
     * @return array
     */
    public function getHTTPHeaders(\Sabre\DAV\Server $server)
    {
        return $this->httpResponseHeaders;
    }
}
