<?php

class Icube_Vpayment_PaymentController extends Mage_Core_Controller_Front_Action {
  
  /**
   * Process Action - Redirect User to veritrans payment gateway
   * 
   * <veritrans_payment_process>
   * 
   * @return  void;
   * @see   <package>/<themes>/layout/veritrans.xml
   */
  public function processAction() {
    $session = Mage::getSingleton('core/session');
    if ($session->getTokenBrowser() == null) {
      $this->_redirect('vpayment/payment/unauthorized');
    } else {
      $this->loadLayout();
      $this->getLayout()->getBlock('head')->setTitle($this->__('Veritrans Payment Gateway'));
      $this->renderLayout();
    }
  }
  
  /**
   * Success Action - Customer successfully paid their order.
   *
   * <veritrans_payment_success>
   *
   * @return  void;
   * @see   <package>/<themes>/layout/veritrans.xml
   */
  public function successAction() {
    $this->loadLayout();
    $this->getLayout()->getBlock('head')->setTitle($this->__('Veritrans Payment Gateway'));
    $this->renderLayout();
  }
  
  /**
   * Cancel Action - Customer cancelled their order.
   *
   * <veritrans_payment_cancel>
   *
   * @return  void;
   * @see   <package>/<themes>/layout/veritrans.xml
   */
  public function cancelAction() {
    $this->loadLayout();
    $this->getLayout()->getBlock('head')->setTitle($this->__('Veritrans Payment Gateway'));
    $this->renderLayout();
  }
  
  /**
   * Error Action
   *
   * <veritrans_payment_error>
   *
   * @return  void;
   * @see   <package>/<themes>/layout/veritrans.xml
   */
  public function errorAction() {
    $this->loadLayout();
    $this->getLayout()->getBlock('head')->setTitle($this->__('Veritrans Payment Gateway'));
    $this->renderLayout();
  }
  
  /**
   * Unauthorized Action
   *
   * <veritrans_payment_unauthorized>
   *
   * @return  void;
   * @see   <package>/<themes>/layout/veritrans.xml
   */
  public function unauthorizedAction() {
    $message = $this->__("Whoaa there!");
    Mage::getSingleton('core/session')->addError($message);
    
    $this->loadLayout();
    $this->getLayout()->getBlock('head')->setTitle($this->__('Veritrans Unauthorized'));
    $this->renderLayout();
  }
  
  /**
   * Notification Action - Handle Incoming data from veritrans
   *
   * @return  void;
   * @see   <package>/<themes>/layout/veritrans.xml
   */
  public function notificationAction() {
    $checkout = Mage::getSingleton('checkout/session');
    $postdata = Mage::app()->getRequest()->getPost();
    
      Mage::log('POST:'.print_r($postdata,true),null,'POST_notif.log',true);
  }
  
  /**
   * Create Invoice for successfull veritrans Payment
   * 
   * @param string $orderIncrementId
   */
  protected function _createInvoice($orderIncrementId){
    $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
    $itemsQty = count($order->getAllItems());
    
    $invoice = $order->prepareInvoice($itemsQty);
    $invoice->register();
    $invoice->setOrder($order);
    $invoice->setEmailSent(true);
    $invoice->getOrder()->setIsInProcess(true);
    $invoice->pay();
    $invoice->save();
    $order->save();
  
    return $invoice->getIncrementId();
  }
  
  /**
   * Clear Veritrans Session
   * 
   * @return void
   */
  protected function _clearVeritransSession() {
    $session = Mage::getSingleton('core/session');
    
    $session->unsTokenBrowser();
    $session->unsVeritransQuoteId();
  }

    protected function getQuoteGrandTotalAction() {
        $id = $this->getRequest()->getPost('id');
        $quote = Mage::getModel('sales/quote')->load($id);
        //calculate Giftcard
        /*$giftcards = Mage::helper('aw_giftcard/totals')->getQuoteGiftCards($quote->getId());
        $total_giftcard = 0;
        $giftcard_item_balance = 0;
        foreach($giftcards as $giftcard) {
            Mage::log('giftcard:'.print_r($giftcard,true),null,'Giftcard.log',true);
            $total_giftcard += $giftcard->getGiftcardAmount();
        }
        Mage::log("total_giftcard:".$total_giftcard,null,"GT_aj.log",true);
        $base_subtotal = $quote->getBaseSubtotal();
        Mage::log("base_subtotal:".$base_subtotal,null,"GT_aj.log",true);
        if($total_giftcard > $base_subtotal) {
            $total_giftcard = $base_subtotal;
        }
        Mage::log("total_giftcard:".$total_giftcard,null,"GT_aj.log",true);
        $items_size = 0;
        foreach ($items as $itemId => $item){
            if ($item->getPrice() > 0 && $item->getQty() > 0)
                $items_size += $item->getQty();
        }
        Mage::log("items_size:".$items_size,null,"GT_aj.log",true);
//        Mage::log('$items->getSize():'.$items_size,null,'$items_size.log',true);
        if($total_giftcard > 0 && $items_size > 0) {
            $giftcard_item_balance = $total_giftcard/$items_size;
        }
        Mage::log("giftcard_item_balance:".$giftcard_item_balance,null,"GT_aj.log",true);*/
                //$price -= $giftcard_item_balance;
        $gt = intval(round($quote->getData()['grand_total']));
        Mage::log("GRAND TOTAL without disc:".$gt,null,"GT_aj.log",true);
        echo $gt;
    }
}