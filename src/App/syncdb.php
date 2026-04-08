<?php 
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/**
* This script is used to manage sync database.
**/

function printHelp($argv)
{
	error_log("Usage: " . $argv[0] . " action [parameters]");
	error_log("");
	error_log("-- Actions");
	error_log("help:             Print this help and exit.");
	error_log("init:             Initialize sync database.");
	error_log("manage (default): Manage objects in sync database.");
	error_log("housekeeping:     Physically delete logically deleted records.");
	error_log("");
	error_log("-- Parameter(s) for action manage. Omitting any optional parameter below may turn on interactive mode to obtain it.");
	error_log("  user: (optional) Manage a user.");
	error_log("    delete: (optional) Delete a user.");
	error_log("      <user_id>: (optional) entryUUID of the user from backend.");
	error_log("");
	error_log("  addressbook: (optional) Manage an address book.");
	error_log("    list:   (optional) List address book(s) present in sync database.");
	error_log("    add:    (optional) Add an address book.");
	error_log("    rename: (optional) Rename an address book.");
	error_log("    delete: (optional) Delete an address book.");
	error_log("");
	error_log("-- Parameter(s) for action housekeeping");
	error_log("  <batch_size>: (optional, integer) Restrict action to maximum of these many items. Should be >= 1, defaults to 1000. Since this action can be time consuming set this parameter to a value in range 1000 to 10000 to be efficient. Avoid setting this to a very small or very large value as it may cause performance issues.");
	
	return;
}

/*import database connection*/
require_once __DIR__ . '/include/bootstrap.php';

/*database Tables*/
$addressBooksTableName = 'cards_addressbook';
$userTableName = 'cards_user';
$backendMapTableName = 'cards_backend_map';
$fullSyncTableName = 'cards_full_sync';

$initialized = true;

function getAddressBooks()
{
	$addressBooksTableName = $GLOBALS['addressBooksTableName'];
	$pdo = $GLOBALS['pdo'];
	$addressBooks = [];
	
	$query = 'SELECT * FROM '. $addressBooksTableName;
	$stmt = $pdo->prepare($query);
	$stmt->execute();

  while ($row = $stmt->fetch(\PDO::FETCH_ASSOC))
  	$addressBooks[$row['addressbook_id']] = ['user_specific' => (bool)$row['user_specific'], 'writable' => (bool)$row['writable']];
  
  return $addressBooks;
}

function getNotImportedAddressBooks()
{
	$config = $GLOBALS['config'];
	$addressBooksTableName = $GLOBALS['addressBooksTableName'];
	$pdo = $GLOBALS['pdo'];
	$notImportedAddressBooks = [];
	
	foreach($config['card']['addressbook']['ldap'] as $addressbookId => $addressbookConfig)
	{
		$query = 'SELECT * FROM '. $addressBooksTableName . ' WHERE addressbook_id = ?';
		$stmt = $pdo->prepare($query);
		$stmt->execute([$addressbookId]);
		
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		if($row !== false)
			continue;
		
		$name = !isset($addressbookConfig['name']) || !is_string(($addressbookConfig['name']))?'':$addressbookConfig['name'];
		$userSpecific = !isset($addressbookConfig['user_specific']) || !is_bool(($addressbookConfig['user_specific']))?null:$addressbookConfig['user_specific'];
		$writable = !isset($addressbookConfig['writable']) || !is_bool($addressbookConfig['writable'])?null:$addressbookConfig['writable'];
		$description = !isset($addressbookConfig['description']) || !is_string(($addressbookConfig['description']))?'':$addressbookConfig['description'];

		$notImportedAddressBooks[$addressbookId] = ['name' => $name, 'user_specific' => $userSpecific, 'writable' => $writable, 'description' => $description];
	}
  
  return $notImportedAddressBooks;
}

function getConfiguredAddressBooks()
{
	$config = $GLOBALS['config'];
	$configuredAddressBooks = [];
	
	foreach($config['card']['addressbook']['ldap'] as $addressbookId => $addressbookConfig)
	{
		$name = !isset($addressbookConfig['name']) || !is_string(($addressbookConfig['name']))?'':$addressbookConfig['name'];
		$userSpecific = !isset($addressbookConfig['user_specific']) || !is_bool(($addressbookConfig['user_specific']))?null:$addressbookConfig['user_specific'];
		$writable = !isset($addressbookConfig['writable']) || !is_bool($addressbookConfig['writable'])?null:$addressbookConfig['writable'];
		$description = !isset($addressbookConfig['description']) || !is_string(($addressbookConfig['description']))?'':$addressbookConfig['description'];

		$configuredAddressBooks[$addressbookId] = ['name' => $name, 'user_specific' => $userSpecific, 'writable' => $writable, 'description' => $description];
	}
  
  return $configuredAddressBooks;
}

function addAddressBook($addressbookName = null)
{
	$config = $GLOBALS['config'];
	$addressBooksTableName = $GLOBALS['addressBooksTableName'];
	$pdo = $GLOBALS['pdo'];
	
	if($addressbookName == null)
	{
		error_log("[ERROR] Address book name not provided.");
		return false;
	}
		
	try
	{
		if(!isset($config['card']['addressbook']['ldap'][$addressbookName]))
		{
				error_log("[ERROR] Address book '$addressbookName' is not present in the configuration file. Add it to configuration file and try again.");
				return false;
		}
    
  	$userSpecific = !isset($config['card']['addressbook']['ldap'][$addressbookName]['user_specific']) || !is_bool(($config['card']['addressbook']['ldap'][$addressbookName]['user_specific']))?null:$config['card']['addressbook']['ldap'][$addressbookName]['user_specific'];
  	$writable = !isset($config['card']['addressbook']['ldap'][$addressbookName]['writable']) || !is_bool($config['card']['addressbook']['ldap'][$addressbookName]['writable'])?null:$config['card']['addressbook']['ldap'][$addressbookName]['writable'];
	  	
	  if($userSpecific === null || $writable === null) {
			error_log("[ERROR] 'user_specific' and/or 'writable' keys for address book '$addressbookName' are not configured correctly. Fix and try again.");
			return false;
	  }
	  
		$query = 'INSERT INTO '. $addressBooksTableName .' (addressbook_id, user_specific, writable) VALUES (?, ?, ?)';
		$stmt = $pdo->prepare($query);
		$stmt->execute([$addressbookName, $userSpecific, $writable]);
		echo "Address book '$addressbookName' has been successfully added to sync database.\n";
    
  	} catch (\Throwable $th) {
			trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
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
	trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
  exit(1);
}

if(isset($argv[1]) && $argv[1] == 'help')
{
	printHelp($argv);
	exit;
}
elseif(isset($argv[1]) && $argv[1] == 'init')
{
		echo "Initializing sync database ...\n";
		
		if($initialized)
		{
			error_log("[NOTE] Sync database has already been initialized");
		  exit;
		}
		
    try {
      	if(isset($config['card']['addressbook']['ldap']))
      	{
          	foreach($config['card']['addressbook']['ldap'] as $addressBooksName => $values)
          	{
          		if(addAddressBook($addressBooksName) == false)
          		{
          			error_log("[ERROR] Failed to add address book '$addressBooksName'. Sync database initialization failed. Reverting changes.");
          		
								$query = 'DELETE * FROM '. $addressBooksTableName;
								$stmt = $pdo->prepare($query);
								$stmt->execute([]);
								exit(1);
          		}
          	}
      	}
      
      	echo "Address book(s) successfully imported.\n";

    } catch (\Throwable $th) {
			trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
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
		error_log("[ERROR] Invalid batch size provided. Cannot continue. Quitting.");
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
			
		trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
		exit(1);
	}
	
	echo "Complete\n";
	echo "[NOTE] After this action sync database table(s) '$backendMapTableName' may need optimization (re-indexing/re-build). Use native database command(s) to achieve the same.\n";
	exit;
}

if(!$initialized)
{
  	error_log("[NOTE] Sync database has not been initialized. Initialize it first.");
  	error_log("");
		printHelp($argv);
  	exit(1);
}

if(!isset($argv[1]) || $argv[1] == 'manage')
{
	$options = [1 => 'user', 2 => 'addressbook'];
	
	if(isset($argv[2]) && $argv[2] !== '')
	{
		if(!in_array($argv[2], $options))
		{
			error_log('[ERROR] Please enter correct entry you want to operate upon.');
			exit(1);
		}
		$choice = array_search($argv[2], $options);
	}
	else
	{
		echo "-- Choose the object you want to manage --\n";
		
		foreach($options as $key => $value)
			echo $key . " for " . $value . "\n";
			
		$choice = readline("Enter choice: ");
		
		echo "\n";
		
		if(!array_key_exists($choice, $options))
		{
			error_log('[ERROR] Please enter correct option.');
			exit(1);
		}
	}


	if($options[$choice] == 'user')
	{
		if(isset($argv[3]) && $argv[3] == 'delete') 
		{
			if(isset($argv[4]) && $argv[4] !== '')
			{
				$oldUserId = $argv[4];
			}
			else
			{
				error_log("[ERROR] User id not provided.");
  			error_log("");
				printHelp($argv);
				exit(1);
			}
		}
		elseif(!isset($argv[3]))
		{
			$oldUserId = readline("\nEnter the backend user id to delete: ");
			
			if($oldUserId == null || $oldUserId == '')
			{
				error_log("[ERROR] User id not provided.");
				exit(1);
			}
			
			$confirm = readline("\nAre you sure you want to proceed (y/N): ");
			
			if($confirm == '' || ($confirm != 'Y' && $confirm != 'y'))
				exit;
		}
		else
		{
			error_log("[ERROR] '$argv[3]' is not a valid operation. Quitting.");
  		error_log("");
			printHelp($argv);
			exit(1);
		}
		
		try {
			$query = 'DELETE FROM '. $userTableName .' AS user_tab WHERE user_tab.user_id = ? AND NOT EXISTS (SELECT 1 FROM cards_system_user AS sys_user_tab WHERE sys_user_tab.user_id = user_tab.user_id)';
			$stmt = $pdo->prepare($query);
			$stmt->execute([$oldUserId]);
			
			if(!$stmt->rowCount() > 0)
			{
		  		error_log("[ERROR] User having user id '$oldUserId' does not exist.");
		  		exit(1);
			}

			echo "User having user id '$oldUserId' has been deleted.\n";
			echo "[NOTE] After this action sync database table(s) '$backendMapTableName' may need optimization (re-indexing/re-build). Use native database command(s) to achieve the same.\n";
			echo "[NOTE] After this action sync database table(s) '$userTableName' may need optimization (re-indexing/re-build) (if you have deleted a large number of '$options[$choice]' objects). Use native database command(s) to achieve the same.\n";
		} catch (\Throwable $th) {
			trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
			exit(1);
		}
	}
	elseif($options[$choice] == 'addressbook')
	{
		$options = [0 => 'list', 1 => 'add', 2 => 'rename', 3 => 'delete'];
		
		if(isset($argv[3]) && $argv[3] !== '')
		{
			if(!in_array($argv[3], $options))
			{
				error_log('[ERROR] Please enter correct entry you want to operate upon.');
				exit(1);
			}
			$choice = array_search($argv[3], $options);
		}
		else
		{
			echo "-- Choose the operation to perform on a address book --\n";
			
			foreach($options as $key => $value)
				echo $key . " to " . $value . "\n";
				
			$choice = readline("Enter choice: ");
			
			echo "\n";
			
			if(!array_key_exists($choice, $options))
			{
				error_log('[ERROR] Please enter correct option.');
				exit(1);
			}
		}

		try {
			if($options[$choice] != 'add') {
				$addressBooks = getAddressBooks();

				if(count($addressBooks) < 1) {
					echo "[INFO] No address book present in sync database. Quitting.\n";
					exit;
				}
				
				echo "-- Address books present in sync database [ id => info ] --\n";
				
				foreach($addressBooks as $addressbookId => $addressbookConfig)
					echo $addressbookId . " => " . json_encode($addressbookConfig, JSON_NUMERIC_CHECK) . "\n";
					
				echo "\n";
			}
			
			if($options[$choice] == 'list')
				exit;
			
			if($options[$choice] == 'add') {
				$notImportedAddressBooks = getNotImportedAddressBooks();
				
				if(count($notImportedAddressBooks) < 1) {
					echo "[INFO] No address book available to " . $options[$choice] . ". Quitting.\n";
					exit;
				}
				
			  echo "-- Address books available in configuration file to " . $options[$choice] . " [ id => info ] --\n";

				foreach($notImportedAddressBooks as $addressbookId => $addressbookConfig)
					echo $addressbookId . " => " . json_encode($addressbookConfig, JSON_NUMERIC_CHECK) . "\n";
					
				echo "\n";
			}
			
			$oldAddressBook = readline("\nEnter id of the address book to " . $options[$choice] . ": ");

			if($oldAddressBook == null || $oldAddressBook == '')
			{
				error_log("[ERROR] Address book id not provided.");
				exit(1);
			}

			if($options[$choice] == 'add')
			{
				if(!array_key_exists($oldAddressBook, getNotImportedAddressBooks())) {
					error_log("[ERROR] Invalid address book id provided.");
					exit(1);
				}

				$confirm = readline("\nAre you sure you want to proceed (y/N): ");
				
				if($confirm == '' || ($confirm != 'Y' && $confirm != 'y'))
					exit;
				
				if(addAddressBook($oldAddressBook) == false)
					exit(1);
			}
			elseif($options[$choice] == 'rename')
			{
					if(!array_key_exists($oldAddressBook, getAddressBooks())) {
						error_log("[ERROR] Invalid address book id provided.");
						exit(1);
					}
					
				  $newAddressbook = readline("\nEnter new address book id: ");
				  
					if(array_key_exists($newAddressbook, getAddressBooks())) {
			  		error_log("[ERROR] Address book '$newAddressbook' is already present in sync database.");
						exit(1);
					}
				  
					if(!array_key_exists($newAddressbook, getConfiguredAddressBooks())) {
			  		error_log("[ERROR] Address book '$newAddressbook' does not exist in configuration file. Rename address book '$oldAddressBook' to '$newAddressbook' in the configuration file and try again.");
						exit(1);
					}
					
					$confirm = readline("\nAre you sure you want to proceed (y/N): ");
					
					if($confirm == '' || ($confirm != 'Y' && $confirm != 'y'))
						exit;
					
				  $query = 'UPDATE '. $addressBooksTableName. ' SET addressbook_id = ? WHERE addressbook_id = ?';
				  $stmt = $pdo->prepare($query);
					$stmt->execute([$newAddressbook, $oldAddressBook]);
					
					if(!$stmt->rowCount() > 0)
					{
				  	error_log("[ERROR] Address book '$oldAddressBook' does not exist.");
				  	exit(1);
					}

				  echo "Address book '$oldAddressBook' has been renamed to '$newAddressbook'.\n";
				  echo "[NOTE] After this action sync database table(s) '$backendMapTableName' may need optimization (re-indexing/re-build). Use native database command(s) to achieve the same.\n";
			}
			elseif($options[$choice] == 'delete')
			{
					if(!array_key_exists($oldAddressBook, getAddressBooks())) {
						error_log("[ERROR] Invalid address book id provided.");
						exit(1);
					}
					
					if(array_key_exists($oldAddressBook, getConfiguredAddressBooks())) {
			  		error_log("[ERROR] Address book '$oldAddressBook' is present in the configuration file. Delete it from configuration file and try again.");
						exit(1);
					}
					
					$confirm = readline("\nAre you sure you want to proceed (y/N): ");
					
					if($confirm == '' || ($confirm != 'Y' && $confirm != 'y'))
						exit;
					
				  $query = 'DELETE FROM '. $addressBooksTableName .' WHERE addressbook_id = ?';
				  $stmt = $pdo->prepare($query);
				  $stmt->execute([$oldAddressBook]);
				  
					if(!$stmt->rowCount() > 0)
					{
				  	error_log("[ERROR] Address book '$oldAddressBook' does not exist.");
				  	exit(1);
					}

				  echo "Address book '". $oldAddressBook ."' has been deleted.\n";
				  echo "[NOTE] After this action sync database table(s) '$backendMapTableName' may need optimization (re-indexing/re-build). Use native database command(s) to achieve the same.\n";
			}

		} catch (\Throwable $th) {
			trigger_error("Caught exception. Error message: " . $th->getMessage(), E_USER_WARNING);
			exit(1);
		}
	}
}
else
{
	error_log("[ERROR] '$argv[1]' is not a valid action. Quitting.");
 	error_log("");
	printHelp($argv);
	exit(1);
}

exit;
