<?php
// Controller for service-related operations
// Handles service catalog and service details

require_once('../../database.php');
require_once('../Models/ServiceModel.php');

class ServiceController {
    private $serviceModel;
    
    public function __construct() {
        $this->serviceModel = new ServiceModel();
    }
    
    public function viewServices() {
        // Display available services
    }
    
    public function viewServiceDetails() {
        // Display specific service details
    }
    
    public function createService() {
        // Create new service (admin)
    }
    
    public function updateService() {
        // Update service (admin)
    }
    
    public function deleteService() {
        // Delete service (admin)
    }
}
?>