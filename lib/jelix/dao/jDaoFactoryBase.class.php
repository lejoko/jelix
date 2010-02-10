<?php
/**
 * @package     jelix
 * @subpackage  dao
 * @author      Laurent Jouanneau
 * @contributor Loic Mathaud
 * @contributor Julien Issler
 * @contributor Thomas
 * @contributor Yoan Blanc
 * @contributor Michael Fradin
 * @contributor Christophe Thiriot
 * @copyright   2005-2010 Laurent Jouanneau
 * @copyright   2007 Loic Mathaud
 * @copyright   2007-2009 Julien Issler
 * @copyright   2008 Thomas
 * @copyright   2008 Yoan Blanc
 * @copyright   2009 Mickael Fradin
 * @copyright   2009 Christophe Thiriot
 * @link        http://www.jelix.org
 * @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
 */

/**
 * base class for all factory classes generated by the dao compiler
 * @package  jelix
 * @subpackage dao
 */
abstract class jDaoFactoryBase  {
    /**
     * informations on tables
     *
     * Keys of elements are the alias of the table. values are arrays like that :
     * <pre> array (
     *   'name' => ' the table alias',
     *   'realname' => 'the real name of the table',
     *   'pk' => array ( list of primary keys name ),
     *   'fields' => array ( list of property name attached to this table )
     * )
     * </pre>
     * @var array
     */
    protected $_tables;
    /**
     * the id of the primary table
     * @var string
     */
    protected $_primaryTable;
    /**
     * the database connector
     * @var jDbConnection
     */
    protected $_conn;
    /**
     * the select clause you can reuse for a specific SELECT query
     * @var string
     */
    protected $_selectClause;
    /**
     * the from clause you can reuse for a specific SELECT query
     * @var string
     */
    protected $_fromClause;
    /**
     * the where clause you can reuse for a specific SELECT query
     * @var string
     */
    protected $_whereClause;
    /**
     * the class name of a dao record for this dao factory
     * @var string
     */
    protected $_DaoRecordClassName;

    /**
     * the selector of the dao, to be sent with events
     * @var string
     */
    protected $_daoSelector;

    /**
     * @since 1.0
     */
    protected $_deleteBeforeEvent = false;
    /**
     * @since 1.0
     */
    protected $_deleteAfterEvent = false;
    /**
     * @since 1.0
     */
    protected $_deleteByBeforeEvent = false;
    /**
     * @since 1.0
     */
    protected $_deleteByAfterEvent = false;

    /**
     * @since 1.0
     */
    protected $trueValue = 1;
    /**
     * @since 1.0
     */
    protected $falseValue = 0;
    /**
     * @param jDbConnection $conn the database connection
     */
    function  __construct($conn){
        $this->_conn = $conn;

        if($this->_conn->hasTablePrefix()){
            foreach($this->_tables as $table_name=>$table){
                $this->_tables[$table_name]['realname'] = $this->_conn->prefixTable($table['realname']);
            }
        }
    }

    /**
     * informations on all properties
     *
     * keys are property name, and values are an array like that :
     * <pre> array (
     *  'name' => 'name of property',
     *  'fieldName' => 'name of fieldname',
     *  'regExp' => NULL, // or the regular expression to test the value
     *  'required' => true/false,
     *  'isPK' => true/false, //says if it is a primary key
     *  'isFK' => true/false, //says if it is a foreign key
     *  'datatype' => '', // type of data : string
     *  'unifiedType'=> '' // the corresponding unified type
     *  'table' => 'grp', // alias of the table the property is attached to
     *  'updatePattern' => '%s',
     *  'insertPattern' => '%s',
     *  'selectPattern' => '%s',
     *  'sequenceName' => '', // name of the sequence when field is autoincrement
     *  'maxlength' => NULL, // or a number
     *  'minlength' => NULL, // or a number
     *  'ofPrimaryTable' => true/false
     *  'autoIncrement'=> true/false
     * ) </pre>
     * @return array informations on all properties
     * @since 1.0beta3
     */
    abstract public function getProperties();

    /**
     * list of id of primary properties
     * @return array list of properties name which contains primary keys
     * @since 1.0beta3
     */
    abstract public function getPrimaryKeyNames();

    /**
     * return all records
     * @return jDbResultSet
     */
    public function findAll(){
        $rs = $this->_conn->query ($this->_selectClause.$this->_fromClause.$this->_whereClause);
        $this->finishInitResultSet($rs);
        return $rs;
    }

    /**
     * return the number of all records
     * @return int the count
     */
    public function countAll(){
        $query = 'SELECT COUNT(*) as c '.$this->_fromClause.$this->_whereClause;
        $rs  = $this->_conn->query ($query);
        $res = $rs->fetch ();
        return intval($res->c);
    }

    /**
     * return the record corresponding to the given key
     * @param string  one or more primary key
     * @return jDaoRecordBase
     */
    final public function get(){
        $args=func_get_args();
        if(count($args)==1 && is_array($args[0])){
            $args=$args[0];
        }
        $keys = array_combine($this->getPrimaryKeyNames(),$args );

        if($keys === false){
            throw new jException('jelix~dao.error.keys.missing');
        }

        $q = $this->_selectClause.$this->_fromClause.$this->_whereClause;
        $q .= $this->_getPkWhereClauseForSelect($keys);

        $rs = $this->_conn->query ($q);
        $this->finishInitResultSet($rs);
        $record =  $rs->fetch ();
        return $record;
    }

    /**
     * delete a record corresponding to the given key
     * @param string  one or more primary key
     * @return int the number of deleted record
     */
    final public function delete(){
        $args=func_get_args();
        if(count($args)==1 && is_array($args[0])){
            $args=$args[0];
        }
        $keys = array_combine($this->getPrimaryKeyNames(), $args);
        if($keys === false){
            throw new jException('jelix~dao.error.keys.missing');
        }
        $q = 'DELETE FROM '.$this->_tables[$this->_primaryTable]['realname'].' ';
        $q.= $this->_getPkWhereClauseForNonSelect($keys);

        if ($this->_deleteBeforeEvent) {
            jEvent::notify("daoDeleteBefore", array('dao'=>$this->_daoSelector, 'keys'=>$keys));
        }
        $result = $this->_conn->exec ($q);
        if ($this->_deleteAfterEvent) {
            jEvent::notify("daoDeleteAfter", array('dao'=>$this->_daoSelector, 'keys'=>$keys, 'result'=>$result));
        }
        return $result;
    }

    /**
     * save a new record into the database
     * if the dao record has an autoincrement key, its corresponding property is updated
     * @param jDaoRecordBase $record the record to save
     */
    abstract public function insert ($record);

    /**
     * save a modified record into the database
     * @param jDaoRecordBase $record the record to save
     */
    abstract public function update ($record);

    /**
     * return all record corresponding to the conditions stored into the
     * jDaoConditions object.
     * you can limit the number of results by given an offset and a count
     * @param jDaoConditions $searchcond
     * @param int $limitOffset
     * @param int $limitCount
     * @return jDbResultSet
     */
    final public function findBy ($searchcond, $limitOffset=0, $limitCount=null){
        $query = $this->_selectClause.$this->_fromClause.$this->_whereClause;
        if ($searchcond->hasConditions ()){
            $query .= ($this->_whereClause !='' ? ' AND ' : ' WHERE ');
            $query .= $this->_createConditionsClause($searchcond);
        }
        $query.= $this->_createGroupClause($searchcond);
        $query.= $this->_createOrderClause($searchcond);

        if($limitCount !== null){
            $rs = $this->_conn->limitQuery ($query, $limitOffset, $limitCount);
        }else{
            $rs = $this->_conn->query ($query);
        }
        $this->finishInitResultSet($rs);
        return $rs;
    }

    /**
     * return the number of records corresponding to the conditions stored into the
     * jDaoConditions object.
     * @author Loic Mathaud
     * @copyright 2007 Loic Mathaud
     * @since 1.0b2
     * @param jDaoConditions $searchcond
     * @return int the count
     */
    final public function countBy($searchcond, $distinct=null) {
        $count = '*';
        if ($distinct !== null) {
            $props = $this->getProperties();
            if (isset($props[$distinct]))
                $count = 'DISTINCT '.$this->_tables[$props[$distinct]['table']]['name'].'.'.$props[$distinct]['fieldName'];
        }

        $query = 'SELECT COUNT('.$count.') as c '.$this->_fromClause.$this->_whereClause;
        if ($searchcond->hasConditions ()){
            $query .= ($this->_whereClause !='' ? ' AND ' : ' WHERE ');
            $query .= $this->_createConditionsClause($searchcond);
        }
        $rs  = $this->_conn->query ($query);
        $res = $rs->fetch();
        return intval($res->c);
    }

    /**
     * delete all record corresponding to the conditions stored into the
     * jDaoConditions object.
     * @param jDaoConditions $searchcond
     * @return number of deleted rows
     * @since 1.0beta3
     */
    final public function deleteBy ($searchcond){
        if ($searchcond->isEmpty ()){
            return;
        }

        $query = 'DELETE FROM '.$this->_tables[$this->_primaryTable]['realname'].' WHERE ';
        $query .= $this->_createConditionsClause($searchcond, false);

        if ($this->_deleteByBeforeEvent) {
            jEvent::notify("daoDeleteByBefore", array('dao'=>$this->_daoSelector, 'criterias'=>$searchcond));
        }
        $result = $this->_conn->exec($query);
        if ($this->_deleteByAfterEvent) {
            jEvent::notify("daoDeleteByAfter", array('dao'=>$this->_daoSelector, 'criterias'=>$searchcond, 'result'=>$result));
        }
        return $result;
    }

    /**
     * create a WHERE clause with conditions on primary keys with given value. This method
     * should be used for SELECT queries. You haven't to escape values.
     *
     * @param array $pk  associated array : keys = primary key name, values : value of a primary key
     * @return string a 'where' clause (WHERE mypk = 'myvalue' ...)
     */
    abstract protected function _getPkWhereClauseForSelect($pk);

    /**
     * create a WHERE clause with conditions on primary keys with given value. This method
     * should be used for DELETE and UPDATE queries.
     * @param array $pk  associated array : keys = primary key name, values : value of a primary key
     * @return string a 'where' clause (WHERE mypk = 'myvalue' ...)
     */
    abstract protected function _getPkWhereClauseForNonSelect($pk);

    /**
    * @internal
    */
    final protected function _createConditionsClause($daocond, $forSelect=true){
        $props = $this->getProperties();
        return $this->_generateCondition ($daocond->condition, $props, $forSelect, true);
    }

    /**
     * @internal
     */
    final protected function _createOrderClause($daocond) {
        $order = array ();
        $props =$this->getProperties();
        foreach ($daocond->order as $name => $way){
            if (isset($props[$name]))
                $order[] = $this->_conn->encloseName($name).' '.$way;
        }

        if(count ($order)){
            return ' ORDER BY '.implode (', ', $order);
        }
        return '';
    }

    /**
     * @internal
     */
    final protected function _createGroupClause($daocond) {
        $group = array ();
        $props = $this->getProperties();
        foreach ($daocond->group as $name) {
            if (isset($props[$name]))
                $group[] = $this->_conn->encloseName($name);
        }

        if (count ($group)) {
            return ' GROUP BY '.implode(', ', $group);
        }
        return '';
    }

    /**
     * @internal it don't support isExpr property of a condition because of security issue (SQL injection)
     * because the value could be provided by a form, it is escaped in any case
     */
    final protected function _generateCondition($condition, &$fields, $forSelect, $principal=true){
        $r = ' ';
        $notfirst = false;
        foreach ($condition->conditions as $cond){
            if ($notfirst){
                $r .= ' '.$condition->glueOp.' ';
            }else
                $notfirst = true;

            $prop=$fields[$cond['field_id']];

            if($forSelect)
                $prefixNoCondition = $this->_tables[$prop['table']]['name'].'.'.$prop['fieldName'];
            else
                $prefixNoCondition = $this->_conn->encloseName($prop['fieldName']);

            $op = strtoupper($cond['operator']);
            $prefix = $prefixNoCondition.' '.$op.' '; // ' ' for LIKE

            if ($op == 'IN' || $op == 'NOT IN'){
                if(is_array($cond['value'])){
                    $values = array();
                    foreach($cond['value'] as $value)
                        $values[] = $this->_prepareValue($value,$prop['unifiedType']);
                    $values = join(',', $values);
                }
                else
                    $values = $cond['value'];

                $r .= $prefix.'('.$values.')';
            }
            else {
                if ($op == 'LIKE' || $op == 'NOT LIKE') {
                    $type = 'varchar';
                }
                else {
                    $type = $prop['unifiedType'];
                }

                if (!is_array($cond['value'])) {
                    $value = $this->_prepareValue($cond['value'], $type);
                    if ($cond['value'] === null) {
                        if (in_array($op, array('=','LIKE','IS','IS NULL'))) {
                            $r .= $prefixNoCondition.' IS NULL';
                        } else {
                            $r .= $prefixNoCondition.' IS NOT NULL';
                        }
                    } else {
                        $r .= $prefix.$value;
                    }
                } else {
                    $r .= ' ( ';
                    $firstCV = true;
                    foreach ($cond['value'] as $conditionValue){
                        if (!$firstCV) {
                            $r .= ' or ';
                        }
                        $value = $this->_prepareValue($conditionValue, $type);
                        if ($conditionValue === null) {
                            if (in_array($op, array('=','LIKE','IS','IS NULL'))) {
                                $r .= $prefixNoCondition.' IS NULL';
                            } else {
                                $r .= $prefixNoCondition.' IS NOT NULL';
                            }
                        } else {
                            $r .= $prefix.$value;
                        }
                        $firstCV = false;
                    }
                    $r .= ' ) ';
                }
            }
        }
        //sub conditions
        foreach ($condition->group as $conditionDetail){
            if ($notfirst){
                $r .= ' '.$condition->glueOp.' ';
            }else{
                $notfirst=true;
            }
            $r .= $this->_generateCondition($conditionDetail, $fields, $forSelect, false);
        }

        //adds parenthesis around the sql if needed (non empty)
        if (strlen (trim ($r)) > 0 && !$principal){
            $r = '('.$r.')';
        }
        return $r;
    }

    /**
     * prepare the value ready to be used in a dynamic evaluation
     */
    final protected function _prepareValue($value, $fieldType, $notNull = false){
        if (!$notNull && $value === null)
            return 'NULL';
        
        switch(strtolower($fieldType)){
            case 'integer':
                return intval($value);
            case 'double':
            case 'float':
                return doubleval($value);
            case 'numeric':
            case 'decimal':
                if(is_numeric($value))
                    return $value;
                else
                    return doubleval($value);
            case 'boolean':
                if ($value === true|| strtolower($value)=='true'|| $value =='1' || $value ==='t')
                    return $this->trueValue;
                else
                    return $this->falseValue;
                break;
            default:
                return $this->_conn->quote ($value, true, ($fieldType == 'binary'));
        }
    }

    /**
     * finish to initialise a record set. Could be redefined in child class
     * to do additionnal processes
     * @param jDbResultSet $rs the record set
     */
    protected function finishInitResultSet($rs) {
        $rs->setFetchMode(8, $this->_DaoRecordClassName);
    }
}
