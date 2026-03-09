<?php 
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/**
* This script is used to manage sync database.
**/

function printHelp($argv)
{
	error_log("");
	error_log("Usage: " . $argv[0] . " action [parameters]");
	error_log("");
	error_log("Parameters for action init.");
	error_log("-- none --");
	error_log("");
	error_log("Parameters for action manage.");
	error_log("entity (string): Entity to act upon. Valid values are user, addressbook.");
	error_log("");
	error_log("Parameters for entity user.");
	error_log("operation (optional, string): Perform this operation on the entity. Valid value is delete.");
	error_log("user id (string): entryUUID of the user from backend.");
	error_log("");
	error_log("Parameters for action housekeeping.");
	error_log("batch size (optional, integer): Restrict action to maximum of these many items. Should be >= 1, Defaults to 1000. Since actions can be time consuming set this parameter to a value in range 1000 to 10000 to be efficient. Avoid setting this to a very large value as it may cause performance issues.");
	
	return;
}

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
		error_log("Address book name not provided.");
		return false;
	}
		
	try
	{
		if(!isset($config['card']['addressbook']['ldap'][$addressbookName]))
		{
				error_log("Address book is not configured in configuration file.");
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

if(isset($argv[1]) && $argv[1] == 'help')
{
	printHelp($argv);
	exit;
}
else if(isset($argv[1]) && $argv[1] == 'init')
{
		echo "Initializing sync database ...\n";
		
		if($initialized)
		{
			error_log("Sync database has already been initialized");
		  exit;
		}
		
    try {
      	if(isset($config['card']['addressbook']['ldap']))
      	{
          	foreach($config['card']['addressbook']['ldap'] as $addressBooksName => $values)
          	{
          		if(addAddressBook($addressBooksName) == false)
          		{
          			error_log("Failed to add address book '$addressBooksName'. Sync database initialization failed. Reverting changes.");
          		
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
	
	if(isset($argv[2]))
			$batchSize = $argv[2];
			
	if(!settype($batchSize, 'integer') || $batchSize < 1) {
		error_log("Invalid batch size provided. Cannot continue. Quitting.");
		print_help($argv);
		exit(1);
	}
			
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
  	error_log("Sync database has not been initialized. Initialize it first.");
		printHelp($argv);
  	exit(1);
}

if(!isset($argv[1]) || $argv[1] == 'manage')
{
	$options = [0 => 'addressbook', 1 => 'user'];
	
	if(isset($argv[2]) && $argv[2] !== '')
	{
		if(!in_array($argv[2], $options))
		{
			error_log('Please enter correct entry you want to operate upon.');
			exit(1);
		}
		$operation = array_search($argv[2], $options);
	}
	else
	{
		$operation = readline("Choose the entity you want to manage. Enter 0 for $options[0] and 1 for $options[1]: ");
		if(!array_key_exists($operation, $options))
		{
			error_log('Please enter correct option.');
			exit(1);
		}
	}


	if($options[$operation] == 'user')
	{
		if(isset($argv[3]) && $argv[3] == 'delete') 
		{
			if(isset($argv[4]) && $argv[4] !== '')
			{
				$oldUserId = $argv[4];
			}
			else
			{
				error_log("User id not provided.");
				printHelp($argv);
				exit(1);
			}
		}
		else if(!isset($argv[3]))
		{
			$oldUserId = readline("\nEnter the backend user id to delete: ");
			
			if($oldUserId == null || $oldUserId == '')
			{
				error_log("User id not provided.");
				exit(1);
			}
		}
		else
		{
			error_log("'$argv[3]' is not a valid operation.");
			printHelp($argv);
			exit(1);
		}
		
		try {
			$query = 'DELETE FROM '. $userTableName .' AS user_tab WHERE user_tab.user_id = ? AND NOT EXISTS (SELECT 1 FROM cards_system_user AS sys_user_tab WHERE sys_user_tab.user_id = user_tab.user_id)';
			$stmt = $pdo->prepare($query);
			$stmt->execute([$oldUserId]);
			
			if(!$stmt->rowCount() > 0)
			{
		  		error_log("User having user id '$oldUserId' does not exist.");
		  		exit(1);
			}

			echo "User having user id '$oldUserId' has been deleted.\n";
			echo "[NOTE] After this action sync database table(s) '$backendMapTableName' may need optimization. Use native database command(s) to achieve the same.\n";
			echo "[NOTE] After this action sync database table(s) '$userTableName' may need optimization (if you have deleted a large number of '$options[$operation]' entities). Use native database command(s) to achieve the same.\n";
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
			error_log('Please enter correct option.');
			exit(1);
		}

		try {
			if($options[$operation] != 'list')
			{
				$oldAddressBook = readline("\nEnter name of the address book to ". $options[$operation].": ");

				if($oldAddressBook == null || $oldAddressBook == '')
				{
					error_log("Address book name not provided.");
					exit(1);
				}
				
				$query = 'SELECT * FROM '. $addressBooksTableName .' WHERE addressbook_id = ?';
				$stmt = $pdo->prepare($query);
				$stmt->execute([$oldAddressBook]);

				$row = $stmt->fetch(\PDO::FETCH_ASSOC);
					
				if($row === false && $options[$operation] != 'add')
				{
					error_log("Addressbook '". $oldAddressBook. "' is not present in sync database.");
					exit(1);
				}
				else if($row !== false && $options[$operation] == 'add')
				{
					error_log("Addressbook '". $oldAddressBook. "' is already present in sync database.");
					exit(1);
				}
			}

			if($options[$operation] == 'list')
			{
				$query = 'SELECT * FROM '. $addressBooksTableName;
				$stmt = $pdo->prepare($query);
				$stmt->execute();

		    echo "-- Address books --\n";
		    
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
				  	error_log("Address book '$oldAddressBook' does not exist.");
				  	exit(1);
					}

				  echo "Address book '$oldAddressBook' has been renamed to '$newAddressbook'.\n";
				  echo "[NOTE] After this action sync database table(s) '$backendMapTableName' may need optimization. Use native database command(s) to achieve the same.\n";
			}
			else if($options[$operation] == 'delete')
			{   
				  $query = 'DELETE FROM '. $addressBooksTableName .' WHERE addressbook_id = ?';
				  $stmt = $pdo->prepare($query);
				  $stmt->execute([$oldAddressBook]);
				  
					if(!$stmt->rowCount() > 0)
					{
				  	error_log("Address book '$oldAddressBook' does not exist.");
				  	exit(1);
					}

				  echo "Address book '". $oldAddressBook ."' has been deleted.\n";
				  echo "[NOTE] After this action sync database table(s) '$backendMapTableName' may need optimization. Use native database command(s) to achieve the same.\n";
			}

		} catch (\Throwable $th) {
				trigger_error("Some unexpected error occurred in database - " . $th->getMessage(), E_USER_WARNING);
				exit(1);
		}
	}
}
else
{
	error_log("'$argv[1]' is not a valid action. Quitting.");
	printHelp($argv);
	exit(1);
}

exit;
