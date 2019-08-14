<?php

// Load Magento core
$mageFilename = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/Mage.php';
if (!file_exists($mageFilename)) {
    echo 'Magento file does not exist!';
    exit;
}

require_once $mageFilename;

umask(0);

Mage::app();

// Set memory limit from configuration otherwise default to 512M
$memoryLimitValue = Mage::getStoreConfig('windsorcircle_export_options/messages/memory_limit');
if ($memoryLimitValue && is_numeric($memoryLimitValue)) {
    ini_set('memory_limit', "{$memoryLimitValue}M");
} else {
    ini_set('memory_limit','512M');
}

// Set the timestamp for the files in the registry
Mage::register('windsor_file_timestamp', date('YmdHis'));
// Set Custom Entry Point in case Url Rewrites is disabled (this forces index.php instead of using Background.php)
Mage::register('custom_entry_point', true);

$parameters = array();
foreach ($argv as $parameter) {
    if (strstr($parameter, '=')) {
        list($key, $value) = explode('=', $parameter);
        $parameters[$key] = $value;
    } else {
        $parameters[] = $parameter;
    }
}

$files = array();

// Debug flag
$debug = (isset($parameters['debug']) && $parameters['debug'] == 1) ? (bool) $parameters['debug'] : false;

// Page size value - defaults to 10,000
$pageSize = isset($parameters['pageSize']) ? (int) $parameters['pageSize'] : 10000;

if(!empty($parameters['dataType'])) {
    switch ($parameters['dataType']) {
        case 'ProductsRebuild':
            Mage::log('Getting Products Data', null, 'windsorcircle.log');

            $lastExportFolder = Mage::getBaseDir('media') . DS . 'windsorcircle_export';
            @unlink($lastExportFolder . DS . 'lastexport.txt');
            @unlink($lastExportFolder . DS . 'updated.txt');
            $files[] = Mage::getModel('windsorcircle_export/format')
                ->setDebug($debug)
                ->advancedFormatProductData();

            Mage::log('All Products Gathered', null, 'windsorcircle.log');
            break;
        case 'ExecOrders':
            Mage::log('Getting Orders Data', null, 'windsorcircle.log');

            // Get Order Data and Order Details Data
            $orders = Mage::getModel('windsorcircle_export/order')
                ->setDebug($debug)
                ->setPageSize($pageSize)
                ->getOrders($parameters['startDate'], $parameters['endDate']);

            // Format Order Data and Order Details Data
            $files[] = Mage::getSingleton('windsorcircle_export/format')->formatOrderData($orders[0]);
            $files[] = Mage::getSingleton('windsorcircle_export/format')->formatOrderDetailsData($orders[1]);

            Mage::log('All Orders received', null, 'windsorcircle.log');
            break;
        case 'ExecOrdersPlus':
            $formatModel = Mage::getSingleton('windsorcircle_export/format')
                ->setDebug($debug);

            // Get Order Data and Order Details Data
            $orders = Mage::getModel('windsorcircle_export/order')
                ->setDebug($debug)
                ->setPageSize($pageSize)
                ->getOrders($parameters['startDate'], $parameters['endDate']);

            // Format Order Data and Order Details Data
            $files[] = $formatModel->formatOrderData($orders[0]);
            $files[] = $formatModel->formatOrderDetailsData($orders[1]);

            // Get flag for inventory enable update
            $inventoryEnabled = Mage::getStoreConfigFlag('windsorcircle_export_options/messages/inventory_enable');

            // Get order item ids from orders
            if ($inventoryEnabled) {
                $orderItemIds = $orders[2];
            } else {
                $orderItemIds = array();
            }

            // Get product data if updated
            if ($productFile = $formatModel->getProductDataIfUpdated($orderItemIds)) {
                $files[] = $productFile;
            }
            break;
    }
}

if(!empty($files)) {
    Mage::log('Sending Files to FTP Server', null, 'windsorcircle.log');

    try {
        // Attempt to send files via FTP (FTP or SFTP)
        Mage::getModel('windsorcircle_export/ftp')->sendFiles($files);
        Mage::log('Files Sent', null, 'windsorcircle.log');
    } catch (Exception $e) {
        Mage::log('Error sending files', null, 'windsorcircle.log');
    }

    // Remove all files from tmp directory after script is complete
    $mask = Mage::getBaseDir('tmp') . DS . Mage::getStoreConfig('windsorcircle_export_options/messages/client_name') . '_*';
	array_map('unlink', glob($mask));
}
