<?php
namespace Model;

/**
 *
 */
class bqQuery
{
  // INNERJOIN
  // disconnect
  public function selectConnectionTable($tableA, $tableB){
    return "SELECT tab FROM bq_connections WHERE `t1` = '{$tableA}' AND `t2` = '{$tableB}'";
  }
  public function isTableConnection($tableA, $tableConnection){
    
  }

}



 ?>
