<?php
    class WindsorCircle_Export_Model_Products extends Mage_Core_Model_Abstract
    {
        protected $productData = array();

        protected $allowedCountries = array();

        protected $taxCollection = array();

        protected $categoryList = array();

        protected $breadcrumb = array();

        protected $completedBreadcrumbIds = array();

        protected $treeCollection = '';

        protected $attributeValues = array();

        protected function _construct(){
            $this->_init('windsorcircle_export/products');
        }

        protected function loadTreeCollection() {
            // Get Current Store
            $previousStore = Mage::app()->getStore(null)->getId();

            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

            $this->treeCollection = Mage::getResourceSingleton('catalog/category_tree')
                ->load();

            $collection = Mage::getSingleton('catalog/category')->getCollection()
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('is_active');

            $this->treeCollection->addCollectionData($collection, true);

            // Set store back to previous store
            Mage::app()->setCurrentStore($previousStore);
        }

        /**
         *
         * Build Category Array by Node
         * Recursive function for getting all children from a root node
         * @param Varien_Data_Tree_Node $node
         * $return array $result
         */
        protected function nodeToArray(Varien_Data_Tree_Node $node, $storeId = false)
        {
            if($node->getIsActive()) {
                $this->categoryList[$storeId][$node->getLevel()][$node->getId()] = array('name' => $node->getName(), 'parent_id' => $node->getParentId());
            }

            foreach($node->getChildren() as $child) {
                $this->nodeToArray($child, $storeId);
            }
        }

        /**
         * Load Tree Array
         *
         * @param bool $parentId
         * @param bool $storeId
         */
        protected function loadTree($parentId = false, $storeId = false) {

            if($parentId == false) {
                $parentId = 1;
            }

            if($this->treeCollection == '') {
                $this->loadTreeCollection();
            }

            $root = $this->treeCollection->getNodeById($parentId);

            if($root && $root->getId() == 1) {
                $root->setName(Mage::helper('catalog')->__('Root'));
            }

            if($root != null) {
                if($storeId == false) {
                    $this->nodeToArray($root);
                } else {
                    $this->nodeToArray($root, $storeId);
                    krsort($this->categoryList[$storeId]);
                }
            }
        }

        /**
         * Get category list for product
         * Recursive function for getting children
         *
         * @param $productCategoryIds
         * @param $storeCategoryList
         * @param bool $levelFlag
         * @param bool $parentId
         *
         * @return string
         */
        protected function searchArray($productCategoryIds, $storeCategoryList, $levelFlag = false, $parentId = false) {
            $string = '';

            if($levelFlag == false) {
                reset($storeCategoryList);
                $levelFlag = key($storeCategoryList);
            }

            for($i = $levelFlag; $i > 1; $i--) {
                foreach($storeCategoryList[$i] as $id => $data) {
                    if(in_array($id, $productCategoryIds)) {
                        if($parentId != false) {
                            if($id == $parentId) {
                                if(($i - 1) != 0) {
                                    $this->completedBreadcrumbIds[$id] = $id;
                                    $string = $data['name'];
                                    $additionalString = $this->searchArray($productCategoryIds, $storeCategoryList, ($i - 1), $data['parent_id']);
                                    !empty($additionalString) ? $string = $additionalString . ' > ' . $string : '';
                                    return $string;
                                }
                            }
                        } else {
                            if(!in_array($id, $this->completedBreadcrumbIds)) {
                                $string = $data['name'];
                                $additionalString = $this->searchArray($productCategoryIds, $storeCategoryList, ($i - 1), $data['parent_id']);
                                !empty($additionalString) ? $string = $additionalString . ' > ' . $string : '';
                                $this->breadcrumb[] = $string;
                            }
                        }
                    }
                }
            }
            return $string;
        }

        /**
         * Get products partly from the catalog
         *
         * @param Mage_Catalog_Model_Product $product
         * @param $handle
         */
        public function getProductsAdvanced($product, $handle){

            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

            $helper = Mage::helper('windsorcircle_export');

            $productData_Adv = array();

            $stores = Mage::getStoreConfig('windsorcircle_export_options/messages/store');
            if (!empty($stores)) {
                $stores = explode(',', $stores);
            }

            foreach ($product->getStoreIds() as $_store) {

                if (!empty($stores) && !in_array($_store, $stores)) {
                    continue;
                }

                Mage::app()->setCurrentStore($_store);

                $outputIndividual = false;
                if ($product->isVisibleInSiteVisibility()) {
                    $outputIndividual = true;
                }

                if (empty($this->categoryList[$_store])) {
                    $root_id = Mage::app()->getStore()->getRootCategoryId();
                    $this->loadTree($root_id, $_store);
                }
                $this->breadcrumb = array();
                $this->completedBreadcrumbIds = array();

                $this->searchArray($product->getCategoryIds(), $this->categoryList[$_store]);

                // Image Array
                $images = $this->getImages($product);

                $parentIdRelation = $product->getParentSkuRelation();

                // Unset $product request path and url if previously set from getProductUrl function
                $product->unsRequestPath();
                $product->unsUrl();
                $url = $product->setStoreId($_store)->getProductUrl(false);
                $categoryList = '"' . implode('","', $this->breadcrumb) . '"';

                // If no custom attribute for brand then we will use default brand attribute
                $attribute = Mage::getStoreConfig('windsorcircle_export_options/messages/brand_attribute');
                if (empty($attribute)) {
                    $brandName = $this->getAttributeValue('brand', $product->getBrand());
                } else {
                    $brandName = $this->getAttributeValue($attribute, $product->getData($attribute));
                }

                $keyName = $product->getId() . ':' . $_store;

                if (empty($productData_Adv[$keyName])) {

                    $productData_Adv[$keyName] = array(
                        $product->getId(),
                        $_store,
                        ($product->getStatus() == 1 ? 'Y' : 'N'),
                        $product->getSku(),
                        (!empty($parentIdRelation) ? $parentIdRelation : ''),
                        $categoryList,
                        $helper->formatString($product->getName()),
                        $url,
                        array_shift($images),
                        $product->getPrice(),
                        $product->getSpecialPrice(),
                        $this->formatDates($product),
                        (!empty($brandName) ? $brandName : ''),
                        $this->getAvailability($product),
                        $product->getQty(),
                        $this->getShippingWeight($product),
                        $product->getTypeId(),
                        $product->getVisibility()
                    );

                    $customAttributes = Mage::helper('windsorcircle_export')->getCustomAttributes('product');

                    foreach ($customAttributes as $customAttribute) {
                        if ($product->getData($customAttribute) === null) {
                            array_push($productData_Adv[$keyName], '');
                            continue;
                        }
                        $data = $product->getResource()->getAttribute($customAttribute)->getFrontEnd()->getValue($product);
                        array_push($productData_Adv[$keyName], $helper->formatString($data));
                    }

                    // If product is available for purchase individually then output product without parent
                    if ($outputIndividual
                        && !empty($productData_Adv[$keyName])
                        && (!empty($parentIdRelation))
                    ) {
                        $productData_Adv[$keyName . ':individual'] = array_values($productData_Adv[$keyName]);
                        // Remove parent id or parent id relation from array for invidual product
                        $productData_Adv[$keyName . ':individual'][4] = '';
                        fputcsv($handle, $productData_Adv[$keyName . ':individual'], "\t");
                    }
                }

                fputcsv($handle, $productData_Adv[$keyName], "\t");
            }

            return;
        }

        /**
         * Format dates for specials in ISO 8601 format
         *
         * @param Mage_Catalog_Model_Product $product
         *
         * @return string FromDate/ToDate or if no ToDate then just returns FromDate
         */
        protected function formatDates(Mage_Catalog_Model_Product $product){
            $formatDate = array();

            if($product->getSpecialFromDate() != null){
                $formatDate[] = date_format(date_create($product->getSpecialFromDate()), 'c');
            }

            if($product->getSpecialToDate() != null){
                $formatDate[] = date_format(date_create($product->getSpecialToDate()), 'c');
            }

            if($formatDate == null){
                return '';
            } else {
                return implode('/', $formatDate);
            }
        }

        /**
         * Gets availability of product
         *
         * @param Mage_Catalog_Model_Product $product
         *
         * @return string in_stock|out of stock|available for order
         */
        protected function getAvailability(Mage_Catalog_Model_Product $product){
            if ($product->getData('use_config_manage_stock') == 1) {
                if (Mage::getStoreConfig(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_ITEM . 'manage_stock') == 0) {
                    return 'in stock';
                }
            } else if ($product->getData('manage_stock') == 0) {
                return 'in stock';
            }

            if ($product->getData('is_in_stock') == 1) {
                return 'in stock';
            } else if ($product->getData('backorders') == 0) {
                return 'out of stock';
            }
            return 'available for order';
        }

        /**
         * getImages URL
         *
         * @param Mage_Catalog_Model_Product $product
         *
         * @return array ImageUrls
         */
        protected function getImages(Mage_Catalog_Model_Product $product){
            $allImages = array();
            $imageType = Mage::getStoreConfig('windsorcircle_export_options/messages/image_type');
            if(!empty($imageType) && $imageType == '2') {
                $productImage = $product->getSmallImage();
            } else {
                $productImage = $product->getImage();
            }
            if(!empty($productImage) && $productImage != 'no_selection') {
                $allImages[] = Mage::getModel('catalog/product_media_config')
                                ->getMediaUrl($productImage);
            }
            return $allImages;
        }

        /**
         * Shipping Weight of product
         * @param Mage_Catalog_Model_Product $product
         * @return string Product Weight
         */
        protected function getShippingWeight(Mage_Catalog_Model_Product $product){
            $weight = (float) $product->getWeight();
            $weight = !empty($weight) ? $weight . ' lb' : '';

            return $weight;
        }

        /**
         * Get Attribute Option Text
         *
         * @param string $attribute
         * @param int $option
         *
         * @return string
         */
        protected function getAttributeValue($attribute, $option) {
            if(empty($attribute) || empty($option)) {
                return;
            }

            if(isset($this->attributeValues[$attribute][$option])) {
                return $this->attributeValues[$attribute][$option];
            }

            $attribute_model	= Mage::getModel('eav/entity_attribute');
            $attribute_table	= Mage::getModel('eav/entity_attribute_source_table');

            $attribute_code		= $attribute_model->getIdByCode('catalog_product', $attribute);
            $loadedAttribute	= $attribute_model->load($attribute_code);

            $attribute_table->setAttribute($loadedAttribute);

            $optionValue = $attribute_table->getOptionText($option);

            if(!empty($optionValue)) {
                if (is_array($optionValue)) {
                    $optionValue = implode(',', $optionValue);
                }

                $this->attributeValues[$attribute][$option] = $optionValue;
                return $optionValue;
            } else {
                return $option;
            }
        }

        /**
         * Get taxes of product (e.g. Country:State:Value:TaxShipping => US:IL:0.0825:y)
         * @param Mage_Catalog_Model_Product $product
         * @return string TaxRates of current product
         */
        protected function getTax(Mage_Catalog_Model_Product $product){
            $taxRate = array();

            if(empty($this->taxCollection[$product->getTaxClassId()])){
                $taxCollection = Mage::getModel('tax/calculation')->getCollection()
                    ->addFieldToFilter('product_tax_class_id', array('eq' => $product->getTaxClassId()));

                foreach($taxCollection as $taxes){
                    $tax = Mage::getSingleton('tax/calculation')->getRatesByCustomerAndProductTaxClasses($taxes['customer_tax_class_id'], $product->getTaxClassId());
                    foreach($tax as $taxRule){
                        $this->taxCollection[$product->getTaxClassId()][] = $taxRule['country'] . ':' . $taxRule['state'] . ':' . $taxRule['value'] . ':y';
                        // Use data as array key so there is not duplicate data
                        $taxRate[$taxRule['country'].$taxRule['state'].$taxRule['postcode'].$taxRule['product_class']] = $taxRule['country'] . ':' . $taxRule['state'] . ':' . $taxRule['value'] . ':y';
                    }
                }
            } else {
                foreach($this->taxCollection[$product->getTaxClassId()] as $taxRule){
                    $taxRate[] = $taxRule;
                }
            }

            return implode(',', $taxRate);
        }

        /**
         * Shipping country of product
         * @param Mage_Catalog_Model_Product $product
         * @return string All 2 Letter Country Codes that product can sell too
         */
        protected function getShippingCountry(Mage_Catalog_Model_Product $product){
            $countryNames = array();

            $storeIds = $product->getStoreIds();

            foreach($storeIds as $id){
                if(array_key_exists($id, $this->allowedCountries)){
                    foreach($this->allowedCountries[$id] as $country){
                        $countryNames[$country['value']] = $country['value'];
                    }
                } else {
                    Mage::app()->setCurrentStore($id);

                    $this->allowedCountries[$id] = Mage::getResourceModel('directory/country_collection')->loadByStore()->toOptionArray(false);

                    foreach($this->allowedCountries[$id] as $country){
                        $countryNames[$country['value']] = $country['value'];
                    }
                }
            }

            // Set store back to admin
            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

            ksort($countryNames);
            return implode(',', $countryNames);
        }
    }
