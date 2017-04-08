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
  $scope.currentCustomer = false;
  $scope.order = {}

  // edit task
  $scope.editOrder = function(item){
    // if item is set we are editin item
    oItem = {}
    if(!item){
      // new item
    }
    else{

    }
  }

  $scope.deleteTask = function(order){
    Ajax.call({
      disconnect:'orders',
      where:[['customer.id', 'orders.id'],['=','AND','='],[$scope.currentCustomer.id, order.id]]
    }, function(res){
      loadTasks();
    });
  }
  const loadTasks = function(){
    Ajax.call({
      select:'orders',
      where:[['customer.id'],['='],[$scope.currentCustomer.id]]
    }, function(res){
      $scope.customerOrders = res.data == 'null' ? {} : res.data;
    });
  }

  const loadCustomers = function(){
    Ajax.call({ select: 'customer' }, function(res){
      $scope.customers = res.data;
    });
  }

  $scope.insertCustomer = function(customer){
    Ajax.call({ insert:'customer', values: customer }, function(res){
      loadCustomers();
    });
  }

  $scope.changeCustomer = function(customer){
    $scope.currentCustomer = customer; loadTasks();
  }
  $scope.orderByCustomer = function(orders){
    Ajax.call({ insert: 'orders', values: orders }, function(response){
      Ajax.call({ action:'connect', data:['customer','orders', $scope.currentCustomer.id, response.data]},function(res){
        loadTasks();
      });
    })
  }
  $scope.dropTable = function(table){
    Ajax.call({ drop: table },function(res){ loadTables(); });
  }

  loadCustomers();


});
