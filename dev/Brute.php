<?php
spl_autoload_register(function ($class_name) { require ($class_name . '.php'); });



class bqData extends AnotherClass
{
  public $data;
  function __construct($data)
  {
    $this->data = $data;
  }
}

class Brute
{
  private $action;
  private $data;

  function __construct($data){
    $this->db = new Model/Database;
    $this->data = new Model/bqData($data);
    $this->strings = new Model/bqString($this->data, $this->db);
    $this->query = new Model/Query($this->strings, $this->db);
  }
  private function query(Query $action){
    $this->createdQuery = new $action();
    return ($this->createdQuery->execute($this->data));

    return $this->query->select(new bqString(new bqData($this->data)));
  }



  // QUERY SQL
  private function insert(){ $this->query(new Insert()); }
  private function select(){ $this->query(new Select()); }
  private function update(){ $this->query(new Update()); }
  private function delete(){ $this->query(new Delete()); }
}
