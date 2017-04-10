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

    $params = [];
    function addParamsValues($array, &$params){
      foreach ($array as $key => $value) { if($key!=0){ $params[] = $value; } }
    }
    $params[] = $this->bqString->set_params[0];
    $params[0] .= $this->bqString->where_params[0];
    addParamsValues($this->bqString->set_params, $params);
    addParamsValues($this->bqString->where_params, $params);

    if($result = $this->db->query($sql, $params)){
      $this->bqString->log('SQL: UPDATED');
      return $result;
    }
    else{
      $this->bqString->err("ERROR: {$sql} ");

    }
  }
}



 ?>
