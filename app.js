const app = angular.module('app',['ngSanitize']);
// Ajax Service
app.service('Ajax',function($http){
  this.call = function(data, callback){
    $http({ method: 'POST', url: 'brutesql.php', data: data})
    .then( function(res){ callback(res);}, function(res){} );
  }
});
// contentEditable
app.directive('contenteditable', ['$sce', function($sce) {
  return {
    restrict: 'A', // only activate on element attribute
    require: '?ngModel', // get a hold of NgModelController
    link: function(scope, element, attrs, ngModel) {
      if (!ngModel) return; // do nothing if no ng-model

      // Specify how UI should be updated
      ngModel.$render = function() {
        element.html($sce.getTrustedHtml(ngModel.$viewValue || ''));
      };

      // Listen for change events to enable binding
      element.on('blur keyup change', function() {
        scope.$evalAsync(read);
      });
      read(); // initialize

      // Write data to the model
      function read() {
        var html = element.html();
        // When we clear the content editable the browser leaves a <br> behind
        // If strip-br attribute is provided then we strip this out
        if ( attrs.stripBr && html == '<br>' ) {
          html = '';
        }
        ngModel.$setViewValue(html);
      }
    }
  };
}]);
// Main Controller
app.controller('main', function($scope, $http, Ajax)
{
  $scope.customers = {};
  $scope.currentCustomer = false;
  $scope.order = {}

  const loadTasks = function(){
    Ajax.call({ select:'orders',  where:[['customer.id'],['='],[$scope.currentCustomer.id]] }, function(res){
    $scope.customerOrders = res.data == 'null' ? {} : res.data;
  });}
  const loadCustomers = function(){
    Ajax.call({ select: 'customer' }, function(res){
    $scope.customers = res.data == 'null' ? {} : res.data;
  });}

  $scope.editCustomer = function(item){ $('#customersModal').modal('show');
    if(item){ $scope.edit_customer = Object.assign({}, item); }
    else{ $scope.edit_customer = {}; }
  }
  $scope.editOrder = function(item){ $('#ordersModal').modal('show');
    if(item){ $scope.edit_order = Object.assign({}, item); }
    else{ $scope.edit_order = {}; }
  }

  $scope.changeCustomer = function(customer){ $scope.currentCustomer = customer; loadTasks(); }

  $scope.saveCustomer = function(customer){
    if(!customer.id){
      Ajax.call({ insert: 'customer', values: customer }, function(response){
        loadCustomers();
      });
    }
    else{
      Ajax.call({ update: 'customer',
        set: { name: customer.name, surname: customer.surname },
        where: [['customer.id'],['='],[customer.id]]
      }, function(response){
        loadCustomers();
      });
    }
  }
  /* Delete Customer */
  $scope.deleteCustomer = function(customer){
    Ajax.call({ delete:'customer', where:[['customer.id'],['='],[customer.id]]},function(res){
      Ajax.call({ disconnect:'orders', where: [['customer.id'],['='],[customer.id]]}, function(res){
          loadCustomers();
      });
    });
  }
  /*
  $scope.xcom = function(){
    document.execCommand("formatblock", false, "h3");
  }
  */
  /* Save Order */
  $scope.saveOrder = function(order){
    if(!order.id){
    Ajax.call({ insert: 'orders', values: order }, function(response){
      Ajax.call({ action:'connect', data:['customer','orders', $scope.currentCustomer.id, response.data]},function(){
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
  /* Delete Order */
  $scope.deleteOrder = function(order){
    Ajax.call({
      disconnect:'orders',
      where:[['customer.id', 'orders.id'],['=','AND','='],[$scope.currentCustomer.id, order.id]]
    }, function(){
      loadTasks();
    });
  }
  $scope.dropTable = function(table){
    Ajax.call({ drop: table },function(res){ loadTables(); });
  }

  loadCustomers();


});
