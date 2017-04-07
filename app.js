const app = angular.module('app',[]);
// Ajax Service
app.service('Ajax',function($http){
  this.call = function(data, callback){
    $http({ method: 'POST', url: 'brutesql.php', data: data})
    .then( function(res){ callback(res);}, function(res){} );
  }
});
// Main Controller
app.controller('main', function($scope, $http, Ajax){

  $scope.customers = {};
  $scope.allTables = {};
  $scope.currentCustomer = false;
  $scope.order = {}

  const loadTables = function(){
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

  const loadTasks = function(){
    Ajax.call({
      action:'select',
      table:'orders',
      where:[['customer.id'],['='],[$scope.currentCustomer.id]]
    }, function(res){
      $scope.customerOrders = res.data == 'null' ? {} : res.data;
    });
  }

  const loadCustomers = function(){
    Ajax.call({
      action:'select',
      table:'customer'
    }, function(res){
      $scope.customers = res.data; console.log(res);
    });
  }

  $scope.insertCustomer = function(customer){
    Ajax.call({action:'insert', table:'customer', values: customer}, function(res){
      loadCustomers();
    });
  }
  $scope.changeCustomer = function(customer){
    $scope.currentCustomer = customer; loadTasks();
  }
  $scope.orderByCustomer = function(orders){
    Ajax.call({action:'insert', table:'orders', values: orders}, function(response){
      Ajax.call({action:'connect', data:['customer','orders', $scope.currentCustomer.id, response.data]},function(res){
        loadTasks();
      });
    })
  }
  $scope.dropTable = function(table){
    Ajax.call({action:'drop_table' ,table:table},function(res){ loadTables(); });
  }
});
