<?php

class CardPaymentUtility
{

	const CC_DATA_SALT = '';


	function payViaAuthorizeNetGateway($data, $conf, $total, $isCredit = 0)
	{

		$gateway_ok = true;
		$operation_xid = 0;
		if(!isset($conf->payment->payment_gateway))
		{
			$ret_result = 'Config error: parameters are not properly set';
			$gateway_ok = false;
		}
		if(!isset($conf->authorizenet->login))
		{
			$ret_result = 'Config error: Merchant login not set';
			$gateway_ok = false;
		}
		if(!isset($conf->authorizenet->password))
		{
			$ret_result = 'Config error: Merchant password not set';
			$gateway_ok = false;
		}
		if($gateway_ok)
		{
			$a = new Authorizenet();

			$a->add_field('x_login', $conf->authorizenet->login);
			$a->add_field('x_tran_key', trim($conf->authorizenet->password) );

			$a->add_field('x_version', '3.1');
			$a->add_field('x_test_request', 'FALSE');
			$a->add_field('x_relay_response', 'FALSE');

			$a->add_field('x_delim_data', 'TRUE');
			$a->add_field('x_delim_char', '|');
			$a->add_field('x_encap_char', '');
			$a->add_field('x_method', 'CC');

			$a->add_field('x_amount',  $total);
			$a->add_field('x_card_num', preg_replace('/\D/', '', $data->credit_card->cc_number));
			$a->add_field('x_exp_date', $data->credit_card->expiration_month.$data->credit_card->expiration_year);

			if ($data->invoice_number != '') $a->add_field('x_invoice_num',$data->invoice_number);

			if($isCredit){
				$transaction_date = date('Y-m-d', strtotime($data->payment->transaction_date));

				if ($transaction_date == date('Y-m-d')){
					$a->add_field('x_type', 'VOID');
				}else{
					$a->add_field('x_type', 'CREDIT');
				}
				$a->add_field('x_trans_id',  $data->payment->operation_xid);

			}else{
				$a->add_field('x_type', 'AUTH_CAPTURE');

				$a->add_field('x_card_code', preg_replace('/\D/', '', $data->credit_card->code));

				$a->add_field('x_first_name', $data->payer->first_name);
				$a->add_field('x_last_name', $data->payer->last_name);
				$a->add_field('x_address', $data->payer->address);
				$a->add_field('x_city', $data->payer->city);
				$a->add_field('x_state', $data->payer->state_code);
				$a->add_field('x_zip', $data->payer->zip);
				$a->add_field('x_country', $data->payer->country);
				$a->add_field('x_email', $data->payer->email);
				$a->add_field('x_phone', $data->payer->contact_phone);
			}

	        // Process the payment and output the results
			CardPaymentUtility::logMsg($a->dump_fields());
			$ch_ret = $a->process();
			CardPaymentUtility::logMsg($a->dump_response());
	        if ($ch_ret == 1) {
	            $ret_result = 1;
				$operation_xid = $a->response['Transaction ID'];
	        }else{
				$ret_result = $a->get_response_reason_text();
			}
		}
		CardPaymentUtility::logMsg($ret_result);
		$ret->result = $ret_result;
		$ret->operation_xid = $operation_xid;
		return $ret;
	}



	function payViaTransactionCentralGateway($data, $conf, $total, $isCredit = 0)
	{

		$TC_CCSALE_URL = 'https://webservices.primerchants.com/billing/TransactionCentral/processCC.asp';
		$TC_CCCREDIT_URL = 'https://webservices.primerchants.com/billing/TransactionCentral/voidcreditcconline.asp';

		$operation_xid = 0;
		$tc_gateway_ok = true;

		$params = array();

		if(0){
			$params['MerchantID'] = TC_TEST_MERCHANTID;
			$params['RegKey'] = TC_TEST_REGKEY;
		} else {
			if(!isset($conf->payment->payment_gateway))
			{
				$ret_result = 'Config error: parameters are not properly set';
				$tc_gateway_ok = false;
			}
			if(!isset($conf->tc_gateway->merchant_id))
			{
				$ret_result = 'Config error: Merchant ID not set';
				$tc_gateway_ok = false;
			}
			if(!isset($conf->tc_gateway->reg_key))
			{
				$ret_result = 'Config error: Merchant reg key not set';
				$tc_gateway_ok = false;
			}
			if($tc_gateway_ok)
			{
				$params['MerchantID'] = (int) $conf->tc_gateway->merchant_id;
				$params['RegKey'] = trim($conf->tc_gateway->reg_key);
			}
		}


		if(fmod($total, 1) == 0 ) {
			$total = number_format($total,0);
		}
		$formattedTotal = str_replace(",","",$total);
		if($tc_gateway_ok)
		{
			if($isCredit)
			{
				$params['CreditAmount'] = $formattedTotal;
				$params['TransID'] = (int) $data->payment->operation_xid;

				$post_url = $TC_CCCREDIT_URL;
			} else
			{
				$cc_year = $data->credit_card->expiration_year;
				if($cc_year >= 2000)
					$cc_year -= 2000;
				else
					$cc_year -= 1900;

				$params['Amount'] =  $formattedTotal;
				$params['AccountNo'] = preg_replace('/\D/', '', $data->credit_card->cc_number);

				if(isset($data->credit_card->code) && $data->credit_card->code){
					$params['CVV2'] = preg_replace('/\D/', '', $data->credit_card->code);
				}
				$params['NameonAccount'] = strtoupper($data->payer->first_name . ' ' . $data->payer->last_name );
				$params['CCMonth'] = sprintf("%02d", (int) $data->credit_card->expiration_month);
				$params['CCYear'] = sprintf("%02d", (int) $cc_year);
				$params['REFID'] = $data->payer->id . '-' . date('YmdHis');
				$params['AVSADDR'] = strtoupper($data->payer->address);
				$params['AVSZIP'] = strtoupper($data->payer->zip);

				$post_url = $TC_CCSALE_URL;
			}
			CardPaymentUtility::logParams($params, $post_url);
			$ch_ret = TransactionCentral::process($params , $post_url);
			CardPaymentUtility::logMsg($ch_ret);

			$errorOrbital = new Zend_Session_Namespace("errorOrbitalGateway");
			if ($ch_ret)
			{
				if(preg_match('/.*\<body\>\s*(\S.*\S)\s*\<\/body\>.*/', $ch_ret, $hits))
				{
					parse_str($hits[1], $retA);
					if(strtolower($retA['Auth']) == 'declined' ||
						strlen(trim($retA['Auth'])) == 0 ||
						(int) $retA['TransID'] == 0 ||
						strtolower($retA['Status']) == 'f')
					{
						$ret_result = $errorOrbital->msg = 'Fatal error: ' . $retA['Notes'];
						$errorOrbital->anyError = true;
					} else
					{
						$ret_result = 1;
						$operation_xid = $retA['TransID'];
					}
				} else{
					$ret_result = $errorOrbital->msg = 'Fatal error: The payment gateway processor returned an unrecognized data. ' . $ch_ret;
					$errorOrbital->anyError = true;
				}
			}else{
				$ret_result = $errorOrbital->msg = 'Error: Payment operation not supported';
				$errorOrbital->anyError = true;
			}

		}
		CardPaymentUtility::logMsg($ret_result);
		$ret->result = $ret_result;
		$ret->operation_xid = $operation_xid;
		return $ret;

	}

 	function payViaOrbitalGateway($data, $conf, $total, $isCredit = 0)
 	{
		Zend_Loader::loadClass("BaseView");

		$classView = new BaseView();
		$classView->setNoLayout();

		$classView->assign("payer", $data->payer);
		$classView->assign("credit_card", $data->credit_card);

		$classView->assign("BIN", $conf->orbital->BIN);
		$classView->assign("MerchantID", $conf->orbital->MerchantID);

		// added API auth if any, if not, they just receive from whitelisted IPs
		$classView->assign("AuthUsername", $conf->orbital->AuthUsername);
		$classView->assign("AuthPassword", $conf->orbital->AuthPassword);

		if ($conf->orbital->TerminalID){
			$classView->assign("TerminalID", $conf->orbital->TerminalID);
		} else {
			$classView->assign("TerminalID", '001');
		}

		$operation_xid = '';
		$newTotal = number_format($total, 2);
		$formattedTotal = str_replace(".","",$newTotal);
		$formattedTotal = str_replace(",","",$formattedTotal);
		$classView->assign("total", $formattedTotal);


		$cc_year = $data->credit_card->expiration_year;

		if($cc_year >= 2000)	$cc_year -= 2000;
		else					$cc_year -= 1900;

		$CardSecVal = $data->credit_card->code;

		$data->payer->address = substr($data->payer->address, 0, 29);
		$AVSaddress1 = strtoupper($data->payer->address);

		$Exp = sprintf("%02d%02d", (int) $data->credit_card->expiration_month, (int) $cc_year);

		$classView->assign("CardSecVal", $CardSecVal);
		if (isset($CardSecVal) && $CardSecVal && ($data->credit_card->card_type == "Visa" || $data->credit_card->card_type == "Discover")){
			$classView->assign("CardSecValInd", 1);
		}

		$classView->assign("AVSaddress1", $AVSaddress1);
		$classView->assign("Exp", $Exp);

		if ($isCredit) {
			$TxRefNum = $data->payment->operation_xid;
			$TxRefIdx = 0;

			$classView->assign("TxRefNum", $TxRefNum);
			$classView->assign("TxRefIdx", $TxRefIdx);

			$transaction_date = date('Y-m-d', strtotime($data->payment->transaction_date));

			if ($transaction_date == date('Y-m-d')){
				// if the this is a refund on same day, get the order id from order id from payment that has been made
				$order_id = ($data->payment->order_id)? $data->payment->order_id : ($data->payment_id. '-' . $data->payer->id . '-' . date('Ymd'));
				$classView->assign("OrderID", $order_id);

				// should be null or blank for reversal request
				$classView->assign("TxRefIdx", "");

				// should be Y (yes) for Online Reversal Indicator
				$classView->assign("OnlineRevInd", "Y");

				$classView->setTemplateDir(DEFAULT_DIR . "views/globals/");
				$classView->setTemplate('orbitalreversal.xml');
				$xml = $classView->getHTML();

			}else{
				// order id should be unique for original transaction (in this case REFUND)
				// changing it to concat payer id and current date+time
				// $order_id = date('HisYmd') . '-' . $data->payer->id;
				$order_id = $data->payer->id . '-' . date('siHYmd'); // 2013Dec11
				$classView->assign("OrderID", $order_id);
				$classView->assign("MessageType", 'R');
				$classView->assign("Comments", "Refund from Class Registration");

				$classView->setTemplateDir(DEFAULT_DIR . "views/globals/");
				$classView->setTemplate('orbitalneworder.xml');
				$xml = $classView->getHTML();
			}

		} else {
			$order_id = $data->payment_id. '-' . $data->payer->id . '-' . date('Ymd');
			$classView->assign("OrderID", $order_id);
			$classView->assign("MessageType", 'AC');
			$classView->assign("Comments", "Payment from Class Registration");

			$classView->setTemplateDir(DEFAULT_DIR . "views/globals/");
			$classView->setTemplate('orbitalneworder.xml');
			$xml = $classView->getHTML();
		}

		$tempReplaceStr = $data->credit_card->cc_number;
		$tempNewVal = substr($tempReplaceStr, -4);

		// hide cc
		$logxml = str_replace(str_replace('-', '', $tempReplaceStr), 'xxxxxxxxxxxx' . $tempNewVal, $xml);
		CardPaymentUtility::logXml ("\n".date("D M j G:i:s T Y - ") . $logxml); // $xml

		if ($xml){
			$orbital = new Orbital($xml);

			// set the environtment, affect to Orbital's URL used
			$orbital->setURL($conf->orbital->Environtment);

			$result = $orbital->process();

			CardPaymentUtility::logResult ($result[1]);
			$result = $result[0];

			$errorOrbital = new Zend_Session_Namespace("errorOrbitalGateway");

			if (empty($result)){
				$errorOrbital->anyError = true;
				$ret_result = "General problem trying to reach Orbital Payment Gateway. Payment has not been made.";
			} else {
				if ($isCredit) {
					if (isset($result['Response'][0]['NewOrderResp'])) {
						$response = $result['Response'][0]['NewOrderResp'][0];
					} else {
						$response = $result['Response'][0]['ReversalResp'][0];
					}
					if ($response['ProcStatus'][0] == 0 && !empty($response['TxRefNum'][0])) {
						$ret_result = 1;
						$operation_xid = $response['TxRefNum'][0];
					} else {
						$errorOrbital->anyError = true;
						if (is_array($result['Response'][0]['QuickResp'])) {
							$ret_result = 'The payment cannot be processed. Error message received from the payment gateway:\n' . $result['Response'][0]['QuickResp'][0]['StatusMsg'][0];
						} else {
							if($response['RespCode'][0]) {
								preg_match('/(.*)\s{2,}/U', $response['RespMsg'][0], $matches);
								if($response['AVSRespCode'][0] == 'H')
									$ret_result = 'The payment cannot be processed. Error message received from the payment gateway:\n' . $response['StatusMsg'][0] . ' ' . ucfirst(strtolower($matches[1]));
								else
									$ret_result = 'Card verification error:\n' . $orbital->get_avs_error_message($response['AVSRespCode'][0]);

							} else {
								if($response['StatusMsg'][0])
									$ret_result = 'The payment cannot be processed. Error message received from the payment gateway: \n' . $response['StatusMsg'][0];
								else
									$ret_result = 'Card Transaction Error. We cannot process your payment.';
							}
						}
					}
				} else {
					$response = $result['Response'][0]['NewOrderResp'][0];

					if ($response['ProcStatus'][0] == 0 && $response['ApprovalStatus'][0] == 1){ // SUCCESS
						$ret_result = 1;
						$operation_xid = $response['TxRefNum'][0];
					} else { // FAILED
						$errorOrbital->anyError = true;
						if (isset($result['Response'][0]['QuickResp']) && is_array($result['Response'][0]['QuickResp'])){
							$ret_result = 'The payment cannot be processed. Error message received from the payment gateway:\n' . $result['Response'][0]['QuickResp'][0]['StatusMsg'][0];
						} else {
							if($response['RespCode'][0]) {
								if($response['StatusMsg'][0])
									$ret_result = 'The payment cannot be processed. Error message received from the payment gateway:\n' . $response['StatusMsg'][0] . '. Payment has not been made.';
								else{
									preg_match('/(.*)\s{2,}/U', $response['RespMsg'][0], $matches);
									if($response['AVSRespCode'][0] == 'H')
										$ret_result = 'The payment cannot be processed. Error message received from the payment gateway:\n' . $response['StatusMsg'][0] . ' ' . ucfirst(strtolower($matches[1]));
									else
										$ret_result = 'Card verification error:\n' . $orbital->get_avs_error_message($response['AVSRespCode'][0]);
									
									if($response['CVV2RespCode'][0] != 'M')
	                  					$ret_result = "The credit card security code number is incorrect or invalid. Please correct it to continue.";
	                  				else
	              						$ret_result = 'The payment cannot be processed. Error message received from the payment gateway';
								}
							} else {
								if($response['StatusMsg'][0])
									$ret_result = 'The payment cannot be processed. Error message received from the payment gateway:\n' . $response['StatusMsg'][0] . '. Payment has not been made.';
								else
									$ret_result = 'Card Transaction Error. We cannot process your payment.';
							}
						}
					}
				}
			}
		}else{
			$errorOrbital->anyError = true;
			$ret_result = 'Error: Payment operation not supported';
		}

		$errorOrbital->msg = $ret_result;
		$ret->result = $ret_result;

		if($errorOrbital->anyError == true) {
			$log = new BaseLogger();
			$log->logInfo("Returned error message: " . $ret_result . " - " . $orbital->get_avs_error_message($response['AVSRespCode'][0] . " - " . $response['StatusMsg'][0]));
		}
		
		$ret->operation_xid = $operation_xid;
		$ret->order_id = $order_id;
		return $ret;
 	}

 	/*
 	 * Process payment using EPay payment gateway
 	 * 
 	 * @params struct $data, struct $conf, float $total, boolean $isCredit
 	 * @return struct $gateway_result
 	 * @author: Indra
	 * @on: 1/23/2014
 	 * 
 	 * */
 	function payViaUSAEpayGateway($data, $conf, $total, $isCredit = 0)
 	{
 		$tran = new USAePay;

 		// default value
 		$tran->key = $conf->epay->key;
 		$tran->pin = $conf->epay->pin;
 		$tran->usesandbox = ($conf->epay->environtment == "staging")? true : false;
 		$tran->testmode = ($conf->epay->environtment == "staging")? true : false;
 		$tran->ignoresslcerterrors = ($conf->epay->environtment == "staging")? true : false;
 		$tran->cabundle = $conf->epay->certificate;
 		$tran->cardauth = true;
 		$tran->software = "Class Registration V3.0 - USA ePay";

 		// payer cc information
 		$tran->cardholder = $data->payer->first_name . " " . $data->payer->last_name;
 		$tran->card = str_replace("-", "", $data->credit_card->cc_number);
 		$tran->exp = sprintf("%02d%02d", (int)$data->credit_card->expiration_month, (int)substr($data->credit_card->expiration_year, -2));
 		$tran->cvv2 = preg_replace('/\D/', '', $data->credit_card->code);
 		$tran->currency = "840"; // USD

 		// payer information
 		$tran->billfname = $data->payer->first_name;
 		$tran->billlname = $data->payer->last_name;
 		$tran->billstreet = $data->payer->address;
 		$tran->billzip = $data->payer->zip;
 		$tran->billcity = $data->payer->city;
 		$tran->billstate = $data->payer->state_code;
 		$tran->billcountry = $data->payer->country;
 		$tran->email = $data->payer->email;

 		// the amount
 		$tran->amount = number_format($total, 2);

 		if ($isCredit)
 		{
 			if(strlen($data->payment->operation_xid) > 10 || $data->payment->operation_xid == "")
 			{
 				// this action will credit funds to specified CC, with no reference number, can be transaction from Orbital or else.
 				// Note: not all CC provider supported
 				$tran->orderid = $data->payment_id . '-' . $data->payer->id . '-' . date('Ymd', strtotime($data->payment->transaction_date));
 				$tran->description = "Credit from Class Registration V3.0";
 				$tran->command = "credit";
 			}
 			else
 			{
 				// manage refund
 				$tran->orderid = $data->payment->order_id;
 				$transaction_date = date('Y-m-d', strtotime($data->payment->transaction_date));
 				$tran->refnum = $data->payment->operation_xid;
 				
 				if ($transaction_date == date('Y-m-d'))
 				{
 					if ($data->payment->refundable_amount != '0.00' || $data->payment->refundable_amount > 0)
 					{
		 				// this action will adjust amount on unsettled trans, used for partially refund on same day
		 				$tran->description = "Adjust for refund from Class Registration V3.0";
 						$tran->amount = $data->payment->refundable_amount;
 						$tran->command = "adjust";
 					}
 					else
 					{
 						// this action will void the unsettled transaction
 						$tran->description = "Void from Class Registration V3.0";
 						$tran->command = "void";
 					}
 				}
 				else
 				{
	 				// this action will refund if the transaction has been settled
	 				$tran->description = "Refund from Class Registration V3.0";
		 			$tran->command = "refund";
 				}
 			}
 		}
 		else
 		{
 			// get the invoice number, this is optional
 			$invoice_number = CardPaymentUtility::_getInvoiceNumber($data->payment_id);
 			$tran->description = "Payment for Invoice #{$invoice_number} from Class Registration V3.0";
 			$tran->orderid = $data->payment_id . '-' . $data->payer->id . '-' . date('Ymd');
 			$tran->command = "sale";
 		}

 		// process the transaction
 		if($tran->Process())
 		{
 			// success
 			$gateway_result->result = 1;
 			$gateway_result->order_id = $tran->orderid;
 			$gateway_result->operation_xid = $tran->refnum;
 		}
 		else
 		{
 			// error
 			$errorOrbital = new Zend_Session_Namespace("errorOrbitalGateway");
 			$errorOrbital->anyError = true;
 			if($tran->curlerror)
 			{
 				$gateway_result->result = $errorOrbital->msg = "General problem trying to reach USA e-Pay Payment Gateway. Payment has not been made.";
 			}
 			else
 			{
 				$gateway_result->result = $errorOrbital->msg = "Transaction Error: " . $tran->error;
 			}
 		}

 		// log result
 		$log['Action'] = $tran->description;
 		$log['Total'] = number_format($total, 2);
 		$log['Exp'] = $tran->exp;
 		$log['Type'] = $tran->command;
 		$log['Result'] = $tran->result;
 		$log['Result Code'] = $tran->resultcode;
 		$log['AVS Result'] = $tran->avs_result;
 		$log['Ref ID'] = $tran->refnum;
 		$log['Order ID'] = $tran->orderid;
 		if ($tran->error && $tran->resultcode != 'A') $log['Result Error'] = $tran->error . " - " . $tran->curlerror;

 		CardPaymentUtility::logResult(serialize($log));

 		// all done
 		return $gateway_result;
 	}

 	/*
 	 * Process payment using Payline payment gateway
 	 *
 	 * @params struct $data, struct $conf, float $total, boolean $isCredit
 	 * @return struct $gateway_result
 	 * @author: Ian
	 * @on: 6/16/2015
 	 *
 	 * */
 	function payViaPaylineGateway($data, $conf, $total, $isCredit = 0)
 	{
 		$http = explode(':',$_SERVER['HTTP_REFERER']);
        $http_protocol = ($http[0] == "") ? "http" : $http[0];
        $sitename = $http_protocol.'://'.$_SERVER['HTTP_HOST'];

 		$tran = new Payline;

 		$tran->APIKey = $conf->payline->key;
 		$tran->applicationSource = $conf->payline->app;
 		$tran->siteName = $sitename;
 		$tran->vaultId = $data->credit_card->customer_vault_id;
 		$tran->httpdReferrer = ($_SERVER['HTTP_REFERER'] != "") ? $_SERVER['HTTP_REFERER'] : $sitename."/";

 		// get cvv
 		if (isset($data->credit_card->code) && $data->credit_card->code != "") {
 			$tran->order['cvv'] = $data->credit_card->code;
 		}

 		$tran->currency = "USD";

 		// payer order information
 		$tran->order['amount'] = $total;

 		if ($isCredit)
 		{
 			if(strlen($data->payment->operation_xid) > 10 || $data->payment->operation_xid == "")
 			{
 				// this action will credit funds to specified CC, with no reference number, can be transaction from Orbital or else.
 				// Note: not all CC provider supported
 				$tran->order['order-id'] = $data->payment_id . '-' . $data->payer->id . '-' . date('Ymd', strtotime($data->payment->transaction_date));
 				$tran->order['order-description'] = "Credit from Class Registration V3.0";
 				$tran->transactionType = "credit";
 			}
 			else
 			{
 				// manage refund
 				$tran->order['order-id'] = $data->payment->order_id;
 				$transaction_date = date('Y-m-d', strtotime($data->payment->transaction_date));
 				$tran->transactionId = $data->payment->operation_xid;

 				/*
 				 * logging
 				 */
 				$logger = new BaseLogger();
 				$logger->logInfo("**** start logging on refund ****");
 				$logger->logInfo("refundable_amount = " . $data->payment->refundable_amount);
 				$logger->logInfo("transaction_date = " . $transaction_date);
 				$logger->logInfo("**** end logging on refund ****");

 				if ($transaction_date == date('Y-m-d'))
 				{
 					if ($data->payment->refundable_amount != '0.00' || $data->payment->refundable_amount > 0)
 					{
		 				// this action will adjust amount on unsettled trans, used for partially refund on same day
		 				$tran->order['order-description'] = "Adjust for refund from Class Registration V3.0";
 						// $tran->order['amount'] = $data->payment->refundable_amount;
 						$tran->transactionType = "refund";
 					}
 					else
 					{
 						// this action will void the unsettled transaction
 						// check if any refunded transaction before.
 						// if any, use refund. 
 						if ($data->payment->any_refund_before) {
 							$tran->transactionType = "refund";
 							$tran->order['order-description'] = "Refund from Class Registration V3.0";
 						} else {
 							$tran->transactionType = "void";
 							$tran->order['order-description'] = "Void from Class Registration V3.0";
 						}
 					}
 				}
 				else
 				{
	 				// this action will refund if the transaction has been settled
	 				$tran->order['order-description'] = "Refund from Class Registration V3.0";
		 			$tran->transactionType = "refund";
 				}
 			}
 		} else {
 			$tran->transactionType = 'sale';

 			// get the invoice number, this is optional
 			$invoice_number = CardPaymentUtility::_getInvoiceNumber($data->payment_id);
 			$tran->order['order-description'] = "Payment for Invoice #{$invoice_number} from Class Registration V3.0";
 			$tran->order['order-id'] = $data->payment_id . '-' . $data->payer->id . '-' . date('Ymd');
 		}

 		// process the transaction
		$gateway_result = new stdClass();
 		if($tran->processRequest())
 		{
 			// success
 			$gateway_result->result = 1;
 			$gateway_result->order_id = $tran->order['order-id'];
 			$gateway_result->operation_xid = $tran->result->transaction_id;

            $paySession = new Zend_Session_Namespace("session_" .  $tran->order['order-id']);
            $paySession->operation_xid = $tran->result->transaction_id;
            $paySession->order_id = $tran->order['order-id'];
 		}
 		else
 		{
 			// error
			if ($data->hide_prompt) {

			} else {
                $errorOrbital = new Zend_Session_Namespace("errorOrbitalGateway");
                $errorOrbital->anyError = true;
                $gateway_result->result = $errorOrbital->msg = "Transaction Error: " . $tran->result->text;
            }
 		}

 		// log result
 		$log['Action'] = $tran->order['order-description'];
 		$log['Total'] = number_format($total, 2);
 		$log['Exp'] = $tran->exp;
 		$log['Type'] = $tran->transactionType;
 		$log['Result'] = $tran->result->result;
 		$log['Result Code'] = $tran->result->code;
 		$log['AVS Result'] = $tran->result->avs_result;
 		$log['Ref ID'] = $tran->result->transaction_id;
 		$log['Order ID'] = $tran->order['order-id'];
 		$log['Result Error'] = $tran->result->text . " - " . $tran->result->msg;

 		CardPaymentUtility::logResult(serialize($log));

 		// all done
 		return $gateway_result;
 	}

 	private function _getInvoiceNumber($payment_id)
 	{
 		$query = new QueryCreator();
 		$query->addSelect("aci.invoice_number");
 		$query->addFrom(AccountingClassInvoice::TABLE_NAME, "aci");
 		$query->addJoin("LEFT JOIN " . AccountingClassPaymentInvoice::TABLE_NAME . " AS acpi ON aci.id = acpi.invoice_id");
 		$query->addWhere("acpi.payment_id = {$payment_id}");

 		$db = DBCon::instance();
 		$result = $db->executeQuery($query->createSQL());
 		return ($result[0]["invoice_number"]);
 	}

 	/**
 	 * @param data - contains the ff fields:
 	 * payer->id
 	 * payer->last_name
 	 * payer->first_name
 	 * payer->zip
 	 * payer->city
 	 * payer->state
 	 * payer->address
 	 * payer->email
 	 * payer->contact_phone
 	 * credit_card->card_type
 	 * credit_card->cc_number
 	 * credit_card->expiration_month
 	 * credit_card->expiration_year
 	 * credit_card->code
 	 *
	 * payment->operation_xid
	 * payment->transaction_date - orbital only
	 *
 	 * @param total - total payment
 	 * @param conf - configuration file contents
 	 *
 	 *
 	 */
 	function payByCreditCard($data, $total, $conf, $isCredit = 0)
 	{
 		$result = new stdClass();
 		$result->value = 1;
 		$result->code = CodesUtility::TRANSACTION_CODE_SUCCESS;
 		$result->msg = "";
 		
 		$data->credit_card->cc_number = str_replace("-", "", $data->credit_card->cc_number); 		
 		$card_num = substr($data->credit_card->cc_number, -4);
		if(!isset($data->credit_card->code)){
			$data->credit_card->code = "";
		}

		$logger = new BaseLogger();
		$logger->logInfo("TOTAL PAYMENT BY CREDIT CARD BRO = ".$total);
			
		// $userid = AuthUtility::getCurrentUserId();
		$userid = $data->payer->user_id;
		CardPaymentUtility::logMsg(date('Y-m-d H:i:s') . " Verifying : " . $data->payer->last_name . " " . $data->credit_card->card_type . "-$card_num" . " userid: $userid ---------------------------------------------------------" );

		if(!$conf->payment->gateway_enabled){
			if($data->credit_card->expiration_year < date("Y")){
				$result->value = 0;
				$result->code = CodesUtility::TRANSACTION_CODE_ERROR_EXTERNAL;
				$result->msg = "Credit card expired";
				
				$errorOrbital = new Zend_Session_Namespace("errorOrbitalGateway");
				$errorOrbital->anyError = true;
				$errorOrbital->msg = $result->msg;
			} else {
				$result->value = 1;
				$result->code = CodesUtility::TRANSACTION_CODE_SUCCESS;
				$result->msg = "";
				$result->operation_xid = "DUMMYXID". date("YmdGis");
			}
		}else{
			$time_start = microtime(true);
			if ($conf->payment->payment_gateway == 'orbital'){
	 			$ret_result = CardPaymentUtility::payViaOrbitalGateway($data, $conf, $total, $isCredit);
	 		}elseif($conf->payment->payment_gateway == 'tc_gateway') {
				$ret_result = CardPaymentUtility::payViaTransactionCentralGateway($data, $conf, $total, $isCredit);
			}elseif($conf->payment->payment_gateway == 'authorizenet') {
				$ret_result = CardPaymentUtility::payViaAuthorizeNetGateway($data, $conf, $total, $isCredit);
			}elseif($conf->payment->payment_gateway == 'epay') {
				$ret_result = CardPaymentUtility::payViaUSAEPayGateway($data, $conf, $total, $isCredit);
			}elseif($conf->payment->payment_gateway == 'payline') {
				$ret_result = CardPaymentUtility::payViaPaylineGateway($data, $conf, $total, $isCredit);
			}
			$time_end = microtime(true);
			$time = $time_end - $time_start;
			
			$logger = new BaseLogger();
			$logger->logInfo("DEBUG CardPaymentUtility.php (" . __LINE__ . ") TIME QUERYING PAYMENT GW: " . $time . " SECONDS");
			
			if ($ret_result->result == 1){
				$result->value = 1;
				$result->code = CodesUtility::TRANSACTION_CODE_SUCCESS;
				$result->msg = "";
				$result->operation_xid = $ret_result->operation_xid;
				$result->order_id = $ret_result->order_id;
			}else{
				$result->value = 0;
				$result->code = CodesUtility::TRANSACTION_CODE_ERROR_EXTERNAL;
				$result->msg = $ret_result->result;
			}
		}

		Zend_Loader::loadClass("CardPaymentsLog");
		$cardPaymentsLog = new CardPaymentsLog();
		$cardPaymentsLog->payer_id = $data->payer->id;
		$cardPaymentsLog->user_id = $userid;
		$cardPaymentsLog->amount = $total;
		$cardPaymentsLog->operation_xid = $result->operation_xid;
		$tempReplaceStr = $data->credit_card->cc_number;
		$tempNewVal = substr($tempReplaceStr, -4);
		$cardPaymentsLog->cc_number = $tempNewVal;
		$cardPaymentsLog->credit = $isCredit;
		$cardPaymentsLog->save();

 		return $result;
 	}

 	function logResponse ($result)
	{
		ob_start ();
		//print_r($result);
		$res_print = ob_get_clean ();

		Zend_Loader::loadClass("Zend_Session_Namespace");
		$client_session = new Zend_Session_Namespace(SESSION_CLIENT);

		file_put_contents(CLIENT_DIR . 'logs/'.date("Ymd").'_card_log.txt',$res_print . "\n\r" , FILE_APPEND);
	}

	function logResult ($result)
	{
		Zend_Loader::loadClass("CADUtility");
		Zend_Loader::loadClass("Zend_Session_Namespace");
		$client_session = new Zend_Session_Namespace(SESSION_CLIENT);
		if($result){
			if(is_array($result)){
				$result = CADUtility::cadImplode($result,",");
				try {
					$result .= "\n\r" . serialize($result);
				} catch (Exception $e) {}
			}
		}

		file_put_contents(CLIENT_DIR . 'logs/'.date("Ymd").'_card_log.txt',$result . "\n\r" , FILE_APPEND);
	}


	function logParams ($params = array(),$post_url = "")
	{
		if (isset($params)) {
            // If fields is an array then turn it into a string.
            if (is_array($params)) {
                $sets = array();
                foreach ($params as $key => $val) {
                    $sets[] = $key . '=' . urlencode($val);
                }
                $fields = implode('&',$sets);
            } else {
                $fields = $params;
            }

			Zend_Loader::loadClass("Zend_Session_Namespace");
			$client_session = new Zend_Session_Namespace(SESSION_CLIENT);

			file_put_contents(CLIENT_DIR . '/logs/'.date("Ymd").'_card_params.txt',$fields . " " . $post_url . "\n\r", FILE_APPEND);
        }
	}

	function logXml ($xml)
	{
		Zend_Loader::loadClass("Zend_Session_Namespace");
		$client_session = new Zend_Session_Namespace(SESSION_CLIENT);

		file_put_contents(CLIENT_DIR . '/logs/'.date("Ymd").'_card_xml.txt',$xml .  "\n\r", FILE_APPEND);
	}

	function logMsg ($msg)
	{
		Zend_Loader::loadClass("Zend_Session_Namespace");
		$client_session = new Zend_Session_Namespace(SESSION_CLIENT);

		file_put_contents(CLIENT_DIR . '/logs/'.date("Ymd").'_card_log.txt',$msg . "\n\r" , FILE_APPEND);
	}

}
?>
