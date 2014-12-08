<?php
abstract class ActiveRecord
{	
	protected $data = array();
	protected $fields = array();	
	protected $constraints = array();
	protected $table;
	protected $exists = false;
	
	function ActiveRecord()
	{
		$this->initFields();
		$this->initTable();
		
		$this->constraints[] = $this->fields[0];
	}
	
	public function get()
	{
		$sql = "SELECT * FROM {$this->table}";
		if(count($this->data) > 0)
			$sql .= " WHERE ".implode(' AND ', $this->getPairedFields($this->data));
			
		$res = mysql_query($sql) or die(mysql_error()." : ".$sql);
		
		if(mysql_num_rows($res) > 0)
		{
			$this->exists = true;
		}
		
		$this->data = mysql_fetch_assoc($res);
	}
	
	public function getAll()
	{
		$sql = "SELECT * FROM {$this->table}";
		if(count($this->data) > 0)
			$sql .= " WHERE ".implode(' AND ', $this->getPairedFields($this->data));
		
		$res = mysql_query($sql) or die(mysql_error());
		
		$objects = array();
		while($data = mysql_fetch_assoc($res))
		{
			$classname = get_class($this);
			$object = new $classname();
			$object->setData($data);
			$object->exists = true;
			
			$objects[] = $object;
		}
		
		return $objects;
	}
	
	public function getAllLike()
	{
		$sql = "SELECT * FROM {$this->table}";
		if(count($this->data) > 0)
			$sql .= " WHERE ".implode(' AND ', $this->getPairedLikeFields($this->data));
		
		$res = mysql_query($sql) or die(mysql_error());		
			
		$objects = array();
		while($data = mysql_fetch_assoc($res))
		{
			$classname = get_class($this);
			$object = new $classname();
			$object->setData($data);
			$object->exists = true;
			
			$objects[] = $object;
		}
		
		return $objects;
	}
	
	public function setData($data)
	{
		$this->data = $data;
	}
	
	public function setAttrib($key, $val)
	{
		$val = mysql_real_escape_string($val);
		
		$settername = 'set'.ucfirst($key);
		
		if(method_exists($this, $settername))
		{
			return $this->$settername($val);
		}
		else
		{
			$this->data[$key] = $val;
			return true;
		}
	}
	
	public function getAttrib($key)
	{
		$gettername = 'get'.ucfirst($key);
		
		if(method_exists($this, $gettername))
		{
			return $this->$gettername();
		}
		else
		{
			if(isset($this->data[$key]))
			{
				return $this->data[$key];
			}
			else
			{
				return false;
			}
		}
	}
		
	public function load()
	{
		$res = mysql_query("SELECT * FROM {$this->table} WHERE {$this->IDField} = '{$this->id}'")
			or die(mysql_error());
			
		$this->data = mysql_fetch_assoc($res);
	}
	
	public function insert()
	{				
		$sql = "INSERT INTO {$this->table} (".implode(', ', array_keys($this->data)).")
			VALUES (".implode(',', $this->getQuotedFields()).")";
		$res = mysql_query($sql)
			or die(mysql_error().' : '.$sql);
		
		return mysql_insert_id();
	}
	
	public function update()
	{
		$sql = "
			UPDATE
				{$this->table}
			SET
				".implode(',', $this->getPairedFields($this->data))."			
		";
		
		if(count($this->data) > 0)
			$sql .= " WHERE ".implode(' AND ', $this->getPairedConstraintFields());
		
		$res = mysql_query($sql)
			or die(mysql_error().' : '.$sql);
		
		return mysql_affected_rows();
	}
	
	public function delete()
	{
		$sql = "DELETE FROM {$this->table}";
		if(count($this->data) > 0)
			$sql .= " WHERE ".implode(' AND ', $this->getPairedFields());
			
		$res = mysql_query($sql) or die(mysql_error()." : ".$sql);

		return mysql_affected_rows(); 
	}
	
	protected function getPairedFields()
	{
		$paired = $this->data;
		
		foreach($paired as $key => $val)
		{
			$paired[$key] = $key." = '".$val."'";
		}
		
		return $paired;
	}
	
	protected function getPairedConstraintFields()
	{
		$paired = array();
		
		foreach($this->constraints as $constraint)
		{			
			$paired[] = $constraint." = '".$this->data[$constraint]."'";
		}
		
		return $paired;
	}
	
	protected function getPairedLikeFields()
	{
		$paired = $this->data;
		
		foreach($paired as $key => $val)
		{
			$paired[$key] = $key." LIKE '".$val."'";
		}
		
		return $paired;
	}
	
	protected function getQuotedFields()
	{
		$quoted = $this->data;
		
		$num = count($quoted);
		foreach($quoted as $key => $val)
		{
			$quoted[$key] = "'{$val}'";			
		}
		
		return $quoted;
	}
	
	public function getData()
	{
		return $this->data;
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
		return $this->fields;
	}
	
	public function setAttribsFromPost()
	{
		if(isset($_POST))
		{
			foreach($_POST as $key => $val)
			{
				if(in_array($key, $this->fields))
				{
					$this->data[$key] = $val;
				}
			}
		}
	}
	
	abstract function initFields();	
	abstract function initTable();
}
?>
