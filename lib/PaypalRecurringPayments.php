<?php
//  PaypalRecurringPayments.php
//  PaypalRecurringPayments
//
// Copyright 2011 Roman Efimov <romefimov@gmail.com>
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//    http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
//
// Documentation and useful links:
// https://www.x.com/docs/DOC-1208#id0848H0Q0P5Z__id089O10Y0FPN
// https://www.x.com/thread/38753

require_once "HTTPRequest.php";
require_once "PaypalGateway.php";
require_once "PaypalBillingDetails.php";

class PaypalRecurringPayments {
	
	private $gateway;
	private $endpoint = '/nvp';
	
	public function __construct(PaypalGateway $gateway) {
		$this->gateway = $gateway;
	}
	
	private function response($data){
		$request = new HTTPRequest($this->gateway->getHost(), $this->endpoint, 'POST', true);
		$result = $request->connect($data);
		if ($result < 400) return $request;
		return false;
	}
	
	private function responseParse($resp) {
		$a = explode("&", $resp);
		$out = array();
		foreach ($a as $v) {
			$k = strpos($v, '=');
			if ($k) {
				$key = trim(substr($v, 0, $k));
				$value = trim(substr($v, $k+1));
				if (!$key) continue;
				$out[$key] = urldecode($value);
			} else {
				$out[] = $v;
			}
		}
		return $out;
	}
	
	private function buildQuery($data = array()) {
		$data['USER'] = $this->gateway->apiUsername;
		$data['PWD'] = $this->gateway->apiPassword;
		$data['SIGNATURE'] = $this->gateway->apiSignature;
		$data['VERSION'] = '65.0';
		$data['RETURNURL'] = $this->gateway->returnUrl;
		$data['CANCELURL']  = $this->gateway->cancelUrl;
		$query = http_build_query($data);
		return $query;
	}
	
	
	private function runQueryWithParams($data) {
		$query = $this->buildQuery($data);
		$result = $this->response($query);
		if (!$result) return false;
		
		$response = $result->getContent();
		$return = $this->responseParse($response);
		$return['ACK'] = strtoupper($return['ACK']);
		return $return;
	}
	
	public function obtainBillingAgreement($description = '', $predefinedEmail = '', $currency = 'USD', &$resultData = array()) {
		$data = array('METHOD' => 'SetExpressCheckout',
					  'LOCALECODE' => 'US',
				  	  'EMAIL' => $predefinedEmail,
					  'AMT' => 0, // For recurring payments must be 0
					  'CURRENCYCODE' => $currency,
					  'L_BILLINGTYPE0' => 'MerchantInitiatedBilling',
					  'L_BILLINGAGREEMENTDESCRIPTION0' => $description,
					  'NOSHIPPING' => 1);
		
		if (!$resultData = $this->runQueryWithParams($data)) return false;

		if ($resultData['ACK'] == 'FAILURE' && $resultData['L_ERRORCODE0'] == 11452)
			throw new Exception("You need to have 'reference transactions' enabled on your account. See https://www.x.com/thread/38753");
			
		if ($resultData['ACK'] == 'FAILURE') return false;
		
		if ($resultData['ACK'] == 'SUCCESS') {
			header('Location: '.$this->gateway->getGate().'cmd=_express-checkout&token='.$resultData['TOKEN']);
			exit();
		}
		return true;
	}
	
	public function getBillingDetails(&$resultData = array()) {
		$token = $_GET['token'];
		$data = array('METHOD' => 'GetExpressCheckoutDetails',
					  'TOKEN' => $token);
		if (!$resultData = $this->runQueryWithParams($data)) return false;
		
		if ($resultData['ACK'] == 'FAILURE') return false;
		
		$details = new PaypalBillingDetails();
		$details->email = $resultData['EMAIL'];
		$details->payerId = $resultData['PAYERID'];
		$details->token = $resultData['TOKEN'];
		$details->billingAgreementAccepted = intval($resultData['BILLINGAGREEMENTACCEPTEDSTATUS']);
		return $details;
	}
	
	public function doInitialPayment($token, $payerId, $amount, &$resultData = array()) {
		$data = array('METHOD' => 'DoExpressCheckoutPayment',
					  'PAYERID' => $payerId,
					  'TOKEN' => $token,
					  'AMT' => floatval($amount),
					  'PAYMENTACTION' => 'Sale');
		
		if (!$resultData = $this->runQueryWithParams($data)) return false;
		
		if ($resultData['ACK'] == 'FAILURE')
			return false;
		
		$bId = $resultData['BILLINGAGREEMENTID'];
		return $bId ? $bId : false;
	}
	
	public function doSubscriptionPayment($billingAgreementId, $amount, &$resultData = array()) {
		$data = array('METHOD' => 'DoReferenceTransaction',
					  'REFERENCEID' => $billingAgreementId,
					  'AMT' => floatval($amount),
					  'PAYMENTACTION' => 'Sale');
		
		if (!$resultData = $this->runQueryWithParams($data)) return false;
		if ($resultData['ACK'] == 'SUCCESS') return true;
		return false;
	}
	
}


?>