<?php
/**
 * @brief		Jahanpay Gateway
 * @author		<a href='http://skinod.com.com'>Skinod</a>
 * @copyright	(c) 2015 Skinod.com
 */

require_once '../../../../init.php';
\IPS\Session\Front::i();

try
{
	$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->order_id );
	
	if ( $transaction->status !== \IPS\nexus\Transaction::STATUS_PENDING )
	{
		throw new \OutofRangeException;
	}
}
catch ( \OutOfRangeException $e )
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=payments&controller=checkout&do=transaction&id=&t=" . \IPS\Request::i()->order_id, 'front', 'nexus_checkout', \IPS\Settings::i()->nexus_https ) );
}

try
{

	$res = $transaction->method->api(
		array(
			'amount' 				=> $transaction->amount->amount / 10,
			'au' 					=> \IPS\Request::i()->au,
		), TRUE
	);

	if(!empty($res) and $res == 1){
		$transaction->gw_id = \IPS\Request::i()->au;
		$transaction->save();
		$transaction->checkFraudRulesAndCapture( NULL );
		$transaction->sendNotification();
		\IPS\Session::i()->setMember( $transaction->invoice->member ); // This is in case the checkout was a guest, meaning checkFraudRulesAndCapture() may have just created an account. There is no security issue as we have just verified they were just bounced back from Jahanpay
		\IPS\Output::i()->redirect( $transaction->url() );
	}
	
	throw new \OutofRangeException;	
}
catch ( \Exception $e )
{
	\IPS\Output::i()->redirect( $transaction->invoice->checkoutUrl()->setQueryString( array( '_step' => 'checkout_pay', 'err' => $transaction->member->language()->get( 'gateway_err' ) ) ) );
}