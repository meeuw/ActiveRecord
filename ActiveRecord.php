<?php
/**
Copyright (c) 2005 Leendert Brouwer

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
	protected $fields = array();	
	protected $constraints = array();
	protected $table;
	protected $exists = false;
	protected $orderbyFields = array();
	protected $groupbyFields = array();
	
	function ActiveRecord()
	{
		$this->initTable();
		$this->initFields();		
		
		$this->constraints[] = $this->fields[0];
	}
	
	public function get()
	{
		$this->exists = false;
		
		$sql = "SELECT * FROM {$this->table}";
		if(count($this->data) > 0)
			$sql .= " WHERE ".implode(' AND ', $this->getPairedFields($this->data));
			
		$res = mysql_query($sql) or die(mysql_error()." : ".$sql);
		
		if(mysql_num_rows($res) > 0)
		{
			$this->exists = true;			
			$this->data = mysql_fetch_assoc($res);
		}
		
		return $this->exists;
	}
	
	public function getAll()
	{
		$sql = "SELECT * FROM {$this->table}";
		if(count($this->data) > 0)
			$sql .= " WHERE ".implode(' AND ', $this->getPairedFields($this->data));
			
		if(count($this->orderbyFields) > 0)
			$sql .= " ORDER BY ".implode(',', $this->orderbyFields);

		if(count($this->groupbyFields) > 0)
			$sql .= " GROUP BY ".implode(',', $this->groupbyFields);
		
		$res = mysql_query($sql) or die(mysql_error());
		
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
		$sql = "SELECT * FROM {$this->table}";
		if(count($this->data) > 0)
			$sql .= " WHERE ".implode(' AND ', $this->getPairedLikeFields($this->data));
			
		if(count($this->orderbyFields) > 0)
			$sql .= " ORDER BY ".implode(',', $this->orderbyFields);
		
		$res = mysql_query($sql) or die(mysql_error());		
			
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
		$sql = "SELECT * FROM {$this->table}";		
		$sql .= " WHERE ".$whereClause;
				
		$res = mysql_query($sql) or die(mysql_error());
		
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
			$this->data[$key] = $val;
		}		
	}
	
	public function setAttrib($key, $val)
	{		
		$val = $this->quote($val);				
		
		$settername = 'set'.ucfirst($key);
		
		if(method_exists($this, $settername))
		{
			$this->data[$key] = $this->$settername($val);
			return true;
		}
		else
		{
			$this->data[$key] = $val;
			return true;
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
			if(isset($this->data[$key]))
			{
				if($htmlentities)
				{
					return htmlentities($this->data[$key]);
				}
				else
				{
					return $this->data[$key];
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
		if(isset($this->data[$key]))
		{
			unset($this->data[$key]);
		}
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
			//$paired[$key] = $key." = '".addslashes($val)."'";
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
				
		foreach($quoted as $key => $val)
		{
			$quoted[$key] = "'{$val}'";			
		}
		
		return $quoted;
	}
	
	public function getData()
	{
		$data = array();
		
		foreach($this->data as $key => $val)
		{
			$data[$key] = $this->getAttrib($key);
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
				if(in_array($key, $this->fields))
				{
					$this->setAttrib($key, $val);
				}
			}
		}
	}
	
	public function printConfigStats()
	{
		$query = "SELECT ".implode(', ', $this->fields)." FROM {$this->table}";
		$rs = mysql_query($query);
		
		if($rs)
		{
			echo 'OK';
		}
		else
		{
			echo 'The query "'.$query.'" generated errors: '.mysql_error();
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
	
	abstract function initFields();	
	abstract function initTable();
}
?>
