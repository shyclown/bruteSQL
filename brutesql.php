<?php
// Listen to post value
if(isset($_POST)
&& isset($_POST['action'])
){ $data = $_POST; }
else{
  $data = file_get_contents("php://input");
  $data = json_decode($data, true); //array
}
if($data){
  $bq = new bruteSQL;
  /* actions */
  if(isset($data['action'])){
  if ( method_exists($bq, $data['action']) ){
    echo json_encode($bq->{$data['action']}($data));
    }
  }

}

// - - - - - - - - - - - - - - - - - - -
//  BruteSQL
// - - - - - - - - - - - - - - - - - - -

class bruteSQL
{
  private $db;
  public $wall;
  public $errors;

  // Temp
  private $table = false;
  private $connected_tables = [];
  // SET DATA
  private $set_params = [];
  private $set_string = '';
  // WHERE DATA
  private $where_params = [];
  private $where_string = '';

  function __construct(){
    $this->db = new Database;
    $this->errors = [];
    $this->debug = [];
  }
  /* Public functions */
  public function insert($data){ return $this->sqlInsert($data);}
  public function select($data){ return $this->bqSelect($data);}
  public function update($data){ }
  public function delete($data){ }
  public function connect($data){ return $this->sqlConnectRowsByID($data['data'][0],$data['data'][1],$data['data'][2],$data['data'][3]); }
  public function alltables(){ return $this->sqlAllTables(); }

  // LOG
  public function log_read($limit){}
  public function log_drop(){}
  // DUMP TABLES
  public function dump_tables(){ }
  public function drop_table($data){ return $this->sqlDropTable($data); }

  /* Private functions */
  private function sqlDropTable($data){
    if($this->sqlTableExist($data['table'])){
      return $this->db->query("DROP TABLE {$data['table']}");
    }
  }

  // - - - - - - - - - - - - - - - - - - -
  // SELECT
  // - - - - - - - - - - - - - - - - - - -

  public function bqSelect($data)
  {
    $this->table = $data['table'];

    $str_inner = '';

    // WHERE
    if(isset($data['where'])){ $this->bqStringWhere($data['where']); }
    // INTERJOIN
    if($this->connected_tables){
      $str_inner = $this->bqStringInnerJoin($this->table, $this->connected_tables[0]);
    }
    // LIMIT
    // ORDERBY
    $sql = "SELECT {$this->table}.* FROM {$this->table} {$str_inner} {$this->where_string}";
    if($result = $this->db->query($sql,$this->where_params)){
      $this->log('SQL: SELECTED');
      return $result;
    }
    else{
      $this->err("ERROR: {$sql} ");
    }
  }

  // - - - - - - - - - - - - - - - - - - -
  // UPDATE
  // - - - - - - - - - - - - - - - - - - -

  public function sqlUpdate($data)
  {
    $table = $data['table'];
    if($this->sqlTableExist($this->table))
    {
      // SET
      if(isset($data['set'])){ $this->bqStringSet($data['set']); }
      // WHERE
      if(isset($data['where'])){ $this->bqStringWhere($data['where']); }
      // INNERJOIN
      if($this->connected_tables){
        $str_inner = $this->bqStringInnerJoin($this->table, $this->connected_tables[0]);
      }

      $sql = "UPDATE {$table} SET {$set} {$where}"; $this->log($sql); // debug
      if($result = $this->db->query($sql, $params)){
        $this->log('SQL: UPDATED');
        foreach ($params as $k => $value) { $this->log("PARAMETER {$k}: {$value}"); }
        return $result;
      }
      else{
        $this->err("ERROR: {$sql}");
        foreach ($params as $k => $value) { $this->err("PARAMETER {$k}: {$value}"); }
      }
    }
  }

  // - - - - - - - - - - - - - - - - - - -
  // INSERT
  // - - - - - - - - - - - - - - - - - - -

  private function sqlInsert($data)
  {
    $table = $data['table'];
    if( !$this->sqlTableExist($table) ){ $this->sqlCreateTable($table); }
    if(isset($data['values'])){
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
      if($result = $this->db->query($sql,$params,'get_id')){
        $this->log('SQL: INSERTED');
        return $result;
      }
      else{
        $this->err("ERROR: {$sql}");
      }
    }
  }

  // - - - - - - - - - - - - - - - - - - -
  // FUNCTION
  // - - - - - - - - - - - - - - - - - - -

  // STRING INNER JOIN
  private function bqStringInnerJoin($table, $join_table)
  {
    $str_inner = '';
    if($is_connect_table = $this->db->query("SELECT tab FROM bq_connections WHERE `t1` = '{$table}' AND `tab` = '{$join_table}'")){
      $str_inner = "INNER JOIN {$join_table} ON {$table}.id = {$join_table}.{$table}ID";
    }
    elseif($conn_table = $is_table = $this->db->query("SELECT tab FROM bq_connections WHERE `t1` = '{$table}' AND `t2` = '{$join_table}'")[0]['tab']){
      $str_inner = "INNER JOIN {$conn_table} ON {$table}.id = {$conn_table}.{$table}ID
      INNER JOIN {$join_table} ON {$conn_table}.{$join_table}ID = {$join_table}.id";
    }
    else{
      $this->err("Table named {$join_table} is not connected to {$table} table");
    }
    return $str_inner;
  }

  // STRING SET
  private function bqStringSet($set)
  {
    $i = 0;
    foreach ($set as $property => $value)
    {
      $this->bqPropertyExists($property);
      if($this->errors){ continue; }
      else{
        if($i != 0){ $this->set_string .= ', '; }
        $this->set_string .= $property.' = ?';
        if($i == 0){ array_push($this->set_params, $this->param_type($value)); }
        else{ $this->set_params[0] .= $this->param_type($value); }
        array_push($this->set_params, $value);
        $i++;
      }
    }
  }

  // STRING WHERE
  private function bqStringWhere($where)
  {
    $this->where_string .= 'WHERE ';
    foreach ($where[0] as $key => $property){
      $j = $key*2;
      $this->bqPropertyExists($where[0][$key]);
      $this->bqPropertyExists($where[2][$key]);
      if($this->errors){ continue; }
      else{
        $this->where_string .= $this->bqVal($property).' '.$where[1][$j].' '.$this->bqVal($where[2][$key]);
        if(isset($op[$j+1])){ $this->where_string  .= " {$op[$j+1]} "; }
      }
    }
  }

  // VAL
  private function bqVal($value){
    if(strpos($value, ".")!== false){
      if($value[0]=='.'){ $value = substr($value, 1); }
      return $value;
    }else{
      $this->where_params[0] = $this->param_type($value);
      array_push($this->where_params, $value);
      return '?';
    }
  }

  // PROPERTY EXISTS
  private function bqPropertyExists($value)
  {
    if (strpos($value, ".") !== false)
    {
      if($value[0] != "."){
        // defined with table as table.property
        $v = explode(".", $value);
        $table_name = $v[0];
        $test_value = $v[1];
      }
      else{
        // defined without table as .property
        $table_name = $this->table;
        $test_value = substr($value, 1);
      }
      if($this->sqlTableExist($table_name)){
        if($this->bqColumnExists($table_name, $test_value)){
          if($table_name != $this->table && !in_array($table_name, $this->connected_tables)){
            array_push($this->connected_tables, $table_name); // store used table name
            return true;
          }
        }
        else{ $this->err("Could not find column {$test_value} in table: {$table_name}."); }
      }
      else{ $this->err("Could not find table {$table_name} defined in: {$value}."); }
    }
    else{
      return true;
    }
    return false;
  }

  // CONNECT TABLES
  private function bqConnectTable($value)
  {
    if(strpos($value, ".") !== false && $value[0] != "."){
      $table_name = explode(".", $value)[0];
      if($this->sqlTableExist($table_name)){ return $table_name; }
      else{ $this->err("Could not find table {$table_name} defined as part of value: {$value}."); }
    }
    return false;
  }

  // CHECK TABLES PROPRTY
  private function checkTablesProperty($table, $where){
    $properties = $where;
    $connectTables = [];
    foreach ($properties as $k => $property)
    {
      if(!$this->bqColumnExists($table, $property)){
        if($connected_table = $this->bqColumnExistsInConnected($table, $property)){
            array_push($connectTables, $connected_table);
        }
        else{   $this->errors[] = 'Error: Could not find property: '.$property;
        }
      }
    }
    return $connectTables;
  }

  // ERR
  private function err($str){ array_push($this->errors, $str); }

  // LOG
  private function log($str){ array_push($this->debug, $str); }

  // COLUMN EXISTS IN CONNECTED
  private function bqColumnExistsInConnected($table, $property){
    if($this->sqlTableExist('bq_connections')){
      $xTable = $this->db->query("SELECT t2 FROM bq_connections WHERE t1 = {$table}");
      if($this->bqColumnExists($xTable, $property)){ return true; }
    }
    return false;
  }

  // CONNECT ROWS BY ID
  private function sqlConnectRowsByID($tableOne, $tableTwo, $tableOneID, $tableTwoID){
    $this->sqlConnectTables($tableOne, $tableTwo);
    $table = $tableOne.'_'.$tableTwo;
    if(!$this->sqlTableExist($table)){ $table = $tableTwo.'_'.$tableOne; }
    $this->sqlInsert(json_decode('{"table":"'.$table.'","values":{"'.$tableOne.'ID":"'.$tableOneID.'","'.$tableTwo.'ID":"'.$tableTwoID.'"}}', true));
  }

  // CONNECT TABLES
  private function sqlConnectTables($tableOne, $tableTwo){

    if(!$this->sqlTableExist('bq_connections')){
      $this->sqlCreateTable('bq_connections');
    }
    $t1 = $tableOne.'_'.$tableTwo;
    $t2 = $tableTwo.'_'.$tableOne;
    if($this->sqlTableExist($tableOne)
    && $this->sqlTableExist($tableTwo)
    && !$this->sqlTableExist($t1)
    && !$this->sqlTableExist($t2)
    ){

      $table = $tableOne.'_'.$tableTwo;

      $this->sqlCreateTable($table);
      $this->sqlInsert(json_decode('{"table":"bq_connections","values":{"t1":"'.$tableOne.'","t2":"'.$tableTwo.'","tab":"'.$table.'"}}', true));
      $this->sqlInsert(json_decode('{"table":"bq_connections","values":{"t1":"'.$tableTwo.'","t2":"'.$tableOne.'","tab":"'.$table.'"}}', true));
    };
    return false;
  }

  // VALUE TYPE
  private function valueType($val){
    if(is_bool($val)){ return 'tinyint';}
    if(is_numeric($val)){ return 'int';}
    if(is_string($val)){ return 'varchar';}
  }
  private function param_type($value){
    if(is_numeric($value)){ return 'i'; } else { return 's'; }
  }

  // READ NUMBER FROM TYPE
  private function readNumberFromType($type){
    return filter_var($type, FILTER_SANITIZE_NUMBER_INT);
  }

  // TYPE PRIORITY
  private function typePriority($new_type, $old_type){
    $priority = array( 'tinyint' => 0, 'int'=> 1, 'varchar' => 2, 'text'=> 3);
    if($priority[$new_type] > $priority[$old_type]){ return $new_type; }
    else{ return $old_type; }
  }

  //NEXT POWER OF TWO
  private function getNextPowerOfTwo($nr){
    $power = 1; while($power < $nr){ $power*=2; } return $power;
  }

  // ALL TABLES
  private function sqlAllTables(){
    $tables = $this->db->query("SHOW TABLES");
    $tablenames = [];
    foreach ($tables as $k => $value) {
      array_push($tablenames, $value['Tables_in_brutesql']);
    }
    return $tablenames;
  }
  // TABLE EXIST
  private function sqlTableExist($table){
    return $this->db->query(
      "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '{$table}' LIMIT 1"
  );}

  // CREATE TABLE
  private function sqlCreateTable($table){ return $this->db->query(
      "CREATE TABLE IF NOT EXISTS {$table} ( id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY )"
  );}

  // ADD COLUMN
  private function sqlAddColumn($table, $column, $type){ return $this->db->query(
      "ALTER TABLE {$table} ADD COLUMN {$column} {$type}"
  );}

  // CHANGE COLUMN
  private function sqlChangeColumn($table, $column, $type){ return $this->db->query(
      "ALTER TABLE {$table} CHANGE {$column} {$column} {$type}"
  );}

  // GET TABLE COLUMNS
  private function sqlTableColumns($table){ return $this->db->query(
      "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$table}'"
  );}

  // SELECT ALL
  private function sqlSelectAll($table){ return $this->db->query(
      "SELECT * FROM {$table} "
  );}

  // CLOSE DB
  private function sqlClose(){
    $this->db->destroy();
  }


  // COLUMN EXISTS
  private function bqColumnExists($table, $test_column)
  {
    $columns = $this->sqlTableColumns($table);
    foreach ($columns as $key => $column) {
      if($column['COLUMN_NAME'] == $test_column){ return true; }
    }
    return false;
  }

  // PREPARE COLUMN
  private function bqPrepareColumn($table, $property, $value)
  {
    // creates column in table for property if it doesn't exist
    // type is set to type of inserted value
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

/*------------------------------------------
                  Database
------------------------------------------*/

class Database
{
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
