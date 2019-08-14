<?php

class Windsorcircle_Export_Model_Observer
{
    /**
     * Variable for saving product ids updated
     *
     * @var bool
     */
    protected $updated = array();

    /**
     * Saving product type related data
     *
     * @param $observer
     */
    public function afterProductSave($observer)
    {
        $event = $observer->getEvent();
        $product = $event->getProduct();
        $oldProd = Mage::getModel('catalog/product')->load($product->getId())->getOrigData();
        $productId = $product->getId();
        if (isset($oldProd['entity_id']) && $oldProd['entity_id'] !== NULL) {
            $updatedFile = @fopen($this->getMediaUpdateFile(), 'a');
            if (!$updatedFile) {
                Mage::log('Could not open update file to save id - ' . $productId, null, 'windsorcircle.log');
            } else {
                fputs($updatedFile, "!" . $productId . "\n");
                fclose($updatedFile);
                $this->updated[] = $productId;
            }
        }
    }

    /**
     * Init indexing process after product delete commit
     *
     * @param $observer
     */
    public function afterProductDelete($observer)
    {
        $event = $observer->getEvent();
        $product = $event->getProduct();
        $updatedFile = @fopen($this->getMediaUpdateFile(), 'a');
        $productId = $product->getId();
        if (!$updatedFile) {
            Mage::log('Could not open update file to save id - ' . $productId, null, 'windsorcircle.log');
        } else {
            fputs($updatedFile, "-" . $productId . "\n");
            fclose($updatedFile);
            $this->updated[] = $productId;
        }
    }

    /**
     * After product inventory update save product ids to updated.txt file
     *
     * @param $observer
     */
    public function afterProductInventoryUpdate($observer)
    {
        $event = $observer->getEvent();
        $product = $event->getItem();
        $productId = $product->getProductId();

        if (in_array($productId, $this->updated)) {
            return;
        }

        $updatedFile = @fopen($this->getMediaUpdateFile(), 'a');
        if (!$updatedFile) {
            Mage::log('Could not open update file to save id - ' . $productId, null, 'windsorcircle.log');
        } else {
            fputs($updatedFile, '!' . $productId . "\n");
            fclose($updatedFile);
            $this->updated[] = $productId;
        }
    }

    /**
     * Get Media Update file 'updated.txt'
     *
     * @return resource
     */
    public function getMediaUpdateFile()
    {
        $updatedProdFolder = Mage::getBaseDir('media') . DS . 'windsorcircle_export';
        $updatedProd = $updatedProdFolder . DS . 'updated.txt';
        if (!file_exists($updatedProdFolder)) {
            mkdir($updatedProdFolder);
        }
        return $updatedProd;
    }
}
