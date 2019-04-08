 <?php

 class PaymentUtility
 {

    public static function transactCreditCardPayment($amount , $payer_id, $payer_card_id,$payer_cbd_number=0,$additionals = array())
        {
            //prepare payer and card info
            $payerInfo = new Payer(true);
    
            $payerInfo->includeDeleted();
            $payerInfo->load($payer_id);


            $payer = new stdClass();
            $payer->id = $payerInfo->id;
 
            $payerContact = new PayerContact();

            $payerContact->loadByPayerIdContactType($payer_id , ContactType::CODE_EMAIL);
            $payer->email = $payerContact->value;

            $payerContact->loadByPayerIdContactType($payer_id , ContactType::CODE_DAYPHONE_EXT);
            $payer->contact_phone = $payerContact->value;
            $payerCard = new PayerCard(true);
            $payerCard->load($payer_card_id);
            $payerCard = (object) $payerCard->toArray();

            // alter - get data from payer card, since AVS need data from CC
            $payer->last_name  = $payerCard->last_name;
            $payer->first_name = $payerCard->first_name;
            $payer->zip = $payerCard->zip;
            $payer->city = $payerCard->city;
            $payer->country = 'US';
            $payer->state_code = $payerCard->state;
            $payer->address = $payerCard->address;

            $cardType = new CreditCardType();
            $cardType->load($payerCard->card_type_id);

            // get payer sec_key
            $user_id_payer = $payerInfo->user_id;
            $payer->user_id = $user_id_payer;
            
            $cckey = "";
            $client_session = new Zend_Session_Namespace(SESSION_CLIENT);
            $config = $client_session->config;

            $payerCardData = new PayerCardData();
            $ccNumber = $payerCardData->getPayerCardNumber($payer_card_id,$user_id_payer, $cckey);

            // override cc number if use payline
            // use cutomer vault instead
            $credit_card = new stdClass();
            if( $config->payment->gateway_enabled && $config->payment->payment_gateway == 'payline'){
                $ccNumber = "";
                $credit_card->customer_vault_id = $payerCard->customer_vault_id;
            } 

            $credit_card->card_type = $cardType->value;
            $credit_card->cc_number = str_replace(" ","",$ccNumber);
            $credit_card->expiration_month = date("m", strtotime($payerCard->expiration));
            $credit_card->expiration_year = date("Y", strtotime($payerCard->expiration));
            if($payer_cbd_number != 0)
                $credit_card->code = $payer_cbd_number;

            $card_data = new stdClass();
            $card_data->payer = $payer;
            $card_data->credit_card = $credit_card;

            //include invoice number --> changed to payment_id
            if (isset($additionals['payment_id']) && $additionals['payment_id']) {
                $card_data->payment_id = $additionals['payment_id'];
            }

            if (isset($additionals['hide_prompt']) && $additionals['hide_prompt'] != "") {
                $card_data->hide_prompt = $additionals['hide_prompt'];
            }

            if($additionals['is_refund'] == true) {
                $card_data->payment->transaction_date = $additionals['transaction_date'];
                $card_data->payment->operation_xid = $additionals['operation_xid'];
                $card_data->payment->refundable_amount = $additionals['refundable_amount'];
                $card_data->payment->order_id = $additionals['order_id'];
                $card_data->payment->any_refund_before = $additionals['any_refunded_transaction_before'];
                $result = CardPaymentUtility::payByCreditCard($card_data, $amount, $client_session->config, true);
            }
            else
                $result = CardPaymentUtility::payByCreditCard($card_data, $amount, $client_session->config, false);

            return $result;
        }

    }
