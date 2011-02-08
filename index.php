<?php
// You need to have reference transactions enabled
// to enable it on sandbox account, you need to ask it here: https://www.x.com/thread/38753
// To enable reference transactions on live site you need to contact PayPal Business Services Group

require_once "lib/PaypalRecurringPayments.php";

$gateway = new PaypalGateway();
$gateway->apiUsername = "YOUR API USERNAME HERE";
$gateway->apiPassword = "YOUR API PASSWORD HERE";
$gateway->apiSignature = "YOUR API SIGNATURE HERE";
$gateway->testMode = true;

// Return (success) and cancel url setup
$gateway->returnUrl = "http://test.site/?action=success";
$gateway->cancelUrl = "http://test.site/?action=cancel";

$recurring = new PaypalRecurringPayments($gateway);

switch ($_GET['action']) {
	case "": // Index page, here you should be redirected to Paypal
		$resultData = array();
		$isOk = $recurring->obtainBillingAgreement("Test subscription", "testuser@gmail.com", 'USD', $resultData);
		if (!$isOk) {
			print_r($resultData);
		}
		break;
	
	case "success": // Paypal says everything's fine (see $gateway->returnUrl)
		$resultData = array();
		$details = $recurring->getBillingDetails($resultData);
		if (!$details) {
			echo "Something went wrong\n";
			print_r($resultData);
			return;
		}
		$billingAgreementId = $recurring->doInitialPayment($details->token, $details->payerId, 12.34, $resultData);
		if (!$billingAgreementId) {
			echo "Something went wrong\n";
			print_r($resultData);
			return;
		}
		echo "agreementId = ".$billingAgreementId;
		break;
	
	// Type ?action=test in browser to perform a subscription (reference) transaction
	case "test":
		$resultData = array();
		$billingAgreementId = 'B-5YW327438T794174S';
		// To perform payments you need to store billing agreement ID in your database
		$isOk = $recurring->doSubscriptionPayment($billingAgreementId, 21.17, $resultData);
		if ($isOk) {
			echo "Success!";
		} else {
			print_r($resultData);
		}
		break;
	
	case "cancel": // User cancel subscription process (see $gateway->cancelUrl)
		echo "User canceled";
		break;
}

?>