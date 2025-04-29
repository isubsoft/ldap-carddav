<?php 
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/*import database connection*/
require_once __DIR__ . '/Bootstrap.php';

/*database Tables*/
$addressBooksTableName = 'cards_addressbook';
$userTableName = 'cards_user';

$initialized = true;

function addAddressBook($addressbookName = null)
{
	$config = $GLOBALS['config'];
	$addressBooksTableName = $GLOBALS['addressBooksTableName'];
	$userTableName = $GLOBALS['userTableName'];
	$pdo = $GLOBALS['pdo'];
	
	if($addressbookName == null)
	{
		echo "[ERROR] Address book name not provided.";
		return false;
	}
		
	try
	{
		if(!isset($config['card']['addressbook']['ldap'][$addressbookName]))
		{
				echo "[ERROR] Address book is not configured in configuration file.";
				return false;    	
		}
    
	  	$userSpecific = (!isset($config['card']['addressbook']['ldap'][$addressbookName]['user_specific']) || $config['card']['addressbook']['ldap'][$addressbookName]['user_specific'] === null)?true:(bool)$config['card']['addressbook']['ldap'][$addressbookName]['user_specific'];
	  	$writable = (!isset($config['card']['addressbook']['ldap'][$addressbookName]['writable']) || $config['card']['addressbook']['ldap'][$addressbookName]['writable'] === null)?true:(bool)$config['card']['addressbook']['ldap'][$addressbookName]['writable'];
	  
		$query = 'INSERT INTO '. $addressBooksTableName .' (`addressbook_id`, `user_specific`, `writable`) VALUES (?, ?, ?)';
		$stmt = $pdo->prepare($query);
		$stmt->execute([$addressbookName, (int)$userSpecific, (int)$writable]);
		echo "\nAddress book '$addressbookName' has been successfully added to sync database.";
    
  	} catch (\Throwable $th) {
    	error_log("[ERROR] Some unexpected error occurred while executing database operation - ".$th->getMessage());
		return false;
  }
	
	return true;
}

try {
	$query = 'SELECT * FROM '. $addressBooksTableName .' LIMIT 1' ;
	$stmt = $pdo->prepare($query);
	$stmt->execute([]);

	$row = $stmt->fetch(\PDO::FETCH_ASSOC);

	if($row === false)
		$initialized = false;
}
catch (\Throwable $th) {
    error_log("[ERROR] Some unexpected error occurred in database - ".$th->getMessage());
    exit(1);
}

if(isset($argv[1]) && $argv[1] == 'init')
{
		echo "Initializing sync database ...";
		
		if($initialized)
		{
			echo "\n[INFO] Sync database has already been initialized";
		  
			echo "\n";
		  	exit;
		}
		
    try {
      	if(isset($config['card']['addressbook']['ldap']))
      	{
          	foreach($config['card']['addressbook']['ldap'] as $addressBooksName => $values)
          	{
          		if(addAddressBook($addressBooksName) == false)
          		{
          			echo "\n[ERROR] Failed to add address book '$addressBooksName'. Sync database initialization failed. Reverting changes.";
          		
					$query = 'DELETE * FROM '. $addressBooksTableName;
					$stmt = $pdo->prepare($query);
					$stmt->execute([]);
					exit(1);
          		}
          	}
      	}
      
      	echo "\nAddress book(s) successfully imported.";

    } catch (\Throwable $th) {
        error_log("[ERROR] Some unexpected error occurred in database - ".$th->getMessage());
        exit(1);
    }
    
	echo "\n";    
    exit;
}

if(!$initialized)
{
  	echo "\n[ERROR] Sync database has not been initialized. Initialize it first.";
  
	echo "\n";
  	exit(1);
}

$options = [0 => 'addressbook', 1 => 'user'];

if(isset($argv[1]) && $argv[1] !== '')
{
	if(!in_array($argv[1], $options))
	{
		echo '[ERROR] Please enter correct entry you want to operate upon.';
		echo "\n";
		exit(1);
	}
	$operation = array_search($argv[1], $options);
}
else
{
	$operation = readline("Choose the entity you want to operate upon. Enter 0 for $options[0] and 1 for $options[1]: ");
	if(!array_key_exists($operation, $options))
	{
		echo '[ERROR] Please enter correct option.';
		
		echo "\n";
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
			echo "[ERROR] User id not provided.";
			echo "\n";
			exit(1);
		}
	}
	
	try {
		$query = 'DELETE FROM '. $userTableName .' AS user_tab WHERE user_tab.user_id = ? AND NOT EXISTS (SELECT 1 FROM cards_system_user AS sys_user_tab WHERE sys_user_tab.user_id = user_tab.user_id)';
		$stmt = $pdo->prepare($query);
		$stmt->execute([$oldUserId]);
		
		if(!$stmt->rowCount() > 0)
		{
    		echo "\n[ERROR] User having user id '$oldUserId' does not exist.";
			echo "\n";
    		exit(1);
		}

		echo "\nUser having user id '$oldUserId' has been deleted.";
	} catch (\Throwable $th) {
		error_log("[ERROR] Some unexpected error occurred in database - ".$th->getMessage());
		echo "\n";
		exit(1);
	}
}
else if($options[$operation] == 'addressbook')
{
	$options = [0 => 'list', 1 => 'add', 2 => 'rename', 3 => 'delete'];

	$operation = readline("Enter the operation to perform on address book. Enter 0 to $options[0], 1 to $options[1], 2 to $options[2] and 3 to $options[3]: ");

	if(!array_key_exists($operation, $options))
	{
		echo '[ERROR] Please enter correct option.';
		
		echo "\n";
		exit(1);
	}

	try {
		if($options[$operation] != 'list')
		{
			$oldAddressBook = readline("\nEnter name of the address book to ". $options[$operation].": ");

			if($oldAddressBook == null || $oldAddressBook == '')
			{
				echo "[ERROR] Address book name not provided.";
				echo "\n";
		  	exit(1);
			}
			
			$query = 'SELECT * FROM '. $addressBooksTableName .' WHERE addressbook_id = ? LIMIT 1';
			$stmt = $pdo->prepare($query);
			$stmt->execute([$oldAddressBook]);

			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
				
			if($row === false && $options[$operation] != 'add')
			{
				echo "[ERROR] Addressbook '". $oldAddressBook. "' is not present in sync database.";
				
				echo "\n";
				exit(1);
			}
			else if($row !== false && $options[$operation] == 'add')
			{
				echo "[ERROR] Addressbook '". $oldAddressBook. "' is already present in sync database.";
				
				echo "\n";
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
          echo "\n" . $row['addressbook_id'] . "\t" . json_encode($row, JSON_NUMERIC_CHECK);
      }
		}
		else if($options[$operation] == 'add')
		{
			if(addAddressBook($oldAddressBook) == false)
			{
				echo "\n";
				exit(1);
			}
		}
		else if($options[$operation] == 'rename')
		{	
		    $newAddressbook = readline("\nEnter new address book name: ");

		    $query = 'UPDATE '. $addressBooksTableName. ' SET addressbook_id = ? WHERE addressbook_id = ?';
		    $stmt = $pdo->prepare($query);
				$stmt->execute([$newAddressbook, $oldAddressBook]);
				
				if(!$stmt->rowCount() > 0)
				{
		    	echo "\n[ERROR] Address book '$oldAddressBook' does not exist.";
					echo "\n";
		    	exit(1);
				}

		    echo "\nAddress book '$oldAddressBook' has been renamed to '$newAddressbook'.";
		}
		else if($options[$operation] == 'delete')
		{   
		    $query = 'DELETE FROM '. $addressBooksTableName .' WHERE addressbook_id = ?';
		    $stmt = $pdo->prepare($query);
		    $stmt->execute([$oldAddressBook]);
		    
				if(!$stmt->rowCount() > 0)
				{
		    	echo "\n[ERROR] Address book '$oldAddressBook' does not exist.";
					echo "\n";
		    	exit(1);
				}

		    echo "\nAddress book '". $oldAddressBook ."' has been deleted.";
		}

	} catch (\Throwable $th) {
		  error_log("[ERROR] Some unexpected error occurred in database - ".$th->getMessage());
			echo "\n";
		  exit(1);
	}
}


echo "\n";
exit;
