<?php
/**
 *
 */
class Task
{
  private $name;
  function __construct($name)
  {
    $this->name = $name;
  }
  public function getName($value){
    return $this->name.$value;
  }
}


class User
{
  public $task;
  function __construct(Task $task)
  {
    $this->task = $task;
  }
  public function see($name){
    echo  $this->task->getName($name);
  }
}

class Shop{
  public $task;
}

$task = new Task('Pouzivatel: ');

$user = new User($task);
$user->see('Roman');
echo '<br>';
$user = new User($task);
$user->see('Lenka');



 ?>
