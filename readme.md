
***anonymize.php*** is a **Magento 1** shell (command line) tool used to anonymize customer and order data for GDPR and CCPR compliance.

## The problem

With the advent of new data privacy laws, eCommerce customers have the right to be **forgotten**.  This means that their personally identifiable information must be deleted or anonymized from Magento's database on request.

Magento's built-in solution is the ability for an administrator to delete a customer from the database within the customer editing screen. However, this does not delete any orders, quotes, or other sales-related data, so the PII continues to exist.

Additionally, there is also no method in Magento to remove or anonymize guest orders.

## A Solution

This tool provides the anonymization of customer data.  It does not delete Customer or Order data, because that would play havoc with record keeping. Instead, it changes the customer's personal identification information to obscured data. 

For example, using this tool, a customer's name will be changed to "*anon 123456*" where *123456* is the customer record number.

## Method

To define what fields to anonymize in the Customer and Order data,  items are added to the $_anonymizeMapping table at the top of the code. This array contains the field name from the Magento object and an action to take for that field.

The current anonymizing actions are -

-   remove - empty the data
-   const - replace with the constant given
-   email - replace with "recordid@nowhere.anon"
-   name - replace with "anon recordid"
-   anonid - replace with "recordid"
-   street - replace with '**** ******* ********' (could replace with const)

Currently, the anonymized fields are -

 1. order, quote 
    * 'customer_firstname'   => ['const', 'anon'],
    * 'customer_middlename'  => ['remove'], 
    * 'customer_lastname'    =>['lastname'], 
    * 'customer_email'       => ['email'], 
    * 'remote_ip'   => ['remove'], 
    * 'customer_dob'         => ['remove'], 
    * 'customer_gender'      => ['remove'],
 2. order address, quote address 
    * 'firstname'            => ['const',   'anon'], 
    * 'middlename'           => ['remove'], 
    * 'lastname'            => ['lastname'], 
    * 'company'              => ['remove'], 
    * 'vat_id'               => ['remove'], 
    * 'street'               => ['street'], 
    * 'city'                 => ['const', 'Anytown'], 
    * 'email'                => ['email'], 
    * 'telephone'            => ['const', '********'],
 3. order grid, invoice grid, shipment grid, credit memo grid
    * 'shipping_name'        => ['name'], 
    * 'billing_name'         => ['name'],
 4. order payment, invoice payment 
    * 'cc_owner'             => ['name'],
    * 'cc_last4'             => ['const', '****'], 
    * 'cc_number_enc'       => ['remove'], 
    * 'cc_exp_month'         => ['const', '\*\*'], 
    * 'cc_exp_year'          => ['const', '\*\*'], 
    * 'cybersource_token'    => ['remove'],
 
 5. customer 
    * 'password_hash'        => ['remove'],

## Installation

To install, checkout anonymize.php and place it on the shell directory.

  
## Using
To run, start a command line (ssh, terminal, whatever) session and navigate to Magneto's

shell directory and run the command -

    php anonymize.php <options>   *website <website code or id>   *email <email address>

  

## Command Usage

  

    Usage: php -f anonymize.php.php   * [options]   *website <website_code>    *email <email_address>
    
      *email <email_address> 	Customer email address (required)
    
      *website <website_code> 	Magento website code or id (required)
    
    customer 			Anonymizes customer data, saved addresses and order information.
    orders 				Anonymizes customer order/quote item    
    wishlist 			Removes customer wishlists  
    alerts 				Removes customer product stock & price alerts  
    misc 				Removes customer wishlists, product stock & price alerts  
    all 				Do all Checks/Removals|Anonymizations (default)
    
    
    force 				Run without confirming
    quiet 				Run without output
    test 				Make no changes to data
    
    debug 				Enables additional messaging and output of log file (anon.log)
    
    help 				This help

All data information based on docs provided by Magento - https://devdocs.magento.com/compliance/privacy/pi-data-reference-m1.html
