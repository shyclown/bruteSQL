<?php
namespace Model;
class Disconnect implements Query
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
    $sql = "DELETE {$this->bqString->inner_table}
            FROM {$this->bqString->inner_table}
            {$this->bqString->inner_string}
            {$this->bqString->where_string}";

    if($result = $this->db->query($sql, $this->bqString->where_params)){
      $this->bqString->log('SQL: DELETED');
      return $result;
    }
    else{
      $this->bqString->err("ERROR: {$sql} ");
    }
  }
}



 ?>
