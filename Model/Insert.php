<?php
namespace Model;
class Insert implements Query
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
      if( !$this->bqString->sqlTableExist($this->bqString->table) ){
        $this->bqString->sqlCreateTable($this->bqString->table);
      }

      $sql = "INSERT INTO {$this->bqString->table}
              ({$this->bqString->value_properties})
              VALUES ({$this->bqString->value_values})";

      if($result = $this->db->query($sql, $this->bqString->value_params, 'get_id')){
        $this->bqString->log('SQL: INSERTED');
        return $result;
      }
      else{
        $this->bqString->err("ERROR: {$sql}");
      }
  }
}




 ?>
