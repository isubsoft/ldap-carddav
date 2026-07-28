<?php

namespace ISubsoft\DAV\PropertyStorage\Backend;

use Sabre\DAV\PropFind;
use Sabre\DAV\Xml\Property\Complex;

/**
 * PropertyStorage PDO backend.
 *
 * This backend class uses a PDO-enabled database to store webdav properties.
 * Both sqlite and mysql have been tested.
 *
 * The database structure can be found in the examples/sql/ directory.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class PDO extends \Sabre\DAV\PropertyStorage\Backend\PDO
{
    private $propFindPdoPrepStmt = [];
    
    /**
     * Fetches properties for a path.
     *
     * This method received a PropFind object, which contains all the
     * information about the properties that need to be fetched.
     *
     * Usually you would just want to call 'get404Properties' on this object,
     * as this will give you the _exact_ list of properties that need to be
     * fetched, and haven't yet.
     *
     * However, you can also support the 'allprops' property here. In that
     * case, you should check for $propFind->isAllProps().
     *
     * @param string $path
     */
    public function propFind($path, PropFind $propFind)
    {
        if (!$propFind->isAllProps() && 0 === count($propFind->get404Properties())) {
            return;
        }
        
        if(!isset($this->propFindPdoPrepStmt['stmt01'])) {
        	$query = 'SELECT name, value, valuetype FROM '.$this->tableName.' WHERE path = ?';
          $this->propFindPdoPrepStmt['stmt01'] = $this->pdo->prepare($query);
        }
        
        $stmt01 = $this->propFindPdoPrepStmt['stmt01'];

        $stmt01->execute([$path]);

        while ($row = $stmt01->fetch(\PDO::FETCH_ASSOC)) {
            if ('resource' === gettype($row['value'])) {
                $row['value'] = stream_get_contents($row['value']);
            }
            switch ($row['valuetype']) {
                case null:
                case self::VT_STRING:
                    $propFind->set($row['name'], $row['value']);
                    break;
                case self::VT_XML:
                    $propFind->set($row['name'], new Complex($row['value']));
                    break;
                case self::VT_OBJECT:
                    $propFind->set($row['name'], unserialize($row['value']));
                    break;
            }
        }
    }
}
