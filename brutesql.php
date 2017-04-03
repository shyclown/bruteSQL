<?php


class bruteSQL {
  private $db;
  public $keys;
  function __construct(){ $this->db = new Database; }

  public function sqlSelect($data){ $data = json_decode($data, true);
    $table = $data['table'];
    $what = [];
    if($this->sqlTableExist($table)){
      // checkSelected
      if(isset($data['select'])){
        foreach ($data['select'] as $select => $value) {
          if($this->bqColumnExists($table, $value)){ array_push($what, $value); }
        }
      }

      if(isset($data['where']))
      {
        $propertyOne = $data['where'][0];
        $propertyTwo = $data['where'][2];
        $propertyOperator = $data['where'][1];

        $listProperties = [];
        $connectTables = [];

        foreach ($propartyOne as $k => $prop) {
          if($this->bqColumnExists($table, $prop)){ array_push($what, $prop); }
          else($table = $this->bqColumnExistsInConnected($table, $prop)){
            array_push($listProperties, $prop); array_push($connectTables, $table);
          }
        }
      }
    }
  }


  private function sqlConnectRowsByID($tableOne, $tableTwo, $tableOneID, $tableTwoID){

    if(!$this->sqlTableExist($tableOne.'_'.$tableTwo)
    && !$this->sqlTableExist($tableTwo.'_'.$tableOne)){
      $this->sqlConnectTables($tableOne, $tableTwo);
    }
    $table = $tableOne.'_'.$tableTwo;
    if(!$this->sqlTableExist($table)){ $table = $tableTwo.'_'.$tableOne; }
    $this->sqlInsert('
    "table":"'.$table.'",
    "values":{"'.$tableOne.'ID":"'.$tableOneID.'","'.$tableTwo.'ID":"'.$tableTwo.'"}
    ');
  }
  private function sqlConnectTables($tableOne, $tableTwo){
    if(!$this->sqlTableExist('bq_connections'){
      $this->sqlCreateTable('bq_connections');
    }
    if($this->sqlTableExist($tableOne)
    && $this->sqlTableExist($tableTwo)
    && !$this->sqlTableExist($tableOne.'_'.$tableTwo)
    && !$this->sqlTableExist($tableTwo.'_'.$tableOne)
    ){
      $this->sqlCreateTable($tableOne.'_'.$tableTwo);
      $this->sqlInsert('"table":"bq_connections","values":{"t1":"'.$tableOne.'","t2":"'.$tableTwo.'"}');
      $this->sqlInsert('"table":"bq_connections","values":{"t1":"'.$tableTwo.'","t2":"'.$tableOne.'"}');
    };

  }
  public function sqlInsert($data){ $data = json_decode($data, true);

    $table = $data['table'];
    if(!$this->sqlTableExist($table)){ $this->sqlCreateTable($table); }
    if(isset($data['values'])){
      var_dump($data);
      $properties = '';
      $values = '';
      $params = [];
      $nr = count($data['values']);
      $i = 0;
      // CHECK AND CREATE COLUMNS
      foreach ($data['values'] as $property => $value){

        $this->bqPrepareColumn($table, $property, $value);

        $properties .= $property;
        $values .= '?';
        if($i == 0){ array_push($params,$this->param_type($value)); }
        else{ $params[0] .= $this->param_type($value); }
        array_push($params, $value);
        if($i != $nr - 1){ $properties .= ', '; $values .= ', ';}
        $i++;
      }
      // INSERT
      $sql = "INSERT INTO {$table} ({$properties}) VALUES ({$values})";
      $this->db->query($sql,$params);
    }

  }
  private function valueType($val){
    if(is_bool($val)){ return 'tinyint';}
    if(is_numeric($val)){ return 'int';}
    if(is_string($val)){ return 'varchar';}
  }
  private function param_type($value){
    if(is_numeric($value)){ return 'i'; } else { return 's'; }
  }
  private function readNumberFromType($type){
    return filter_var($type, FILTER_SANITIZE_NUMBER_INT);
  }
  private function typePriority($new_type, $old_type){
    $priority = array( 'tinyint' => 0, 'int'=> 1, 'varchar' => 2, 'text'=> 3);
    if($priority[$new_type] > $priority[$old_type]){ return $new_type; }
    else{ return $old_type; }
  }
  private function getNextPowerOfTwo($nr){
    $power = 1; while($power < $nr){ $power*=2; } return $power;
  }
  private function sqlTableExist($table){ return $this->db->query(
      "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '{$table}' LIMIT 1"
  );}
  private function sqlCreateTable($table){ return $this->db->query(
      "CREATE TABLE IF NOT EXISTS {$table} ( id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY )"
  );}
  private function sqlAddColumn($table, $column, $type){ return $this->db->query(
      "ALTER TABLE {$table} ADD COLUMN {$column} {$type}"
  );}
  private function sqlChangeColumn($table, $column, $type){ return $this->db->query(
      "ALTER TABLE {$table} CHANGE {$column} {$column} {$type}"
  );}
  private function sqlTableColumns($table){ return $this->db->query(
      "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$table}'"
  );}
  public function sqlSelectAll($table){ return $this->db->query(
      "SELECT * FROM {$table} "
  );}
  public function sqlClose(){
    $this->db->destroy();
  }


  //BruteSQL
  private function bqColumnExists($table, $test_column){
    $columns = $this->sqlTableColumns($table);
    foreach ($columns as $key => $column) {
      if($column['COLUMN_NAME'] == $test_column){ return true; }
    }
    return false;
  }

  private function bqPrepareColumn($table, $property, $value){

    $columns = $this->sqlTableColumns($table);
    $value_length = strlen((string) $value);
    $value_type = $this->valueType($value);
    if($value_type == 'varchar' && $value_length > 256){ $value_type = 'text'; }
    $new_type = "{$value_type}({$value_length})";

    $column_exist = false;
    foreach ($columns as $key => $column)
    {
      if($column['COLUMN_NAME'] == $property)
      {
        $column_exist = true;
        $column_length = $this->readNumberFromType($column['COLUMN_TYPE']);
        $column_type = $column['DATA_TYPE'];
        if($column_type != 'text'){
          $new_length = $this->getNextPowerOfTwo($value_length);
          $new_type = $this->typePriority($value_type, $column_type);
          if($column_length < $new_length || $column_type != $new_type){
            $data_type = "{$new_type}({$new_length})";
            $this->sqlChangeColumn($table, $property, $data_type);
          }
        }
      }
    }
    // CREATE NEW IF DOESNT EXIST
    if (!$column_exist) {
      $this->sqlAddColumn($table, $property, $new_type);
    }
  }
}

$bq = new bruteSQL;
$data = "{}";
$bq->sqlInsert('{
  "table":"named",
  "values":{
    "ue":"hifsddsfdsfsdfsdsName",
    "ufsddse":"dsfsdfsdsName"
  }
}');
$res = $bq->sqlSelectAll('named');
$bq->sqlClose();
var_dump($res);

/**
 *
 */
 class Database{
   public $_mysqli;
   public $error;
   public function __construct(){
     mysqli_report(MYSQLI_REPORT_STRICT);
     try {
       // from define.php file
       $this->_mysqli = new mysqli('localhost', 'root', '', 'brutesql');
       $this->_mysqli->set_charset('utf8');
     }
     catch(Exception $e){
       echo "Service unavailable";
       echo "message: " . $e->message;
       $error = $e->message;
       exit;
     }
   }
   public function select($sql_string){
     $result = $this->_mysqli->query($sql_string);
     if($result){
       $_array = array();
       while($row = $result->fetch_array(MYSQLI_ASSOC))
         { $_array[] = $row; }
       return $_array;
     }
   }
   public function query($sql_string = null, $values = null, $returning = 'array')
   {
     $return_value = false;
     if($stmt = $this->_mysqli->prepare($sql_string)){

       if($values){
         if(is_array($values)){
           if(call_user_func_array(array($stmt, 'bind_param'), $this->refValues($values))){
           };
         }
       }
       if($stmt->execute())
       {
         if($result = $stmt->get_result())
         {
           $_array = array();
           while($row = $result->fetch_array(MYSQLI_ASSOC)){
             $_array[]= $row;
           }
           if($returning == 'array'){
             $return_value = $_array;
           }
         }
         else {
           if($returning == 'get_id'){
             $return_value = $stmt->insert_id;
           }else{
             $return_value = true;
           }
         }
       } else {
         var_dump($this->_mysqli->error);
       }
       $stmt->close();
     }
     else
     {
       var_dump($this->_mysqli->error);
     }
     return $return_value;
   } // end of query
   private function refValues($arr){
     if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
     {
         $refs = array();
         foreach($arr as $key => $value)
             $refs[$key] = &$arr[$key];
         return $refs;
     }
     return $arr;
   }
   public function destroy(){
     $thread_id = $this->_mysqli->thread_id;
     $this->_mysqli->kill($thread_id);
     $this->_mysqli->close();
   }
 }
?>
