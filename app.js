const app = angular.module('app',[]);
// Ajax Service
app.service('Ajax',function($http){
  this.call = function(data, callback){
    $http({ method: 'POST', url: 'brutesql.php', data: data})
    .then( function(res){ callback(res);}, function(res){} );
  }
});
app.service('Shared', function()
{
  const directiveOBJ = function(name, generatedOBJ, item, callback, scope)
  {
    this.html = '<'+name+' edit-obj="'+generatedOBJ+'"></'+name+'>';
    this.el = $compile( this.html )( scope );
    this.item = item;
    this.callback = callback;
    this.close = function(){ this.el.remove(); };
  }
  this.directiveElement = function( name, item, callback, scope )
  {
    callback = callback || function(){};
    item = item || false;
    const generatedID = 'item_'+windowID;
    self.openElement[generatedID] = new directiveOBJ(name, generatedID, item, callback, scope);
    angular.element($document).find('body').append(self.openElement[generatedID].el);
    windowID++;
    return self.openElement[generatedID];
  }
});
app.controller('editOrder', function($scope, $http, Ajax){

});
// Main Controller
app.controller('main', function($scope, $http, Ajax){

  $scope.customers = {};
  $scope.currentCustomer = false;
  $scope.order = {}

  $scope.eOrder = {};

  // edit task
  $scope.editOrder = function(item){
    const el = $('#ordersModal').modal('show');
    if(item){ $scope.edit_order = Object.assign({}, item); }
    else{ $scope.edit_order = {}; }
    console.log($scope.edit_order);
  }
  $scope.deleteOrder = function(order){
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
  $scope.saveOrder = function(order){
    if(!order.id){
    Ajax.call({ insert: 'orders', values: order }, function(response){
      Ajax.call({ action:'connect', data:['customer','orders', $scope.currentCustomer.id, response.data]},function(res){
        loadTasks();
      });
    });
    }
    else{
      Ajax.call({ update: 'orders',
        set: { name: order.name, todo: order.todo },
        where: [['orders.id'],['='],[order.id]]
      }, function(response){
        loadTasks();
      });
    }
  }
  $scope.dropTable = function(table){
    Ajax.call({ drop: table },function(res){ loadTables(); });
  }

  loadCustomers();


});
