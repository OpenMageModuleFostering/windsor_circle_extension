<?php
class Windsorcircle_Export_Model_Order extends Mage_Core_Model_Abstract
{
    // Order Data
    protected $orderData = array();

    // Order Items Data
    protected $orderDetailsData = array();

    // Array of Order Item Ids
    protected $orderItems = array();

    // Abandoned Shopping Cart Quote Data
    protected $quoteAscData = array();

    // Abandoned Shopping Cart Quote Items Data
    protected $quoteAscDetailsData = array();

    // Order ID Array
    protected $orderIdArray = array();

    // Quote ID Array
    protected $quoteIdArray = array();

    // Shipped Array
    protected $shippedArray = array();

    // Page Size - defaults to 10,000
    protected $pageSize = 10000;

    // Debug Parameter
    protected $debug = false;

    // Windsorcircle log file
    protected $logFile = 'windsorcircle.log';

    /**
     * Constructor
     */
    protected function _construct(){
        $this->_init('windsorcircle_export/order');
    }

    /**
     * Get Orders and OrderDetails between dates
     * @param string $startDate
     * @param string $endDate
     * @return array Returns Array of Order (Array[0]) and Order Details (Array[1])
     */
    public function getOrders($startDate, $endDate){
        $this->getOrder($startDate, $endDate)
            ->getOrderItems()
            ->getOrder($startDate, $endDate, true)
            ->getOrderItems();

        return array($this->orderData, $this->orderDetailsData, $this->orderItems);
    }

    /**
     * Get Abandoned Shopping Cart Order Data
     *
     * @param   string  $startDate  Start Date of Orders
     * @param   string  $endDate    End Date of Orders
     * @return  array
     */
    public function getAscOrders($startDate, $endDate) {
        $this->getAscOrder($startDate, $endDate)
            ->getAscOrderItems();

        return array($this->quoteAscData, $this->quoteAscDetailsData, $this->orderItems);
    }

    /**
     * Set page size for order/order items collections
     *
     * @param $pageSize
     * @return $this
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = (int) $pageSize;
        return $this;
    }

    /**
     * Set debug flag
     *
     * @param $flag
     * @return $this
     */
    public function setDebug($flag) {
        $this->debug = $flag;
        return $this;
    }

    /**
     * Get Order Data between dates
     *
     * @param string        $startDate  Start Date of Orders
     * @param string        $endDate    End Data of Orders
     * @param bool|false    $canceled
     * @return $this
     */
    protected function getOrder($startDate, $endDate, $canceled = false) {
        if ($this->debug) {
            if ($canceled) {
                Mage::log('Canceled Orders Started', null, $this->logFile);
            } else {
                Mage::log('Orders Started', null, $this->logFile);
            }
        }

        $orders = Mage::getModel('sales/order')->getCollection();

        if ($canceled) {
            $canceledStates = Mage::getStoreConfig('windsorcircle_export_options/messages/canceled_state');
            $canceledStates = explode(',', $canceledStates);
            if (is_array($canceledStates)) {
                $filter = array();
                foreach ($canceledStates as $canceledState) {
                    $filter[] = array('eq' => $canceledState);
                }
                $orders->addFieldToFilter('state', $filter);
            } else {
                $orders->addFieldToFilter('state', array(array('eq' => 'closed'), array('eq' => 'canceled')));
            }
        }

        if (Mage::getSingleton('core/resource')->getTableName('newsletter/subscriber')) {
            $newsletterExists = Mage::getSingleton('core/resource')->getConnection('core_read')
                ->showTableStatus(Mage::getSingleton('core/resource')->getTableName('newsletter/subscriber'));
        } else {
            $newsletterExists = false;
        }

        $mainTableAlias = 'main_table';

        $version = explode('.', Mage::getVersion());
        if ( $version[0] == 1 && $version[1] <= 3 )
        {
            $mainTableAlias = 'e';

            $orders->addFieldToFilter(
                array(
                    array('attribute' => 'updated_at', 'datetime' => true, 'from' => $startDate, 'to' => $endDate),
                    array('attribute' => 'created_at', 'datetime' => true, 'from' => $startDate, 'to' => $endDate)
                ))
                ->addAttributeToSort('increment_id', 'ASC');
            $orders->getSelect()->joinLeft(array('customer' => 'customer_entity'), 'e.customer_id = customer.entity_id', array('customer_email' => 'customer.email'));

            if ($newsletterExists) {
                $orders->getSelect()->joinLeft(array('newsletter' => Mage::getSingleton('core/resource')->getTableName('newsletter/subscriber')), 'customer.email = newsletter.subscriber_email', array('newsletter.subscriber_status'));
            }

            $orders->getSelect()->joinLeft(array('customer_group' => Mage::getSingleton('core/resource')->getTableName('customer/customer_group')), 'customer.group_id = customer_group.customer_group_id', array('customer_group.customer_group_code'));
        } else if ($version[0] == 1 && $version[1] > 3 && $version[1] <= 5 ||
            $this->isEnterprise() && $version[0] == 1 && in_array($version[1], array(9, 10)))
        {

            $adapter = $orders->getSelect()->getAdapter();
            $startDate = $adapter->quote($startDate);
            $endDate = $adapter->quote($endDate);

            $orders->getSelect()->where(vsprintf('(main_table.updated_at >= %s AND main_table.updated_at <= %s)' .
                ' ' . Zend_Db_Select::SQL_OR . ' (main_table.created_at >= %s AND main_table.created_at <= %s)',
                array($startDate, $endDate, $startDate, $endDate)));

            $orders->addAttributeToSort('main_table.increment_id', 'ASC');

            if ($newsletterExists) {
                $orders->getSelect()->joinLeft(array('newsletter' => Mage::getSingleton('core/resource')->getTableName('newsletter/subscriber')), 'main_table.customer_email = newsletter.subscriber_email',array('newsletter.subscriber_status'));
            }

            $orders->getSelect()->joinLeft(array('customer' => Mage::getSingleton('core/resource')->getTableName('customer/entity')), 'main_table.customer_id = customer.entity_id', array('customer_email' => 'customer.email'));
            $orders->getSelect()->joinLeft(array('customer_group' => Mage::getSingleton('core/resource')->getTableName('customer/customer_group')), 'customer.group_id = customer_group.customer_group_id', array('customer_group.customer_group_code'));
        } else {
            $conditions = array();
            $condition = array('datetime' => true, 'from' => $startDate, 'to' => $endDate);
            $conditions[] = $orders->getConnection()->prepareSqlCondition('main_table.updated_at', $condition);
            $conditions[] = $orders->getConnection()->prepareSqlCondition('main_table.created_at', $condition);

            $resultCondition = '(' . join(') ' . Zend_Db_Select::SQL_OR . ' (', $conditions) . ')';

            $orders->getSelect()->where($resultCondition);

            $orders->addAttributeToSort('main_table.increment_id', 'ASC');

            if ($newsletterExists) {
                $orders->getSelect()->joinLeft(array('newsletter' => Mage::getSingleton('core/resource')->getTableName('newsletter/subscriber')), 'main_table.customer_email = newsletter.subscriber_email',array('newsletter.subscriber_status'));
            }

            $orders->getSelect()->joinLeft(array('customer' => Mage::getSingleton('core/resource')->getTableName('customer/entity')), 'main_table.customer_id = customer.entity_id', array('customer_entity_id' => 'customer.entity_id', 'customer_email' => 'customer.email'));
            $orders->getSelect()->joinLeft(array('customer_group' => Mage::getSingleton('core/resource')->getTableName('customer/customer_group')), 'customer.group_id = customer_group.customer_group_id', array('customer_group.customer_group_code'));
        }

        // Add Shipment Table
        if ( $version[0] == 1 && $version[1] <= 3 ) {
            $orders->getSelect()->joinLeft(array('shipment' => 'sales_order_entity'), 'e.entity_id = shipment.entity_id', array('last_shipment_date' => 'max(shipment.updated_at)'));
        } else {
            $orders->getSelect()->joinLeft(array('shipment' => Mage::getSingleton('core/resource')->getTableName('sales/shipment')), $mainTableAlias . '.entity_id=shipment.order_id', array('last_shipment_date' => 'max(shipment.updated_at)'));
        }

        // Limit by store
        $stores = Mage::getStoreConfig('windsorcircle_export_options/messages/store');
        if (!empty($stores)) {
            $stores = explode(',', $stores);
            $orders->addFieldToFilter($mainTableAlias . '.store_id', array('in' => $stores));
        }

        // Custom Customer attributes to add to order
        $this->addCustomerCustomAttributes($orders);

        if (!$canceled) {
            $state = Mage::getModel('windsorcircle_export/state');
            $this->addHeaders();
        } else {
            $this->orderIdArray = array();
        }

        $helper = Mage::helper('windsorcircle_export');

        $orders->setPageSize($this->pageSize);
        $totalPages = $orders->getLastPageNumber();
        $currentPage = 1;

        // Add group by to collection
        // if duplicate order id's come back for some reason it will not throw a magento duplicate id error
        //
        // NOTE: This needs to be called after getLastPageNumber since some versions of Magento
        //       do not clear the group by clause when getting total record counts
        $orders->getSelect()->group(array($mainTableAlias . '.entity_id'));

        do {
            if ($this->debug) {
                $log = 'Page: ' . $currentPage;
                $log.= ' - Memory: ' . $helper->getMemoryUsage();
                Mage::log($log, null, $this->logFile);
            }

            $orders->setCurPage($currentPage);

            foreach($orders as $orderOriginal){
                $orderData = $orderOriginal->getData();
                $customerGroupCode = $orderData['customer_group_code'];

                if (isset($orderData['subscriber_status'])) {
                    $subscriber_status = $orderData['subscriber_status'];
                } else {
                    $subscriber_status = '';
                }

                $order = Mage::getSingleton('sales/order')->load($orderData['entity_id']);
                $orderData = $order->getData();
                $couponCode = isset($order['coupon_code']) ? $order['coupon_code'] : $order->getCouponCode();

                if ($canceled && !empty($this->orderData[$orderData['increment_id']]) && is_array($this->oderData[$orderData['increment_id']])) {
                    $this->orderData[$orderData['increment_id']]['cancelled'] = 'Y';
                } else {
                    if(empty($orderData['shipping_address_id']) && !empty($orderData['billing_address_id'])) {
                        $shippingAddress = Mage::getSingleton('sales/order_address')->load($orderData['billing_address_id'])->getData();
                        $billingAddress = Mage::getSingleton('sales/order_address')->load($orderData['billing_address_id'])->getData();
                    } elseif(!empty($orderData['shipping_address_id']) && !empty($orderData['billing_address_id'])) {
                        $shippingAddress = Mage::getSingleton('sales/order_address')->load($orderData['shipping_address_id'])->getData();
                        $billingAddress = Mage::getSingleton('sales/order_address')->load($orderData['billing_address_id'])->getData();
                    }

                    if(empty($orderData['shipping_address_id']) && empty($orderData['billing_address_id'])) {
                        $custBName = '';
                        $custName = '';
                        $custFName = '';
                        $custLName = '';
                        $custAddr1 = '';
                        $custCity = '';
                        $custState = '';
                        $custZip = '';
                        $custCountry = '';
                        $custPhone = '';

                        $shipName = '';
                        $shipFName = '';
                        $shipLName = '';
                        $shipAddr1 = '';
                        $shipCity = '';
                        $shipState = '';
                        $shipZip = '';
                        $shipCountry = '';
                    } else {
                        $custBName = $billingAddress['company'];
                        $custName = $billingAddress['firstname'] . ' ' . $billingAddress['lastname'];
                        $custFName = $billingAddress['firstname'];
                        $custLName = $billingAddress['lastname'];
                        $custAddr1 = $helper->formatString($billingAddress['street']);
                        $custCity = $helper->formatString($billingAddress['city']);
                        $custState = $helper->formatString($billingAddress['region']);
                        $custZip = $helper->formatString($billingAddress['postcode']);
                        $custCountry = $helper->formatString($billingAddress['country_id']);
                        $custPhone = $billingAddress['telephone'];

                        $shipName = $shippingAddress['firstname'] . ' ' . $shippingAddress['lastname'];
                        $shipFName = $shippingAddress['firstname'];
                        $shipLName = $shippingAddress['lastname'];
                        $shipAddr1 = $helper->formatString($shippingAddress['street']);
                        $shipCity = $helper->formatString($shippingAddress['city']);
                        $shipState = $helper->formatString($shippingAddress['region']);
                        $shipZip = $helper->formatString($shippingAddress['postcode']);
                        $shipCountry = $helper->formatString($shippingAddress['country_id']);
                    }

                    $this->orderIdArray['entity_id'][] = $orderData['entity_id'];
                    $this->orderIdArray['order_id'][$orderData['entity_id']]  = $orderData['increment_id'];

                    $custEmailOpt = $this->getNewsletterStatus($subscriber_status);

                    // Format for time fields
                    // Could be put in a custom class
                    $time = '';
                    $time = new DateTime($orderData['created_at']);
                    $orderDate = $time->format('Ymd');
                    $orderTime = $time->format('H:i:s');

                    // Array of Order Data
                    $this->orderData[$orderData['increment_id']] = array(
                        'orderId' 		=>	$orderData['increment_id'],
                        'orderDate' 	=>	$orderDate,
                        'orderTime'		=>	$orderTime,
                        'storeId'		=> 	$orderData['store_id'],
                        'custId'		=>	$orderData['customer_id'],
                        'custGroupId'   =>  $orderData['customer_group_id'],
                        'custGroupName' =>  $customerGroupCode,
                        'custBName'		=>	$custBName,
                        'custName'		=>	$custName,
                        'custFName'		=>	$custFName,
                        'custLName'		=>	$custLName,
                        'custEmail'		=>	$orderData['customer_email'],
                        'custEmailOpt'	=>	$custEmailOpt,
                        'custAddr1'		=>	$custAddr1,
                        'custAddr2'		=>	'',
                        'custCity'		=>	$custCity,
                        'custState'		=>	$custState,
                        'custZip'		=>	$custZip,
                        'custCountry'	=>	$custCountry,
                        'custPhone'		=>	$custPhone,
                        'shipName'		=>	$shipName,
                        'shipFName'		=>	$shipFName,
                        'shipLName'		=>	$shipLName,
                        'shipAddr1'		=>	$shipAddr1,
                        'shipAddr2'		=>	'',
                        'shipCity'		=>	$shipCity,
                        'shipState'		=>	$shipState,
                        'shipZip'		=>	$shipZip,
                        'shipCountry'	=>	$shipCountry,
                        'shipMethod'	=>	$orderData['shipping_description'],
                        'shipCost'		=>	$orderData['base_shipping_amount'],
                        'couponCodes'	=>	$couponCode,
                        'couponDiscount'=>	$orderData['discount_amount'],
                        'discountType'	=>	'',
                        'discount'		=>	'',
                        'prodTotal'		=>	($orderData['base_subtotal'] + $orderData['discount_amount']),
                        'cancelled'		=>	(!empty($state) ? $state->canceled($orderData['state']) : ($canceled ? 'Y' : 'N')),
                        'orderStatus'   =>  $orderData['status'],
                        'shipped'       =>  'N',
                        'shipment_date' =>  $orderOriginal['last_shipment_date']
                    );

                    $this->shippedArray[$orderData['increment_id']] = $orderData['total_qty_ordered'];

                    $this->appendCustomerCustomFields($orderOriginal);
                }
            }

            $currentPage++;

            // Clear orders collection (memory cleanup)
            $orders->clear();

        } while($currentPage <= $totalPages);

        return $this;
    }

    protected function getAscOrder($startDate, $endDate) {
        /** @var Mage_Sales_Model_Resource_Quote_Collection $quotes */
        $quotes = Mage::getModel('sales/quote')->getCollection();

        $version = explode('.', Mage::getVersion());
        if ( $version[0] == 1 && $version[1] <= 3 )
        {
            $quotes->getSelect()->orWhere('updated_at >= :startDate AND updated_at <= :endDate');
            $quotes->getSelect()->orWhere('created_at <= :startDate AND created_at <= :endDate');

            $quotes->addBindParam('startDate', $startDate);
            $quotes->addBindParam('endDate', $endDate);

            $quotes->addFieldToFilter('items_qty', array('gt' => 0))
                ->setOrder('increment_id', 'ASC');
        } else if ($version[0] == 1 && $version[1] > 3 && $version[1] <= 5 ||
            $this->isEnterprise() && $version[0] == 1 && in_array($version[1], array(9, 10)))
        {

            $adapter = $quotes->getSelect()->getAdapter();
            $startDate = $adapter->quote($startDate);
            $endDate = $adapter->quote($endDate);

            $quotes->getSelect()->where(vsprintf('(main_table.updated_at >= %s AND main_table.updated_at <= %s)' .
                ' ' . Zend_Db_Select::SQL_OR . ' (main_table.created_at >= %s AND main_table.created_at <= %s)',
                array($startDate, $endDate, $startDate, $endDate)));

            $quotes->addFieldToFilter('main_table.items_qty', array('gt' => 0))
                ->setOrder('main_table.entity_id', 'ASC');

            $quotes->getSelect()->joinLeft(array('address_billing' => Mage::getSingleton('core/resource')->getTableName('sales/quote_address')), 'main_table.entity_id = address_billing.quote_id and address_billing.address_type = "billing"', array('billing_id' => 'address_billing.address_id'));
            $quotes->getSelect()->joinLeft(array('address_shipping' => Mage::getSingleton('core/resource')->getTableName('sales/quote_address')), 'main_table.entity_id = address_shipping.quote_id and address_shipping.address_type = "shipping"', array('shipping_id' => 'address_shipping.address_id'));
        } else {
            $conditions = array();
            $condition = array('datetime' => true, 'from' => $startDate, 'to' => $endDate);
            $conditions[] = $quotes->getConnection()->prepareSqlCondition('main_table.updated_at', $condition);
            $conditions[] = $quotes->getConnection()->prepareSqlCondition('main_table.created_at', $condition);

            $resultCondition = '(' . join(') ' . Zend_Db_Select::SQL_OR . ' (', $conditions) . ')';

            $quotes->getSelect()->where($resultCondition);

            $quotes->addFieldToFilter('main_table.items_qty', array('gt' => 0))
                ->setOrder('main_table.entity_id', 'ASC');
            $quotes->getSelect()->joinLeft(array('address_billing' => Mage::getSingleton('core/resource')->getTableName('sales/quote_address')), 'main_table.entity_id = address_billing.quote_id and address_billing.address_type = "billing"', array('billing_id' => 'address_billing.address_id'));
            $quotes->getSelect()->joinLeft(array('address_shipping' => Mage::getSingleton('core/resource')->getTableName('sales/quote_address')), 'main_table.entity_id = address_shipping.quote_id and address_shipping.address_type = "shipping"', array('shipping_id' => 'address_shipping.address_id'));
        }

        // Limit by store
        $stores = Mage::getStoreConfig('windsorcircle_export_options/messages/store');
        if (!empty($stores)) {
            $stores = explode(',', $stores);
            $quotes->addFieldToFilter('store_id', array('in' => $stores));
        }

        $this->addAscHeaders();
        $helper = Mage::helper('windsorcircle_export');

        // Set Page size as 100 at a time
        $quotes->setPageSize(100);
        $totalPages = $quotes->getLastPageNumber();
        $currentPage = 1;

        do {
            $quotes->setCurPage($currentPage);

            foreach($quotes->getData() as $quoteData){

                if ($quoteData['customer_id']) {
                    $customer = Mage::getModel('customer/customer')->load($quoteData['customer_id']);
                    $billingAddress = Mage::getSingleton('customer/address')
                        ->setData(array())
                        ->load($customer->getDefaultBilling());
                    $shippingAddress = Mage::getSingleton('customer/address')
                        ->setData(array())
                        ->load($customer->getDefaultShipping());
                } else {
                    $billingAddress = Mage::getSingleton('sales/quote_address')->setData(array())->load($quoteData['billing_id']);
                    $shippingAddress = Mage::getSingleton('sales/quote_address')->setData(array())->load($quoteData['shipping_id']);
                }

                if (!$billingAddress->isEmpty()) {
                    $custBName = $billingAddress->getCompany();
                    $custName = implode(' ', array($billingAddress->getFirstname(), $billingAddress->getLastname()));
                    $custFName = $billingAddress->getFirstname();
                    $custLName = $billingAddress->getLastname();
                    $custAddr1 = $helper->formatString(implode('\n', $billingAddress->getStreet()));
                    $custCity = $helper->formatString($billingAddress->getCity());
                    $custState = $helper->formatString($billingAddress->getRegion());
                    $custZip = $helper->formatString($billingAddress->getPostcode());
                    $custCountry = $helper->formatString($billingAddress->getCountryId());
                    $custPhone = $billingAddress->getTelephone();
                } else {
                    $custBName  = '';
                    $custName   = '';
                    $custFName  = '';
                    $custLName  = '';
                    $custAddr1  = '';
                    $custCity   = '';
                    $custState  = '';
                    $custZip    = '';
                    $custCountry= '';
                    $custPhone  = '';
                }

                if (!$shippingAddress->isEmpty()) {
                    $shipName = implode(' ', array($shippingAddress->getFirstname(), $shippingAddress->getLastname()));
                    $shipFName = $shippingAddress->getFirstname();
                    $shipLName = $shippingAddress->getLastname();
                    $shipAddr1 = $helper->formatString(implode('\n', $shippingAddress->getStreet()));
                    $shipCity = $helper->formatString($shippingAddress->getCity());
                    $shipState = $helper->formatString($shippingAddress->getRegion());
                    $shipZip = $helper->formatString($shippingAddress->getPostcode());
                    $shipCountry = $helper->formatString($shippingAddress->getCountryId());
                } else {
                    $shipName   = '';
                    $shipFName  = '';
                    $shipLName  = '';
                    $shipAddr1  = '';
                    $shipCity   = '';
                    $shipState  = '';
                    $shipZip    = '';
                    $shipCountry= '';
                }

                $this->quoteIdArray['entity_id'][] = $quoteData['entity_id'];

                // Format for time fields
                $createdTime = new DateTime($quoteData['created_at']);
                $createdTime = $createdTime->format('Ymd H:i:s');
                $updatedTime = new DateTime($quoteData['updated_at']);
                $updatedTime = $updatedTime->format('Ymd H:i:s');
                $convertedAt = '';

                // Current Store Cart Url
                Mage::app()->setCurrentStore($quoteData['store_id']);
                $cartUrl = Mage::helper('checkout/url')->getCheckoutUrl();

                // Array of Order Data
                $this->quoteAscData[$quoteData['entity_id']] = array(
                    'orderId'              =>  $quoteData['entity_id'],
                    'reserved_order_id'    =>  $quoteData['reserved_order_id'],
                    'createdtimestamp'     =>  $createdTime,
                    'updatedtimestamp'     =>  $updatedTime,
                    'convertedtimestamp'   =>  $convertedAt,
                    'is_active'            =>  $quoteData['is_active'],
                    'storeId'              =>  $quoteData['store_id'],
                    'cartUrl'              =>  $cartUrl,
                    'custId'               =>  $quoteData['customer_id'],
                    'custBName'            =>  $custBName,
                    'custName'             =>  $custName,
                    'custFName'            =>  $custFName,
                    'custLName'            =>  $custLName,
                    'custEmail'            =>  $quoteData['customer_email'],
                    'custAddr1'            =>  $custAddr1,
                    'custAddr2'            =>  '',
                    'custCity'             =>  $custCity,
                    'custState'            =>  $custState,
                    'custZip'              =>  $custZip,
                    'custCountry'          =>  $custCountry,
                    'custPhone'            =>  $custPhone,
                    'shipName'             =>  $shipName,
                    'shipFName'            =>  $shipFName,
                    'shipLName'            =>  $shipLName,
                    'shipAddr1'            =>  $shipAddr1,
                    'shipAddr2'            =>  '',
                    'shipCity'             =>  $shipCity,
                    'shipState'            =>  $shipState,
                    'shipZip'              =>  $shipZip,
                    'shipCountry'          =>  $shipCountry,
                    'shipMethod'           =>  isset($quoteData['shipping_description']) ? $quoteData['shipping_description']:'',
                    'shipCost'             =>  isset($quoteData['base_shipping_amount']) ? $quoteData['base_shipping_amount']:'',
                    'couponCodes'          =>  $quoteData['coupon_code'],
                    'couponDiscount'       =>  $quoteData['base_subtotal'] - $quoteData['base_subtotal_with_discount'],
                    'discountType'         =>  '',
                    'discount'             =>  '',
                    'prodTotal'            =>  $quoteData['base_subtotal_with_discount'],
                );
            }

            $currentPage++;

            // Clear orders collection (memory cleanup)
            $quotes->clear();
            $quotes->resetData();

        } while($currentPage <= $totalPages);

        // Set Store back to Admin
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        return $this;
    }

    /**
     * Adds headers to array
     */
    protected function addHeaders(){
        $this->orderData[0] = array(
            'orderId'		=>	'OrderId',
            'orderDate' 	=>	'OrderDate',
            'orderTime'		=>	'OrderTime',
            'storeId'		=> 	'StoreId',
            'custId'		=>	'CustId',
            'custGroupId'   =>  'CustGroupId',
            'custGroupName' =>  'CustGroupName',
            'custBName'		=>	'CustBName',
            'custName'		=>	'CustName',
            'custFName'		=>	'CustFName',
            'custLName'		=>	'CustLName',
            'custEmail'		=>	'CustEmail',
            'custEmailOpt'	=>	'CustEmailOpt',
            'custAddr1'		=>	'CustAddr1',
            'custAddr2'		=>	'CustAddr2',
            'custCity'		=>	'CustCity',
            'custState'		=>	'CustState',
            'custZip'		=>	'CustZip',
            'custCountry'	=>	'CustCountry',
            'custPhone'		=>	'CustPhone',
            'shipName'		=>	'ShipName',
            'shipFName'		=>	'ShipFName',
            'shipLName'		=>	'ShipLName',
            'shipAddr1'		=>	'ShipAddr1',
            'shipAddr2'		=>	'ShipAddr2',
            'shipCity'		=>	'ShipCity',
            'shipState'		=>	'ShipState',
            'shipZip'		=>	'ShipZip',
            'shipCountry'	=>	'ShipCountry',
            'shipMethod'	=>	'ShipMethod',
            'shipCost'		=>	'ShipCost',
            'couponCodes'	=>	'CouponCodes',
            'couponDiscount'=>	'CouponDiscount',
            'discountType'	=>	'DiscountType',
            'discount'		=>	'Discount',
            'prodTotal'		=>	'ProdTotal',
            'cancelled'		=>	'Cancelled',
            'orderStatus'   =>  'OrderStatus',
            'shipped'       =>  'Shipped',
            'shipment_date' =>  'ShipmentDate'
        );

        $customAttributes = Mage::helper('windsorcircle_export')->getCustomAttributes('customer');
        foreach ($customAttributes as $customAttribute) {
            $this->orderData[0]['custom_customer_' . $customAttribute] = 'custom_customer_' . $customAttribute;
        }

        $customAttributes = Mage::helper('windsorcircle_export')->getCustomAttributes('customer_address');
        foreach ($customAttributes as $customAttribute) {
            $this->orderData[0]['custom_customer_address_' . $customAttribute] = 'custom_customer_address_' . $customAttribute;
        }

        $orderFields = Mage::helper('windsorcircle_export')->getCustomAttributes('order');
        foreach ($orderFields as $orderField) {
            $this->orderData[0]['custom_order_' . $orderField] = 'custom_order_' . $orderField;
        }

        $this->orderDetailsData[0][0] = array(
            'orderId'		=>	'OrderId',
            'storeId'		=>	'StoreId',
            'prodId'		=>	'ProdId',
            'qtyOrdered'	=>	'QtyOrdered',
            'qtyReturned'	=>	'QtyReturned',
            'masterId'	    =>	'PSKU',
            'simpleId'	    =>	'VSKU',
            'price'		    =>	'Price',
            'productOptions'=>  'ProductOptions'
        );

        $buyRequests = Mage::helper('windsorcircle_export')->getCustomAttributes('order_item_buy_request');
        foreach ($buyRequests as $buyRequest) {
            $this->orderDetailsData[0][0]['custom_order_item_buy_request_' . $buyRequest] = 'custom_order_item_buy_request_' . $buyRequest;
        }
    }

    protected function addAscHeaders(){
        $this->quoteAscData[0] = array(
            'orderId'		    =>	'CartID',
            'reserved_order_id'  =>  'ReservedOrderID',
            'createdtimestamp'   =>  'CreatedTimestamp',
            'updatedtimestamp'   =>  'UpdatedTimestamp',
            'convertedtimestamp' =>  'ConvertedTimestamp',
            'is_active'          =>  'IsActive',
            'storeId'            =>  'StoreID',
            'cartUrl'            =>  'CartURL',
            'custId'             =>  'CustID',
            'custBName'          =>  'CustBName',
            'custName'           =>  'CustName',
            'custFName'          =>  'CustFName',
            'custLName'          =>  'CustLName',
            'custEmail'          =>  'CustEmail',
            'custAddr1'          =>  'CustAddr1',
            'custAddr2'          =>  'CustAddr2',
            'custCity'           =>  'CustCity',
            'custState'          =>  'CustState',
            'custZip'            =>  'CustZip',
            'custCountry'        =>  'CustCountry',
            'custPhone'          =>  'CustPhone',
            'shipName'           =>  'ShipName',
            'shipFName'          =>  'ShipFName',
            'shipLName'          =>  'ShipLName',
            'shipAddr1'          =>  'ShipAddr1',
            'shipAddr2'          =>  'ShipAddr2',
            'shipCity'           =>  'ShipCity',
            'shipState'          =>  'ShipState',
            'shipZip'            =>  'ShipZip',
            'shipCountry'        =>  'ShipCountry',
            'shipMethod'         =>  'ShipMethod',
            'shipCost'           =>  'ShipCost',
            'couponCodes'        =>  'CouponCodes',
            'couponDiscount'     =>  'CouponDiscount',
            'discountType'       =>  'DiscountType',
            'discount'           =>  'Discount',
            'prodTotal'          =>  'ProdTotal',
        );

        $this->quoteAscDetailsData[0][0] = array(
            'cartId'        =>   'CartID',
            'storeId'       =>   'StoreID',
            'simpleId'      =>   'VSKU',
            'masterId'      =>   'PSKU',
            'prodId'        =>   'ProdID',
            'price'         =>   'Price',
            'quantity'      =>   'Quantity',
            'productOptions'=>  'ProductOptions'
        );

        $buyRequests = Mage::helper('windsorcircle_export')->getCustomAttributes('order_item_buy_request');
        foreach ($buyRequests as $buyRequest) {
            $this->quoteAscDetailsData[0][0]['custom_quote_item_buy_request_' . $buyRequest] = 'custom_quote_item_buy_request_' . $buyRequest;
        }
    }

    protected function getNewsletterStatus($status){
        if (empty($status) || $status == 3) {
            return '3';
        } elseif (!empty($status) && $status == 1) {
            return '1';
        } else {
            return '';
        }
    }

    protected function getOrderItems(){
        if ($this->debug) {
            Mage::log('Order Details Started', null, $this->logFile);
        }

        if(!empty($this->orderIdArray['entity_id'])){
            $version = explode('.', Mage::getVersion());
            $orderItems = Mage::getModel('sales/order_item')->getCollection()
                ->addFieldToFilter('order_id', array('in' => $this->orderIdArray['entity_id']));

            // If Magento version 1.3 then you must use addOrder instead of addAttributeToSort otherwise it breaks
            if ( $version[0] == 1 && $version[1] <= 3 ) {
                $orderItems->addOrder('product_type', 'ASC');
            } else {
                $orderItems->addAttributeToSort('product_type', 'ASC');
            }

            $orderItems->setPageSize($this->pageSize);
            $totalPages = $orderItems->getLastPageNumber();
            $currentPage = 1;

            // If version 1.4 or above then we need to add group by clause so we do not get duplicate ids
            // NOTE: This needs to be called after getLastPageNumber since some versions of Magento do not clear
            //       the group by clause
            if ( $version[0] == 1 && $version[1] > 3 ) {
                $orderItems->getSelect()->group('main_table.item_id');
            }

            // Items array
            $items = array();
            $itemCheck = array();

            $helper = Mage::helper('windsorcircle_export');

            do {
                if ($this->debug) {
                    $log = 'Page: ' . $currentPage;
                    $log.= ' - Memory: ' . $helper->getMemoryUsage();
                    Mage::log($log, null, $this->logFile);
                }

                $orderItems->setCurPage($currentPage);

                $categorySkus = array();

                foreach($orderItems->getData() as $item) {
                    $itemCheck[$item['item_id']] = array('type' => $item['product_type'], 'sku' => $item['sku']);

                    $items[$item['item_id']] = array(
                        'order_id'          =>  $item['order_id'],
                        'store_id'          =>  $item['store_id'],
                        'item_id'           =>  $item['item_id'],
                        'product_id'        =>  $item['product_id'],
                        'sku'               =>  $item['sku'],
                        'product_type'      =>  $item['product_type'],
                        'parent_item_id'    =>  $item['parent_item_id'],
                        'qty_ordered'       =>  $item['qty_ordered'],
                        'qty_refunded'      =>  $item['qty_refunded'],
                        'qty_shipped'       =>  $item['qty_shipped'],
                        'base_price'        =>  $item['base_price'],
                        'product_options'   =>  $item['product_options']
                    );

                    $this->orderItems[] = $item['product_id'];

                    // If we are on version 1.3 or below then we need to set the shippedArray amount to qty_ordered
                    // since the order does not fill in the total_qty_ordered column
                    if ( $version[0] == 1 && $version[1] <= 3 ) {
                        if ($item['parent_item_id'] === null) {
                            $realOrderId = $this->orderIdArray['order_id'][$item['order_id']];
                            $this->shippedArray[$realOrderId] = $this->shippedArray[$realOrderId] + $item['qty_ordered'];
                        }
                    }

                    if (empty($item['sku'])) {
                        $categorySkus[] = $item['item_id'];
                    }
                    if (!empty($item['parent_item_id'])) {
                        $categorySkus[] = $item['parent_item_id'];
                    }
                }

                // This will only populate if the sku from the sales_flat_order_item table is empty
                if (!empty($categorySkus)) {
                    $categorySkuCollection = Mage::getModel('sales/order_item')->getCollection()
                        ->addFieldToFilter('item_id', array('in' => $categorySkus));

                    $categorySkuCollection->getSelect()
                        ->joinLeft(
                            array('catalog/product' => Mage::getSingleton('core/resource')->getTableName('catalog/product')),
                            'main_table.product_id=`catalog/product`.entity_id',
                            array('sku' => 'catalog/product.sku')
                        );

                    if ( $version[0] == 1 && $version[1] <= 3 ) {
                        $categorySkuCollection->addOrder('product_type', 'ASC');
                    } else {
                        $categorySkuCollection->addAttributeToSort('product_type', 'ASC');
                        $categorySkuCollection->getSelect()->group('main_table.item_id');
                    }

                    foreach ($categorySkuCollection as $categorySku) {
                        $items[$categorySku['item_id']]['sku'] = $categorySku['sku'];
                    }
                }

                $currentPage++;

                $orderItems->clear();
                $orderItems->resetData();

            } while($currentPage <= $totalPages);

            foreach($items as $item){
                $parentType = false;

                $realOrderId = $this->orderIdArray['order_id'][$item['order_id']];

                if ( $version[0] == 1 && $version[1] <= 3 ) {
                    $order = Mage::getSingleton('sales/order')->load($item['order_id']);
                    $storeId = $order->getStoreId();
                } else {
                    $storeId = $item['store_id'];
                }

                if((isset($item['product_type']) && $item['product_type'] == 'configurable') || $item['product_type'] == '') {
                    $this->orderDetailsData[$realOrderId][$item['item_id']] = array(
                        'orderId'		=>	$realOrderId,
                        'storeId'		=>	$storeId,
                        'prodId'		=>	$item['product_id'],
                        'qtyOrdered'	=>	$item['qty_ordered'],
                        'qtyReturned'	=>	$item['qty_refunded'],
                        'masterId'		=>	'',
                        'simpleId'		=>	$item['sku'],
                        'price'			=>	number_format($item['base_price'], 2,'.',''));
                } elseif((isset($item['parent_item_id']) && $item['parent_item_id'] != null) &&
                    (isset($itemCheck[$item['parent_item_id']]['type']) && $itemCheck[$item['parent_item_id']]['type'] == 'bundle'))
                {
                    $parentType = 'bundle';
                    $this->orderDetailsData[$realOrderId][$item['item_id']]['orderId']		= $realOrderId;
                    $this->orderDetailsData[$realOrderId][$item['item_id']]['storeId']		= $storeId;
                    $this->orderDetailsData[$realOrderId][$item['item_id']]['prodId']		= $item['product_id'];
                    $this->orderDetailsData[$realOrderId][$item['item_id']]['qtyOrdered']	= $item['qty_ordered'];
                    $this->orderDetailsData[$realOrderId][$item['item_id']]['qtyReturned']	= $item['qty_refunded'];
                    $this->orderDetailsData[$realOrderId][$item['item_id']]['masterId']		= $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['simpleId'];
                    $this->orderDetailsData[$realOrderId][$item['item_id']]['simpleId']		= $item['sku'];
                    $this->orderDetailsData[$realOrderId][$item['item_id']]['price']		= '0.00';
                } elseif((isset($item['parent_item_id']) && $item['parent_item_id'] != null) &&
                    (isset($itemCheck[$item['parent_item_id']]['type']) && $itemCheck[$item['parent_item_id']]['type'] != 'simple'))
                {
                    $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['orderId']		= $realOrderId;
                    $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['storeId']		= $storeId;
                    $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['prodId']		= $item['product_id'];
                    $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['qtyOrdered']	= $item['qty_ordered'];
                    $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['qtyReturned']	= $item['qty_refunded'];
                    $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['masterId']		= $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['simpleId'];
                    $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['simpleId']		= $item['sku'];

                    // Force base_price to be a float because a string '0.0000' is not empty from php
                    $item['base_price'] = (float) $item['base_price'];
                    if(!empty($item['base_price'])) {
                        $this->orderDetailsData[$realOrderId][$item['parent_item_id']]['price']			= number_format($item['base_price'], 2,'.','');
                    }
                } else {
                    $this->orderDetailsData[$realOrderId][$item['item_id']] = array(
                        'orderId'		=>	$realOrderId,
                        'storeId'		=>	$storeId,
                        'prodId'		=>	$item['product_id'],
                        'qtyOrdered'	=>	$item['qty_ordered'],
                        'qtyReturned'	=>	$item['qty_refunded'],
                        'masterId'		=>	'',
                        'simpleId'		=>	$item['sku'],
                        'price'			=>	number_format($item['base_price'], 2,'.',''));
                }

                if ($item['parent_item_id'] === null) {
                    $this->shippedArray[$realOrderId] = $this->shippedArray[$realOrderId] - $item['qty_shipped'];
                    if ($this->shippedArray[$realOrderId] <= 0) {
                        if (isset($this->orderData[$realOrderId]['shipped'])) {
                            $this->orderData[$realOrderId]['shipped'] = 'Y';
                        }
                    }
                }

                $this->appendProductOptions($item, $parentType, false);
            }
        }
        return $this;
    }

    protected function getAscOrderItems() {
        if(!empty($this->quoteIdArray['entity_id'])){
            $version = explode('.', Mage::getVersion());

            $quoteItems = Mage::getModel('sales/quote_item')->getCollection()
                ->join('catalog/product',
                    'main_table.product_id=`catalog/product`.entity_id',
                    array('sku' => 'IF(ISNULL(`catalog/product`.sku), main_table.sku, `catalog/product`.sku)'))
                ->addFieldToFilter('quote_id', array('in' => $this->quoteIdArray['entity_id']))
                ->addOrder('product_type', 'ASC');

            $quoteItems->getSelect()->joinLeft(array('item_option' => Mage::getSingleton('core/resource')->getTableName('sales/quote_item_option')), "item_option.item_id=main_table.item_id AND code='info_buyRequest'", array('product_options' => 'value'));

            $quoteItems->setPageSize(100);
            $totalPages = $quoteItems->getLastPageNumber();
            $currentPage = 1;

            // Items array
            $items = array();
            $itemCheck = array();

            do {
                $quoteItems->setCurPage($currentPage);

                foreach($quoteItems->getData() as $item) {
                    $itemCheck[$item['item_id']] = array('type' => $item['product_type'], 'sku' => $item['sku']);

                    $items[] = array(
                        'quote_id'          =>  $item['quote_id'],
                        'store_id'          =>  $item['store_id'],
                        'item_id'           =>  $item['item_id'],
                        'sku'               =>  $item['sku'],
                        'product_id'        =>  $item['product_id'],
                        'product_type'      =>  $item['product_type'],
                        'parent_item_id'    =>  $item['parent_item_id'],
                        'qty'               =>  $item['qty'],
                        'base_price'        =>  $item['base_price'],
                        'product_options'   =>  $item['product_options']
                    );

                    $this->orderItems[] = $item['product_id'];
                }

                $currentPage++;

                $quoteItems->clear();
                $quoteItems->resetData();

            } while($currentPage <= $totalPages);

            foreach($items as $item){
                $parentType = false;
                if ( $version[0] == 1 && $version[1] <= 3 ) {
                    $quote = Mage::getSingleton('sales/quote')->load($item['quote_id']);
                    $storeId = $quote->getStoreId();
                } else {
                    $storeId = $item['store_id'];
                }

                if($item['product_type'] == 'configurable' || $item['product_type'] == '') {
                    $this->quoteAscDetailsData[$item['quote_id']][$item['item_id']] = array(
                        'cartId'=>  $item['quote_id'],
                        'storeId'    =>  $storeId,
                        'simpleId'   =>  $item['sku'],
                        'masterId'   =>  '',
                        'prodId'     =>  $item['product_id'],
                        'price'      =>  number_format($item['base_price'], 2,'.',''),
                        'quantity'   =>  $item['qty']);
                } elseif($item['parent_item_id'] != null
                    && $itemCheck[$item['parent_item_id']]['type'] == 'bundle'
                ) {
                    $parentType = 'bundle';
                    $this->quoteAscDetailsData[$item['quote_id']][$item['item_id']]['cartId']        = $item['quote_id'];
                    $this->quoteAscDetailsData[$item['quote_id']][$item['item_id']]['storeId']       = $storeId;
                    $this->quoteAscDetailsData[$item['quote_id']][$item['item_id']]['simpleId']      = $item['sku'];
                    $this->quoteAscDetailsData[$item['quote_id']][$item['item_id']]['masterId']      = $this->quoteAscDetailsData[$item['quote_id']][$item['parent_item_id']]['simpleId'];
                    $this->quoteAscDetailsData[$item['quote_id']][$item['item_id']]['prodId']        = $item['product_id'];
                    $this->quoteAscDetailsData[$item['quote_id']][$item['item_id']]['price']         = '0.00';
                    $this->quoteAscDetailsData[$item['quote_id']][$item['item_id']]['quantity']      = $item['qty'];
                } elseif($item['parent_item_id'] != null
                    && $itemCheck[$item['parent_item_id']]['type'] != 'simple'
                ) {
                    $this->quoteAscDetailsData[$item['quote_id']][$item['parent_item_id']]['cartId']     = $item['quote_id'];
                    $this->quoteAscDetailsData[$item['quote_id']][$item['parent_item_id']]['storeId']    = $storeId;
                    $this->quoteAscDetailsData[$item['quote_id']][$item['parent_item_id']]['simpleId']   = $item['sku'];
                    $this->quoteAscDetailsData[$item['quote_id']][$item['parent_item_id']]['masterId']   = $this->quoteAscDetailsData[$item['quote_id']][$item['parent_item_id']]['simpleId'];
                    $this->quoteAscDetailsData[$item['quote_id']][$item['parent_item_id']]['prodId']     = $item['product_id'];

                    // Force base_price to be a float because a string '0.0000' is not empty from php
                    $item['base_price'] = (float) $item['base_price'];
                    if(!empty($item['base_price'])) {
                        $this->quoteAscDetailsData[$item['quote_id']][$item['parent_item_id']]['price'] = number_format($item['base_price'], 2,'.','');
                    }

                    $this->quoteAscDetailsData[$item['quote_id']][$item['parent_item_id']]['quantity'] = $item['qty'];
                } else {
                    $this->quoteAscDetailsData[$item['quote_id']][$item['item_id']] = array(
                        'cartId'=>  $item['quote_id'],
                        'storeId'    =>  $storeId,
                        'simpleId'   =>  $item['sku'],
                        'masterId'   =>  '',
                        'prodId'     =>  $item['product_id'],
                        'price'      =>  number_format($item['base_price'], 2,'.',''),
                        'quantity'   =>  $item['qty']);
                }

                $this->appendProductOptions($item, $parentType, true);
            }
        }
    }

    /**
     * Check if Enterprise version
     *
     * @return bool
     */
    public function isEnterprise()
    {
        if (Mage::getConfig()->getModuleConfig('Enterprise_Enterprise') && Mage::getConfig()->getModuleConfig('Enterprise_AdminGws') && Mage::getConfig()->getModuleConfig('Enterprise_Checkout') && Mage::getConfig()->getModuleConfig('Enterprise_Customer')) {
            return true;
        }
        return false;
    }

    /**
     * Add Customer Custom Attributes to Collection
     *
     * @param $collection
     */
    protected function addCustomerCustomAttributes(&$collection)
    {
        foreach (array('customer', 'customer_address') as $type) {
            $attributes = Mage::getStoreConfig('windsorcircle_export_options/messages/custom_' . $type . '_attributes');
            $attributes = Mage::helper('windsorcircle_export')->makeArrayFieldValue($attributes);
            $model = $type === 'customer' ? 'customer/customer' : 'customer/address';
            $customerType = Mage::getResourceSingleton($model)->getType();

            if ($type === 'customer') {
                $billingAttribute = Mage::getSingleton('eav/config')->getCollectionAttribute($customerType, 'default_billing');
                $billingTable = $billingAttribute->getBackendTable();
                $billingId = $billingAttribute->getId();
                $billingAlias = $type . '_billing';

                $collection->getSelect()->joinLeft(array($billingAlias => $billingTable), $type . '.entity_id = ' . $billingAlias . '.entity_id AND ' . $billingAlias . '.attribute_id = \'' . $billingId . '\'', array(/*$billingAlias => $billingAlias . '.value'*/));

                $entityTable = Mage::getSingleton('core/resource')->getTableName('customer/entity');
                $describe = Mage::getModel('core/resource')->getConnection('core_read')->describeTable($entityTable);
            } else {
                $collection->getSelect()->joinLeft(array($type => Mage::getSingleton('core/resource')->getTableName('customer/address_entity')), $type . '.parent_id = customer.entity_id AND ' . $type . '.entity_id = customer_billing.value', array());

                $entityTable = Mage::getSingleton('core/resource')->getTableName('customer/address_entity');
                $describe = Mage::getModel('core/resource')->getConnection('core_read')->describeTable($entityTable);
            }

            foreach ($attributes as $attribute) {
                $attributeCode = $attribute['attribute_code'];
                $tableAlias = 'custom_' . $type . '_' . $attributeCode;

                if (isset($describe[$attributeCode])) {
                    $collection->getSelect()->joinLeft(array($tableAlias => $entityTable), $type . '.entity_id = ' . $tableAlias . '.entity_id', array($tableAlias => $tableAlias . '.' . $attributeCode));
                } else {
                    $eavAttribute = Mage::getSingleton('eav/config')->getCollectionAttribute($customerType, $attributeCode);
                    $backendTable = $eavAttribute->getBackendTable();
                    $attributeId = $eavAttribute->getId();

                    $collection->getSelect()->joinLeft(array($tableAlias => $backendTable), $type . '.entity_id = ' . $tableAlias . '.entity_id AND ' . $tableAlias . '.attribute_id = \'' . $attributeId . '\'', array($tableAlias => $tableAlias . '.value'));
                }
            }
        }
    }

    /**
     * Append Customer Custom Fields to passed in order
     */
    protected function appendCustomerCustomFields($order)
    {
        $helper = Mage::helper('windsorcircle_export');
        $customCustomerAttributes = Mage::helper('windsorcircle_export')->getCustomAttributes('customer');
        $customCustomerAddressAttributes = Mage::helper('windsorcircle_export')->getCustomAttributes('customer_address');
        $customOrderFields = Mage::helper('windsorcircle_export')->getCustomAttributes('order');

        foreach ($customCustomerAttributes as $customAttribute) {
            if ($order->getData('custom_customer_' . $customAttribute) === null) {
                $this->orderData[$order['increment_id']]['custom_customer_' . $customAttribute] = '';
                continue;
            }

            $frontend = Mage::getResourceSingleton('customer/customer')->getAttribute($customAttribute)->getFrontend();
            $frontend->getAttribute()->setAttributeCode('custom_customer_' . $customAttribute);
            $data = $frontend->getValue($order);
            $this->orderData[$order['increment_id']]['custom_customer_' . $customAttribute] = $helper->formatString($data);
        }

        foreach ($customCustomerAddressAttributes as $customAddressAttribute) {
            if ($order->getData('custom_customer_address_' . $customAddressAttribute) === null) {
                $this->orderData[$order['increment_id']]['custom_customer_address_' . $customAddressAttribute] = '';
                continue;
            }

            $frontend = Mage::getResourceSingleton('customer/address')->getAttribute($customAddressAttribute)->getFrontend();
            $frontend->getAttribute()->setAttributeCode('custom_customer_address_' . $customAddressAttribute);
            $data = $frontend->getValue($order);
            $this->orderData[$order['increment_id']]['custom_customer_address_' . $customAddressAttribute] = $helper->formatString($data);
        }

        foreach ($customOrderFields as $customOrderField) {
            $data = $order->getData($customOrderField);
            $this->orderData[$order['increment_id']]['custom_order_' . $customOrderField] = $helper->formatString($data);
        }
    }

    /**
     * Append Product Options
     *
     * @param $item
     * @param bool|false $parentType
     * @param bool|false $asc
     */
    protected function appendProductOptions($item, $parentType = false, $asc = false)
    {
        $columnName = 'custom_order_item_buy_request_';
        if ($asc) { $columnName = 'custom_quote_item_buy_request_'; }
        $buyRequest = '';

        if (!$asc) {
            $realOrderId = $this->orderIdArray['order_id'][$item['order_id']];
        }

        // If parent type is bundle then use item_id instead of parent_item_id
        $id = (isset($item['parent_item_id'])
            && !empty($item['parent_item_id'])
            && $parentType != 'bundle') ? $item['parent_item_id'] : $item['item_id'];

        if (!empty($item['product_options'])) {
            $productOptions = unserialize($item['product_options']);

            if (!$asc) {
                if ($productOptions !== false && isset($productOptions['info_buyRequest'])) {
                    $buyRequest = $productOptions['info_buyRequest'];
                }
                $this->orderDetailsData[$realOrderId][$id]['productOptions'] = $item['product_options'];
            } else {
                if ($productOptions !== false) {
                    $buyRequest = $productOptions;
                }
                $this->quoteAscDetailsData[$item['quote_id']][$id]['productOptions'] = $item['product_options'];
            }
        } else {
            if (!$asc) {
                $this->orderDetailsData[$realOrderId][$id]['productOptions'] = '';
            } else {
                $this->quoteAscDetailsData[$item['quote_id']][$id]['productOptions'] = '';
            }
        }

        $helper = Mage::helper('windsorcircle_export');
        $customProductOptions = Mage::helper('windsorcircle_export')->getCustomAttributes('order_item_buy_request');

        foreach ($customProductOptions as $customProductOption) {
            if (isset($buyRequest[$customProductOption]) && !empty($buyRequest[$customProductOption])) {
                if (is_array($buyRequest[$customProductOption])) {
                    $productOption = implode(',', $buyRequest[$customProductOption]);
                } else {
                    $productOption = $buyRequest[$customProductOption];
                }

                if ($asc) {
                    $this->quoteAscDetailsData[$item['quote_id']][$id][$columnName . $customProductOption] = $helper->formatString($productOption);
                } else {
                    $this->orderDetailsData[$realOrderId][$id][$columnName . $customProductOption] = $helper->formatString($productOption);
                }
            } else {
                if ($asc) {
                    $this->quoteAscDetailsData[$item['quote_id']][$id][$columnName . $customProductOption] = '';
                } else {
                    $this->orderDetailsData[$realOrderId][$id][$columnName . $customProductOption] = '';
                }
            }
        }
    }
}
