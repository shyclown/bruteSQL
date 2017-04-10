<?php
namespace Model;
class Update implements Query
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
    $sql = "UPDATE {$this->bqString->table}
            SET {$this->bqString->set_string}
            {$this->bqString->inner_string}
            {$this->bqString->where_string}";

    if($result = $this->db->query($sql, $this->where_params)){
      $this->log('SQL: SELECTED');
      return $result;
    }
    else{
      $this->err("ERROR: {$sql} ");
    }
  }
}



 ?>
