<?php

abstract class ActiveRecord
{
    const PRIMARY_TO_FOREIGN = 1;
    protected $fieldObjects = array();
    protected $orderbyFields = array();
    protected $limitbyField;
    protected $groupbyFields = array();
    protected $entity = null;
    public function __construct()
    {
        $this->initTable();
    }
    public function __wakeup()
    {
        $this->entity = $this;
    }
    private static function EM()
    {
        return EM();
    }

    /**
     * @return ActiveRecord
     */
    public function getEntity()
    {
        if (!$this->entity) {
            $className = get_called_class();
            if (self::EM()->contains($this)) {
                $this->entity = $this;
            } else {
                $this->entity = new $className();
                $this->entity->entity = $this->entity;
            }
        }
        self::EM()->getUnitOfWork()->getEntityState($this);
        return $this->entity;
    }
    public function hasOne($object, $method = self::PRIMARY_TO_FOREIGN)
    {
    }

    public function hasMany($object, $hooktable = false, $colThis = false, $colForeign = false)
    {
    }

    protected function addHookTable($table)
    {
    }

    protected function getHookTables()
    {
    }

    protected function hasHookTables()
    {
    }

    /**
     * Returns arField for field associated with primary key
     * @return arField
     */
    public function getKeyField()
    {
        $classMetadata = self::EM()->getClassMetadata(get_called_class());
        $field = new arField();

        $field->setName($classMetadata->getColumnName($classMetadata->getIdentifierFieldNames()[0]));
        return $field;
    }

    /**
     * No-op
     * @param bool $force
     */
    public function loadForeigns($force = false)
    {
    }

    /**
     * No-op
     * @param string $className
     */
    protected function loadForeignsOneToOne()
    {
    }

    /**
     * No-op
     * @param string $className
     */
    protected function loadForeignsOneToMany()
    {
    }

    /**
     * Returns *ToOne for related Field by name
     * @param $objectName
     * @return bool|ActiveRecord
     */
    public function getOneToOne($objectName)
    {
        $classMetadata = self::EM()->getClassMetadata(get_called_class());
        $found = false;
        foreach ($classMetadata->getAssociationMappings() as $relation => $mapping) {
            if ($mapping["targetEntity"] == $objectName) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }

        $result = $this->getEntity()->$relation;
        if (is_null($result)) {
            return false;
        }
        if (get_class($result) != $objectName) {
            try {
                /** @var $result \Doctrine\ORM\Proxy\Proxy */
                $result->__load();
                return $result;
            } catch (Doctrine\ORM\EntityNotFoundException $e) {
                $result = new $objectName();
                return $result;
            }
        }
        return $result;
    }

    public function getOneToMany($objectName)
    {
        $relation = "id$objectName";
        $result = $this->getEntity()->$relation;
        if (is_null($result)) {
            return false;
        } else {
            return iterator_to_array($result);
        }
    }

    public function get($loadForeigns = false)
    {
        /** @var $query \Doctrine\ORM\QueryBuilder */
        $query = self::EM()->createQueryBuilder()->select('o')->from(get_called_class(), 'o');
        $query = $this->getEntity()->where($query)->setMaxResults(1);
        $result = $query->getQuery()->getResult();
        if ($result) {
            $this->entity = $result[0];
            return true;
        } else {
            return false;
        }
    }

    public function getBySQL($sql, $parameters=null)
    {
        return iterator_to_array($this->getGenerator($sql, $parameters));
    }

    public function getGenerator($sql = null, $parameters=null)
    {
        if ($sql) {
            $rsm = new Doctrine\ORM\Query\ResultSetMappingBuilder(self::EM());
            $rsm->addRootEntityFromClassMetadata(get_called_class(), 'o');
            $query = self::EM()->createNativeQuery($sql, $rsm);
            if ($parameters) {
                foreach ($parameters as $key => $value) {
                    $query->setParameter($key, $value);
                }
            }
            foreach ($query->iterate() as $item) {
                yield $item[0];
            }
        } else {
            /** @var $query \Doctrine\ORM\QueryBuilder */
            $query = self::EM()->createQueryBuilder()->select('o')->from(get_called_class(), 'o');
            foreach ($this->getEntity()->orderbyFields as $key) {
                $s = explode(" ", $key);

                $field = self::EM()->getClassMetadata(get_called_class())->getFieldForColumn($s[0]);
                if (count($s) == 2) {
                    $order = $s[1];
                } else {
                    $order = null;
                }
                $query->addOrderBy("o.".$field, $order);
            }
            foreach ($this->getEntity()->groupbyFields as $key) {
                $field = self::EM()->getClassMetadata(get_called_class())->getFieldForColumn($key);
                $query->addGroupBy("o.".$field);
            }
            if ($this->getEntity()->limitbyField) {
                $s = explode(" ", $this->getEntity()->limitbyField);
                $query->setMaxResults($s[0]);
                if (count($s) == 2) {
                    $query->setFirstResult($s[1]);
                }
            }
            /** @var $query \Doctrine\ORM\Query */
            $query = $this->getEntity()->where($query)->getQuery();
            foreach ($query->iterate() as $item) {
                yield $item[0];
            }
        }
    }

    public function getAll()
    {
        return iterator_to_array($this->getGenerator());
    }

    public function getAllLike()
    {
        /** @var $query \Doctrine\ORM\QueryBuilder */
        $query = self::EM()->createQueryBuilder()->select('o')->from(get_called_class(), 'o');
        foreach ($this->getEntity()->orderbyFields as $key) { //TODO: test this
            $s = explode(" ", $key);
            if (count($s) < 2) {
                $s[] = null;
            }
            $orderbyField = self::EM()->getClassMetadata(get_called_class())->getFieldForColumn($s[0]);
            $query = $query->orderBy("o.".$orderbyField, $s[1]);
        }
        foreach ($this->getEntity()->groupbyFields as $key) {
            $groupbyField = self::EM()->getClassMetadata(get_called_class())->getFieldForColumn($key);

            $query = $query->groupBy("o.".$groupbyField);
        }
        return $this->getEntity()->where($query, "or", "like")->getQuery()->getResult();
    }

    public function getAllWhere($whereClause, $useAttribValues = false)
    {
        $where = array($whereClause);
        $parameters = null;
        if ($useAttribValues && count($this->getEntity()->fieldObjects) > 0) {
            foreach ($this->getEntity()->fieldObjects as $key => $value) {
                $where[] = "$key = ?";
                if (!$parameters) {
                    $parameters = array();
                }
                $parameters[] = $value[0];
            }
        }
        $where = implode(" AND ", $where);

        $sql = "select * from {$this->table} where $where";
        if (count($this->getEntity()->groupbyFields) > 0) {
            $sql .= " GROUP BY " . implode(',', $this->getEntity()->groupbyFields);
        }
        if (count($this->getEntity()->orderbyFields) > 0) {
            $sql .= " ORDER BY " . implode(',', $this->getEntity()->orderbyFields);
        }
        return $this->getEntity()->getBySQL($sql, $parameters);
    }

    /* -----------------------------------
       DATA FETCHING RELATED FUNCTIONS
       ----------------------------------- */


    public function setData($data)
    {
    }

    public function setAttrib($key, $val, $method='=')
    {
        if (!$val) {
            $val = "";
        }

        $classMetadata = self::EM()->getClassMetadata(get_called_class());
        try {
            $field = $classMetadata->getFieldForColumn($key);
        } catch (Doctrine\ORM\Mapping\MappingException $e) {
            $field = "";
        }
        if ($classMetadata->hasField($field) or $classMetadata->hasAssociation($field)) {
            $settername = 'set' . ucfirst($key);
            if (method_exists($this->getEntity(), $settername)) {
                $this->getEntity()->$settername($val);
                return true;
            }
            $this->getEntity()->fieldObjects[$key] = array($val, $method);
            if ($classMetadata->hasField($field)) {
                if ($classMetadata->getTypeOfField($field) == "datetime") {
                    $this->getEntity()->$field = DateTime::createFromFormat('Y-m-d H:i:s', $val);
                } else {
                    $this->getEntity()->$field = $val;
                }
            } else {
                $this->getEntity()->$field = self::EM()->find($classMetadata->getAssociationMapping($field)["targetEntity"], $val);
            }
            return true;
        } else {
            return false;
        }
    }
    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param string $where_join_type
     * @param null $force_operator
     * @return \Doctrine\ORM\QueryBuilder
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function where($query, $where_join_type="and", $force_operator=null)
    {
        foreach ($this->getEntity()->fieldObjects as $key => $value) {
            $key = self::EM()->getClassMetadata(get_called_class())->getFieldForColumn($key);
            if ($force_operator) {
                $operator = $force_operator;
            } else {
                $operator = $value[1];
            }
            if ($where_join_type == "and") {
                $query = $query->andWhere("o.$key $operator :$key")->setParameter($key, "$value[0]");
            } elseif ($where_join_type == "or") {
                $query = $query->orWhere("o.$key $operator :$key")->setParameter($key, "$value[0]");
            }
        }
        return $query;
    }
    public function getAttrib($key, $htmlentities = true)
    {
        $gettername = 'get' . ucfirst($key);

        if (method_exists($this->getEntity(), $gettername)) {
            $result = $this->getEntity()->$gettername();
        } else {
            try {
                $fieldName = self::EM()->getClassMetadata(get_called_class())->getFieldForColumn($key);
            } catch (Doctrine\ORM\Mapping\MappingException $e) {
                $fieldName = "";
            }

            if (property_exists(get_called_class(), $fieldName)) {
                $result = $this->getEntity()->$fieldName;
                if ($result instanceof DateTime) {
                    $result = $result->format('Y-m-d H:i:s');
                    if (substr($result, -8) == "00:00:00") {
                        $result = substr($result, 0, -9);
                    }

                }
                if (is_object($result)) {
                    $result = $result->$fieldName;
                }
            } else {
                return false;
            }
        }
        if ($htmlentities) {
            return htmlentities($result, ENT_NOQUOTES, 'UTF-8');
        } else {
            return $result;
        }
    }

    public function unsetAttrib($key)
    {
    }

    public function insert()
    {
        self::EM()->persist($this->getEntity());
        self::EM()->flush($this->getEntity());

        $keyField = $this->getEntity()->getKeyField();
        return $this->getEntity()->getAttrib($keyField->getName());
    }

    public function update()
    {
        self::EM()->flush();
    }

    public function delete()
    {
        self::EM()->remove($this->getEntity());
    }

    public function deleteAll()
    {
        /** @var $query \Doctrine\ORM\QueryBuilder */
        $query = self::EM()->createQueryBuilder()->delete()->from(get_called_class(), 'o');
        $query = $this->getEntity()->where($query)->getQuery();
        /** @var $query \Doctrine\ORM\QueryBuilder */
        $query->execute();
    }


    protected function getPairedFields()
    {
    }

    protected function getPairedConstraintFields()
    {
    }

    protected function getPairedLikeFields()
    {
    }

    protected function getQuotedFields()
    {
    }

    public function getData()
    {
    }

    public function addConstraint($constraint_field)
    {
    }

    public function exists()
    {
        return self::EM()->getUnitOfWork()->getEntityState($this->getEntity()) == \Doctrine\ORM\UnitOfWork::STATE_MANAGED;
    }

    public function getFields()
    {
        $f = array();
        foreach ($this->getFieldObjects() as $field) {
            $f[] = $field->getName();
        }

        return $f;
    }

    public function getFieldObjects()
    {
        $result = array();
        $classMetadata = self::EM()->getClassMetadata(get_called_class());
        foreach ($classMetadata->getColumnNames() as $columnName) {
            $field = new arField();
            $field->setName($columnName);
            $result[$columnName] = $field;
        }
        foreach ($classMetadata->getAssociationMappings() as $name => $mapping) {
            if ($mapping["type"] != \Doctrine\ORM\Mapping\ClassMetadataInfo::MANY_TO_ONE) {
                continue;
            }
            $targetMetadata = self::EM()->getClassMetadata($mapping["targetEntity"]);
            $columnName = $targetMetadata->getColumnName($name);
            $field = new arField();
            $field->setName($columnName);
            $result[$columnName] = $field;
        }
        return $result;
    }

    public function getFieldObject($key)
    {
    }

    public function setLimitByField($val)
    {
        $this->getEntity()->limitbyField = $val;
    }

    public function setAttribsFromPost()
    {
    }

    public function setAttribsFromRequest()
    {
        if (isset($_REQUEST)) {
            foreach ($_REQUEST as $key => $val) {
                $this->setAttrib($key, $val);
            }
        }
    }

    public function addOrderByField($field)
    {
        $this->getEntity()->orderbyFields[$field] = $field;
    }

    public function addGroupByField($field)
    {
        $this->getEntity()->groupbyFields[$field] = $field;
    }

    private function loadFields()
    {
    }

    abstract function initTable();
    protected function initDatabase() {}
    protected function initRelations() {}

    protected function getTable()
    {
    }
    protected function getHookTable($relation_object)
    {
    }

}

class arField
{
    private $name;
    public function setName($name)
    {
        $this->name = $name;
    }
    public function getName()
    {
        return $this->name;
    }
}
?>
