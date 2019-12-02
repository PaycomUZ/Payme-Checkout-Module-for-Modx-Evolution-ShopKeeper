<?php
if ($runMode==1) {
 
return array(
        'paymeMerchantId'      => $modx->getOption('paymeMerchantId', $scriptProperties, 0),
        'paymeTestMode' 	   => $modx->getOption('paymeTestMode', $scriptProperties, 0),
		'paymePassword'        => $modx->getOption('paymePassword', $scriptProperties, 0),
		'paymePasswordForTest' => $modx->getOption('paymePasswordForTest', $scriptProperties, 0) 
    ) ;

} else if ($runMode==2) {
	
	$modx->addPackage('shopkeeper3', $modx->getOption('core_path').'components/shopkeeper3/model/');
	 
	$orderId = $modx->getOption('id', $_SESSION['shk_lastOrder'], null);
	$order   = $modx->getObject('shk_order', $orderId);
 

	if (($_SESSION['shk_lastOrder']['payment'] != 'payme') && (!$_GET['ord_id'])) {
		$modx->sendRedirect('/checkout/success.html', 0, 'REDIRECT_HEADER');
	}

	if (isset($order)) {
		 
		$amount = round($order->get('price') * 100);
		$paymeTestMode = $modx->getOption('paymeTestMode', $scriptProperties, 0);
		
			 if ($paymeTestMode=="yes") $paymeUrl=$modx->getOption('paymeCheckoutUrlForTest', $scriptProperties, 0);
		else if ($paymeTestMode=="no")  $paymeUrl=$modx->getOption('paymeCheckoutUrl', $scriptProperties, 0);


		$paymeUrl=$paymeUrl.'/'.base64_encode(
												'ac.order_id='.$orderId.
												';a=' .$amount.
												';cr='.'860'.
												';m=' .$modx->getOption('paymeMerchantId', $scriptProperties, 0).
												';ct='.$modx->getOption('paymeAfterPayment', $scriptProperties, 0).
												';c=' .$modx->getOption('paymeReturnUrl', $scriptProperties, 0)
											);

		$modx->sendRedirect($paymeUrl,array('type' => 'REDIRECT_META'));
	}

} // end run mode 2