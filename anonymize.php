<?php

require_once 'abstract.php';

class CNA_Customer_Anon extends Mage_Shell_Abstract
{
    const ACTION_HELP = 0;
    const ACTION_CUSTOMER_ONLY = 1;
    const ACTION_ORDERS_ONLY = 2;
    const ACTION_WISHLISTS_ONLY = 3;
    const ACTION_ALERTS_ONLY = 4;
    const ACTION_MISC_ONLY = 5;
    const ACTION_ALL = 99;


    protected $_customer = false;
    protected $_anonId;

    protected $_test = true;
    protected $_debug = true;
    protected $_force = false;
    protected $_isEnterprise;
    protected $_quiet = false;
    protected $_action;

    protected $_customerEmail;
    protected $_websiteId;
    protected $_websiteName;
    protected $_storeId;

    // what data fields to anonymize and how add more fields as needed
    protected $_anonymizeMapping
        = [
            //order, quote
            'customer_first_name'  => ['const', 'anon'],
            'customer_middle_name' => ['remove'],
            'customer_last_name'   => ['lastname'],
            'customer_firstname'   => ['const', 'anon'],
            'customer_middlename'  => ['remove'],
            'customer_lastname'    => ['anonid'],
            'customer_email'       => ['email'],
            'remote_ip'            => ['remove'],
            'customer_dob'         => ['remove'],
            'customer_gender'      => ['remove'],

            //order address, quote address
            'firstname'            => ['const', 'anon'],
            'middlename'           => ['remove'],
            'lastname'             => ['anonid'],
            'company'              => ['remove'],
            'vat_id'               => ['remove'],
            'street'               => ['street'],
            'city'                 => ['const', 'Anytown'],
            'email'                => ['email'],
            'telephone'            => ['const', '********'],

            //order grid, invoice grid, shipment grid, credit memo grid
            'shipping_name'        => ['name'],
            'billing_name'         => ['name'],

            //order payment, invoice payment
            'cc_owner'             => ['name'],
            'cc_last4'             => ['const', '****'],
            'cc_number_enc'        => ['remove'],
            'cc_exp_month'         => ['const', '**'],
            'cc_exp_year'          => ['const', '**'],
            'cybersource_token'    => ['remove'],

            //customer
            'password_hash'        => ['remove'],
        ];

    function __construct()
    {
        parent::__construct();
        $this->_anonId = time();
    }

    /***
     * Iterate through all the fields and look for items to anonymize based on mappings above
     *
     * @param $object - any varien data object
     */
    protected function _anonymize(&$object)
    {
        foreach ($object->getData() as $key => $value) {
            if (key_exists($key, $this->_anonymizeMapping)) {
                $object[$key] = $this->_anonymizeField($this->_anonymizeMapping[$key]);
            }
        }
    }

    /***
     * Anonymize functions to provide obscuring methods.
     *
     * @param $method
     *
     * @return bool|int|string
     */
    protected function _anonymizeField($method)
    {
        $action = $method[0];
        if (sizeof($method) == 2) {
            $data = $method[1];
        } else {
            $data = '****';
        }

        $return = false;
        switch ($action) {
            case 'const':
                $return = $data;
                break;
            case 'remove':
                $return = '';
                break;
            case 'name':
                $return = 'anon ' . $this->_anonId;
                break;
            case 'anonid':
                $return = $this->_anonId;
                break;
            case 'street':
                $return = '**** *******  ********';
                break;
            case 'email':
                $return = $this->_anonId . '@nowhere.anon'; //emails sometimes must be unique
                break;
        }
        return $return;
    }

    /***
     * Get the customer from the database and sets $this->_customer
     * No customer is required for order anonymization, so always return false
     *
     * @return bool|Mage_Customer_Model_Customer
     */
    private function _getCustomer()
    {
        //orders anonymizing does not use a magento customer record so don't just ignore
        if ($this->_action != $this::ACTION_ORDERS_ONLY) {
            if (!$this->_customer) {
                $customer = Mage::getModel('customer/customer')
                    ->setWebsiteId($this->_websiteId)
                    ->loadByEmail($this->_customerEmail);

                if (!$customer->getId()) {
                    $err = "Error - no customer with email address {$this->_customerEmail} in database\n";
                    if ($this->_debug) {
                        $this->_log($err);
                    }
                    $this->_alert($err, true);
                    return false;
                }
                // only fetch this once
                $this->_alert("\nCustomer with email address {$this->_customerEmail} in database");
                $this->_customer = $customer;
            }
        }

        return $this->_customer;
    }


    private function _log($msg)
    {
        Mage::log($msg, null, 'anon.log', true);
    }

    /***
     * @param string $message
     * @param bool   $force
     */
    private function _alert(string $message, $force = false)
    {
        if ($this->_quiet && !$force) {
            return;
        }
        echo $message . "\n";
    }

    /***
     * if debugging is on, echo the thing to the user and log
     * trys to handle most magento-y things
     *
     * @param $thing
     */
    private function _debugEcho($thing)
    {
        if ($this->_debug) {
            if ($thing instanceof Varien_Object) {
                echo print_r($thing->getData(), true);
                $this->_log(print_r($thing->getData(), true));
            } elseif (is_string($thing) || is_numeric($thing)) {
                echo $thing;
                $this->_log($thing);
            } else {
                echo print_r($thing, true);
                $this->_log(print_r($thing, true));
            }
        }
    }

    /***
     * Get all the runtime command elements and setup processing the run
     *
     * @return bool
     */
    private function _getConfig()
    {
        $this->_test = $this->getArg('test');
        $this->_debug = $this->getArg('debug');
        $this->_force = $this->getArg('force');
        $this->_quiet = $this->getArg('quiet');
        $this->_isEnterprise = Mage::getEdition() == Mage::EDITION_ENTERPRISE;

        if ($this->getArg('all')) {
            $this->_action = $this::ACTION_ALL;
            $this->_alert("\nWill anonymize customer, order data and remove product stock and price alerts\n");
        } elseif ($this->getArg('customer')) {
            $this->_action = $this::ACTION_CUSTOMER_ONLY;
            $this->_alert("\nWill anonymize customer and orders\n");
        } elseif ($this->getArg('orders') || $this->getArg('order')) {
            $this->_action = $this::ACTION_ORDERS_ONLY;
            $this->_alert("\nWill anonymize orders only (guest or customer)\n");
        } elseif ($this->getArg('wishlist')) {
            $this->_action = $this::ACTION_WISHLISTS_ONLY;
            $this->_alert("\nWill remove wishlists\n");
        } elseif ($this->getArg('alerts')) {
            $this->_action = $this::ACTION_ALERTS_ONLY;
            $this->_alert("\nWill remove product stock and price alerts\n");
        } elseif ($this->getArg('misc')) {
            $this->_action = $this::ACTION_MISC_ONLY;
            $this->_alert("\nWill remove wishlists, product stock and price alerts\n");
        } else {
            // default to all
            $this->_action = $this::ACTION_HELP;
            return true;
        }

        if ($websiteCode = $this->getArg('website')) {
            try {
                $this->_websiteId = Mage::app()->getWebsite($websiteCode)->getId();
                $this->_websiteName = Mage::app()->getWebsite($websiteCode)->getName();
                $this->_storeId = Mage::app()->getWebsite($websiteCode)->getDefaultStore()->getId();
            } catch (Exception $e) {
                $err = "Error - website $websiteCode does not exist";
                if ($this->_debug) {
                    $this->_log($err);
                }
                $this->_alert($err);
                return false;
            }
        } else {
            $err = "\n\nError - Website code is required, see help.\n";
            $this->_alert($err);
            return false;
        }

        if ($this->_customerEmail = $this->getArg('email')) {
            if (!filter_var($this->_customerEmail, FILTER_VALIDATE_EMAIL)) {
                $err = "Error - '{$this->_customerEmail}' is not a valid email address\n";
                if ($this->_debug) {
                    $this->_log($err);
                }
                $this->_alert($err);
                return false;
            }
            $this->_alert("Processing {$this->_customerEmail}\n");
        } else {
            $err = "\n\nError - Email address is required, see help.\n";
            $this->_alert($err);
            return false;
        }
        return true;
    }

    /***
     * Anonymize the customer and customer address data
     *
     * @return bool
     */
    private function _anonymizeCustomerData()
    {
        $this->_alert("Process customer data for {$this->_customerEmail}");

        if (!$customer = $this->_getCustomer()) {
            //cant find customer
            return false;
        }
        //used to as a common anon string
        $this->_anonId = $customer->getId();

        $saveCust = Mage::getModel('core/resource_transaction'); // setup transaction

        $this->_anonymize($customer);
        $saveCust->addObject($customer);
        $this->_debugEcho($customer);

        $count = 0;
        foreach ($customer->getAddressesCollection() as $address) {
            $this->_anonymize($address);
            $saveCust->addObject($address);
            $this->_debugEcho($address);
            $count++;
        }
        $this->_alert("Anonymized $count addresses");

        if (!$this->_test) {
            try {
                $saveCust->save();
            } catch (EXCEPTION $e) {
                $this->_alert("An error occurred saving customer data, see log for details");
                $this->_log("An error occurred saving customer data for {$this->_customerEmail}");
                $this->_log($e->getMessage());
                exit(0);
            }
        } else {
            $this->_alert("TEST MODE - no data changed");
        }

        $this->_alert("Customer data for {$this->_customerEmail} anonymized\n");

        return true;
    }

    /***
     * Find all quotes and orders for a given email address and anonymize the PII
     *      quote, quote address, quote payment,
     *      order, order address, order payment, order grid,
     *      invoice (nothing), invoice grid,
     *      shipment (nothing), shipment grid,
     *      credit_memo (nothing), credit_memo_grid
     *      and archive records (TBD)
     */
    private function _anonymizeOrderData()
    {
        $this->_alert("Process order data for {$this->_customerEmail}");
        $saveOrder = Mage::getModel('core/resource_transaction'); // setup transaction

        if ($customer = $this->_getCustomer()) {
            // if removing for a customer, then get customer id as key
            $this->_anonId = $customer->getId();
        }

        //quotes
        $quoteCollection = Mage::getModel('sales/quote')
            ->getCollection()
            ->addFieldToFilter('store_id', $this->_storeId)
            ->addFieldToFilter('customer_email', $this->_customerEmail);

        /*** @var Mage_Sales_Model_Quote $quote ** */
        $quotecount = 0;
        foreach ($quoteCollection as $quote) {
            if (!$customer) {
                //use quote ID as a common anon string
                $this->_anonId = $quote->getId();
            }
            $this->_alert("Found quote id {$quote->getId()}");
            $quotecount++;

            $this->_anonymize($quote);
            $saveOrder->addObject($quote);
            $this->_debugEcho($quote);

            $billingAddress = $quote->getBillingAddress();
            $this->_anonymize($billingAddress);
            $saveOrder->addObject($billingAddress);
            $this->_debugEcho($billingAddress);

            $shippingAddress = $quote->getShippingAddress();
            $this->_anonymize($shippingAddress);
            $saveOrder->addObject($shippingAddress);
            $this->_debugEcho($billingAddress);

            $payment = $quote->getPayment();
            $this->_anonymize($payment);
            $saveOrder->addObject($payment);
            $this->_debugEcho($payment);

        }
        $this->_alert("Anonymized $quotecount order quotes");

        // orders
        $orderCollection = Mage::getModel('sales/order')
            ->getCollection()
            ->addFieldToFilter('store_id', $this->_storeId)
            ->addFieldToFilter('customer_email', $this->_customerEmail);

        /*** @var Mage_Sales_Model_Order $order ** */
        $ordercount = 0;
        foreach ($orderCollection as $order) {
            if (!$customer) {
                //used to as a common anon string
                $this->_anonId = $order->getId();
            }

            $this->_alert("Found order {$order->getIncrementId()}");
            $ordercount++;
            $this->_anonymize($order);
            $saveOrder->addObject($order);
            $this->_debugEcho($order);

            $billingAddress = $order->getBillingAddress();
            $this->_anonymize($billingAddress);
            $saveOrder->addObject($billingAddress);
            $this->_debugEcho($billingAddress);

            $shippingAddress = $order->getShippingAddress();
            $this->_anonymize($shippingAddress);
            $saveOrder->addObject($shippingAddress);
            $this->_debugEcho($billingAddress);

            $count = 0;
            foreach ($payments = $order->getAllPayments() as $payment) {
                $this->_anonymize($payment);
                $saveOrder->addObject($payment);
                $this->_debugEcho($payment);
                $count++;
            }
            $this->_alert("Anonymized $count payment records");

            $gridCollection = Mage::getResourceModel('sales/order_grid_collection')
                ->addFieldToFilter('entity_id', $order->getId());

            foreach ($gridCollection as $gridOrder) {
                $this->_anonymize($gridOrder);
                $saveOrder->addObject($gridOrder);
                $this->_debugEcho($gridOrder);
            }

            //invoices
            foreach ($order->getInvoiceCollection() as $invoice) {
                $this->_debugEcho($invoice);
                // no need to update the invoice

                //update the invoice grid records
                $gridCollection = Mage::getResourceModel('sales/order_invoice_grid_collection')
                    ->addFieldToFilter('entity_id', $invoice->getId());

                $count = 0;
                foreach ($gridCollection as $gridInvoice) {
                    $this->_anonymize($gridInvoice);
                    $saveOrder->addObject($gridInvoice);
                    $this->_debugEcho($gridInvoice);
                    $count++;
                }
                $this->_alert("Anonymized $count invoices");

            }

            //shipments
            foreach ($order->getShipmentsCollection() as $shipment) {
                $this->_debugEcho($shipment);
                // no need to update the shipment

                //update the shipment grid records
                $gridCollection = Mage::getResourceModel('sales/order_shipment_grid_collection')
                    ->addFieldToFilter('entity_id', $shipment->getId());

                $count = 0;
                foreach ($gridCollection as $gridShipment) {
                    $this->_anonymize($gridShipment);
                    $saveOrder->addObject($gridShipment);
                    $this->_debugEcho($gridShipment);
                    $count++;
                }
                $this->_alert("Anonymized $count shipments");

            }

            //credit memos
            foreach ($order->getCreditmemosCollection() as $creditMemo) {
                $this->_debugEcho($creditMemo);
                // no need to update the shipment

                //update the shipment grid records
                $gridCollection = Mage::getResourceModel('sales/order_creditmemo_grid_collection')
                    ->addFieldToFilter('entity_id', $creditMemo->getId());
                $count = 0;
                foreach ($gridCollection as $gridCM) {
                    $this->_anonymize($gridCM);
                    $saveOrder->addObject($gridCM);
                    $this->_debugEcho($gridCM);
                    $count++;
                }
                $this->_alert("Anonymized $count credit memos");
            }

            /*
            if ($this->_isEnterprise) { //then boldly go!
                //TBD find out how to remove/update Enterprise Archive records
                if($archive=Mage::getModel('enterprise_salesarchive/archive')->load($order->getId())){
                   echo print_r($archive->getData(),true);
                }
            }
            */
        }
        $this->_alert("Anonymized $ordercount orders");

        if (!$this->_test) {
            try {
                $saveOrder->save();
            } catch (EXCEPTION $e) {
                $this->_alert("An error occurred saving order data, see log for details");
                $this->_log("An error occurred saving order data for customer {$this->_customerEmail}");
                $this->_log($e->getMessage());
                exit(0);
            }
        } else {
            $this->_alert("TEST MODE - no data changed");
        }

        $this->_alert("Order data for {$this->_customerEmail} anonymized \n");
        return;
    }

    /***
     * Cover to collect misc items together
     */
    private function _removeMiscItems()
    {
        $this->_removeAlerts();
        $this->_removeWishlists();
    }

    /***
     * Remove any product stock or price alerts
     */
    private function _removeAlerts()
    {
        $this->_alert("\nProcess alert data for {$this->_customerEmail}");
        if (!$customer = $this->_getCustomer()) {
            //cant find customer
            return;
        }
        //used to as a common anon string
        $this->_anonId = $customer->getId();

        $removeAll = Mage::getModel('core/resource_transaction'); // setup transaction
        $count = 0;

        $stockAlerts = Mage::getModel('productalert/stock')
            ->getCollection()
            ->addFieldToFilter('customer_id', $customer->getId());
        foreach ($stockAlerts as $stockAlert) {
            $removeAll->addObject($stockAlert);
            $this->_debugEcho("remove stock alert {$stockAlert->getId()}");
            $count++;
        }

        $priceAlerts = Mage::getModel('productalert/price')
            ->getCollection()
            ->addFieldToFilter('customer_id', $customer->getId());
        foreach ($priceAlerts as $priceAlert) {
            $removeAll->addObject($priceAlert);
            $this->_debugEcho("remove price alert {$priceAlert->getId()}");
            $count++;
        }

        if (!$this->_test) {
            try {
                $removeAll->delete();
            } catch (EXCEPTION $e) {
                $this->_alert("An error occurred removing customer alert data, see log for details");
                $this->_log("An error occurred removing customer alert data for {$this->_customerEmail}");
                $this->_log($e->getMessage());
                exit(0);
            }
        } else {
            $this->_alert("TEST MODE - no data changed");
        }

        $this->_alert("Removed $count product stock & price alerts");
        $this->_alert("Product stock & price alerts {$this->_customerEmail} cleared\n");

    }

    /***
     * remove wishlists
     */
    private function _removeWishlists()
    {
        $this->_alert("\nProcess wishlist data for {$this->_customerEmail}");
        if (!$customer = $this->_getCustomer()) {
            //cant find customer
            return;
        }
        $this->_anonId = $customer->getId();

        $removeAll = Mage::getModel('core/resource_transaction'); // setup transaction
        $count = false;

        $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customer->getId());
        if ($wishlist->getId()) {
            $removeAll->addObject($wishlist);
            $this->_debugEcho("remove wishlist {$wishlist->getId()}\n");
            $count = true;
        }

        if (!$this->_test) {
            try {
                $removeAll->delete();
            } catch (EXCEPTION $e) {
                $this->_alert("An error occurred removing customer wishlist data, see log for details");
                $this->_log("An error occurred removing customer wishlist data for {$this->_customerEmail}");
                $this->_log($e->getMessage());
                exit(0);
            }
        } else {
            $this->_alert("TEST MODE - no data changed");
        }

        if ($count) {
            $this->_alert("Removed wishlist for {$this->_customerEmail}");
        } else {
            $this->_alert("No wishlist for for {$this->_customerEmail}");
        }
        $this->_alert("Whislist data for {$this->_customerEmail} cleared\n");

    }

    /***
     * main run function 
     */
    public function run()
    {
        if (!$this->_getConfig()) {
            echo $this->usageHelp();
            return;
        }

        if ($this->_action == $this::ACTION_HELP) {
            echo $this->usageHelp();
            return;
        } 

        //verify that the user wants to do this thing
        if (!$this->_force) {
            $line = readline("Permanently alter data for {$this->_customerEmail} on website {$this->_websiteName}? (y/n) ");
            if ($line <> "y") {
                echo "Quitting without changes.\n";
                return;
            }
        }

        if ($this->_action == $this::ACTION_ALL
            || $this->_action == $this::ACTION_CUSTOMER_ONLY
        ) {
            $this->_anonymizeCustomerData();
        }

        if ($this->_action == $this::ACTION_ALL
            || $this->_action == $this::ACTION_ORDERS_ONLY
            || $this->_action == $this::ACTION_CUSTOMER_ONLY
        ) {
            $this->_anonymizeOrderData();
        }

        if ($this->_action == $this::ACTION_ALL
            || $this->_action == $this::ACTION_MISC_ONLY
            || $this->_action == $this::ACTION_CUSTOMER_ONLY
        ) {
            $this->_removeMiscItems();
        }

        if ($this->_action == $this::ACTION_WISHLISTS_ONLY) {
            $this->_removeWishlists();
        }

        if ($this->_action == $this::ACTION_ALERTS_ONLY) {
            $this->_removeAlerts();
        }

        $this->_alert("\nProcessing completed for email address {$this->_customerEmail} on website {$this->_websiteName} \n\n");

    }
    
    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE

Usage:  php -f anonymize.php.php -- [options]

  --email <email_address>       Customer email address (required)
  --website <website_code>      Magento website code or id (required)
                        
  customer                      Anonymizes customer data, saved addresses and order information.
  orders                        Anonymizes customer order/quote items
  wishlist                      Removes customer wishlists
  alerts                        Removes customer product stock & price alerts
  misc                          Removes customer wishlists, product stock & price alerts
  all                           Do all Checks/Removals|Anonymizations  (default)

  force                         Run without confirming
  quiet                         Run without output

  test                          Make no changes to data
  debug                         Enables additional messaging and output of log file (anon.log)

  help                          This help
  
  All information based on - https://devdocs.magento.com/compliance/privacy/pi-data-reference-m1.html

USAGE;
    }
}

$shell = new CNA_Customer_Anon();
$shell->run();
