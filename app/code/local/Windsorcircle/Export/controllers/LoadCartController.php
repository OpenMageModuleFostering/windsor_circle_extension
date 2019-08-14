<?php
/**
 * Abandoned Shipping Cart Reload Controller
 *
 * @category  WindsorCircle
 * @package   Windsorcircle_Export
 * @author    Mark Hodge <mhodge@lyonscg.com>
 * @copyright Copyright (c) 2016 WindsorCircle (www.windsorcircle.com)
 */

require_once Mage::getModuleDir('controllers', 'Mage_Checkout') . DS . 'CartController.php';

class Windsorcircle_Export_LoadCartController extends Mage_Checkout_CartController
{
    public function indexAction()
    {
        $params = $this->getRequest()->getQuery();
        $cart = 'checkout/cart';

        // If logged in then redirect to the cart page
        if (Mage::helper('customer')->isLoggedIn()) {
            $this->getResponse()->setRedirect(Mage::getUrl($cart, array('_query' => $params)), 301);
            return;
        }

        $cartData = Mage::getModel('windsorcircle_export/openssl')
            ->decrypt($params['cid'], $params['iv']);

        // Remove encryption data from params url
        unset($params['cid']);
        unset($params['iv']);

        if ($cartData) {
            list($email, $quoteId) = explode(':', $cartData);
        }

        if (!empty($email) && !empty($quoteId)) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);

            if (!$quote->getId()) {
                $customerSession = Mage::getSingleton('customer/session');
                $customerSession->setAfterAuthUrl(Mage::getUrl($cart, array('_query' => $params)));
                $customerSession->addNotice('We are unable to access your shopping cart. Please login to continue.');
                $this->_redirect('customer/account');
            } else if (!$quote->getIsActive()) {
                $this->_getSession()->addNotice('Your shopping cart is no longer available.');
                $this->_redirect($cart, array('_query' => $params));
            } else if ($quote->getCustomerEmail() == $email) {
                if (!$quote->getCustomerId()) {
                    $this->_getSession()->setQuoteId($quote->getId());
                    $this->getResponse()->setRedirect(Mage::getUrl($cart, array('_query' => $params)), 301);
                } else {
                    $customer = Mage::getModel('customer/customer')->load($quote->getCustomerId());
                    if ($customer->getId()) {
                        Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
                    }
                    $this->getResponse()->setRedirect(Mage::getUrl($cart, array('_query' => $params)), 301);
                }
            } else {
                $this->_getSession()->addNotice('Your shopping cart is no longer available.');
                $this->_redirect($cart, array('_query' => $params));
            }
        } else {
            $customerSession = Mage::getSingleton('customer/session');
            $customerSession->setAfterAuthUrl(Mage::getUrl($cart, array('_query' => $params)));
            $customerSession->addNotice('We are unable to access your shopping cart. Please login to continue.');
            $this->_redirect('customer/account');
        }
    }
}
