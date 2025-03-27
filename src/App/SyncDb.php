<?php 
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/*import database connection*/
require_once 'Bootstrap.php';

/*database Tables*/
$addressBooksTableName = 'cards_addressbook';
$userTableName = 'cards_user';

function addUpdateAddressBook($addressbookName = null, $operation = 'add')
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
    
	  $userSpecific = isset($config['card']['addressbook']['ldap'][$addressbookName]['user_specific'])?(bool)$config['card']['addressbook']['ldap'][$addressbookName]['user_specific']:true;
	  $writable = isset($config['card']['addressbook']['ldap'][$addressbookName]['writable'])?(bool)$config['card']['addressbook']['ldap'][$addressbookName]['writable']:true;
	  
		if($operation == 'update')
		{
			$query = 'UPDATE '. $addressBooksTableName . ' SET `user_specific` = ?, `writable` = ? WHERE `addressbook_id` = ?';
			$stmt = $pdo->prepare($query);
			$stmt->execute([$userSpecific, $writable, $addressbookName]);
  		echo "\nAddress book '$addressbookName' has been successfully updated in sync database.";
		}
		else
		{
			$query = 'INSERT INTO '. $addressBooksTableName .' (`addressbook_id`, `user_specific`, `writable`) VALUES (?, ?, ?)';
			$stmt = $pdo->prepare($query);
			$stmt->execute([$addressbookName, $userSpecific, $writable]);
  		echo "\nAddress book '$addressbookName' has been successfully added to sync database.";
		}
    
  } catch (\Throwable $th) {
    error_log("[ERROR] Some unexpected error occurred while executing database operation - ".$th->getMessage());
		return false;
  }
	
	return true;
}

if(isset($argv[1]) && $argv[1] == 'init')
{
		echo "Initializing sync database ...";
		
    try {
      $query = 'SELECT * FROM '. $addressBooksTableName .' LIMIT 1' ;
	    $stmt = $pdo->prepare($query);
	    $stmt->execute([]);

      $row = $stmt->fetch(\PDO::FETCH_ASSOC);
      
      if(!empty($row))
      {
          echo "\n[INFO] Sync database has already been initialized";
          
					echo "\n";
          exit;
      }

      if(isset($config['card']['addressbook']['ldap']))
      {
          foreach($config['card']['addressbook']['ldap'] as $addressBooksName => $values)
          {
          	if(addUpdateAddressBook($addressBooksName) == false)
          	{
          		echo "\n[ERROR] Failed to add address book '$addressBooksName'. Sync database initialization failed. Reverting changes.";
          		
							$query = 'DELETE * FROM '. $addressBooksTableName;
							$stmt = $pdo->prepare($query);
							$stmt->execute([]);
							exit(1);
          	}
          }
      }
      
      echo "\nAddressbooks has been successfully imported to database.";

    } catch (\Throwable $th) {
        error_log("[ERROR] Some unexpected error occurred in database - ".$th->getMessage());
        exit(1);
    }
    
		echo "\n";    
    exit;
}

$options = [0 => 'add', 1 => 'update', 2 => 'rename', 3 => 'delete'];

$operation = readline("Enter the operation to perform on address book. Enter 0 to add, 1 to update, 2 to rename and 3 to delete: ");

if(!array_key_exists($operation, $options))
{
  echo '[ERROR] Please enter correct option.';
	
	echo "\n";
  exit;
}

$oldAddressBook = readline("\nEnter name of the address book to ". $options[$operation].": ");

try {
  $query = 'SELECT * FROM '. $addressBooksTableName .' WHERE addressbook_id = ? LIMIT 1';
  $stmt = $pdo->prepare($query);
  $stmt->execute([$oldAddressBook]);

  $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    
  if($row === false && $options[$operation] != 'add')
  {
      echo "[ERROR] Addressbook '". $oldAddressBook. "' is not present in sync database.";
      
			echo "\n";
      exit;
  }

  if($options[$operation] == 'add')
  {
  	if(addUpdateAddressBook($oldAddressBook) == false)
  		exit(1);
  }
  else if($options[$operation] == 'update')
  {
  	if(addUpdateAddressBook($oldAddressBook, 'update') == false)
  		exit(1);  
  }
  else if($options[$operation] == 'rename')
  {	
      $newAddressbook = readline("\nEnter new address book name: ");

      $query = 'UPDATE '. $addressBooksTableName. ' SET addressbook_id = ? WHERE addressbook_id = ?';
      $stmt = $pdo->prepare($query);
			$stmt->execute([$newAddressbook, $oldAddressBook]);

      echo "\nAddress book '$oldAddressBook' has been renamed to '$newAddressbook'.";
  }
  else if($options[$operation] == 'delete')
  {   
      $query = 'DELETE FROM '. $addressBooksTableName .' WHERE addressbook_id = ?';
      $stmt = $pdo->prepare($query);
      $stmt->execute([$oldAddressBook]);

      echo "\nAddress book '". $oldAddressBook ."' has been deleted.";
  }

} catch (\Throwable $th) {
    error_log("[ERROR] Some unexpected error occurred in database - ".$th->getMessage());
    exit(1);
}

echo "\n";
exit;
