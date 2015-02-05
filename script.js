(function() {
	// Define Angular module/app
	// Create Angular controller and pass in $scope and $http
	var app = angular.module( 'app', [] ).controller( 'controller', function( $scope, $http ) {

		var start_date = new Date(),
		    end_date = new Date();

		start_date.setFullYear(end_date.getFullYear() - 1);

		// Create a blank object to hold our form information
		// $scope will allow this pass between controller and view
		$scope.form = {
			'start_date': start_date,
			'end_date'  : end_date
		};

		// Process the form
		$scope.process = function() {
			$http({
				method : 'POST',
				url    : 'process.php',
				data   : $.param( $scope.form ), // Pass in data as strings
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' } // Set the headers so Angular passing info as form data (not request payload)
			}).success( function( data ) {
				console.log( data );

				$scope.data = data;
			});
		};
	});
})();