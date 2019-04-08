<?php
/**
* payline class for 3-step transaction
* @on: 05.29.2015
*/

define("APPROVED", 1);
define("DECLINED", 2);
define("ERROR", 3);

class Payline{

	// API Setup parameters
	var $gatewayUrl3step = "https://secure.paylinedatagateway.com/api/v2/three-step";
	var $gatewayUrlTransact = "https://secure.paylinedatagateway.com/api/transact.php";
	var $gatewayUrlQuery = "https://secure.paylinedatagateway.com/api/query.php";

	var $APIKey = "";
	var $tokenId = "";			// $_GET['token-id']
	var $vaultId = "";

	var $applicationSource = "";
	var $siteName = "";

	var $httpdReferrer = ""; 	// $_SERVER['HTTP_REFERER']
	var $remoteAddr = "";		// $_SERVER["REMOTE_ADDR"]

	var $userLogin = "";
	var $userPassword = "";

	var $transactionType = "";	// can be sale/refund/void/add-customer/update-customer
	var $transactionId = 0;
	var $currency;

	var $order = array();
	var $billing = array();

	var $result; // result of the transaction

	var $logger;

	function __construct(){

		$this->logger = new BaseLogger();

  	}

	function setLogin($username, $password) {
		$this->userLogin = $username;
		$this->userPassword = $password;
	}

	function setOrder($data) {

		$this->order['order-id']		  	= $data->order_id;
		$this->order['order-description'] 	= $data->order_description;
		$this->order['amount']           	= $data->amount;
		$this->order['tax']              	= $data->tax;
		$this->order['shipping']         	= $data->shipping;
		$this->order['po-number']         	= $data->po_number;

		$this->order['cardholder']       	= $data->cardholder;
		$this->order['cc-number']        	= $data->cc_number;
		$this->order['cc-exp']			 	= $data->cc_exp;
		$this->order['cvv']         	 	= $data->cvv;

	}

	function setBilling($data) {

		$this->billing['first-name'] 	= $data->first_name;
		$this->billing['last-name']  	= $data->last_name;
		$this->billing['company']   	= $data->company;
		$this->billing['address1']  	= $data->address1;
		$this->billing['address2']  	= $data->address2;
		$this->billing['city']      	= $data->city;
		$this->billing['state']     	= $data->state;
		$this->billing['zip']       	= $data->zip;
		$this->billing['country']   	= $data->country;
		$this->billing['phone']     	= $data->phone;
		$this->billing['fax']       	= $data->fax;
		$this->billing['email']     	= $data->email;
		$this->billing['website']   	= $data->website;


	}

	function processRequest(){

		$this->logger->logInfo("Transaction: " . $this->transactionType);

		try{

			$trans_result = true;

			if($this->transactionType == 'sale' || $this->transactionType == 'credit'){
				// use 3-step to perform sale/credit
				if($this->doPaymentStep1()){
					$trans_result = $this->doPaymentStep3();
				}else{
					$trans_result = false;
				}

			}elseif($this->transactionType == 'refund'){
				// normal transaction
				$trans_result = $this->doRefund();
			}elseif($this->transactionType == 'void'){
				// normal transaction
				$trans_result = $this->doVoid();
			}

			return $trans_result;

		}catch(Exception $e){

			$this->result->result = ERROR;

			return false;

		}

	}

	/**
	* perform payment/addcredit - this function will perform step1 and step2 procedure
	*/
	function doPaymentStep1(){

		$this->logger->logInfo("Start doPaymentStep1...");

		$xmlRequest = new DOMDocument('1.0','UTF-8');

		$xmlRequest->formatOutput = true;
		$xmlSale = $xmlRequest->createElement($this->transactionType);

		// Amount, authentication, and Redirect-URL are typically the bare minimum.
		$this->_appendXmlNode($xmlRequest, $xmlSale,'api-key',$this->APIKey);
		$this->_appendXmlNode($xmlRequest, $xmlSale,'redirect-url',$this->httpdReferrer);

		$this->_appendXmlNode($xmlRequest, $xmlSale, 'ip-address', $this->remoteAddr);

		if(isset($this->applicationSource) && $this->applicationSource != ""){
			$this->_appendXmlNode($xmlRequest, $xmlSale,'merchant-defined-field-1',$this->applicationSource);
		}

		if(isset($this->applicationSource) && $this->applicationSource != ""){
			$this->_appendXmlNode($xmlRequest, $xmlSale,'merchant-defined-field-2',$this->siteName);
		}

		// Some additonal fields may have been previously decided by user
		if (isset($this->currency) && $this->currency != "") {
			$this->_appendXmlNode($xmlRequest, $xmlSale, 'currency', 'USD');
		}
		$this->_appendXmlNode($xmlRequest, $xmlSale, 'order-id', $this->order['order-id']);
		$this->_appendXmlNode($xmlRequest, $xmlSale, 'order-description', $this->order['order-description']);
		$this->_appendXmlNode($xmlRequest, $xmlSale, 'amount', $this->order['amount']);

		if (isset($this->order['tax']) && $this->order['tax'] != "") {
			$this->_appendXmlNode($xmlRequest, $xmlSale, 'tax-amount' , $this->order['tax']);
		}
		if (isset($this->order['shipping']) && $this->order['shipping'] != "") {
			$this->_appendXmlNode($xmlRequest, $xmlSale, 'shipping-amount' , $this->order['shipping']);
		}


		if (isset($this->order['po-number']) && $this->order['po-number'] != "") {
			$this->_appendXmlNode($xmlRequest, $xmlSale, 'po-number', $this->order['po-number']);
		}

		$this->logger->logInfo("this->APIKey: " . $this->APIKey);
		$this->logger->logInfo("this->httpdReferrer: " . $this->httpdReferrer);
		$this->logger->logInfo("this->remoteAddr: " . $this->remoteAddr);


		if($this->vaultId != "") {
			$this->_appendXmlNode($xmlRequest, $xmlSale, 'customer-vault-id' , $this->vaultId);
		}

		// Set the Billing and Shipping from what was collected on initial shopping cart form
		$xmlBillingAddress = $xmlRequest->createElement('billing');

		if (isset($this->billing['first-name']) && $this->billing['first-name'] != "") {
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'first-name', $this->billing['first-name']);
		}
		if (isset($this->billing['last-name']) && $this->billing['last-name'] != "") {
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'last-name', $this->billing['last-name']);
		}

		if(isset($this->billing['address1'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'address1', $this->billing['address1']);
		}

		if(isset($this->billing['city'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'city', $this->billing['city']);
		}

		if(isset($this->billing['state'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'state', $this->billing['state']);
		}

		if(isset($this->billing['zip'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'postal', $this->billing['zip']);
		}

		if (isset($this->billing['email']) && $this->billing['email'] != "") {
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'email', $this->billing['email']);
		}
		if (isset($this->billing['phone']) && $this->billing['phone'] != "") {
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'phone', $this->billing['phone']);
		}

		$xmlSale->appendChild($xmlBillingAddress);

		$xmlRequest->appendChild($xmlSale);


		$this->logger->logInfo("XML ----> \n" . $xmlRequest->saveXML());
		
		$this->logger->logInfo("Creating xml done");

		try{
			$this->logger->logInfo("Sending data doPaymentStep1...");
			// Process Step One: Submit all transaction details to the Payment Gateway except the customer's sensitive payment information.
			// The Payment Gateway will return a variable form-url.
			$data = $this->_sendXMLviaCurl($xmlRequest,$this->gatewayUrl3step);

			$this->logger->logInfo("Response: " . serialize($data));

		}catch(Exception $e1){
			$this->logger->logInfo("Error: " . $e1->getMessage());
			$this->result->text = $e1->getMessage();
			return false;
			// throw new Exception("Error: " . $e1->getMessage());
		}

		$formURL = "";

		// Parse Step One's XML response

		$gwResponse = @new SimpleXMLElement($data);

		if ((string)$gwResponse->result ==1 ) {
			// The form url for used in Step Two below
			$formURL = $gwResponse->{'form-url'};
		} else {
			$this->logger->logInfo(" Error : gwResponse->result " . $data);
			return false;
			// throw New Exception("Error received: " . $data);
		}

		$this->logger->logInfo("formURL: " . $formURL);

		// if vaultId is set, then no customer billing info is required
		if($this->vaultId == "") {
			$dataRequest = "";
			$dataRequest .= "billing-cc-number=" . urlencode($this->order['cc-number']) . "&";
			$dataRequest .= "billing-cc-exp=" . urlencode($this->order['cc-exp']) . "&";
			if (isset($this->order['cvv']) && $this->order['cvv'] != "") {
				$dataRequest .= "billing-cvv=" . urlencode($this->order['cvv']) . "&";
			}
		}

		$this->logger->logInfo("Sending data doPaymentStep2...");

		$this->tokenId = $this->_getTokenId($dataRequest,$formURL, $error);


		$this->logger->logInfo("TokenId: " . $this->tokenId);

		if ($this->tokenId != "") {

			return true;

		} else {

			$this->logger->logInfo(" Error : doPaymentStep2() " . $error);
			$this->result->text = $error;
			return false;
		}
	}

	function doPaymentStep3(){

		if($this->tokenId != ""){

			// Step Three: Once the browser has been redirected, we can obtain the token-id and complete
			// the transaction through another XML HTTPS POST including the token-id which abstracts the
			// sensitive payment information that was previously collected by the Payment Gateway.

			$xmlRequest = new DOMDocument('1.0','UTF-8');
			$xmlRequest->formatOutput = true;
			$xmlCompleteTransaction = $xmlRequest->createElement('complete-action');
			$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'api-key', $this->APIKey);
			$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'token-id', $this->tokenId);

			$xmlRequest->appendChild($xmlCompleteTransaction);

			$this->logger->logInfo("Sending data doPaymentStep3...");

			return $this->_doRequest($xmlRequest, $this->gatewayUrl3step);

		}

	}

	function doRefund(){

		$xmlRequest = new DOMDocument('1.0','UTF-8');
		$xmlRequest->formatOutput = true;
		$xmlCompleteTransaction = $xmlRequest->createElement('refund');
		$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'api-key', $this->APIKey);
		$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'transaction-id', $this->transactionId);
		$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'amount', $this->order['amount']);

		$xmlRequest->appendChild($xmlCompleteTransaction);

		$this->logger->logInfo("Sending data doRefund()...");

		return $this->_doRequest($xmlRequest, $this->gatewayUrl3step);

	}


	function doVoid(){

		$xmlRequest = new DOMDocument('1.0','UTF-8');
		$xmlRequest->formatOutput = true;
		$xmlCompleteTransaction = $xmlRequest->createElement('void');
		$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'api-key', $this->APIKey);
		$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'transaction-id', $this->transactionId);

		$xmlRequest->appendChild($xmlCompleteTransaction);

		$this->logger->logInfo("Sending data doVoid()...");

		return $this->_doRequest($xmlRequest, $this->gatewayUrl3step);

	}

	function doUpdate(){

		$xmlRequest = new DOMDocument('1.0','UTF-8');
		$xmlRequest->formatOutput = true;
		$xmlCompleteTransaction = $xmlRequest->createElement('update');
		$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'api-key', $this->APIKey);
		$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'transaction-id', $this->transactionId);

		$xmlRequest->appendChild($xmlCompleteTransaction);

		$this->logger->logInfo("Sending data doUpdate()...");

		return $this->_doRequest($xmlRequest, $this->gatewayUrl3step);

	}

	/**
	* add/update customer to vault - this function will perform step1 and step2 procedure
	*/
	function doCustomerStep1(){

		$this->logger->logInfo("Start doCustomerStep1...");

		$xmlRequest = new DOMDocument('1.0','UTF-8');

		$xmlRequest->formatOutput = true;
		$xmlSale = $xmlRequest->createElement($this->transactionType);

		// Amount, authentication, and Redirect-URL are typically the bare minimum.
		$this->_appendXmlNode($xmlRequest, $xmlSale,'api-key',$this->APIKey);

		if (isset($this->httpdReferrer) && $this->httpdReferrer != "") {
			$this->_appendXmlNode($xmlRequest, $xmlSale,'redirect-url',$this->httpdReferrer);
		}

		if(isset($this->applicationSource) && $this->applicationSource != ""){
			$this->_appendXmlNode($xmlRequest, $xmlSale,'merchant-defined-field-1',$this->applicationSource);
		}

		if(isset($this->applicationSource) && $this->applicationSource != ""){
			$this->_appendXmlNode($xmlRequest, $xmlSale,'merchant-defined-field-2',$this->siteName);
		}

		if($this->vaultId != "" && $this->transactionType == "update-customer") {
			$this->_appendXmlNode($xmlRequest, $xmlSale, 'customer-vault-id' , $this->vaultId);
		}

		if($this->vaultId != "" && $this->transactionType == "delete-customer") {
			$this->_appendXmlNode($xmlRequest, $xmlSale, 'customer-vault-id' , $this->vaultId);
		}

		if($this->vaultId != "" && $this->transactionType == "update-billing") {
			$this->_appendXmlNode($xmlRequest, $xmlSale, 'customer-vault-id' , $this->vaultId);
		}

		// Set the Billing and Shipping from what was collected on initial shopping cart form
		// bypass this element if from delete customer
		if ($this->transactionType != 'delete-customer') {
			$xmlBillingAddress = $xmlRequest->createElement('billing');
		}

		if (isset($this->billing['first-name']) && $this->billing['first-name'] != "") {
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'first-name', $this->billing['first-name']);
		}

		if (isset($this->billing['last-name']) && $this->billing['last-name'] != "") {
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'last-name', $this->billing['last-name']);
		}

		if(isset($this->billing['address1'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'address1', $this->billing['address1']);
		}

		if(isset($this->billing['city'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'city', $this->billing['city']);
		}

		if(isset($this->billing['state'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'state', $this->billing['state']);
		}

		if(isset($this->billing['zip'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'postal', $this->billing['zip']);
		}

		if(isset($this->billing['email'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'email', $this->billing['email']);
		}

		if(isset($this->billing['phone'])){
			$this->_appendXmlNode($xmlRequest, $xmlBillingAddress,'phone', $this->billing['phone']);
		}

		if ($this->transactionType != 'delete-customer') {
			$xmlSale->appendChild($xmlBillingAddress);
		}

		$xmlRequest->appendChild($xmlSale);

		$this->logger->logInfo("Creating xml done");

		try{
			$this->logger->logInfo("Sending data doCustomerStep2...");
			// Process Step One: Submit all transaction details to the Payment Gateway except the customer's sensitive payment information.
			// The Payment Gateway will return a variable form-url.
			$data = $this->_sendXMLviaCurl($xmlRequest,$this->gatewayUrl3step);

			if ($this->transactionType == 'delete-customer') {
				return true;
			}

			$this->logger->logInfo("Response: " . serialize($data));

		}catch(Exception $e1){
			$this->logger->logInfo("Error: " . $e1->getMessage());
			throw new Exception("Error: " . $e1->getMessage());
		}

		$formURL = "";

		// Parse Step One's XML response
		$gwResponse = @new SimpleXMLElement((string) $data);

		// $this->logger->logInfo("gwResponse: " . serialize($gwResponse));

		if ((string)$gwResponse->result ==1 ) {
			// The form url for used in Step Two below
			$formURL = $gwResponse->{'form-url'};
		} else {
			throw New Exception("Error received: " . $data);
		}

		$this->logger->logInfo("formURL: " . $formURL);

		$dataRequest = "";
		if ( $this->order['cc-number'] != "" ) {
			$dataRequest .= "billing-cc-number=" . urlencode($this->order['cc-number']) . "&";
		}
		if ( $this->order['cc-exp'] != "" ) {
			$dataRequest .= "billing-cc-exp=" . urlencode($this->order['cc-exp']) . "&";
		}
		if ( $this->billing['first-name'] != "" ) {
			$dataRequest .= "billing-first-name=" . urlencode($this->billing['first-name']) . "&";
		}
		if ( $this->billing['last-name'] != "" ) {
			$dataRequest .= "billing-last-name=" . urlencode($this->billing['last-name']) . "&";
		}
		if ( $this->billing['company'] != "" ) {
			$dataRequest .= "billing-company=" . urlencode($this->billing['company']) . "&";
		}
		if ( $this->billing['address1'] != "" ) {
			$dataRequest .= "billing-address1=" . urlencode($this->billing['address1']) . "&";
		}
		if ( $this->billing['address2'] != "" ) {
			$dataRequest .= "billing-address2=" . urlencode($this->billing['address2']) . "&";
		}
		if ( $this->billing['city'] != "" ) {
			$dataRequest .= "billing-city=" . urlencode($this->billing['city']) . "&";
		}
		if ( $this->billing['state'] != "" ) {
			$dataRequest .= "billing-state=" . urlencode($this->billing['state']) . "&";
		}
		if ( $this->billing['zip'] != "" ) {
			$dataRequest .= "billing-postal=" . urlencode($this->billing['zip']) . "&";
		}
		if ( $this->billing['country'] != "" ) {
			$dataRequest .= "billing-country=" . urlencode($this->billing['country']) . "&";
		}
		if ( $this->billing['phone'] != "" ) {
			$dataRequest .= "billing-phone=" . urlencode($this->billing['phone']) . "&";
		}
		if ( $this->billing['fax'] != "" ) {
			$dataRequest .= "billing-fax=" . urlencode($this->billing['fax']) . "&";
		}
		if ( $this->billing['email'] != "" ) {
			$dataRequest .= "billing-email=" . urlencode($this->billing['email']) . "&";
		}

		$this->logger->logInfo("Sending data doCustomerStep2...");

		$this->tokenId = $this->_getTokenId($dataRequest,$formURL, $error);


		$this->logger->logInfo("TokenId: " . $this->tokenId);

		if ($this->tokenId != "") {

			return true;

		} else {

			$this->logger->logInfo(" Error : doCustomerStep2() " . $error);
			throw New Exception(" Error : doCustomerStep2() " . $error);

		}
	}

	function doCustomerStep3(){

		if($this->tokenId != ""){

			// Step Three: Once the browser has been redirected, we can obtain the token-id and complete
			// the transaction through another XML HTTPS POST including the token-id which abstracts the
			// sensitive payment information that was previously collected by the Payment Gateway.

			$xmlRequest = new DOMDocument('1.0','UTF-8');
			$xmlRequest->formatOutput = true;
			$xmlCompleteTransaction = $xmlRequest->createElement('complete-action');
			$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'api-key', $this->APIKey);
			$this->_appendXmlNode($xmlRequest, $xmlCompleteTransaction,'token-id', $this->tokenId);

			$xmlRequest->appendChild($xmlCompleteTransaction);

			$this->logger->logInfo("Sending data doCustomerStep3...");

			return $this->_doRequest($xmlRequest, $this->gatewayUrl3step);

		}

	}


	function doGetCustomer() {

		$query  = "";
		$query .= "username=" . urlencode($this->userLogin) . "&";
		$query .= "password=" . urlencode($this->userPassword) . "&";

		$query .= "report_type=customer_vault&";
		$query .= "customer_vault_id=" . urlencode($this->vaultId);

		return $this->_sendQueryviaCurl($query);
	}


	function _doRequest($xmlRequest, $sUrl){

		$data = $this->_sendXMLviaCurl($xmlRequest, $sUrl);

		$gwResponse = @new SimpleXMLElement((string) $data);

		$this->logger->logInfo("Response: " . (string) $gwResponse->{'result-text'});
		$this->logger->logInfo("Result: " . (string) $gwResponse->result); // bug 67740

		if ((string)$gwResponse->result == 1 ) {

			$this->result->result = APPROVED;
			$this->result->code = (string) $gwResponse->{'result-code'};
			$this->result->text = (string) $gwResponse->{'result-text'};
			$this->result->msg = "Transaction was Approved.";

			$this->result->order_id = (string) $gwResponse->{'order-id'};
			$this->result->transaction_id = (string) $gwResponse->{'transaction-id'};

			if($this->transactionType == "add-customer"){
				$this->vaultId = (string) $gwResponse->{'customer-vault-id'};
			}

			return true;

		} elseif((string)$gwResponse->result == 2)  {

			$this->result->result = DECLINED;
			$this->result->code = (string) $gwResponse->{'result-code'};
			$this->result->text = (string) $gwResponse->{'result-text'};
			$this->result->avs_result = (string) $gwResponse->{'avs-result'};
			$this->result->msg = "Transaction was Declined.";

			return false;

		} else {

			$this->result->result = ERROR;
			$this->result->code = (string) $gwResponse->{'result-code'};
			$this->result->text = (string) $gwResponse->{'result-text'};
			$this->result->avs_result = (string) $gwResponse->{'avs-result'};
			$this->result->msg = "Transaction caused an Error.\nError Description: " . (string) $gwResponse->{'result-text'};

			return false;

		}

	}

	function _sendXMLviaCurl($xmlRequest,$gatewayUrl3step) {
		// helper function demonstrating how to send the xml with curl

		$ch = curl_init(); // Initialize curl handle
		curl_setopt($ch, CURLOPT_URL, $gatewayUrl3step); // Set POST URL

		$headers = array();
		$headers[] = "Content-type: text/xml";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Add http headers to let it know we're sending XML
		$xmlString = $xmlRequest->saveXML();
		curl_setopt($ch, CURLOPT_FAILONERROR, 1); // Fail on errors
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Allow redirects
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return into a variable
		curl_setopt($ch, CURLOPT_PORT, 443); // Set the port number
		curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Times out after 15s
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlString); // Add XML directly in POST

		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);


		// This should be unset in production use. With it on, it forces the ssl cert to be valid
		// before sending info.
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		if (!($data = curl_exec($ch))) {
			$this->logger->logInfo("curl error => " .curl_error($ch));
			$this->result->text = curl_error($ch);
			return false;
		}

		curl_close($ch);

		return $data;
	}

	function _sendQueryviaCurl($query) {
		$this->logger->logInfo('--- send query via curl started ---');

		$mycurl=new curl();
	    $postStr='username='.$username.'&password='.$password. $constraints;
	    $url="https://secure.paylinedatagateway.com/api/query.php?". $postStr;
	    $mycurl->execute($url);
		echo "<pre>"; print_r($result); echo "</pre>";
		echo "<pre>"; print_r(simplexml_load_string($result)); echo "</pre>"; die('die');

		if (!isset($testXmlSimple->transaction)) {
			$this->logger->logInfo("No transactions returned");
            throw new Exception('No transactions returned');
    	}

    	$transNum = 1;
    	foreach($testXmlSimple->transaction as $transaction) {
    		foreach ($this->_getTransactionFields as $xmlField) {
    			if (!isset($transaction->{$xmlField}[0])){
    				$this->logger->logInfo('Error in transaction_id:'.
    										$transaction->transaction_id[0] .
    										' id  Transaction tag is missing  field ' . $xmlField);
	                throw new Exception('Error in transaction_id:'. $transaction->transaction_id[0] .' id  Transaction tag is missing  field ' . $xmlField);
	            }

	             if (!isset ($transaction->action)) {
	             	$this->logger->logInfo('Error, Action tag is missing from
	             							transaction_id '. $transaction->transaction_id[0]);
		            throw new Exception('Error, Action tag is missing from transaction_id '. $transaction->transaction_id[0]);
		        }

		        $actionNum = 1;
		        foreach ($transaction->action as $action){
		            foreach ($actionFields as $xmlField){
		                if (!isset($action->{$xmlField}[0])){
		                	$this->logger->logInfo('Error with transaction_id'.$transaction->transaction_id[0].'
		                                        	Action number '. $actionNum . ' Action tag is missing field ' . $xmlField);
		                    throw new Exception('Error with transaction_id'.$transaction->transaction_id[0].'
		                                        Action number '. $actionNum . ' Action tag is missing field ' . $xmlField);
		                }
		            }
		            $actionNum++;
		        }
		        $transNum++;
    		}
    	}

		return;

	}

	function _getTokenId($formRequest, $formURL, & $formError = "") {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $formURL);

		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17');

		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);

		curl_setopt($ch, CURLOPT_FAILONERROR, 1); // Fail on errors
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Allow redirects
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return into a variable

		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		curl_setopt($ch, CURLOPT_HEADER ,1);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $formRequest);

		curl_setopt($ch, CURLOPT_ENCODING, "");

		$data = curl_exec($ch);

		curl_close($ch);
		unset($ch);


		if (!$data) {
			$formError = ERROR;
		}

		$data = explode("\n",$data);

		// search-parse token Id -- needs an appropriate method/fixing
		$searchKey = "Location: ". $this->httpdReferrer . "?token-id";

		$tokenId = "";

		for($i=0;$i<count($data);$i++) {
			$rdata = explode("=",$data[$i]);

			if($rdata[0] == $searchKey){
				$tokenId = trim($rdata[1]);
			}
		}

		if($tokenId == ""){
			$formError = "Failed fetching token";
		}

		return $tokenId;

	}

	// Helper function to make building xml dom easier
	function _appendXmlNode($domDocument, $parentNode, $name, $value) {
		$childNode      = $domDocument->createElement($name);
		$childNodeValue = $domDocument->createTextNode($value);
		$childNode->appendChild($childNodeValue);
		$parentNode->appendChild($childNode);
	}

	function _doPost($query) {

		$this->logger->logInfo("Post data: " . $query);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->gatewayUrlTransact);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
		curl_setopt($ch, CURLOPT_POST, 1);

		if (!($data = curl_exec($ch))) {
			return ERROR;
		}

		curl_close($ch);
		unset($ch);

		$data = explode("&",$data);

		$aResponse = array();

		for($i=0;$i<count($data);$i++) {
			$rdata = explode("=",$data[$i]);
			$aResponse[$rdata[0]] = $rdata[1];
		}

		/**
		response
			1 = Transaction Approved
			2 = Transaction Declined
			3 = Error in transaction data or system error
		responsetext Textual response
		authcode Transaction authorization code.
		transactionid Payment gateway transaction id.
		avsresponse AVS response code (See Appendix 1).
		cvvresponse CVV response code (See Appendix 2).
		orderid The original order id passed in the transaction request.
		response_code Numeric mapping of processor responses (See Appendix 3).
		*/

		$this->result->result = $aResponse['response'];
		$this->result->code = $aResponse['response_code'];
		$this->result->text = $aResponse['responsetext'];
		$this->result->msg = $aResponse['responsetext'];
		$this->result->transaction_id = $aResponse['transactionid'];

		if($aResponse['response'] == 1){
			return true;
		}else{
			return false;
		}

	}

	function _getTransactionFields()
	{
	    // transactionFields has all of the fields we want to validate
	    // in the transaction tag in the XML output
	    $transactionFields = array(
	        'transaction_id',
	        'transaction_type',
	        'condition',
	        'order_id',
	        'authorization_code',
	        'ponumber',
	        'orderdescription',
	        'avs_response',
	        'csc_response',

	        'first_name',
	        'last_name',
	        'address_1',
	        'address_2',
	        'company',
	        'city',
	        'state',
	        'postal_code',
	        'country',
	        'email',
	        'phone',
	        'fax',
	        'cell_phone',
	        'customertaxid',
	        'customerid',
	        'website',

	        'shipping_last_name',
	        'shipping_address_1',
	        'shipping_address_2',
	        'shipping_company',
	        'shipping_city',
	        'shipping_state',
	        'shipping_postal_code',
	        'shipping_country',
	        'shipping_email',
	        'shipping_carrier',
	        'tracking_number',

	        'cc_number',
	        'cc_hash',
	        'cc_exp',
	        'cc_bin',
	        'avs_response',
	        'csc_response',
	        'cardholder_auth',

	        'processor_id',

	        'tax');

		return $transactionFields;
	}

}

?>
