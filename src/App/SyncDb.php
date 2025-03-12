<?php 
/**************************************************************
* Copyright (C) 2023-2025 ISub Softwares (OPC) Private Limited
**************************************************************/

/*import database connection*/
require_once 'Bootstrap.php';

/*database Tables*/
$addressBooksTableName = 'cards_addressbook';
$userTableName = 'cards_user';



if(isset($argv[1]) && $argv[1] == 'init')
{
    try {
        $query = 'SELECT * FROM '. $addressBooksTableName .' LIMIT 1' ;
	    $stmt = $pdo->prepare($query);
	    $stmt->execute([]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if(!empty($row))
        {
            echo "Addressbooks are already imported to database. \n";
            exit(1);   
        }

        if(isset($config['card']['addressbook']['ldap']))
        {
            foreach($config['card']['addressbook']['ldap'] as $addressBooksName => $values)
            {
                $query = 'INSERT INTO '. $addressBooksTableName .' (`addressbook_id`, `user_specific`, `writable`) VALUES (?, ?, ?)';
                $stmt = $pdo->prepare($query);
                $stmt->execute([$addressBooksName, isset($values['user_specific']) ? $values['user_specific'] : false, isset($values['writable']) ? $values['writable'] : false]);
            }
        }
        echo "Addressbooks has been successfully imported to database. \n";

    } catch (\Throwable $th) {
        error_log("Some unexpected error occurred in database - ".$th->getMessage());
        exit(1);
    }
}





$entity = readline("Please enter in which entity to perform operation. Enter 1 for Addressbook or enter 2 for User. \n");

if($entity !== '1' && $entity !== '2')
{
    echo "Please try again and enter correct option. \n";
    exit;
}

$operation = readline("Enter the operation to perform. Enter 1 for Update or enter 2 for Delete. \n");

if($operation !== '1' && $operation !== '2')
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
