<?php
/**
* @package    jelix
* @subpackage db
* @author     Laurent Jouanneau
* @copyright  2010 Laurent Jouanneau
*
* @link        http://www.jelix.org
* @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*/

/**
 * 
 */
abstract class jDbTable {

    /**
     * @var string the name of the table
     */
    protected $name;

    /**
     * @var jDbSchema the schema which holds the table
     */
    protected $schema;
  
    /**
     * @var jDbColumns[]. null means "columns are not loaded"
     */
    protected $columns = null;
    
    /**
     * @var jDbPrimaryKey the primary key. null means "primary key is not loaded". false means : no primary key
     */
    protected $primaryKey = null;

    /**
     * @var jDbUniqueKey[]. null means "unique key are not loaded"
     */
    protected $uniqueKeys = null;

    /**
     * @var jDbIndex[]. null means "indexes are not loaded"
     */
    protected $indexes = null;

    /**
     * @var jDbReference[]. null means "references are not loaded"
     */
    protected $references = null;

    /**
     * @param string $name the table name
     * @param jDbSchema $schema
     */
    function __construct($name, $schema) {
        $this->name = $name;
        $this->schema = $schema;
    }


    public function getName() {
        return $this->name;
    }

    /**
     *
     * @return Iterator on jDbColumn
     */
    public function getColumns() {
        if ($this->columns === null) {
            $this->_loadColumns();
        }
        return $this->columns;
    }

    public function getColumn($name) {
        if ($this->columns === null) {
            $this->_loadColumns();
        }
        if (isset($this->columns[$name])) {
            return $this->columns[$name];
        }
        return null;
    }

    /**
     * add a column
     * @return boolean  true if the column is added, false if not (already there)
     */
    public function addColumn(jDbColumn $column) {
        $col = $this->getColumn($column->name);
        if ($col) {
            return false;
        }
        $this->_addColumn($column);
        $this->columns[$column->name] = $column;
        return false;
    }

    /**
     * change a column definition. If the column does not exist,
     * it is created.
     * @param jDbColumn $column  the colum with its new properties
     * @param string $oldName  the name of the column to change (if the name is changed)
     * @param boolean $doNotCreate true if the column shoul dnot be created when it does not exist
     * @return boolean true if changed/created
     */
    public function alterColumn(jDbColumn $column, $oldName = '', $doNotCreate=false) {
        $oldColumn = $this->getColumn(($oldName?:$column->name));
        if (!$oldColumn) {
            if ($doNotCreate) {
                return false;
            }
            $this->_addColumn($column);
        }
        else {
            $this->_alterColumn($oldColumn, $column);
        }
        $this->columns[$column->name] = $column;
        return true;
    }

    public function dropColumn($name) {
        $conn = $this->schema->getConn();
        $sql = 'ALTER TABLE '.$conn->encloseName($this->name).' DROP COLUMN '.$conn->encloseName($name);
        $conn->exec($sql);
    }

    /**
     *	@return jDbPrimaryKey|false  false if there is no primary key
     */
    public function getPrimaryKey() {
        if ($this->primaryKey === null)
            $this->_loadIndexesAndKeys();
        return $this->primaryKey;
    }

    public function setPrimaryKey(jDbPrimaryKey $key) {
        $pk = $this->getPrimaryKey();
        if ($pk == $key)
            return;
        if ($pk !== false)
            $this->_removeIndex($pk);
        $this->_createIndex($key);
        $this->primaryKey = $key;
    }

    public function dropPrimaryKey() {
        $pk = $this->getPrimaryKey();
        if ($pk !== false) {
            $this->_removeIndex($pk);
            $this->primaryKey = false;
        }
    }

    /**
     *	@return array of jDbIndex
     */
    public function getIndexes() {
        if ($this->indexes === null)
            $this->_loadIndexesAndKeys();
        return $this->indexes;
    }

    public function getIndex($name) {
        if ($this->indexes === null)
            $this->_loadIndexesAndKeys();
        if (isset($this->indexes[$name]))
            return $this->indexes[$name];
        return null;
    }

    public function addIndex(jDbIndex $index) {
        $this->alterIndex($index);
    }

    public function alterIndex(jDbIndex $index) {
        $idx = $this->getIndex($index->name);
        if ($idx) {
            $this->_dropIndex($idx);
        }
        $this->_createIndex($index);
        $this->indexes[$index->name] = $index;
    }
    
    public function dropIndex($indexName) {
        $idx = $this->getIndex($indexName);
        if ($idx) {
            $this->_dropIndex($idx);
        }
    }

    /**
     *	@return array of jDbUniqueKey
     */
    public function getUniqueKeys() {
        if ($this->uniqueKeys === null)
            $this->_loadIndexesAndKeys();
        return $this->uniqueKeys;
    }

    public function getUniqueKey($name) {
        if ($this->uniqueKeys === null)
            $this->_loadIndexesAndKeys();
        if (isset($this->uniqueKeys[$name]))
            return $this->uniqueKeys[$name];
        return null;
    }

    public function addUniqueKey(jDbUniqueKey $key) {
        $this->alterUniqueKey($key);
    }

    public function alterUniqueKey(jDbUniqueKey $key) {
        $idx = $this->getUniqueKey($index->name);
        if ($idx) {
            $this->_dropIndex($idx);
        }
        $this->_createIndex($index);
        $this->uniqueKeys[$index->name] = $index;
    }

    public function dropUniqueKey($indexName) {
        $idx = $this->getUniqueKey($indexName);
        if ($idx) {
            $this->_dropIndex($idx);
        }
    }

    /**
     *	@return array of jDbReference
     */
    public function getReferences() {
        if ($this->references === null)
            $this->_loadReferences();
        return $this->references;
    }

    public function getReference($refName) {
        if ($this->references === null)
            $this->_loadReferences();

        if (isset($this->references[$refName]))
            return $this->references[$refName];
        return null;
    }

    public function addReference(jDbReference $reference) {
        $this->alterReference($reference);
    }

    public function alterReference(jDbReference $reference) {
        $ref = $this->getReference($reference->name);
        if ($ref) {
            $this->_dropReference($ref);
        }
        $this->_createReference($reference);
        $this->references[$reference->name] = $reference;
    }

    public function dropReference($refName) {
        $ref = $this->getReference($refName);
        if ($ref) {
            $this->_dropReference($ref);
        }
    }
    
    abstract protected function _loadColumns();

    abstract protected function _alterColumn(jDbColumn $old, jDbColumn $new);

    abstract protected function _addColumn(jDbColumn $new);

    abstract protected function _loadIndexesAndKeys();

    abstract protected function _createIndex(jDbIndex $index);

    abstract protected function _dropIndex(jDbIndex $index);

    abstract protected function _loadReferences();
    
    abstract protected function _createReference(jDbReference $ref);

    abstract protected function _dropReference(jDbReference $ref);

}


