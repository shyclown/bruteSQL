const app = angular.module('app',[]);

app.controller('main', function($scope, $http, Ajax){

  const LoadTables = function(){
    $scope.allTables = {};
  Ajax.call({action:'alltables'},function(res){
    let tables = res.data;
    let data = {};
    let i = 0;
    let len = tables.length;
    tables.forEach(function(table){
      Ajax.call({action:'select', table:table }, function(res){
        data[table] = res.data;
        if(i == len-1){ $scope.allTables = data; }
        i++;
      });
    });
  });
  }
  LoadTables();
  $scope.currentCustomer = false;
  $scope.changeCustomer = function(id){ console.log(id); $scope.currentCustomer = id; }
  $scope.romanTask = {};

  $scope.orderByCustomer = function(orders){
    Ajax.call({action:'insert', table:'orders', values: orders}, function(response){
      Ajax.call({action:'connect', data:['customer','orders', $scope.currentCustomer, response.data]},function(res){
        LoadTables();
      });
    })
  }

  $scope.orderByCustomer = function(orders){
    Ajax.call({action:'select', table:'orders', where: [['customer.id'],['='],[$scope.currentCustomer]]}, function(response){
      Ajax.call({action:'connect', data:['customer','orders', $scope.currentCustomer, response.data]},function(res){
        LoadTables();
      });
    })
  }

  const getTasks = function(){
    Ajax.call({action:'select', table:'tasks', where:[['todo'],['='],['_roman']]},function(res){
      $scope.romanTask =  res.data[0];
    });
  }
  getTasks();

  $scope.brutesql = function(action, table, values){
    console.log(action, table, values);
    Ajax.call({action: action, table:table, values: values},function(res){
      console.log(res);
      $scope[table] = {};
      LoadTables();
    });
  }

  $scope.order = {}
  $scope.saveOrder = function(order){
    console.log(order);
    Ajax.call({action:'insert' ,table:'orders', values: order},function(res){
      console.log(res);
      $scope.order = {};
      LoadTables();
    });
  }

  $scope.dropTable = function(table){
    Ajax.call({action:'drop_table' ,table:table},function(res){ LoadTables(); });
  }


});

app.service('Ajax',function($http){
  this.call = function(data, callback){
    $http({ method: 'POST', url: 'brutesql.php', data: data})
    .then( function(res){ callback(res);}, function(res){} );
  }
});
