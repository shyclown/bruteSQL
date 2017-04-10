<?php
namespace Model;

class bruteString
{
  public $errors = [];
  public $debug = [];

  public $table;
  public $connected_tables = [];

  public $set_string = '';
  public $set_params = [];

  public $where_string = '';
  public $where_params = [];

  public $inner_string = '';
  public $inner_tables = [];

  public $value_values = '';
  public $value_properties = '';
  public $value_params = [];

  function __construct(\bqData $bqData, Database $db)
  {
    $this->db = $db;
    $this->data = $bqData->data;
    $this->buildStrings();
  }
  // ERROR LOG
  public function err($str){ array_push($this->errors, $str); }
  // DEBUG LOG
  public function log($str){ array_push($this->debug, $str); }

  private function buildStrings()
  {
    if(isset($this->data['table'])){ $this->table = $this->data['table']; }
    if(isset($this->data['where'])){ $this->bqStringWhere($this->data['where']); }
    if(isset($this->data['values'])){ $this->bqStringValues($this->data['values']); }
    if(isset($this->data['set'])){ $this->bqStringSet($this->data['set']); }
    if($this->connected_tables){ $this->bqStringInnerJoin($this->table, $this->connected_tables[0]);}
  }

  // STRING INNER JOIN
  private function bqStringInnerJoin($tableA, $tableB)
  {
    if($this->isConnectionTable($tableA, $tableB)){
        $this->inner_string = innerJoinTwo($tableA, $tableB);
    }
    elseif($connect = $this->selectConnectionTable($tableA, $tableB)){
      $this->inner_string = $this->innerJoinThree($tableA, $connect, $tableB);
    }
    else{
      $this->err("Table named {$join_table} is not connected to {$table} table");
    }
  }
  private function innerJoinTwo($tableA, $tableB){
    return "INNER JOIN {$tableB} ON {$tableB}.{$tableA}ID = {$tableA}.id";
  }
  private function innerJoinThree($tableA, $connect, $tableB){
    return "INNER JOIN {$connect} ON {$connect}.{$tableA}ID = {$tableA}.id
            INNER JOIN {$tableB}  ON {$connect}.{$tableB}ID = {$tableB}.id";
  }
  private function selectConnectionTable($tableA, $tableB){
    $sql = "SELECT tab FROM bq_connections WHERE `t1` = ? AND `t2` = ?";
    return $this->db->query($sql, array('ss', $tableA, $tableB))[0]['tab'];
  }
  private function isConnectionTable($tableA, $tableB){
    $sql = "SELECT tab FROM bq_connections WHERE `t1` = ? AND `tab` = ?";
    return $this->db->query($sql, array('ss', $tableA, $tableB));
  }

  // STRING INSERT VALUES
  private function bqStringValues($values)
  {
    $nr = count($values);
    $i = 0;
    foreach ($values as $property => $value)
    {
      $this->bqPrepareColumn($this->table, $property, $value);
      $this->value_properties .= $property;
      $this->value_values .= '?';

      if($i == 0){ array_push($this->value_params,$this->param_type($value)); }
      else{ $this->value_params[0] .= $this->param_type($value); }
      array_push($this->value_params, $value);

      if($i != $nr - 1){
        $this->value_properties .= ', ';
        $this->value_values .= ', ';
      }
      $i++;
    }
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
        if(isset($where[1][$j+1])){
          $operator = $where[1][$j+1];
          $this->where_string  .= " {$operator} ";
        }
      }
    }
  }
  // VAL
  private function bqVal($value){
    if(strpos($value, ".")!== false){
      if($value[0]=='.'){ $value = substr($value, 1); }
      return $value;
    }else{
      if(isset($this->where_params[0])){ $this->where_params[0] .= $this->param_type($value); }
      else{ array_push($this->where_params, $this->param_type($value)); }
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

    // CHECK TABLES PROPERTY
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



    // COLUMN EXISTS IN CONNECTED
    private function bqColumnExistsInConnected($table, $property){
      if($this->sqlTableExist('bq_connections')){
        $xTable = $this->db->query("SELECT t2 FROM bq_connections WHERE t1 = {$table}");
        if($this->bqColumnExists($xTable, $property)){ return true; }
      }
      return false;
    }

    // CONNECT ROWS BY ID
    public function sqlConnectRowsByID($tableOne, $tableTwo, $tableOneID, $tableTwoID){
      $this->sqlConnectTables($tableOne, $tableTwo);
      $this->table = $tableOne.'_'.$tableTwo;
      if(!$this->sqlTableExist($this->table)){ $this->table = $tableTwo.'_'.$tableOne; }
      // prepare values for insertion
      $this->value_params = ['ii', $tableOneID, $tableTwoID];
      $this->value_properties = "{$tableOne}ID, {$tableTwo}ID";
      $this->value_values = "? , ?";
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
        $this->bqInsert(json_decode('{"table":"bq_connections","values":{"t1":"'.$tableOne.'","t2":"'.$tableTwo.'","tab":"'.$table.'"}}', true));
        $this->bqInsert(json_decode('{"table":"bq_connections","values":{"t1":"'.$tableTwo.'","t2":"'.$tableOne.'","tab":"'.$table.'"}}', true));
      }
      else{
        $this->log('connect table already exist');
      }
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
    public function sqlTableExist($table){
      return $this->db->query(
        "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '{$table}' LIMIT 1"
    );}

    // CREATE TABLE
    public function sqlCreateTable($table){ return $this->db->query(
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




 ?>
