<?php 
/************************************************************
* Copyright 2023-2025 ISub Softwares (OPC) Private Limited
************************************************************/

//constants
$GLOBALS['__BASE_DIR__'] = __DIR__.'/../..';
$GLOBALS['__DATA_DIR__'] = $GLOBALS['__BASE_DIR__'].'/data';
$GLOBALS['__CONF_DIR__'] = $GLOBALS['__BASE_DIR__'].'/conf';



// Autoloader
require_once $GLOBALS['__BASE_DIR__'].'/vendor/autoload.php';
require_once $GLOBALS['__CONF_DIR__'].'/conf.php';


/* Database */ 
$database = new \isubsoft\App\Bootstrap();
$pdo = $database->init($config);






$addressBooksTableName = 'cards_addressbook';
$userTableName = 'cards_user';

$validOps = ['1', '2'];
$entity = readline("Please enter in which entity to perform operation. Enter 1 for Addressbook or enter 2 for User. \n");

if(!in_array($entity, $validOps))
{
    echo "Please try again and enter correct option. \n";
    exit;
}

$operation = readline("Enter the operation to perform. Enter 1 for Update or enter 2 for Delete. \n");

if(!in_array($operation, $validOps))
{
    echo 'Please enter correct option. \n';
    exit;
}

$options = ['Update', 'Delete'];

if($entity == '1')
{

    $oldAddressBook = readline("Enter the address book name for ". $options[$operation-1].". \n");

    try {
        $query = 'SELECT * FROM '. $addressBooksTableName .' WHERE addressbook_id = ? LIMIT 1';
	    $stmt = $pdo->prepare($query);
	    $stmt->execute([$oldAddressBook]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
	    if($row === false)
        {
            echo 'Addressbook "'. $oldAddressBook. '" is not be found. \n';
            exit;
        }

        if($operation == '1')
        {	
            $newAddressbook = readline("Enter new address book name. \n");

            $query = 'UPDATE '. $addressBooksTableName. ' SET addressbook_id = ? WHERE addressbook_id = ?';
            $stmt = $pdo->prepare($query);
	    	$stmt->execute([$newAddressbook, $oldAddressBook]);

            echo 'Addressbook "'. $oldAddressBook .'" has been updated to "'. $newAddressbook .'" \n';
            exit;
        }
        else
        {   
            $query = 'DELETE FROM '. $addressBooksTableName .' WHERE addressbook_id = ?';
            $stmt = $pdo->prepare($query);
            $stmt->execute([$oldAddressBook]);

            echo 'Addressbook "'. $oldAddressBook .'" has been deleted. \n';
            exit;
        }

    } catch (\Throwable $th) {
        error_log("Some unexpected error occurred in database - ".$th->getMessage());
        exit(1);
    }
}
else
{
    $oldUser = readline("Enter the user name for ". $options[$operation-1] .". \n");

    try {
        $query = 'SELECT * FROM '. $userTableName .' WHERE user_id = ? LIMIT 1';
	    $stmt = $pdo->prepare($query);
	    $stmt->execute([$oldUser]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
	    if($row === false)
        {
            echo 'User "'. $oldUser. '" is not be found. \n';
            exit;
        }	

        if($row['user_id'] == '__SYS_USER')
        {
            echo 'User "'. $oldUser. '" is a system user. It can not be updated or deleted. \n';
            exit;
        }

        if($operation == '1')
        {
            $newUser = readline("Enter new user name. \n");

            $query = 'UPDATE '. $userTableName. ' SET user_id = ? WHERE user_id = ?';
            $stmt = $pdo->prepare($query);
	    	$stmt->execute([$newUser, $oldUser]);

            echo 'User "'. $oldUser .'" has been updated to "'. $newUser .'" \n';
            exit;
        }
        else
        {
            $query = 'DELETE FROM '. $userTableName .' WHERE user_id = ?';
            $stmt = $pdo->prepare($query);
            $stmt->execute([$oldUser]);

            echo 'User "'. $oldUser .'" has been deleted. \n';
            exit;
        }
    } catch (\Throwable $th) {
        error_log("Some unexpected error occurred in database - ".$th->getMessage());
        exit(1);
    }
}




?>