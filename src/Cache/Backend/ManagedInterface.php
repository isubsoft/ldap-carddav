<?php
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

namespace ISubsoft\Cache\Backend;

interface ManagedInterface
{
	public function evictStale(int $batchSize = 0);
}
