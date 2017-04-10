<?php
namespace Model;
class Select implements Query
{
  private $bqString;
  private $db;

  function __construct(bruteString $bqString, Database $db)
  {
    $this->bqString = $bqString;
    $this->db = $db;
  }
  public function execute()
  {

    $sql = "SELECT {$this->bqString->table}.*
            FROM {$this->bqString->table}
            {$this->bqString->inner_string}
            {$this->bqString->where_string}";

    if($result = $this->db->query($sql, $this->bqString->where_params)){
      $this->bqString->log('SQL: SELECTED');
      return $result;
    }
    else{
      $this->bqString->err("ERROR: {$sql} ");
    }
  }
}



 ?>
