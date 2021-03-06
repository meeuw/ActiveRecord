<?php
/**
Copyright (c) 2007 Matthijs Tempels, Leendert Brouwer, Richard Wolterink (Largest name, smallest contribution)

--------------------------------------- README ----------------------------------
THIS VERSION CACHES TABLE COLUMN INFO!
make sure you make a <root>/inc/classes/tables folder that is writable by the
webserver to make this version work if you set loadFieldsMethod to 'file'
--------------------------------------- README ----------------------------------

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included
in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE. 


OK.. here oes nothing.. radical changes in the behavior of AR :-) 
Records are no longer fetched straigt away.. just the primary keys.. 


**/

abstract class ActiveRecord
{
    protected $data = array();
    protected $fieldObjects = array();
    protected $constraints = array();
    protected $table = '';
    protected $database = '';
    protected $loadFieldsMethod = 'db'; //Change this to file if you want the default setting to change!
    protected $exists = false;
    protected $orderbyFields = array();
    protected $groupbyFields = array();
    protected $limitbyField;
    protected $oneToOnesF2P = array();
    protected $oneToOnesP2F = array();
    protected $oneToManys = array();
    protected $foreignOneToOneObjects = array();
    protected $foreignOneToManysObjects = array();
    protected $hookTables = array();
    protected $foreignsLoaded = false;
    protected $oneToOneMethod = '';
    const FOREIGN_TO_PRIMARY = 0;
    const PRIMARY_TO_FOREIGN = 1;

    abstract function initTable();
    protected function initDatabase() {}
    protected function initLoadFields() {}
    protected function initRelations() {}

    public function __construct()
    {
        $this->initTable();
        $this->initDatabase();
        $this->initLoadFields();
        $this->loadFields();
        $this->constraints[] = $this->getKeyField();
    }

    public function hasOne($object, $method = self::PRIMARY_TO_FOREIGN)
    {
        if ($method == self::PRIMARY_TO_FOREIGN)
        {
            $this->oneToOnesP2F[] = $object;
        }
        else
        {
            $this->oneToOnesF2P[] = $object;
        }
    }

    public function updateTable($table)
    {
        $this->table = $table;
    }

    public function hasMany($object, $hooktable = false, $colThis = false, $colForeign = false)
    {
        $relation = new arRelation();
        $relation->setClass($object);
        $relation->setHooktable($hooktable);
        $relation->setColThis($colThis);
        $relation->setColForeign($colForeign);
        $this->oneToManys[] = $relation;
    }

    protected function addHookTable($table)
    {
        $this->hookTables[] = $table;
    }

    protected function getHookTables()
    {
        return $this->hookTables;
    }

    protected function hasHookTables()
    {
        if(isset($this->hookTables) && count($this->hookTables) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function getKeyField()
    {
        foreach($this->fieldObjects as $field)
        {
            if ($field->isPrimary())
            {
                return $field;
            }
        }
        return false;
    }

    public function loadForeigns($force = false)
    {
        if(!$this->foreignsLoaded || $force == true)
        {
               // and fill all again.. 
            $this->initRelations();

            // load one to ones
            $this->loadForeignsOneToOne();

            // load one to many
            $this->loadForeignsOneToMany();

            $this->foreignsLoaded = true;
        }
    }

    public function loadForeignsExclude($excludes)
    {
        //$excludes is the array of objectnames which have to be excluded.
        $this->initRelations();

        $dummyOneToOnesF2P = array();
        foreach ($this->oneToOnesF2P as $object)
        {
            if (!in_array(get_class($object), $excludes))
            {
                $dummyOneToOnesF2P[] = $object;
            }
        }
        $this->oneToOnesF2P = $dummyOneToOnesF2P;

        $dummyOneToOnesP2F = array();

        foreach ($this->oneToOnesP2F as $object)
        {
            if (!in_array(get_class($object), $excludes))
            {
                $dummyOneToOnesP2F[] = $object;
            }
        }
        $this->oneToOnesP2F = $dummyOneToOnesP2F;

        $dummyOneToManys = array();
        foreach ($this->oneToManys as $arObject)
        {
            if (!in_array(get_class($arObject->getClass()), $excludes))
            {
                $dummyOneToManys[] = $arObject;
            }
        }
        $this->oneToManys = $dummyOneToManys;

        // load one to ones
        $this->loadForeignsOneToOne();

        // load one to many
        $this->loadForeignsOneToMany();

        $this->foreignsLoaded = true;
    }

    protected function loadForeignsOneToOne()
    {
        //first clear old info..
        unset ($this->foreignOneToOneObjects);
        $this->foreignOneToOneObjects = array(); 
        
        //Primary to Foreign
        foreach($this->oneToOnesP2F as $relation)
        {
            if($this->getKeyField())
            {
                $relation->setAttrib(
                $this->getKeyField()->getName(),
                $this->getAttrib($this->getKeyField()->getName())
                );
                if ($relation->get())
                { 
                    $this->foreignOneToOneObjects[get_class($relation)] = $relation; 
                }
            }
        }

        //Foreign to Primary
        foreach($this->oneToOnesF2P as $relation)
        {
            if($relation->getKeyField())
            {
                $relation->setAttrib(
                $relation->getKeyField()->getName(),
                $this->getAttrib($relation->getKeyField()->getName())
                );

                if ($relation->get())
                {
                    $this->foreignOneToOneObjects[get_class($relation)] = $relation; 
                }
            }
        }
    }

    protected function loadForeignsOneToMany()
    {
        //first clear old info..
        unset ($this->foreignOneToManysObjects);
        $this->foreignOneToManysObjects = array();
        foreach($this->oneToManys as $relation_object)
        {
            $relation = $relation_object->getClass();
            $this->foreignOneToManysObjects[get_class($relation)] = array();
            if(!$relation_object->getHooktable())
            {
                $relation->setAttrib(
                $this->getKeyField()->getName(),
                $this->getAttrib($this->getKeyField()->getName())
                );
                foreach($relation->getAll() as $obj)
                {
                    $this->foreignOneToManysObjects[get_class($relation)][] = $obj;
                }
            }
            else
            {
                //ok we have a hooktable, lets see if you have special hook-columns

                if ($relation_object->getColThis())
                {
                    $query = "SELECT * FROM " . $this->getHookTable($relation_object) . " WHERE `".$relation_object->getColThis()."` = '".$this->getKeyField()->getValue()."'";
                }
                else
                {
                    $query = "SELECT * FROM " . $this->getHookTable($relation_object) . " WHERE `".$this->getKeyField()->getName()."` = '".$this->getKeyField()->getValue()."'";
                }

                // query hooktable

                $res = mysql_query($query)
                or die('Error on line '.__LINE__.' '.mysql_error());

                while($row = mysql_fetch_assoc($res))
                {
                    $objName = get_class($relation);
                    $obj = new $objName();
                    if ($relation_object->getColForeign())
                    {
                        $obj->setAttrib($relation->getKeyField()->getName(), $row[$relation_object->getColForeign()]);
                        $obj->get();
                        $this->foreignOneToManysObjects[get_class($relation)][] = $obj;
                    }
                    else
                    {
                        $obj->setAttrib($relation->getKeyField()->getName(), $row[$relation->getKeyField()->getName()]);
                        $obj->get();
                        $this->foreignOneToManysObjects[get_class($relation)][] = $obj;
                    }
                }
            }
        }
    }

    public function getOneToOne($objectName)
    {
        if (array_key_exists($objectName, $this->foreignOneToOneObjects) && $this->foreignOneToOneObjects[$objectName]->getAttrib($this->foreignOneToOneObjects[$objectName]->getKeyField()->getName()) != '')
        {
            return $this->foreignOneToOneObjects[$objectName];
        }
        else
        {
            return false;
        }
    }

    public function getOneToMany($objectName)
    {
        if (array_key_exists($objectName, $this->foreignOneToManysObjects) && count($this->foreignOneToManysObjects[$objectName]) > 0)
        {
            return $this->foreignOneToManysObjects[$objectName];
        }
        else
        {
            return false;
        }
    }

    public function get($loadForeigns = false, $debug = false)
    {
        $this->exists = false;
        $sql = "SELECT * FROM " . $this->getTable();
        if(count($this->getPairedFields()) > 0)
        {
            $sql .= " WHERE ".implode(' AND ', $this->getPairedFields());
        }

        // Sorting in a single get can be useful as well! 
        if(count($this->orderbyFields) > 0) 
        { 
            $sql .= " ORDER BY ".implode(',', $this->orderbyFields); 
        } 
         
        $sql .= ' LIMIT 1';
        if ($debug) 
        { 
            echo '[$sql: ' . $sql . ']<br/>' . "\n"; 
        }
        $res = mysql_query($sql) or die('Error on line '.__LINE__.' '.mysql_error()." : ".$sql);

        if(mysql_num_rows($res) > 0)
        {
            $this->exists = true;
            $this->setInternalData(mysql_fetch_assoc($res));
        }
        if($loadForeigns)
        {
            $this->loadForeigns();
        }
        return $this->exists;
    }

    public function getBySQL($sql)
    {
        $this->exists = false;
        $res = mysql_query($sql) or die('Error on line '.__LINE__.' '.mysql_error()." : ".$sql);
        if(mysql_num_rows($res) > 0)
        {
            $this->exists = true;
            $objects = array();
            while($data = mysql_fetch_assoc($res))
            {
                $classname = get_class($this);
                $object = new $classname();
                $object->setInternalData($data);
                $object->exists = true;
                $objects[] = $object;
            }
            return $objects;
        }
        else
        {
            return false;
        }
    }

    public function getAll()
    {
        $sql = "SELECT * FROM " . $this->getTable();
        if(count($this->getPairedFields()) > 0)
        {
            $sql .= " WHERE ".implode(' AND ', $this->getPairedFields());
        }
        if(count($this->orderbyFields) > 0)
        {
            $sql .= " ORDER BY ".implode(',', $this->orderbyFields);
        }
        if(count($this->groupbyFields) > 0)
        {
            $sql .= " GROUP BY ".implode(',', $this->groupbyFields);
        }
        if(strlen($this->limitbyField) > 0)
        {
            $sql .= " LIMIT " . $this->limitbyField;
        }
        $res = mysql_query($sql) or die('Error on line '.__LINE__.' '.mysql_error());

        $objects = array();
        while($data = mysql_fetch_assoc($res))
        {
            $classname = get_class($this);
            $object = new $classname();
            $object->setInternalData($data);
            $object->exists = true;
            $objects[] = $object;
        }

        return $objects;
    }

    public function getAllLike()
    {
        $sql = "SELECT * FROM " . $this->getTable();
        if(count($this->getPairedLikeFields()) > 0)
        {
            $sql .= " WHERE ".implode(' AND ', $this->getPairedLikeFields());
        }

        if(count($this->orderbyFields) > 0)
        {
            $sql .= " ORDER BY ".implode(',', $this->orderbyFields);
        }

        if(count($this->groupbyFields) > 0)
        {
            $sql .= " GROUP BY ".implode(',', $this->groupbyFields);
        }

        if(strlen($this->limitbyField) > 0)
        {
            $sql .= " LIMIT " . $this->limitbyField;
        }

        $res = mysql_query($sql) or die('Error on line '.__LINE__.' '.mysql_error());

        $objects = array();
        while($data = mysql_fetch_assoc($res))
        {
            $classname = get_class($this);
            $object = new $classname();
            $object->setInternalData($data);
            $object->exists = true;

            $objects[] = $object;
        }

        return $objects;
    }

    public function getAllWhere($whereClause)
    {
        $sql = "SELECT * FROM " . $this->getTable();
        $sql .= " WHERE ".$whereClause;

        $res = mysql_query($sql) or die('Error on line '.__LINE__.' '.mysql_error());

        $objects = array();
        while($data = mysql_fetch_assoc($res))
        {
            $classname = get_class($this);
            $object = new $classname();
            $object->setInternalData($data);
            $object->exists = true;

            $objects[] = $object;
        }

        return $objects;
    }

    /* -----------------------------------
    DATA FETCHING RELATED FUNCTIONS
    ----------------------------------- */


    public function setData($data)
    {
        foreach($data as $key => $val)
        {
            $this->setAttrib($key, $val);
        }
    }

    private function setInternalData($data)
    {
        foreach($data as $key => $val)
        {
            $this->fieldObjects[$key]->setInternalValue($val);
        }
    }

    private function quote($value)
    {
        if (!is_array($value))
        {
            if(get_magic_quotes_gpc())
            {
                $value = stripslashes($value);
            }
            $value = mysql_real_escape_string($value);
        }
        else
        {
            foreach($value as $key=>$val)
            {
                if(get_magic_quotes_gpc())
                {
                    $value[$key] = stripslashes($val);
                }
                $value[$key] = mysql_real_escape_string($val);
            }
        }

        return $value;
    }


    public function setAttrib($key, $val, $method='=')
    {
        $settername = 'set'.ucfirst($key);
        if(method_exists($this, $settername))
        {
            $this->fieldObjects[$key]->setValue($this->$settername($val));
            $this->fieldObjects[$key]->setMethod($method);
            return true;
        }
        else
        {
            if (array_key_exists($key, $this->fieldObjects))
            {
                $this->fieldObjects[$key]->setValue($val);
                $this->fieldObjects[$key]->setMethod($method);
                return true;
            }
            else
            {
                return false;
            }
        }
    }

    public function getAttrib($key, $htmlentities = true)
    {
        $gettername = 'get'.ucfirst($key);

        if(method_exists($this, $gettername))
        {
            return $this->$gettername();
        }
        else
        {
            if(isset($this->fieldObjects[$key]))
            {
                if($htmlentities)
                {
                    return htmlentities($this->fieldObjects[$key]->getValue());
                }
                else
                {
                    return $this->fieldObjects[$key]->getValue();
                }
            }
            else
            {
                return false;
            }
        }
    }

    public function unsetAttrib($key)
    {
        if(isset($this->fieldObjects[$key]) && strlen(trim($this->fieldObjects[$key]->getValue())) > 0)
        {
            $this->fieldObjects[$key]->setValue('');
        }
    }

    public function insert()
    {
        $sql = "INSERT INTO " . $this->getTable() . " (`".implode('`, `', array_keys($this->fieldObjects))."`)
        VALUES (".implode(',', $this->getQuotedFields()).")";
        $res = mysql_query($sql)
        or die('Error on line '.__LINE__.' '.mysql_error().' : '.$sql);

        return mysql_insert_id();
    }

    public function update()
    {
        $sql = "UPDATE " . $this->getTable() . " SET " . implode(',', $this->getPairedFields());
         
        if(count($this->getPairedConstraintFields()) > 0)
        {
            $sql .= " WHERE ".implode(' AND ', $this->getPairedConstraintFields());
        }

        $res = mysql_query($sql)
        or die('Error on line '.__LINE__.' '.mysql_error().' : '.$sql);

        return mysql_affected_rows();
    }

    public function delete()
    {
        //Hier de mogelijkheid voor het verwijderen van PairedFields weggehaald.
        //Dit omdat ik het niet aannemelijk vind dat je alleen het eerste record wilt verwijderen met de naam richard
        //waarbij naam geen primary key is.

        if(count($this->getPairedConstraintFields()) > 0)
        {
            $sql = "DELETE FROM " . $this->getTable() . " WHERE " . implode(' AND ', $this->getPairedConstraintFields());
        }

        $res = mysql_query($sql) or die('Error on line '.__LINE__.' '.mysql_error()." : ".$sql);

        return mysql_affected_rows();
    }

    public function deleteAll()
    {
        $sql = "DELETE FROM " . $this->getTable();
        if(count($this->getPairedFields()) > 0)
        {
            $sql .= " WHERE ".implode(' AND ', $this->getPairedFields());
        }
        $res = mysql_query($sql) or die('Error on line '.__LINE__.' '.mysql_error()." : ".$sql);

        return mysql_affected_rows();
    }


    protected function getPairedFields()
    {
        $paired = array();

        foreach($this->fieldObjects as $field)
        {
            if($field->isTouched())
            {
                $paired[$field->getName()] = '`' . $field->getName()."` " . $field->getMethod() . " '".$this->quote($field->getValue())."'";
            }
        }

        return $paired;
    }

    protected function getPairedConstraintFields()
    {
        $paired = array();

        foreach($this->constraints as $constraint)
        {
            $paired[] = '`' . $constraint->getName()."` " . $constraint->getMethod() . " '".$this->quote($constraint->getValue())."'";
        }

        return $paired;
    }

    protected function getPairedLikeFields()
    {
        // this is obsolete.. we need to check if we still use it somewhere...

        $paired = array();

        foreach($this->fieldObjects as $field)
        {
            if($field->isTouched())
            {
                $paired[$field->getName()] = '`' . $field->getName()."` LIKE '".$this->quote($field->getValue())."'";
            }
        }

        return $paired;
    }

    protected function getQuotedFields()
    {
        $quoted = array();

        foreach($this->fieldObjects as $field)
        {
            $quoted[$field->getName()] = "'".$this->quote($field->getValue())."'";
        }

        return $quoted;
    }

    public function getData()
    {
        $data = array();

        foreach($this->fieldObjects as $field)
        {
            $data[$field->getName()] = $this->getAttrib($field->getName());
        }

        return $data;
    }

    public function addConstraint($constraint_field)
    {
        $this->constraints[] = $constraint_field;
    }

    public function exists()
    {
        return $this->exists;
    }

    public function getFields()
    {
        $f = array();
        foreach($this->fieldObjects as $field)
        {
            $f[] = $field->getName();
        }
        return $f;
    }

    public function getFieldObjects()
    {
        return $this->fieldObjects;
    }

    public function getFieldObject($key)
    {
        return $this->fieldObjects[$key];
    }

    public function setLimitByField($val)
    {
        $this->limitbyField = $val;
    }

    public function setAttribsFromPost()
    {
        if(isset($_POST))
        {
            foreach($_POST as $key => $val)
            {
                if(in_array($key, $this->getFields()))
                {
                    $this->setAttrib($key, $val);
                }
            }
        }
    }

    public function setAttribsFromRequest()
    {
        if(isset($_REQUEST))
        {
            foreach($_REQUEST as $key => $val)
            {
                if(in_array($key, $this->getFields()))
                {
                    $this->setAttrib($key, $val);
                }
            }
        }
    }

    public function setAttribsFromSession($sessionkey = '')
    {
        //$sessionkey is an optional key in the session var
        if ($sessionkey != '')
        {
            if(isset($_SESSION[$sessionkey]))
            {
                foreach($_SESSION[$sessionkey] as $key => $val)
                {
                    if(in_array($key, $this->getFields()))
                    {
                        $this->setAttrib($key, $val);
                    }
                }
            }
        }
        else
        {
            if(isset($_SESSION))
            {
                foreach($_SESSION as $key => $val)
                {
                    if(in_array($key, $this->getFields()))
                    {
                        $this->setAttrib($key, $val);
                    }
                }
            }
        }
    }

    public function addOrderByField($field)
    {
        $this->orderbyFields[] = $field;
    }

    public function addGroupByField($field)
    {
        $this->groupbyFields[] = $field;
    }

    public function loadFields()
    {
        if (strtoupper($this->loadFieldsMethod) == 'DB')
        {
            $sql = 'SHOW COLUMNS FROM ' . $this->getTable();
            //echo '[$sql: ' . $sql . ']<br/>' . "\n";
            $result = mysql_query($sql) or die('Error on line '.__LINE__.' '.mysql_error().' for '.$this->table);
            while ($row = mysql_fetch_object($result))
            {
                $f = new arField();
                $f->setName($row->Field);
                $t = explode("(", $row->Type);
                if (count($t) > 1)
                {
                    $f->setType($t[0]);
                    $f->setSize((int)$t[1]);
                }
                else
                {
                    $f->setType($row->Type);
                    $f->setSize(false);
                }

                if ($row->Key == "PRI")
                {
                    $f->setPrimary();
                }

                $this->fieldObjects[$f->getName()] = $f;
            }
        }
        elseif (strtoupper($this->loadFieldsMethod) == 'FILE')
        {
            // Ok for high volume sites this is a good idea:

            $includefile = dirname(__FILE__) . '/tables/' . $this->getTableFile();

            if (file_exists($includefile) && (filectime($includefile) + 30) > time())
            {
                //deze is nog goed..
            }
            else
            {
                $this->createTableInclude(); //eerst opnieuw maken
            }
            include_once($includefile);
            $t = str_replace('.','_', $this->getTableFile());
            $cols = new $t;

            foreach ($cols->fields as $field)
            {
                if (!array_key_exists($field['FIELD'], $this->fieldObjects))
                {
                    $f = new arField();
                    $f->setName($field['FIELD']);
                    $t = explode("(", $field['TYPE']);
                    if (count($t) > 1)
                    {
                        $f->setType($t[0]);
                        $f->setSize((int)$t[1]);
                    }
                    else
                    {
                        $f->setType($field['TYPE']);
                        $f->setSize(false);
                    }

                    if ($field['KEY'] == "PRI")
                    {
                        $f->setPrimary();
                    }

                    $this->fieldObjects[$f->getName()] = $f;
                }
            }
        }
        else
        {
            die('unknown loadFieldsMethod, check initLoadFields function');
        }
    }


    private function createTableInclude()
    {
        $sql = 'SHOW COLUMNS FROM ' . $this->getTable();
        $result = mysql_query($sql) or die('Error on line '.__LINE__.' '.mysql_error().' for '.$this->getTable());
        $output = "";
        $output .= '<?php' . "\n";
        $output .= 'class ' . str_replace('.','_', $this->getTableFile()) . "\n";
        $output .= '{' . "\n";
        $output .= "\t" . 'public $fields = array();' . "\n";
        $output .= "\t" . 'function __construct()' . "\n";
        $output .= "\t" . '{' . "\n";
        while ($row = mysql_fetch_object($result))
        {
            $output .= "\t\t" . '$this->fields[] = Array("FIELD"=>"' . $row->Field . '", "TYPE"=>"' . $row->Type . '", "KEY"=>"' . $row->Key . '");' . "\n";
        }
        $output .= "\t" . '}' . "\n";
        $output .= '}' . "\n";
        $output .= '?>';

        $includefile = dirname(__FILE__) . '/tables/' . $this->getTableFile();
        file_put_contents($includefile, $output);
        @chmod($includefile, 0777);
    }

    public function getTable()
    {
        $t = '';
        if (strlen($this->database) > 0)
        {
            $t = '`' . $this->database . '`.';
        }

        if (strlen($this->table) > 0)
        {
            $t .= '`' . $this->table . '`';
        }
        else
        {
            return false;
        }
        return $t;
    }

    public function getTableFile()
    {
        $t = 'table.';
        if (strlen($this->database) > 0)
        {
            $t .= $this->database . '.';
        }

        if (strlen($this->table) > 0)
        {
            $t .= $this->table;
        }
        else
        {
            return false;
        }
        $t .= ".php";
        return $t;
    }

    protected function getHookTable($relation_object)
    {
        $t = '';
        if (strlen($this->database) > 0)
        {
            $t = '`' . $this->database . '`.';
        }

        if (strlen($relation_object->getHooktable()) > 0)
        {
            $t .= '`' . $relation_object->getHooktable() . '`';
        }
        else
        {
            return false;
        }
        return $t;
    }

}

class arRelation
{
    private $class = false;
    private $hooktable = false;
    private $colThis = false;
    private $colForeign = false;

    public function setClass($class)
    {
        $this->class = $class;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setHooktable($hooktable)
    {
        $this->hooktable = $hooktable;
    }

    public function getHooktable()
    {
        return $this->hooktable;
    }

    public function setColThis($col)
    {
        $this->colThis = $col;
    }

    public function getColThis()
    {
        return $this->colThis;
    }

    public function setColForeign($col)
    {
        $this->colForeign = $col;
    }

    public function getColForeign()
    {
        return $this->colForeign;
    }
}

class arField
{
    private $name;
    private $type;
    private $method;
    private $size;
    private $primary = false;
    private $value;
    private $touched = false;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setMethod($method)
    {
        $this->method = $method;
    }

    public function getMethod()
    {
        if ($this->method != '')
        {
            return $this->method;
        }
        else
        {
            return '=';
        }
    }

    public function setSize($size)
    {
        $this->size = $size;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function setPrimary()
    {
        $this->primary = true;
    }

    public function isPrimary()
    {
        return $this->primary;
    }

    public function setValue($value)
    {
        $this->value = $value;
        $this->touched = true;
    }

    public function setInternalValue($value, $method = '=')
    {
        $this->value = $value;
        $this->method = $method;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function isTouched()
    {
        return $this->touched;
    }
}
?>
