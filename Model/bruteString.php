<?php
namespace Model;

class bruteString
{

  public $errors = [];
  public $debug = [];
  // targeted table
  public $table;
  // if set
  public $set_string = '';
  public $set_params = [];
  // if where
  public $where_string = '';
  public $where_params = [];
  // if connected to table
  public $connected_tables = [];
  public $inner_string = '';
  public $inner_tables = [];
  public $inner_table = false; // many to many connect table
  // if values
  public $value_values = '';
  public $value_properties = '';
  public $value_params = [];

  function __construct(\bqData $bqData, Database $db)
  {
    $this->db = $db;
    $this->data = $bqData->data;
    $this->sysTables();
    $this->buildStrings();
  }

  //======================================================================
  // PUBLIC
  //======================================================================

  /* Debug - Error */
  public function err($str){ $this->errors[] = $str; }
  public function log($str){ $this->debug[] = $str; }

  /* Table - Exists */
  public function sqlTableExist($table){
    return $this->db->query(
      "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '{$table}' LIMIT 1"
  );}

  /* Table - Create */
  public function sqlCreateTable($table){ return $this->db->query(
      "CREATE TABLE IF NOT EXISTS {$table} ( id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY )"
  );}
  /* Table - Connect Rows by ID */
  public function sqlConnectRowsByID($tableOne, $tableTwo, $tableOneID, $tableTwoID)
  {
    $this->sqlConnectTables($tableOne, $tableTwo);
    $this->table = $tableOne.'_'.$tableTwo;
    if(!$this->sqlTableExist($this->table)){ $this->table = $tableTwo.'_'.$tableOne; }
    $sql_order = "SELECT MAX(`order`) FROM {$this->table} WHERE `{$tableOne}ID` = {$tableOneID}";
    $order = $this->db->query( $sql_order )[0]['MAX(`order`)'];
    if(!$order){ $order = 1; } else { $order++; }

    // prepare values for insertion
    $str_json = '{"insert":"'.$this->table.'","values":{"'.$tableOne.'ID":'.$tableOneID.',"'.$tableTwo.'ID":'.$tableTwoID.', "order":'.$order.'}}';
    $this->data = json_decode($str_json ,true );
    $this->buildStrings();

  }

  /* retreive NAMES of all tables */
    private function sqlAllTables(){
      $tables = $this->db->query("SHOW TABLES");
      $tablenames = [];
      foreach ($tables as $k => $value) {
        $tablenames[] = $value['Tables_in_brutesql'];
      }
      return $tablenames;
  }

  //======================================================================
  // PRIVATE
  //======================================================================

  /* Build Strings */

  private function buildStrings()
  {
    if(isset($this->data['table'])){
      $this->table = $this->data['table'];
      $this->sqlCreateTable($this->table);
    }
    if(isset($this->data['where'])){ $this->bqStringWhere($this->data['where']); }
    if(isset($this->data['values'])){ $this->bqStringValues($this->data['values']); }
    if(isset($this->data['set'])){ $this->bqStringSet($this->data['set']); }
    if($this->connected_tables){
      $disconnect = isset($this->data['disconnect']);
      $this->bqStringInnerJoin($this->table, $this->connected_tables[0], $disconnect);
    }
  }

  //-----------------------------------------------------
  // Inner Join
  //-----------------------------------------------------

  private function bqStringInnerJoin($tableA, $tableB, $disconnect)
  {
    $this->sqlConnectTables($tableA, $tableB);


    if($this->isConnectionTable($tableA, $tableB)){
      $this->inner_string = innerJoinTwo($tableA, $tableB);
    }
    elseif($connect = $this->selectConnectionTable($tableA, $tableB)){
      $this->inner_string = $this->innerJoinThree($tableA, $connect[0]['tab'], $tableB, $disconnect);
      $this->inner_table = $connect[0]['tab'];
    }
    else{
      $this->err("Table named {$tableA} is not connected to {$tableB} table");
    }
  }
  private function innerJoinTwo($tableA, $tableB){
    return "INNER JOIN {$tableB} ON {$tableB}.{$tableA}ID = {$tableA}.id";
  }
  private function innerJoinThree($tableA, $connect, $tableB, $disconnect){

    if($disconnect){ $joined = $tableA; } else { $joined = $connect; }
    return "INNER JOIN {$joined} ON {$connect}.{$tableA}ID = {$tableA}.id
            INNER JOIN {$tableB}  ON {$connect}.{$tableB}ID = {$tableB}.id";
  }
  private function selectConnectionTable($tableA, $tableB){
    $sql = "SELECT tab FROM bq_connections WHERE `t1` = ? AND `t2` = ?";
    return $this->db->query($sql, array('ss', $tableA, $tableB));
  }
  private function isConnectionTable($tableA, $tableB){
    $sql = "SELECT tab FROM bq_connections WHERE `t1` = ? AND `tab` = ?";
    return $this->db->query($sql, array('ss', $tableA, $tableB));
  }

  //-----------------------------------------------------
  // Where Values
  //-----------------------------------------------------

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

  //-----------------------------------------------------
  // Insert Values
  //-----------------------------------------------------

  private function bqStringValues($values)
  {
    $i = 0;
    // make sure table exists


    foreach ($values as $property => $value)
    {
      $this->bqPrepareColumn($this->table, $property, $value);
      if($i != 0){ $dash = ','; }else{ $dash = ''; }
      $this->value_properties .= $dash.'`'.$property.'`';
      $this->value_values .= $dash.'?';
      $type = $this->param_type($value);

      if($i == 0){ $this->value_params[] = $type; }
      else{ $this->value_params[0] .= $type; }
      $this->value_params[]= $value;

      $i++;
    }
  }

  //-----------------------------------------------------
  // Update Values
  //-----------------------------------------------------

  private function bqStringSet($set)
  {
    $i = 0;
    foreach ($set as $property => $value)
    {
      $this->bqPropertyExists($property);
      if($this->errors){ continue; }
      else{
        $this->bqPrepareColumn($this->table, $property, $value);
        if($i != 0){ $this->set_string .= ', '; }
        $this->set_string .= $property.' = ?';
        $type = $this->param_type($value);
        if($i == 0){ $this->set_params[] = $type; }
        else{ $this->set_params[0] .= $type; }
        $this->set_params[]= $value;
        $i++;
      }
    }
  }

  //-----------------------------------------------------
  // Fn
  //-----------------------------------------------------

  // Prepare column changes column if it is different from provided properties
  // in Insert action
  private function bqPrepareColumn($table, $property, $value)
  {
    // creates column in table for property if it doesn't exist
    // type is set to type of inserted value

    // table


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
      $this->log("Column Added: {$property}");
    }
  }

  /* Prepare values for prepared statement */
  private function bqVal($value)
  {
    if(strpos($value, ".")!== false){
      if($value[0]=='.'){ $value = substr($value, 1); }
      return $value;
    }else{
      $type = $this->param_type($value);
      if(isset($this->where_params[0])){ $this->where_params[0] .= $type; }
      else{ $this->where_params[]= $type; }
      $this->where_params[]= $value;
      return '?';
    }
  }

  /* Check if property exists in table */
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
            $this->connected_tables[] = $table_name; // store used table name
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

  /* Connects table if table exists */
  private function bqConnectTable($value)
  {
    if(strpos($value, ".") !== false && $value[0] != "."){
      $table_name = explode(".", $value)[0];
      if($this->sqlTableExist($table_name)){ return $table_name; }
      else{ $this->err("Could not find table {$table_name} defined as part of value: {$value}."); }
    }
    return false;
  }

  /* Check tables property against provided properties, store connected tables if found */
  private function checkTablesProperty($table, $prop)
  {
    $properties = $prop;
    $connectTables = [];
    foreach ($properties as $k => $property)
    {
      if(!$this->bqColumnExists($table, $property)){
        if($connected_table = $this->bqColumnExistsInConnected($table, $property)){
          $connectTables[] = $connected_table;
        }
        else{   $this->errors[] = 'Error: Could not find property: '.$property;
        }
      }
    }
    return $connectTables;
  }

  /* Check property against connected table */
  private function bqColumnExistsInConnected($table, $property)
  {
    if($this->sqlTableExist('bq_connections'))
    {
      $xTable = $this->db->query("SELECT t2 FROM bq_connections WHERE t1 = {$table}");
      if($this->bqColumnExists($xTable, $property))
      {
        return true;
      }
    }
    return false;
  }

  /* This creates note in bruteSQL Connections table that tables are connected */

  // TODO: remove table note when deleted connection
  // for now it is not possible to delete connection

  // brutesql tables
  private function sysTables(){
    $this->db->query("CREATE TABLE IF NOT EXISTS bq_connections
      ( id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        t1 VARCHAR(16),
        t2 VARCHAR(16),
        tab VARCHAR(64)
     )");
  }

  private function sqlConnectTables($tableOne, $tableTwo){


      $t1 = $tableOne.'_'.$tableTwo;
      $t2 = $tableTwo.'_'.$tableOne;
      if($this->sqlTableExist($tableOne)
      && $this->sqlTableExist($tableTwo)
      && !$this->sqlTableExist($t1)
      && !$this->sqlTableExist($t2)
      ){
        $table = $tableOne.'_'.$tableTwo;
        $this->sqlCreateTable($table);
        $this->bqPrepareColumn($table, $tableOne.'ID', 111111);
        $this->bqPrepareColumn($table, $tableTwo.'ID', 111111);
        new \Brute(json_decode('{"insert":"bq_connections","values":{"t1":"'.$tableOne.'","t2":"'.$tableTwo.'","tab":"'.$table.'"}}', true));
        new \Brute(json_decode('{"insert":"bq_connections","values":{"t1":"'.$tableTwo.'","t2":"'.$tableOne.'","tab":"'.$table.'"}}', true));
      }
      else{
        $this->log('connect table already exist');
      }
      return false;
    }

  /* retreive column type from value */
  private function valueType($val){
    if(is_bool($val)){ return 'tinyint';}
    if(is_numeric($val)){ return 'int';}
    if(is_string($val)){ return 'varchar';}
  }
  /* retreive param type from value */
  private function param_type($value){
    if(is_numeric($value)){ return 'i'; } else { return 's'; }
  }

  /* read number from column type - for example : varchar(8) returns 8*/
  private function readNumberFromType($type){
    return filter_var($type, FILTER_SANITIZE_NUMBER_INT);
  }

  /* change type priority */
  // if new type is varchar we change int to varchar, not the other way
  private function typePriority($new_type, $old_type){
    $priority = array( 'tinyint' => 0, 'int'=> 1, 'varchar' => 2, 'text'=> 3);
    if($priority[$new_type] > $priority[$old_type]){ return $new_type; }
    else{ return $old_type; }
  }

  /* size is always in power of two (for no reason) */
  private function getNextPowerOfTwo($nr){
    $power = 1; while($power < $nr){ $power*=2; } return $power;
  }

  /* add column to table */
  private function sqlAddColumn($table, $column, $type){ return $this->db->query(
    "ALTER TABLE {$table} ADD COLUMN `{$column}` {$type}"
  );}

  /* update column in table */
  private function sqlChangeColumn($table, $column, $type){ return $this->db->query(
    "ALTER TABLE {$table} CHANGE {$column} {$column} {$type}"
  );}

  /* read names of columns in table */
  private function sqlTableColumns($table){ return $this->db->query(
    "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$table}'"
  );}

  /* Select whole content of table - probably make it public? */
  private function sqlSelectAll($table){ return $this->db->query(
    "SELECT * FROM {$table} "
  );}

  // Close DB
  private function sqlClose(){
    $this->db->destroy();
  }

  /* check if column exists */
  private function bqColumnExists($table, $test_column)
  {
    $columns = $this->sqlTableColumns($table);
    foreach ($columns as $key => $column) {
      if($column['COLUMN_NAME'] == $test_column){ return true; }
    }
    return false;
  }
} // end of class bruteString




 ?>
