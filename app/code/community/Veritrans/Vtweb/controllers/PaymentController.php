<?php
/**
 * Veritrans VT Web Payment Controller
 *
 * @category   Mage
 * @package    Mage_Veritrans_VtWeb_PaymentController
 * @author     Kisman Hong (plihplih.com), Ismail Faruqi (@ifaruqi_jpn)
 * This class is used for handle redirection after placing order.
 * function redirectAction -> redirecting to Veritrans VT Web
 * function responseAction -> when payment at Veritrans VT Web is completed or failed, the page will be redirected to this function, 
 * you must set this url in your Veritrans MAP merchant account. http://yoursite.com/vtweb/payment/notification
 */

require_once(Mage::getBaseDir('lib') . '/veritrans-php/veritrans.php');
require_once(Mage::getBaseDir('lib') . '/veritrans-php/lib/veritrans_notification.php');

class Veritrans_Vtweb_PaymentController extends Mage_Core_Controller_Front_Action {
    const VERITRANS_PARAM_ALPHABET_PATTERN = 'a-zA-Z';
    const VERITRANS_PARAM_NUMERIC_PATTERN  = '0-9';
    const VERITRANS_PARAM_SYMBOL_PATTERN = '\-_\'\ \,\.\@';
    const STATE_PENDING   = 'pending';
    const TRANSACTION_STATUS_CAPTURE   = 'capture';

	/**
   * @return Mage_Checkout_Model_Session
   */
	protected function _getCheckout() {
		return Mage::getSingleton('checkout/session');
	}
	
	// The redirect action is triggered when someone places an order, redirecting to Veritrans payment page.
	public function redirectAction() {
		$orderIncrementId = $this->_getCheckout()->getLastRealOrderId();
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		$sessionId = Mage::getSingleton("core/session");
        $asAllowedPattern  = '/[^' . self::VERITRANS_PARAM_ALPHABET_PATTERN . self::VERITRANS_PARAM_NUMERIC_PATTERN
            . self::VERITRANS_PARAM_SYMBOL_PATTERN . ']*/';
		
		/* send an order email when redirecting to payment page although payment has not been completed. */
		$order->setState(Mage::getStoreConfig('payment/vtweb/'), true, 'New order, waiting for payment.');
		$order->sendNewOrderEmail();
		$order->setEmailSent(true); 

		$api_version = Mage::getStoreConfig('payment/vtweb/api_version');
		$payment_type = Mage::getStoreConfig('payment/vtweb/payment_types');
		
		$veritrans = new Veritrans;

		// general settings
		$veritrans->api_version = 2;
		// $veritrans->payment_type = ($payment_type == 'vtdirect' ? Veritrans::VT_DIRECT : Veritrans::VT_WEB);
		$veritrans->payment_type = Veritrans::VT_WEB;
        $veritrans->payment_methods = array("mandiri_clickpay");
		$veritrans->environment = (Mage::getStoreConfig('payment/vtweb/environment') == 'production' ? Veritrans::ENVIRONMENT_PRODUCTION : Veritrans::ENVIRONMENT_DEVELOPMENT);

		// v1-specific
		$veritrans->merchant_id = Mage::helper('vtweb/data')->_getMerchantID();
		$veritrans->merchant_hash_key = Mage::helper('vtweb/data')->_getMerchantHashKey();

		// v2-specific
		$veritrans->client_key = Mage::getStoreConfig('payment/vtweb/client_key_v2');
		$veritrans->server_key = Mage::getStoreConfig('payment/vtweb/server_key_v2');

		// $veritrans->settlement_type = '01'; // unused in the new stack
        $prefix = Mage::getStoreConfig('payment/vtweb/prefix_id');
        $veritrans->order_id = $prefix.$orderIncrementId;
		// $veritrans->session_id = $sessionId->getSessionId(); // unused in the new stack
		// Gross amount must be total of commodities price
		// $veritrans->gross_amount = (int)$order->getBaseGrandTotal(); // no need to set the gross amount in the new library

		$veritrans->required_shipping_address = 1;	
		$veritrans->billing_address_different_with_shipping_address = 1;

		$veritrans->first_name = $order->getBillingAddress()->getFirstname();
		$veritrans->last_name = $order->getBillingAddress()->getLastname();
		$veritrans->email = $order->getBillingAddress()->getEmail();
		$veritrans->address1 = $order->getBillingAddress()->getStreet(1);
		$veritrans->address2 = $order->getBillingAddress()->getStreet(2);
        $city = substr(preg_replace($asAllowedPattern, '',
                preg_replace('/(Kab.[ ]*|Kota[ ]*)/', '',
                    $order->getBillingAddress()->getCity())
            ), 0, 20
        );
		$veritrans->city = $city;
        $country_code = $order->getBillingAddress()->getCountry();
        $country = Mage::getModel('directory/country')->loadByCode($country_code);
		$veritrans->country_code = $country->getIso3Code(); // this is hard coded because magento and veritrans country code is not the same.
		$veritrans->postal_code = $order->getBillingAddress()->getPostcode();
		$veritrans->phone = $order->getBillingAddress()->getTelephone();
		
		if(!$order->getShippingAddress()){
		    $veritrans->shipping_first_name = $order->getBillingAddress()->getFirstname();
		    $veritrans->shipping_last_name = $order->getBillingAddress()->getLastname();
		    $veritrans->shipping_address1 = $order->getBillingAddress()->getStreet(1);
		    $veritrans->shipping_address2 = $order->getBillingAddress()->getStreet(2);
		    $city = substr(preg_replace($asAllowedPattern, '',
			    preg_replace('/(Kab.[ ]*|Kota[ ]*)/', '',
				$order->getBillingAddress()->getCity())
			), 0, 20
		    );
		    $veritrans->shipping_city = $city;
		    $country_code = $order->getBillingAddress()->getCountry();
		    $country = Mage::getModel('directory/country')->loadByCode($country_code);
		    $veritrans->shipping_country_code = $country->getIso3Code(); // this is hard coded because magento and veritrans country code is not the same.
		    $veritrans->shipping_postal_code = $order->getBillingAddress()->getPostcode();
		    $veritrans->shipping_phone = $order->getBillingAddress()->getTelephone();
		}else{
		    $veritrans->shipping_first_name = $order->getShippingAddress()->getFirstname();
		    $veritrans->shipping_last_name = $order->getShippingAddress()->getLastname();
		    $veritrans->shipping_address1 = $order->getShippingAddress()->getStreet(1);
		    $veritrans->shipping_address2 = $order->getShippingAddress()->getStreet(2);
		    $city = substr(preg_replace($asAllowedPattern, '',
			    preg_replace('/(Kab.[ ]*|Kota[ ]*)/', '',
				$order->getShippingAddress()->getCity())
			), 0, 20
		    );
		    $veritrans->shipping_city = $city;
		    $country_code = $order->getShippingAddress()->getCountry();
		    $country = Mage::getModel('directory/country')->loadByCode($country_code);
		    $veritrans->shipping_country_code = $country->getIso3Code(); // this is hard coded because magento and veritrans country code is not the same.
		    $veritrans->shipping_postal_code = $order->getShippingAddress()->getPostcode();
		    $veritrans->shipping_phone = $order->getShippingAddress()->getTelephone();
		}

        //BINS
        $promo_bins = Mage::helper('vtweb/data')->_getPromoBins();
        if(!empty($promo_bins)) {
            $promo_bins_arr = explode(',',$promo_bins);
            if(count($promo_bins_arr) > 0)
                $veritrans->promo_bins = $promo_bins_arr;
        }
        $acquiring_bank = Mage::helper('vtweb/data')->_getAcquiringBank();
        if(!empty($acquiring_bank))
            $veritrans->bank = $acquiring_bank;
		$veritrans->enable_3d_secure = Mage::getStoreConfig('payment/vtweb/enable_3d_secure');
		
		// $bank = Mage::helper('vtweb/data')->_getInstallmentBank();
		// $veritrans->installment_banks = array($bank);
        $install_terms = Mage::helper('vtweb/data')->_getInstallmentTerms();
        if(!empty($install_terms)) {
            $bank_terms = explode(';', $install_terms);
            $veritrans->installment_terms = array();
            foreach($bank_terms as $bank_term) {
                $terms = explode(':', $bank_term);
                $bank2 = $terms[0];
                $terms = explode(',', $terms[1]);
                $veritrans->installment_terms = array_merge($veritrans->installment_terms,array($bank2 => $terms));
            }
        }
	
		$items = $order->getAllItems();		
		$shipping_amount = (int)$order->getShippingAmount();
		$shipping_tax_amount = (int) (int)$order->getShippingTaxAmount();

		$commodities =  array();
		foreach ($items as $itemId => $item)
		{
            $priceAfterDiscount = (float) $item->getPrice() - (float) $item->getDiscountAmount();
            if($item->getPrice() > 0 && $item->getQtyToInvoice() > 0 && $priceAfterDiscount > 0) {
                array_push($commodities,
                    array(
                        "item_id" => $item->getProductId(),
                        "price" => (int)$priceAfterDiscount,
                        "quantity" => $item->getQtyToInvoice(),
                        "item_name1" => $item->getName(),
                        "item_name2" => $item->getName(),
                    ));
            }
        }
		
		if($shipping_amount > 0)
		{
			array_push($commodities, 
				array(
					"item_id" => 'SHIPPING', 
					"price" => $shipping_amount, 
					"quantity" => 1, 
					"item_name1" => 'Shipping Cost', 
					"item_name2" => 'Shipping Cost',
					));
		}
		
		if($shipping_tax_amount > 0)
		{
			array_push($commodities, 
				array(
					"item_id" => 'SHIPPING_TAX', 
					"price" => $shipping_tax_amount, 
					"quantity" => 1, 
					"item_name1" => 'Shipping Tax', 
					"item_name2" => 'Shipping Tax',
					));
		}

        if($gift_wrap_amount > 0){
            array_push($commodities,
                array(
                    "item_id" => 'GIFT_WRAP',
                    "price" => $gift_wrap_amount,
                    "quantity" => 1,
                    "item_name1" => 'Gift Wrap Amount',
                    "item_name2" => 'Gift Wrap Amount',
                ));
        }
		// convert to IDR
		$current_currency = Mage::app()->getStore()->getCurrentCurrencyCode();
		if ($current_currency != 'IDR')
		{
			$idr_exist = in_array('IDR', Mage::app()->getStore()->getAvailableCurrencyCodes());
			if ($idr_exist)
			{
				// attempt to use the built-in currency converter
				$conversion_func = function($non_idr_price) use ($current_currency) { return Mage::helper('directory')->currencyConvert($non_idr_price, $current_currency, 'IDR'); };
			} else
			{
				$conversion_func = function($non_idr_price) { return $non_idr_price * Mage::getStoreConfig('payment/vtweb/conversion_rate'); };
			}
			foreach ($commodities as &$item) {
	      $item['price'] = intval(round(call_user_func($conversion_func, $item['price'])));
	    }
		}		

		$veritrans->items = $commodities;
        Mage::log('$veritrans:'.print_r($veritrans,true),null,'$veritrans.log',true);
		$keys = $veritrans->getTokens();
        Mage::log('$keys:'.print_r($keys,true),null,'$keys.log',true);

		if ($api_version == 2)
		{
			if ($payment_type == 'vtdirect')
			{

			} else
			{
				// vtweb
				if ($keys && $keys['status_code'] == 201)
				{
					$this->_redirectUrl($keys['redirect_url']);
				} else
				{
					var_dump($keys);
					exit;
				}
			}
		} else
		{
			if ($payment_type == 'vtdirect')
			{

			} else
			{
				$payment = $order->getPayment();
				$payment->setTokenMerchant($keys['token_merchant'])->save();
				
				$this->loadLayout();
				$block = $this->getLayout()->createBlock('Mage_Core_Block_Template','vtweb',array('template' => 'vtweb/redirect.phtml'));
				$block->setData('token_browser', $keys['token_browser']);
				$block->setData('merchant_id', $veritrans->merchant_id);
				$block->setData('redirect_url', Veritrans::PAYMENT_REDIRECT_URL);
				$this->getLayout()->getBlock('content')->append($block);
				$this->getResponse()->setBody($block->toHtml());
				//$this->renderLayout(); 				
			}
			
		}		
		
	}
	
	// The response action is triggered when your gateway sends back a response after processing the customer's payment, we will not update to success because success is valid when notification (security reason)
	public function responseAction() {
		//var_dump($_POST); use for debugging value.
        $prefix = Mage::getStoreConfig('payment/vtweb/prefix_id');
		if($this->getRequest()->isPost()) {
            Mage::log('POST:'.print_r($_POST,true),null,'responseAction.log',true);
			$orderId = $_POST['order_id']; // Generally sent by gateway
            $orderId = str_replace($prefix,'',$orderId);
			$status = $_POST['status_code'];
			if($status == 'success' && !is_null($orderId) && $orderId != '') {
				// Redirected by Veritrans, if ok
				Mage::getSingleton('checkout/session')->unsQuoteId();				
				Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
			}
			else {
				// There is a problem in the response we got
				$this->cancelAction();
				Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure'=>true));
			}
		}
        else {
            Mage::log('GET:'.print_r($_GET,true),null,'responseAction.log',true);
            $orderId = $_GET['order_id']; // Generally sent by gateway
            $orderId = str_replace($prefix,'',$orderId);
            $status = $_GET['status_code'];
            if($status == '200' && !is_null($orderId) && $orderId != '') {
                // Redirected by Veritrans, if ok
                Mage::getSingleton('checkout/session')->unsQuoteId();
                Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
            } else if($status == '201' && !is_null($orderId) && $orderId != '') {
                //set order to review state
                $new_order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
                $new_order->setStatus(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
                $new_order->save();

                // Redirected by Veritrans, if ok
                Mage::getSingleton('checkout/session')->unsQuoteId();
                Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
            }
            else {
                // Back to merchant - reorder
                Mage_Core_Controller_Varien_Action::_redirect('vtweb/payment/reorder', array('_secure'=>true));
            }
            //			Mage_Core_Controller_Varien_Action::_redirect('');
        }
	}
	
	// Veritrans will send notification of the payment status, this is only way we make sure that the payment is successed, if success send the item(s) to customer :p 
	public function notificationAction() {

		$notification = new VeritransNotification();

		if (Mage::getStoreConfig('payment/vtweb/api_version') == 2)
		{
            if($this->getRequest()->isPost()) {
                $prefix = Mage::getStoreConfig('payment/vtweb/prefix_id');
                $post = Mage::app()->getRequest()->getPost();
                Mage::log('POST:'.print_r($post,true),null,'notificationAction.log',true);
                $value = json_decode(file_get_contents('php://input'));
                Mage::log('POST - php://input:'.print_r($value,true),null,'notificationAction.log',true);
                $orderId = $value->order_id; // Generally sent by gateway
                Mage::log('POST - $orderId:'.$orderId,null,'notificationAction.log',true);
                $orderId = str_replace($prefix,'',$orderId);
                $status = $value->transaction_status;
                if($status == Veritrans_Vtweb_PaymentController::TRANSACTION_STATUS_CAPTURE && !is_null($orderId) && $orderId != '') {
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($orderId);
                    $order->setStatus(Veritrans_Vtweb_PaymentController::STATE_PENDING);
                    $order->save();
                } else if($status == 'cancel' && !is_null($orderId) && $orderId != '') {
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($orderId);
                    if($order->canCancel()) {
                        $order->cancel()->save();
                    }
                    $order->save();
                }
            }
            return true;
		} else
		{
			$orderId = $notification->orderId; // Sent by Veritrans gateway
			$order = Mage::getModel('sales/order');
			$order->loadByIncrementId($orderId);
			$payment = $order->getPayment();
			$tokenMerchant = $payment->getTokenMerchant();

			if($notification->mStatus == 'success' && $tokenMerchant == $notification->TOKEN_MERCHANT) { 

				//update status
				$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Gateway has successed the payment.');				
				$order->sendOrderUpdateEmail(true, '<b>Payment Received Successfully!</b>');
				$paymentDueDate = date("Y-m-d H:i:s");
				$payment->setPaymentDueDate($paymentDueDate);
				$order->save();
				
				Mage::getSingleton('checkout/session')->unsQuoteId();	

				return true;
			}
			else
			{
				//do nothing
				return true;
			}	
		}
		
	}
	
	// The cancel action is triggered when an order is to be cancelled
	public function cancelAction() {
		if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
		    $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
		    if($order->getId()) {
			// Flag the order as 'cancelled' and save it
		        $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
		    }
		}
	}

    public function reorderAction() {
        $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
        if($order->canCancel()){
            $order->cancel()->save();
        }
        $entity_id = $order->getEntityId();
        $this->_redirect('sales/order/reorder/order_id/'.$entity_id);
    }
}

?>
