<?php
spl_autoload_register(function ($class_name) { require ($class_name . '.php'); });
// Listen to post value
if(isset($_POST)
&& isset($_POST['action'])
){ $data = $_POST; }
else{
  $data = file_get_contents("php://input");
  $data = json_decode($data, true); //array
}

$bq = new Brute($data);
//======================================================================
// bqData
//======================================================================

/*
  Prepare data for manipulation.
*/

class bqData
{
  public $data;
  function __construct($data)
  {
    $this->data = $data;
    if(isset($data['select'])){ $this->data['table'] = $data['select']; }
    if(isset($data['insert'])){ $this->data['table'] = $data['insert']; }
    if(isset($data['update'])){ $this->data['table'] = $data['update']; }
    if(isset($data['delete'])){ $this->data['table'] = $data['delete']; }
    // DISCONNECT
    if(isset($data['disconnect'])){ $this->data['table'] = $data['disconnect']; }
    // DROP & TRUNCATE
    if(isset($data['drop'])){ $this->data['drop'] = $data['drop']; }
    if(isset($data['truncate'])){ $this->data['truncate'] = $data['truncate']; }
    // BQ Override
    if(isset($data['action'])){ $this->data['action'] = $data['action'];
      if(isset($data['table'])){ $this->data['table'] = $data['table']; }
    }
  }
}

//======================================================================
// Brute
//======================================================================

class Brute
{
  private $action;
  private $data;

  function __construct($data){
    $this->db = new Model\Database;
    $this->bqData = new bqData($data);
    $this->strings = new Model\bruteString($this->bqData, $this->db);

    echo json_encode($this->action());
    $this->db->destroy();
  }

  private function action(){
    if(isset($this->bqData->data['select'])){ return $this->query(new Model\Select($this->strings, $this->db)); }
    if(isset($this->bqData->data['insert'])){ return $this->query(new Model\Insert($this->strings, $this->db)); }
    if(isset($this->bqData->data['update'])){ return $this->query(new Model\Update($this->strings, $this->db)); }
    if(isset($this->bqData->data['delete'])){ return $this->query(new Model\Delete($this->strings, $this->db)); }
    // DISCONNECT
    if(isset($this->bqData->data['disconnect'])){ return $this->query(new Model\Disconnect($this->strings, $this->db)); }
    // DROP & TRUNCATE
    if(isset($this->bqData->data['drop'])){ return $this->strings->sqlDrop($this->bqData->data['table']); }
    if(isset($this->bqData->data['truncate'])){ return $this->strings->sqlTruncate($this->bqData->data['table']); }
    // CUSTOM
    if(isset($this->bqData->data['action'])){
      if(method_exists($this, $this->bqData->data['action'])){ return $this->{$this->bqData->data['action']}($this->bqData); }
    }
  }
  private function query(Model\Query $action){
    $this->createdQuery = new $action($this->strings, $this->db);
    return ($this->createdQuery->execute());
  }
  // connect rows
  public function connect($bqData){
    $data = $bqData->data;
    $this->strings->sqlConnectRowsByID($data['data'][0],$data['data'][1],$data['data'][2],$data['data'][3]);
    return $this->query(new Model\Insert($this->strings, $this->db));
  }
}
