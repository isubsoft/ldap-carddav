<?php 
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/*import database connection*/
require_once __DIR__ . '/Bootstrap.php';

/*database Tables*/
$addressBooksTableName = 'cards_addressbook';
$userTableName = 'cards_user';
$backendMapTableName = 'cards_backend_map';
$fullSyncTableName = 'cards_full_sync';

$initialized = true;

function addAddressBook($addressbookName = null)
{
	$config = $GLOBALS['config'];
	$addressBooksTableName = $GLOBALS['addressBooksTableName'];
	$userTableName = $GLOBALS['userTableName'];
	$pdo = $GLOBALS['pdo'];
	
	if($addressbookName == null)
	{
		trigger_error("Address book name not provided.", E_USER_NOTICE);
		return false;
	}
		
	try
	{
		if(!isset($config['card']['addressbook']['ldap'][$addressbookName]))
		{
				trigger_error("Address book is not configured in configuration file.", E_USER_WARNING);
				return false;    	
		}
    
	  	$userSpecific = (!isset($config['card']['addressbook']['ldap'][$addressbookName]['user_specific']) || $config['card']['addressbook']['ldap'][$addressbookName]['user_specific'] === null)?true:(bool)$config['card']['addressbook']['ldap'][$addressbookName]['user_specific'];
	  	$writable = (!isset($config['card']['addressbook']['ldap'][$addressbookName]['writable']) || $config['card']['addressbook']['ldap'][$addressbookName]['writable'] === null)?true:(bool)$config['card']['addressbook']['ldap'][$addressbookName]['writable'];
	  
		$query = 'INSERT INTO '. $addressBooksTableName .' (addressbook_id, user_specific, writable) VALUES (?, ?, ?)';
		$stmt = $pdo->prepare($query);
		$stmt->execute([$addressbookName, (int)$userSpecific, (int)$writable]);
		echo "Address book '$addressbookName' has been successfully added to sync database.\n";
    
  	} catch (\Throwable $th) {
    	trigger_error("Some unexpected error occurred while executing database operation - ".$th->getMessage(), E_USER_WARNING);
			return false;
  }
	
	return true;
}

try {
	$query = 'SELECT * FROM '. $addressBooksTableName;
	$stmt = $pdo->prepare($query);
	$stmt->execute([]);

	$row = $stmt->fetch(\PDO::FETCH_ASSOC);

	if($row === false)
		$initialized = false;
}
catch (\Throwable $th) {
    trigger_error("Some unexpected error occurred in database - ".$th->getMessage(), E_USER_WARNING);
    exit(1);
}

if(isset($argv[1]) && $argv[1] == 'init')
{
		echo "Initializing sync database ...\n";
		
		if($initialized)
		{
			trigger_error("Sync database has already been initialized", E_USER_NOTICE);
		  exit;
		}
		
    try {
      	if(isset($config['card']['addressbook']['ldap']))
      	{
          	foreach($config['card']['addressbook']['ldap'] as $addressBooksName => $values)
          	{
          		if(addAddressBook($addressBooksName) == false)
          		{
          			trigger_error("Failed to add address book '$addressBooksName'. Sync database initialization failed. Reverting changes.", E_USER_WARNING);
          		
					$query = 'DELETE * FROM '. $addressBooksTableName;
					$stmt = $pdo->prepare($query);
					$stmt->execute([]);
					exit(1);
          		}
          	}
      	}
      
      	echo "Address book(s) successfully imported.\n";

    } catch (\Throwable $th) {
        trigger_error("Some unexpected error occurred in database - ".$th->getMessage(), E_USER_WARNING);
        exit(1);
    }
    
  exit;
}
elseif(isset($argv[1]) && $argv[1] == 'housekeeping')
{
	$batchSize = 1000;
	
	if(isset($argv[2]) && settype($argv[2], 'integer'))
			$batchSize = $argv[2];
			
	echo "Housekeeping sync database ...\n";
	
	try {
		$query1 = 'SELECT t1.user_id, t1.addressbook_id, t1.card_uri FROM ' . $backendMapTableName . ' AS t1 WHERE t1.delete_sync_token IS NOT NULL AND t1.delete_sync_token < (SELECT t2.sync_token FROM ' . $fullSyncTableName . ' AS t2 WHERE t2.user_id = t1.user_id AND t2.addressbook_id = t1.addressbook_id)';
    $stmt1 = $pdo->prepare($query1);
    $stmt1->execute();
    
    $rowCount = 0;
		$inTransaction = false;
    
    while ($row = $stmt1->fetch(\PDO::FETCH_ASSOC)) {
		  $syncDbUserId = $row['user_id'];
		  $addressBookId = $row['addressbook_id'];
		  $cardUri = $row['card_uri'];
		  
			if($batchSize > 1 && !$inTransaction)
				$pdo->beginTransaction();
			
			$query2 = 'DELETE FROM ' . $backendMapTableName . ' WHERE user_id = ? AND addressbook_id = ? AND card_uri = ?';
			$stmt2 = $pdo->prepare($query2);
			$stmt2->execute([$syncDbUserId, $addressBookId, $cardUri]);
			
			if($batchSize > 1)
				$inTransaction = true;
				
			$rowCount++;
			
			if($inTransaction && $rowCount >= $batchSize) {
				$pdo->commit();
				$inTransaction = false;
				$rowCount = 0;
			}
    }
    
		if($inTransaction)
			$pdo->commit();
	} catch (\Throwable $th) {
		if($inTransaction)
			$pdo->rollback();
			
		trigger_error("Some unexpected error occurred in database - ".$th->getMessage(), E_USER_WARNING);
		exit(1);
	}
	
	echo "Complete\n";
	exit;
}

if(!$initialized)
{
  	trigger_error("Sync database has not been initialized. Initialize it first.", E_USER_NOTICE);
  	exit(1);
}

$options = [0 => 'addressbook', 1 => 'user'];

if(isset($argv[1]) && $argv[1] !== '')
{
	if(!in_array($argv[1], $options))
	{
		trigger_error('Please enter correct entry you want to operate upon.', E_USER_NOTICE);
		exit(1);
	}
	$operation = array_search($argv[1], $options);
}
else
{
	$operation = readline("Choose the entity you want to operate upon. Enter 0 for $options[0] and 1 for $options[1]: ");
	if(!array_key_exists($operation, $options))
	{
		trigger_error('Please enter correct option.', E_USER_NOTICE);
		exit(1);
	}
}


if($options[$operation] == 'user')
{
	if(isset($argv[2]) && $argv[2] !== '')
	{
		$oldUserId = $argv[2];
	}
	else
	{
		$oldUserId = readline("\nEnter the backend user id to delete: ");
		if($oldUserId == null || $oldUserId == '')
		{
			trigger_error("User id not provided.", E_USER_NOTICE);
			exit(1);
		}
	}
	
	try {
		$query = 'DELETE FROM '. $userTableName .' AS user_tab WHERE user_tab.user_id = ? AND NOT EXISTS (SELECT 1 FROM cards_system_user AS sys_user_tab WHERE sys_user_tab.user_id = user_tab.user_id)';
		$stmt = $pdo->prepare($query);
		$stmt->execute([$oldUserId]);
		
		if(!$stmt->rowCount() > 0)
		{
    		trigger_error("User having user id '$oldUserId' does not exist.", E_USER_NOTICE);
    		exit(1);
		}

		echo "User having user id '$oldUserId' has been deleted.\n";
	} catch (\Throwable $th) {
		trigger_error("Some unexpected error occurred in database - ".$th->getMessage(), E_USER_WARNING);
		exit(1);
	}
}
else if($options[$operation] == 'addressbook')
{
	$options = [0 => 'list', 1 => 'add', 2 => 'rename', 3 => 'delete'];

	$operation = readline("Enter the operation to perform on address book. Enter 0 to $options[0], 1 to $options[1], 2 to $options[2] and 3 to $options[3]: ");

	if(!array_key_exists($operation, $options))
	{
		trigger_error('Please enter correct option.', E_USER_NOTICE);
		exit(1);
	}

	try {
		if($options[$operation] != 'list')
		{
			$oldAddressBook = readline("\nEnter name of the address book to ". $options[$operation].": ");

			if($oldAddressBook == null || $oldAddressBook == '')
			{
				trigger_error("Address book name not provided.", E_USER_NOTICE);
		  	exit(1);
			}
			
			$query = 'SELECT * FROM '. $addressBooksTableName .' WHERE addressbook_id = ?';
			$stmt = $pdo->prepare($query);
			$stmt->execute([$oldAddressBook]);

			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
				
			if($row === false && $options[$operation] != 'add')
			{
				trigger_error("Addressbook '". $oldAddressBook. "' is not present in sync database.", E_USER_NOTICE);
				exit(1);
			}
			else if($row !== false && $options[$operation] == 'add')
			{
				trigger_error("Addressbook '". $oldAddressBook. "' is already present in sync database.", E_USER_NOTICE);
				exit(1);
			}
		}

		if($options[$operation] == 'list')
		{
			$query = 'SELECT * FROM '. $addressBooksTableName;
			$stmt = $pdo->prepare($query);
			$stmt->execute();

      fwrite(STDERR,"-- Address books --");
      
      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
          echo $row['addressbook_id'] . "\t" . json_encode($row, JSON_NUMERIC_CHECK) . "\n";
      }
		}
		else if($options[$operation] == 'add')
		{
			if(addAddressBook($oldAddressBook) == false)
				exit(1);
		}
		else if($options[$operation] == 'rename')
		{	
		    $newAddressbook = readline("\nEnter new address book name: ");

		    $query = 'UPDATE '. $addressBooksTableName. ' SET addressbook_id = ? WHERE addressbook_id = ?';
		    $stmt = $pdo->prepare($query);
				$stmt->execute([$newAddressbook, $oldAddressBook]);
				
				if(!$stmt->rowCount() > 0)
				{
		    	trigger_error("Address book '$oldAddressBook' does not exist.", E_USER_NOTICE);
		    	exit(1);
				}

		    echo "Address book '$oldAddressBook' has been renamed to '$newAddressbook'.\n";
		}
		else if($options[$operation] == 'delete')
		{   
		    $query = 'DELETE FROM '. $addressBooksTableName .' WHERE addressbook_id = ?';
		    $stmt = $pdo->prepare($query);
		    $stmt->execute([$oldAddressBook]);
		    
				if(!$stmt->rowCount() > 0)
				{
		    	trigger_error("Address book '$oldAddressBook' does not exist.", E_USER_NOTICE);
		    	exit(1);
				}

		    echo "Address book '". $oldAddressBook ."' has been deleted.\n";
		}

	} catch (\Throwable $th) {
		  trigger_error("Some unexpected error occurred in database - ".$th->getMessage(), E_USER_WARNING);
		  exit(1);
	}
}

exit;
