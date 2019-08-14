<?php
class Windsorcircle_Export_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(){

        $this->setMemoryLimit();

        // Set the timestamp for the files in the registry
        Mage::register('windsor_file_timestamp', date('YmdHis'));

        $filename = Mage::getBaseDir('log') . DS . 'windsorcircle.log';

        // If lastlog has been sent then copy log to '.prev' file
        if ($this->getRequest()->getParam('dataType') == 'LastLog') {
            $io = new Varien_Io_File();
            $path = $io->dirname($filename);
            $io->open(array('path' => $path));
            $io->cp('windsorcircle.log', 'windsorcircle.log.prev');
        }

        // Removing log file
        if(file_exists($filename)) {
            @unlink($filename);
        }

        Mage::log('Checking Parameters', null, 'windsorcircle.log');

        // Get params from URL
        $params = new Varien_Object($this->getRequest()->getParams());

        // Required params must be set!
        if(empty($params['authToken']) || empty($params['startDate']) || empty($params['endDate']) || empty($params['authDate']))
        {
            throw new Exception('Must provide all parameters (authToken, startDate, endDate, AuthDate).');
        }

        $files = array();

        // Check the authDate to make sure it is within one minute of request
        Mage::getModel('windsorcircle_export/date')->checkDate($params['authDate']);

        // Check to make sure that the authToken sent in the URL is valid
        Mage::getModel('windsorcircle_export/openssl')->valid($params['authToken'], $params['authDate']);

        Mage::log('All Parameters Checked OK', null, 'windsorcircle.log');

        // If version parameter is passed then return current module version
        if (isset($params['Version'])) {
            $this->getResponse()
                ->setBody(
                    'Module Version: ' . Mage::helper('windsorcircle_export')->getExtensionVersion()
                    . '<br />'
                    . 'Magento Version: ' . Mage::getVersion()
                );
            return;
        }

        // Debug flag
        $debug = (isset($params['debug']) && $params['debug'] == 1) ? (bool) $params['debug'] : false;

        // Page size value - defaults to 10,000
        $pageSize = isset($params['pageSize']) ? (int) $params['pageSize'] : 10000;

        switch ($params['dataType']) {
            case 'ASC':
                $formatModel = Mage::getSingleton('windsorcircle_export/format')->setDebug($debug);

                $orderModel = Mage::getModel('windsorcircle_export/order')
                    ->setDebug($debug)
                    ->setPageSize($pageSize);

                // Get Order Data and Order Details Data
                $orders = $orderModel->getOrders($params['startDate'], $params['endDate']);

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

                // Get Abandoned Shopping Cart Order Data and Order Details Data
                if (!empty($params['ascStartDate']) && !empty($params['ascEndDate'])) {
                    $ascOrders = $orderModel->getAscOrders($params['ascStartDate'], $params['ascEndDate']);
                } else {
                    $ascOrders = $orderModel->getAscOrders($params['startDate'], $params['endDate']);
                }

                // Format Abandoned Shopping Cart Order Data and Order Details Data
                $files[] = $formatModel->formatOrderData($ascOrders[0], '_ASC_');
                $files[] = $formatModel->formatOrderDetailsData($ascOrders[1], '_ASC_');

                // Get order item ids from ASC orders
                if ($inventoryEnabled) {
                    $ascOrderItemIds = $ascOrders[2];
                } else {
                    $ascOrderItemIds = array();
                }

                // Get product data if updated
                if ($productFile = $formatModel->getProductDataIfUpdated(array_merge($orderItemIds, $ascOrderItemIds))) {
                    $files[] = $productFile;
                }
                break;
            case 'Orders':
                Mage::log('Getting Orders Data', null, 'windsorcircle.log');

                // Get Order Data and Order Details Data
                $orders = Mage::getModel('windsorcircle_export/order')
                    ->setDebug($debug)
                    ->setPageSize($pageSize)
                    ->getOrders($params['startDate'], $params['endDate']);

                // Format Order Data and Order Details Data
                $files[] = Mage::getSingleton('windsorcircle_export/format')->formatOrderData($orders[0]);
                $files[] = Mage::getSingleton('windsorcircle_export/format')->formatOrderDetailsData($orders[1]);

                Mage::log('All Orders received', null, 'windsorcircle.log');
                break;
            case 'ExecOrders':
                $cmd = PHP_BINDIR . '/php -f ' . Mage::getModuleDir('controllers', 'Windsorcircle_Export') . DS . 'Background.php';
                $cmd .= !empty($params['dataType']) ? ' dataType=' . escapeshellarg($params['dataType']) : '';
                $cmd .= !empty($params['startDate']) ? ' startDate=' . escapeshellarg($params['startDate']) : '';
                $cmd .= !empty($params['endDate']) ? ' endDate=' . escapeshellarg($params['endDate']) : '';
                $cmd .= !empty($debug) ? ' debug=' . escapeshellarg($debug) : '';
                $cmd .= !empty($pageSize) ? ' pageSize=' . escapeshellarg($pageSize) : '';

                if ($this->execCommand($cmd)) {
                    $this->getResponse()->setBody('Request sent Successfully!');
                }
                return;
                break;
            case 'OrdersPlus':
                $formatModel = Mage::getSingleton('windsorcircle_export/format')->setDebug($debug);

                // Get Order Data and Order Details Data
                $orders = Mage::getModel('windsorcircle_export/order')
                    ->setDebug($debug)
                    ->setPageSize($pageSize)
                    ->getOrders($params['startDate'], $params['endDate']);

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
            case 'ExecOrdersPlus':
                $cmd = PHP_BINDIR . '/php -f ' . Mage::getModuleDir('controllers', 'Windsorcircle_Export') . DS . 'Background.php';
                $cmd .= !empty($params['dataType']) ? ' dataType=' . escapeshellarg($params['dataType']) : '';
                $cmd .= !empty($params['startDate']) ? ' startDate=' . escapeshellarg($params['startDate']) : '';
                $cmd .= !empty($params['endDate']) ? ' endDate=' . escapeshellarg($params['endDate']) : '';
                $cmd .= !empty($debug) ? ' debug=' . escapeshellarg($debug) : '';
                $cmd .= !empty($pageSize) ? ' pageSize=' . escapeshellarg($pageSize) : '';

                if ($this->execCommand($cmd)) {
                    $this->getResponse()->setBody('Request sent Successfully!');
                }
                return;
                break;
            case 'ProductsRebuild':
                $cmd = PHP_BINDIR . '/php -f ' . Mage::getModuleDir('controllers', 'Windsorcircle_Export') . DS . 'Background.php';
                $cmd .= !empty($params['dataType']) ? ' dataType=' . escapeshellarg($params['dataType']) : '';
                $cmd .= !empty($params['startDate']) ? ' startDate=' . escapeshellarg($params['startDate']) : '';
                $cmd .= !empty($params['endDate']) ? ' endDate=' . escapeshellarg($params['endDate']) : '';
                $cmd .= !empty($params['ascStartDate'])? ' ascStartDate=' . escapeshellarg($params['ascStartDate']) : '';
                $cmd .= !empty($params['ascEndDate'])? ' ascEndDate=' . escapeshellarg($params['ascEndDate']) : '';
                $cmd .= !empty($debug) ? ' debug=' . escapeshellarg($debug) : '';

                if ($this->execCommand($cmd)) {
                    $this->getResponse()->setBody('Request sent Successfully!');
                } else {
                    $this->removeProductsFile();
                    $this->getResponse()->setBody(' Products file removed.');
                }
                return;
                break;
            case 'Products':
                Mage::log('Getting Products Data', null, 'windsorcircle.log');

                // Get Products data
                $files[] = Mage::getSingleton('windsorcircle_export/format')
                    ->setDebug($debug)
                    ->advancedFormatProductData();

                Mage::log('All Products Gathered', null, 'windsorcircle.log');
                break;
            case 'CheckPermissions':
                $dir = Mage::getBaseDir('media') . DS . 'windsorcircle_export';
                $io = new Varien_Io_File();

                try {
                    $io->checkAndCreateFolder($dir);
                    if (is_writable($io->dirname($dir))) {
                        $writable = true;
                    } else {
                        $writable = false;
                    }
                } catch (Exception $e) {
                    $writable = false;
                }

                $response = 'media/windsorcircle_export folder';
                if ($writable) {
                    $response.= ' is writable';
                } else {
                    $response.= ' is not writable';
                }

                $this->getResponse()->setBody($response);
                break;
            case 'Config':
                $break = '<br />';

                $secure = false;
                if (Mage::app()->getRequest()->isSecure()) {
                    $secure = true;
                }

                $prefix = 'windsorcircle_export_options/messages/';
                $response = 'Version: ' . (string) Mage::helper('windsorcircle_export')->getExtensionVersion();
                $response.= $break . 'Client Name: ' . Mage::getStoreConfig($prefix . 'client_name');

                if ($secure) {
                    $response.= $break . 'API Key: ' . Mage::getStoreConfig($prefix . 'api_key');
                }

                $response.= $break . 'FTP Type: ' . Mage::getStoreConfig($prefix . 'ftp_type');
                $response.= $break . 'FTP Host: ' . Mage::getStoreConfig($prefix . 'ftp_host');
                $response.= $break . 'FTP Folder: ' . Mage::getStoreConfig($prefix . 'ftp_folder');
                $response.= $break . 'FTP User: ' . Mage::getStoreConfig($prefix . 'ftp_user');

                if ($secure) {
                    $response.= $break . 'FTP Password: ' . Mage::getStoreConfig($prefix . 'ftp_password');
                }

                $response.= $break . 'Store: ' . Mage::getStoreConfig($prefix . 'store');
                $response.= $break . 'Brand Attribute: ' . Mage::getStoreConfig($prefix . 'brand_attribute');
                $response.= $break . 'Canceled State: ' . Mage::getStoreConfig($prefix . 'canceled_state');

                $imageType = Mage::getStoreConfig($prefix . 'image_type');
                if(!empty($imageType) && $imageType == '2') {
                    $imageType = 'Small Image';
                } else {
                    $imageType = 'Base Image';
                }
                $response.= $break . 'Image Type: ' . $imageType;

                $memoryLimit = Mage::getStoreConfig($prefix . 'memory_limit');
                if (empty($memoryLimit)) {
                    $memoryLimit = '512';
                }
                $response.= $break . 'Memory Limit: ' . $memoryLimit . 'M';

                $response.= $break . 'Inventory Enable: ' . Mage::getStoreConfig($prefix . 'inventory_enable');

                $productsValue = array();
                $productAttributes = unserialize(Mage::getStoreConfig($prefix . 'custom_product_attributes'));
                if (!empty($productAttributes)) {
                    foreach ($productAttributes as $value) {
                        $productsValue[] = $value;
                    }
                }
                $response.= $break . 'Custom Product Attributes: ' . implode(',', $productsValue);

                $customerValue = array();
                $customerAttributes = unserialize(Mage::getStoreConfig($prefix . 'custom_customer_attributes'));
                if (!empty($customerAttributes)) {
                    foreach ($customerAttributes as $value) {
                        $customerValue[] = $value;
                    }
                }
                $response.= $break . 'Custom Customer Attributes: ' . implode(',', $customerValue);

                $customerAddressValue = array();
                $customerAddressAttributes = unserialize(Mage::getStoreConfig($prefix . 'custom_customer_address_attributes'));
                if (!empty($customerAddressAttributes)) {
                    foreach ($customerAddressAttributes as $value) {
                        $customerAddressValue[] = $value;
                    }
                }
                $response.= $break . 'Custom Customer Addresss Attributes: ' . implode(',', $customerAddressValue);

                $buyRequestValue = array();
                $buyRequestAttributes = unserialize(Mage::getStoreConfig($prefix . 'custom_order_item_buy_request_attributes'));
                if (!empty($buyRequestAttributes)) {
                    foreach ($buyRequestAttributes as $value) {
                        $buyRequestValue[] = $value;
                    }
                }
                $response.= $break . 'Custom Order Items Buy Request Attributes: ' . implode(',', $buyRequestValue);

                $this->getResponse()->setBody($response);
                break;
            case 'Count':
                $break = '<br />';

                // Get Updated.txt file count
                $filename = Mage::getBaseDir('media') . DS . 'windsorcircle_export' . DS . 'updated.txt';
                $io = new Varien_Io_File();
                $path = $io->dirname($filename);
                $io->open(array('path' => $path));

                try {
                    $io->streamOpen($filename, 'r+');
                    $linecount = 0;
                    while($io->streamRead()) {
                        $linecount++;
                    }
                    $io->streamClose();
                } catch (Exception $e) {
                    $linecount = 0;
                }
                $response = 'updated.txt - ' . $linecount;

                // Get number of orders returned for orders
                $orders = Mage::getModel('sales/order')->getCollection();

                if (version_compare(Mage::getVersion(), '1.4.0.0', '<')) {
                    $orders
                        ->addFieldToFilter(
                            array(
                                array('attribute' => 'updated_at',
                                    'datetime' => true, 'from' => $params['startDate'], 'to' => $params['endDate']),
                                array('attribute' => 'created_at',
                                    'datetime' => true, 'from' => $params['startDate'], 'to' => $params['endDate'])
                            )
                        );
                } else {
                    $orders
                        ->getSelect()
                        ->where(
                            '(main_table.updated_at >= :start_date' .
                            ' AND main_table.updated_at <= :end_date) ' .
                            Varien_Db_Select::SQL_OR .
                            ' (main_table.created_at >= :start_date' .
                            ' AND main_table.created_at <= :end_date)'
                        );

                    $orders->addBindParam('start_date', $params['startDate']);
                    $orders->addBindParam('end_date', $params['endDate']);
                }
                $response.= $break . 'Total Orders - ' . $orders->getSize();

                // Get number of order items returned for order items
                $orders->getSelect()->reset(Varien_Db_Select::COLUMNS);
                $orders->getSelect()->columns('entity_id');

                $orderItems = Mage::getModel('sales/order_item')->getCollection()
                    ->addFieldToFilter('order_id', array('in' => $orders->getSelect()));

                $orderItems->addBindParam('start_date', $params['startDate']);
                $orderItems->addBindParam('end_date', $params['endDate']);

                $items = array();
                $itemCheck = array();
                foreach($orderItems->getData() as $item) {
                    $itemCheck[$item['item_id']] = array('type' => $item['product_type']);

                    $items[$item['item_id']] = array(
                        'product_type'      =>  $item['product_type'],
                        'parent_item_id'    =>  $item['parent_item_id']
                    );
                }

                $actualItems = array();
                foreach ($items as $key => $item) {
                    if((isset($item['product_type'])
                            && $item['product_type'] == 'configurable')
                        || $item['product_type'] == ''
                    ) {
                        $actualItems[$key] = 1;
                    } elseif (
                        (isset($item['parent_item_id'])
                            && $item['parent_item_id'] != null)
                        && (isset($itemCheck[$item['parent_item_id']]['type'])
                            && $itemCheck[$item['parent_item_id']]['type'] == 'bundle')
                    ) {
                        $actualItems[$key] = 1;
                    } elseif(
                        (isset($item['parent_item_id'])
                            && $item['parent_item_id'] != null)
                        && (isset($itemCheck[$item['parent_item_id']]['type'])
                            && $itemCheck[$item['parent_item_id']]['type'] != 'simple')
                    ) {
                        // Do nothing since the parent should already be set
                    } else {
                        $actualItems[$key] = 1;
                    }
                }

                $response.= $break . 'Total Order Items - ' . count($actualItems);

                $this->getResponse()->setBody($response);
                break;
            case 'LastLog':
                $io = new Varien_Io_File();
                $path = $io->dirname($filename . '.prev');
                $io->open(array('path' => $path));
                $response = $io->read('windsorcircle.log.prev');
                $this->getResponse()->setBody($response);

                // Removing log file
                if(file_exists($filename . '.prev')) {
                    @unlink($filename . '.prev');
                }
                break;
            case 'Report':
                if (Mage::getStoreConfigFlag('windsorcircle_export_options/options/enable_report_output')) {
                    if (!empty($params['file'])) {
                        $io = new Varien_Io_File();
                        $path = $io->dirname(Mage::getBaseDir('var') . DS . 'report' . DS . $params['file']);
                        $io->open(array('path' => $path));
                        if ($io->fileExists($params['file'])) {
                            $response = $io->read($params['file']);
                        } else {
                            $response = 'File does not exist';
                        }
                    } else {
                        $response = 'file parameter is missing';
                    }
                    $this->getResponse()->setBody($response);
                } else {
                    $this->getResponse()->setBody('Error reporting is disabled');
                }
                break;
            case 'ServerDateTime':
                $response = date('r');
                $this->getResponse()->setBody($response);
                break;
            case 'TestConnection':
                $testConnection = Mage::getModel('windsorcircle_export/ftp')->testConnection();
                $this->getResponse()->setBody($testConnection);
                break;
            default:
                Mage::log('Getting Orders Data', null, 'windsorcircle.log');

                // Get Order Data and Order Details Data
                $orders = Mage::getModel('windsorcircle_export/order')
                    ->setDebug($debug)
                    ->setPageSize($pageSize)
                    ->getOrders($params['startDate'], $params['endDate']);

                // Format Order Data and Order Details Data
                $files[] = Mage::getSingleton('windsorcircle_export/format')->formatOrderData($orders[0]);
                $files[] = Mage::getSingleton('windsorcircle_export/format')->formatOrderDetailsData($orders[1]);

                Mage::log('All Orders recieved', null, 'windsorcircle.log');

                // Get flag for inventory enable update
                $inventoryEnabled = Mage::getStoreConfigFlag('windsorcircle_export_options/messages/inventory_enable');

                // Get order item ids from orders
                if ($inventoryEnabled) {
                    $orderItemIds = $orders[2];
                } else {
                    $orderItemIds = array();
                }

                Mage::log('Getting Products Data', null, 'windsorcircle.log');

                // Get Products data
                $files[] = Mage::getSingleton('windsorcircle_export/format')
                    ->setDebug($debug)
                    ->advancedFormatProductData($orderItemIds);

                Mage::log('All Products Gathered', null, 'windsorcircle.log');

                Mage::log('Sending Files to FTP Server', null, 'windsorcircle.log');
                break;
        }

        if (!empty($files)) {
            // Attempt to send files via FTP (FTP or SFTP)
            try {
                Mage::getModel('windsorcircle_export/ftp')->sendFiles($files);
                Mage::log('Files Sent', null, 'windsorcircle.log');
                $response = 'Files successfully sent';
            } catch (Exception $e) {
                Mage::log('Error sending files', null, 'windsorcircle.log');
                $response = 'Error sending files';
            }
            $this->getResponse()->setBody($response);

            // Remove all files from tmp directory after script is complete
            $mask = Mage::getBaseDir('tmp') . DS . Mage::getStoreConfig('windsorcircle_export_options/messages/client_name') . '_*';
            array_map('unlink', glob($mask));
        } elseif (!in_array($params['dataType'],
            array('CheckPermissions', 'Config', 'Count', 'Report', 'LastLog', 'ServerDateTime', 'TestConnection'))
        ) {
            $this->getResponse()->setBody('No Files to send');
        }
    }

    /**
     * Execute command
     *
     * @param $cmd
     *
     * @return bool
     */
    private function execCommand($cmd)
    {
        if (substr(php_uname(), 0, 7) == "Windows") {
            if (!pclose(popen("start /B ". $cmd, "r"))) {
                $this->getResponse()->setBody('Windows popen/pclose is not available.');
                return false;
            }
        } else {
            if (exec('echo EXEC') == 'EXEC') {
                exec($cmd . " > /dev/null &");
            } else {
                $this->getResponse()->setBody('Exec is not available.');
                return false;
            }
        }
        return true;
    }

    /**
     * Set memory limit - defaults to 512M
     */
    private function setMemoryLimit()
    {
        $memoryLimitValue = Mage::getStoreConfig('windsorcircle_export_options/messages/memory_limit');
        if ($memoryLimitValue && is_numeric($memoryLimitValue)) {
            ini_set('memory_limit', "{$memoryLimitValue}M");
        } else {
            ini_set('memory_limit','512M');
        }
    }

    private function removeProductsFile() {
        $lastExportFolder = Mage::getBaseDir('media') . DS . 'windsorcircle_export';
        array_map('unlink', array($lastExportFolder . DS . 'lastexport.txt', $lastExportFolder . DS . 'updated.txt'));
    }
}
