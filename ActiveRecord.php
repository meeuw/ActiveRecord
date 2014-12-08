<?php
/**
Copyright (c) 2005 Leendert Brouwer, Matthijs Tempels

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
**/
        
abstract class ActiveRecord
{    
    protected $data = array();
    protected $fieldObjects = array();
    protected $constraints = array();
    protected $table;
    protected $database = '';
    protected $exists = false;
    protected $orderbyFields = array();
    protected $groupbyFields = array();
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
        
    function ActiveRecord()
    {
        $this->initTable();
        $this->initDatabase();
        
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
        
    public function get($loadForeigns = false)
    {
        $this->exists = false;
        
        $sql = "SELECT * FROM `{$this->database}`.`{$this->table}`";
        if(count($this->fieldObjects) > 0)
            $sql .= " WHERE ".implode(' AND ', $this->getPairedFields($this->getFields()));
        $sql .= 'LIMIT 1';
        //echo '[$sql: ' . $sql . ']<br/>' . "\n";
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
            $this->setInternalData(mysql_fetch_assoc($res));
        }
        
        if(!$this->lazy)
        {
            $this->loadForeigns();
        }
        
        return $this->exists;
    }
        
    public function loadForeigns()
    {        
        if(!$this->foreignsLoaded)
        {
            $this->initRelations();
        
            // load one to ones
            $this->loadForeignsOneToOne();
        
            // load one to many
            $this->loadForeignsOneToMany();
        
            $this->foreignsLoaded = true;
        }
    }
        
    protected function loadForeignsOneToOne()
    {
        //Primary to Foreign
        foreach($this->oneToOnesP2F as $relation)
        {            
            if($this->getKeyField())
            {                
                $relation->setAttrib(
                    $this->getKeyField()->getName(), 
                    $this->getAttrib($this->getKeyField()->getName())                                    
                );
                $relation->get();                
                $this->foreignOneToOneObjects[get_class($relation)] = $relation;
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
        
                $relation->get();                
        
                $this->foreignOneToOneObjects[get_class($relation)] = $relation;
            }            
        }
    }
        
    protected function loadForeignsOneToMany()
    {
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
                    $query = "SELECT * FROM `{$this->database}`.`{$relation_object->getHooktable()}`".
                    " WHERE ".$relation_object->getColThis()." = '".$this->getKeyField()->getValue()."'";            
                }
                else
                {
                    $query = "SELECT * FROM `{$this->database}`.`{$relation_object->getHooktable()}`".
                    " WHERE ".$this->getKeyField()->getName()." = '".$this->getKeyField()->getValue()."'";            
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
        if (array_key_exists($objectName, $this->foreignOneToOneObjects))
        {
            return $this->foreignOneToOneObjects[$objectName];    
        }
        else
        {
            //echo ("AR::OnToOne: Key $objectName does not exist");
            return false;
        }
    }
        
    public function getOneToMany($objectName)
    {    
        if (array_key_exists($objectName, $this->foreignOneToManysObjects))
        {
            return $this->foreignOneToManysObjects[$objectName];
        }
        else
        {
            //echo ("AR::OnToMany: Key $objectName does not exist");
            return false;
        }
    }
        
    public function getAll()
    {
        $sql = "SELECT * FROM `{$this->database}`.`{$this->table}`";
        if(count($this->getPairedFields()) > 0)
            $sql .= " WHERE ".implode(' AND ', $this->getPairedFields());
        
        if(count($this->orderbyFields) > 0)
            $sql .= " ORDER BY ".implode(',', $this->orderbyFields);
        
        if(count($this->groupbyFields) > 0)
            $sql .= " GROUP BY ".implode(',', $this->groupbyFields);
        
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
        $sql = "SELECT * FROM `{$this->database}`.`{$this->table}`";
        if(count($this->getPairedLikeFields()) > 0)
            $sql .= " WHERE ".implode(' AND ', $this->getPairedLikeFields());
        
        if(count($this->orderbyFields) > 0)
            $sql .= " ORDER BY ".implode(',', $this->orderbyFields);

        if(count($this->groupbyFields) > 0)
            $sql .= " GROUP BY ".implode(',', $this->groupbyFields);
        
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
        $sql = "SELECT * FROM `{$this->database}`.`{$this->table}`";        
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
        
    public function setData($data)
    {
        foreach($data as $key => $val)
        {
            $this->setAttrib($key, $val);
        }        
    }
        
    protected function setInternalData($data)
    {        
        foreach($data as $key => $val)
        {
            $this->fieldObjects[$key]->setInternalValue($val);            
        }                
    }
        
    public function setAttrib($key, $val)
    {        
        
        $settername = 'set'.ucfirst($key);
        
        if(method_exists($this, $settername))
        {
            $this->fieldObjects[$key]->setValue($this->$settername($val));
            return true;
        }
        else
        {
            if (array_key_exists($key, $this->fieldObjects))
            {
                $this->fieldObjects[$key]->setValue($val);
                return true;
            }
            else
            {
                //trigger_error("Key: " . $key . "does not exist..", E_USER_WARNING);
                return false;
            }
        }
    }
        
    private function quote($value)
    {        
        if(get_magic_quotes_gpc())
        {            
            $value = stripslashes($value);
        }                                
        
        $value = mysql_real_escape_string($value);
        return $value;
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
        $sql = "INSERT INTO `{$this->database}`.`{$this->table}` (`".implode('`, `', array_keys($this->fieldObjects))."`)
        VALUES (".implode(',', $this->getQuotedFields()).")";
        $res = mysql_query($sql)
        or die('Error on line '.__LINE__.' '.mysql_error().' : '.$sql);
        
        return mysql_insert_id();
    }
        
    public function update()
    {
        $sql = "
            UPDATE
                `{$this->database}`.`{$this->table}`
            SET
                ".implode(',', $this->getPairedFields($this->getFields()))."            
        ";
        
        if(count($this->fieldObjects) > 0)
            $sql .= " WHERE ".implode(' AND ', $this->getPairedConstraintFields());
        
        $res = mysql_query($sql)
        or die('Error on line '.__LINE__.' '.mysql_error().' : '.$sql);
        
        return mysql_affected_rows();
    }
        
    public function delete()
    {
        $sql = "DELETE FROM `{$this->database}`.`{$this->table}`";
        if(count($this->fieldObjects) > 0)
            $sql .= " WHERE ".implode(' AND ', $this->getPairedConstraintFields());
        
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
                $paired[$field->getName()] = '`' . $field->getName()."` = '".$this->quote($field->getValue())."'";
            }
        }
        
        return $paired;
    }
        
    protected function getPairedConstraintFields()
    {
        $paired = array();
        
        foreach($this->constraints as $constraint)
        {            
            $paired[] = '`' . $constraint->getName()."` = '".$this->quote($constraint->getValue())."'";
        }                
        
        return $paired;
    }
        
    protected function getPairedLikeFields()
    {        
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
        
    public function addOrderByField($field)
    {
        $this->orderbyFields[] = $field;
    }
        
    public function addGroupByField($field)
    {
        $this->groupbyFields[] = $field;
    }        
    private function loadFields()
    {
        $sql = 'SHOW COLUMNS FROM ' . "`{$this->database}`.`{$this->table}`";
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
        
    abstract function initTable();
    public function initDatabase() {}
    public function initRelations() {}
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
        
    public function setInternalValue($value)
    {
        $this->value = $value;
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
