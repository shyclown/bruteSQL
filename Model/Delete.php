<?php
namespace Model;
class Delete implements Query
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
    $sql = "DELETE {$this->bqString->table}
            FROM {$this->bqString->table}
            {$this->bqString->innerjoin}
            {$this->bqString->where}";

    if($result = $this->db->query($sql,$this->$bqString->where_params))
    {
      $this->log('SQL: DELETED');
      return $result;
    }
    else{
      $this->err("ERROR: {$sql} ");
    }
  }
}



 ?>
