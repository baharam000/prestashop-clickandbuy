<?php
/**
 * $Id$
 *
 * clickandbuy Module
 *
 * Copyright (c) 2009 touchdesign
 *
 * @category Payment
 * @version 0.4
 * @copyright 01.12.2009, touchdesign
 * @author Christin Gruber, <www.touchdesign.de>
 * @link http://www.touchdesign.de/loesungen/prestashop/clickandbuy.htm
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * Description:
 *
 * Payment module clickandbuy by touchdesign
 *
 * --
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@touchdesign.de so we can send you a copy immediately.
 *
 */

class clickandbuy extends PaymentModule {

  private $_html = '';

  public function __construct() 
  {
    $this->name = 'clickandbuy';
    $this->tab = 'Payment';
    if (version_compare(_PS_VERSION_, '1.4.0', '<')){
      $this->tab = 'Payment';
    }else{
      $this->tab = 'payments_gateways';
    }
    $this->version = '0.4';
    $this->author = 'touchdesign';
	$this->module_key = '17cd5ba9ce0b7844a6357358a54c069c';
    $this->currencies = true;
    $this->currencies_mode = 'radio';
    parent::__construct();
    $this->page = basename(__FILE__, '.php');
    $this->displayName = $this->l('ClickandBuy');
    $this->description = $this->l('Accepts payments by ClickandBuy');
    $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
  }

  public function install() 
  {
    $sql = "CREATE TABLE "._DB_PREFIX_."clickandbuy(
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      status VARCHAR(30) NOT NULL,
      custRef INT NOT NULL,
      BDRID INT NOT NULL,
      externalBDRID VARCHAR(64),
      price INT NOT NULL,
      currency VARCHAR(3) NOT NULL,
      userIP VARCHAR(15) NOT NULL,
      date_submitted TIMESTAMP,
      PRIMARY KEY (id) 
    ) ENGINE=MyISAM default CHARSET=utf8";
    if(!Db::getInstance()->Execute($sql)){
      return false;
    }
    if (!parent::install() || 
      !Configuration::updateValue('CLICKANDBUY_TRANS_LINK', '') || 
      !Configuration::updateValue('CLICKANDBUY_TRANS_PASSWD', '') || 
      !Configuration::updateValue('CLICKANDBUY_MD5_KEY', Tools::passwdGen(20)) ||
      !Configuration::updateValue('CLICKANDBUY_SELLER_ID', '') ||     
      !Configuration::updateValue('CLICKANDBUY_OS_ERROR', 8) ||
      !Configuration::updateValue('CLICKANDBUY_OS_ACCEPTED', 2) ||
      !Configuration::updateValue('CLICKANDBUY_OS_PENDING', 3) ||
      !Configuration::updateValue('CLICKANDBUY_BLOCK_LOGO', 'Y') ||
      !Configuration::updateValue('CLICKANDBUY_CONFIRM_ORDER', 'Y') ||
      !$this->registerHook('payment') ||
      !$this->registerHook('leftColumn') ||
      !$this->registerHook('paymentReturn')){
      return false;
    }

    return true;
  }

  public function uninstall() 
  {
    if (!Configuration::deleteByName('CLICKANDBUY_TRANS_LINK') || 
      !Configuration::deleteByName('CLICKANDBUY_TRANS_PASSWD') ||
      !Configuration::deleteByName('CLICKANDBUY_MD5_KEY') ||      
      !Configuration::deleteByName('CLICKANDBUY_SELLER_ID') ||
      !Configuration::deleteByName('CLICKANDBUY_OS_ERROR') ||
      !Configuration::deleteByName('CLICKANDBUY_OS_ACCEPTED') ||
      !Configuration::deleteByName('CLICKANDBUY_OS_PENDING') ||
      !Configuration::deleteByName('CLICKANDBUY_BLOCK_LOGO') || 
      !Configuration::deleteByName('CLICKANDBUY_CONFIRM_ORDER') ||
      !parent::uninstall()){
      return false;
    }
    $sql = "DROP TABLE "._DB_PREFIX_."clickandbuy";
    if(!Db::getInstance()->Execute($sql)){
      return false;
    }
    return true;
  }

  private function _postValidation() 
  {
    if (Tools::getValue('submitUpdate')){
      if (!Tools::getValue('CLICKANDBUY_TRANS_LINK')){
        $this->_postErrors[] = $this->l('ClickandBuy "transaction link" is required.');
      }
      if (!Tools::getValue('CLICKANDBUY_TRANS_PASSWD')){
        $this->_postErrors[] = $this->l('ClickandBuy "transaction password" is required.');
      }
      if (!Tools::getValue('CLICKANDBUY_SELLER_ID')){
        $this->_postErrors[] = $this->l('ClickandBuy "seller id" is required.');
      }    
    }
  }

  public function getContent() 
  {
    $this->_html .= '<h2>'.$this->displayName.'</h2>';
    if (Tools::isSubmit('submitUpdate')){
      Configuration::updateValue('CLICKANDBUY_TRANS_LINK', Tools::getValue('CLICKANDBUY_TRANS_LINK'));
      Configuration::updateValue('CLICKANDBUY_TRANS_PASSWD', Tools::getValue('CLICKANDBUY_TRANS_PASSWD'));
      Configuration::updateValue('CLICKANDBUY_MD5_KEY', Tools::getValue('CLICKANDBUY_MD5_KEY'));      
      Configuration::updateValue('CLICKANDBUY_SELLER_ID', Tools::getValue('CLICKANDBUY_SELLER_ID'));
      Configuration::updateValue('CLICKANDBUY_BLOCK_LOGO', Tools::getValue('CLICKANDBUY_BLOCK_LOGO'));
      Configuration::updateValue('CLICKANDBUY_CONFIRM_ORDER', Tools::getValue('CLICKANDBUY_CONFIRM_ORDER'));
    } elseif (Tools::getValue('SellerID') && Tools::getValue('LinkURL') 
      && Tools::getValue('TMIPassword')){
      Configuration::updateValue('CLICKANDBUY_TRANS_LINK', Tools::getValue('LinkURL'));
      Configuration::updateValue('CLICKANDBUY_TRANS_PASSWD', Tools::getValue('TMIPassword'));
      Configuration::updateValue('CLICKANDBUY_SELLER_ID', Tools::getValue('SellerID'));
      $this->getSuccessMessage();
    }

    $this->_postValidation();
    if (isset($this->_postErrors) && sizeof($this->_postErrors)){
      foreach ($this->_postErrors AS $err){
        $this->_html .= '<div class="alert error">'. $err .'</div>';
      }
    }elseif(Tools::getValue('submitUpdate') && !isset($this->_postErrors)){
      $this->getSuccessMessage();
    }

    return $this->_displayForm();
  }

  public function getSuccessMessage()
  {
    $this->_html.='
    <div class="conf confirm">
      <img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />
      '.$this->l('Settings updated').'
    </div>';
  }

  private function getEmsPushScriptUrl() 
  {
    return (Configuration::get('PS_SSL_ENABLED') == 1 ? 'https://' : 'http://') 
      . $_SERVER['HTTP_HOST']._MODULE_DIR_.$this->name.'/emsPush.php';
  }

  private function getConfirmationUrl() 
  {
    return (Configuration::get('PS_SSL_ENABLED') == 1 ? 'https://' : 'http://')
      . $_SERVER['HTTP_HOST']._MODULE_DIR_.$this->name.'/validation.php';
  }

  private function getShopUrl() 
  {
    return (Configuration::get('PS_SSL_ENABLED') == 1 ? 'https://' : 'http://') 
      . $_SERVER['HTTP_HOST'].__PS_BASE_URI__;
  }

  private function getAutoRegisterUrl() 
  {
    $params = array(
      'portalid' => 'touchDesign',
      'shopurl' => $this->getShopUrl(),
      'shopname' => Configuration::get('PS_SHOP_NAME'),
      'configurationurl' => 'http://'
      . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
      'md5key' => Configuration::get('CLICKANDBUY_MD5_KEY'),
    );

    $urlTouchDesign= 'http://www.touchdesign.de/loesungen/prestashop/clickandbuy.htm?';
    foreach($params AS $k => $v){
      $urlTouchDesign .= $k.'='.urlencode($v)."&";
    }

    return substr($urlTouchDesign,0,-1);
  }

  private function _displayForm() 
  {
    $this->_html.= '
      <style type="text/css">
      fieldset a {
        color:#0099ff;
        text-decoration:underline;"
      }
      fieldset a:hover {
        color:#000000;
        text-decoration:underline;"
      }
      </style>';

    $this->_html.= '
      <div><img src="'.$this->_path.'logoBig.png" alt="logoBig.png" alt="logoBig.png" title="ClickandBuy" /></div>
      <br /><br />';

    $this->_html.= '
      <fieldset>
        <legend><img src="'.$this->_path.'logo.gif" />'.$this->l('Merchant Registration').'</legend>
        <div>
          '.$this->l('Automatic').' <a href="'.$this->getAutoRegisterUrl().'"><strong>'.$this->l('ClickandBuy Merchant Registration').'</strong></a>.
        </div>
      </fieldset>
      <br /><br />
      <div class="clear"></div>';

    $this->_html.= '
      <form method="post" action="'.$_SERVER['REQUEST_URI'].'">
      <fieldset>
        <legend><img src="'.$this->_path.'logo.gif" />'.$this->l('Settings').'</legend>
        
        <label>'.$this->l('ClickandBuy seller ID?').'</label>
        <div class="margin-form">
          <input type="text" name="CLICKANDBUY_SELLER_ID" value="'.Configuration::get('CLICKANDBUY_SELLER_ID').'" />
          <p>'.$this->l('Your Seller id').'</p>
        </div>
        <div class="clear"></div>

        <label>'.$this->l('ClickandBuy transaction link?').'</label>
        <div class="margin-form">
          <input type="text" name="CLICKANDBUY_TRANS_LINK" value="'.Configuration::get('CLICKANDBUY_TRANS_LINK').'" />
          <p>'.$this->l('Your Premiumlink').'</p>
        </div>
        <div class="clear"></div>       
        
        <label>'.$this->l('ClickandBuy MD5 Password?').'</label>
        <div class="margin-form">
          <input type="text" name="CLICKANDBUY_MD5_KEY" value="'.Configuration::get('CLICKANDBUY_MD5_KEY').'" />
          <p>'.$this->l('Leave it blank for disabling').'</p>
        </div>
        <div class="clear"></div>   
        
        <label>'.$this->l('ClickandBuy transaction password?').'</label>
        <div class="margin-form">
          <input type="text" name="CLICKANDBUY_TRANS_PASSWD" value="'.Configuration::get('CLICKANDBUY_TRANS_PASSWD').'" />
          <p>'.$this->l('Your transaction password').'</p>
        </div>
        <div class="clear"></div>

        <label>'.$this->l('ClickandBuy Logo?').'</label>
        <div class="margin-form">
          <select name="CLICKANDBUY_BLOCK_LOGO">
            <option '.(Configuration::get('CLICKANDBUY_BLOCK_LOGO') == "Y" ? "selected" : "").' value="Y">'.$this->l('Yes, display the logo (recommended)').'</option>
            <option '.(Configuration::get('CLICKANDBUY_BLOCK_LOGO') == "N" ? "selected" : "").' value="N">'.$this->l('No, do not display').'</option>
          </select>
          <p>'.$this->l('Display logo and payment info block in left column').'</p>
        </div>
        <div class="clear"></div>
        
        <label>'.$this->l('Enable order confirmation?').'</label>
        <div class="margin-form">
          <select name="CLICKANDBUY_CONFIRM_ORDER">
            <option '.(Configuration::get('CLICKANDBUY_CONFIRM_ORDER') == "Y" ? "selected" : "").' value="Y">'.$this->l('Yes, let customer confirm orders (recommended)').'</option>
            <option '.(Configuration::get('CLICKANDBUY_CONFIRM_ORDER') == "N" ? "selected" : "").' value="N">'.$this->l('No, redirect direct').'</option>
          </select>
          <p>'.$this->l('Customer have to confirm their order before redirect to clickandbuy').'</p>
        </div>
        <div class="clear"></div>';
        
    $this->_html.= '
        <div class="margin-form clear pspace"><input type="submit" name="submitUpdate" value="'.$this->l('Update').'" class="button" /></div>
      </fieldset>
      </form>
      <div class="clear"></div>';

    $this->_html.= '
      <br /><br />
      <fieldset>
        <legend><img src="'.$this->_path.'logo.gif" />'.$this->l('URLs').'</legend>
        <b>'.$this->l('Confirmation-Url:').'</b><br /><textarea rows=1 style="width:98%;">'.$this->getConfirmationUrl().'</textarea>
        <b>'.$this->l('emsPush-Script:').'</b><br /><textarea rows=1 style="width:98%;">'.$this->getEmsPushScriptUrl().'</textarea>
      </fieldset>';

    $this->_html.= '
      <fieldset class="space">
        <legend><img src="../img/admin/unknown.gif" alt="" class="middle" />'.$this->l('Help').'</legend>   
        '.$this->l('@Link:').' <a target="_blank" href="http://www.touchdesign.de/loesungen/prestashop/clickandbuy.htm">www.touchdesign.de</a><br />
        '.$this->l('@Vendor:').' ClickandBuy International Limited<br />
        '.$this->l('@Author:').' <a target="_blank" href="http://www.touchdesign.de/loesungen/prestashop/clickandbuy.htm">touchdesign</a><br />
      </fieldset><br />';

    return $this->_html;
  }

  public function hookPayment($params) 
  {
    global $smarty;
    
    if(Configuration::get('CLICKANDBUY_CONFIRM_ORDER') == "Y"){
      $smarty->assign(array('gateway' => Tools::getHttpHost(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/payment.php'));
    }else{
      $smarty->assign(array('gateway' => $this->getPremiumLink($params['cart'])));
    }
    
    return $this->display(__FILE__, 'clickandbuy.tpl');
  }

  function hookLeftColumn($params) 
  {
    if(Configuration::get('CLICKANDBUY_BLOCK_LOGO') == "N")
      return false;

    return $this->display(__FILE__, 'blockclickandbuylogo.tpl');
  }

  public function hookPaymentReturn($params) 
  {
    global $smarty;

    $orderObj = $params['objOrder'];
    $orderTotal = $params['total_to_pay'];
    $orderCurrency = $params['currency'];
    $currencyObj = $params['currencyObj'];

    if ($orderObj->module != $this->name || !$this->active){
      return false;
    }

    $state = $params['objOrder']->getCurrentState();
    if ($state == Configuration::get('CLICKANDBUY_OS_PENDING') || 
      $state == Configuration::get('CLICKANDBUY_OS_ACCEPTED')){
      $smarty->assign(array(
        'status' => 'accepted',
        'total_to_pay' => Tools::displayPrice($orderTotal, $currencyObj, false, false),
        'id_order' => $params['objOrder']->id
      ));
    }else{
      $smarty->assign('status', 'failed');
    }

    return $this->display(__FILE__, 'confirmation.tpl');
  } 

  public function isPayment() 
  {
    if (!$this->active)
      return false;
    if (!Configuration::get('CLICKANDBUY_TRANS_LINK'))
      return $this->l($this->displayName.' Error: (invalid or undefined transaction link)');
    if (!Configuration::get('CLICKANDBUY_SELLER_ID'))
      return $this->l($this->displayName.' Error: (invalid or undefined transaction link)');

    return true;
  }

  public function switchOrderState($cartId, $orderState, $amount=NULL, $message=NULL) 
  {
    $orderId = Order::getOrderByCartId($cartId);
    if ($orderId){
      $order = new Order($orderId);

      if($order->getCurrentState() != $orderState){
        $history = new OrderHistory();
        $history->id_order = $orderId;
        $history->changeIdOrderState($orderState, $orderId);
        $history->addWithemail(true);    

        if($message !== NULL){
          $orderMessage = new Message();
          $orderMessage->message = $message;
          $orderMessage->private = 1;
          $orderMessage->id_order = $orderId;
          $orderMessage->add();
        }
        
        return 1;
      }
    } else {
      if (version_compare(_PS_VERSION_, '1.4.0', '<')){
        $this->validateOrder($cartId, $orderState, $amount, 
          $this->displayName, $message, null, null, false);
      }else{
        $cart = new Cart($cartId);
        $customer = new Customer($cart->id_customer);
        $this->validateOrder($cartId, $orderState, $amount, 
          $this->displayName, $message, null, null, false, $customer->secure_key);
      }
      return 0;
    }

    return false;
  }

  function getEMSOrderState($action) 
  {
    switch ($action) {
      case 'payment_successful':
      case 'charge back lifted':
      case 'BDR successfully collected from collection agency':
      case 'booked-in':
        return Configuration::get('CLICKANDBUY_OS_ACCEPTED');
      case 'BDR not collected from collection agency':
      case 'booked-out':
      case 'cancelled':
        return Configuration::get('CLICKANDBUY_OS_ERROR');
      case 'BDR to collection agency':
      case 'charge back':
        return Configuration::get('CLICKANDBUY_OS_PENDING');
      default:
        return false;
    }
  }  

  public function execPayment($cart)
  {
    global $smarty;
    
    $smarty->assign(array('gateway' => $this->getPremiumLink($cart), 
      'nbProducts' => $cart->nbProducts(),
      'total' => number_format(Tools::convertPrice($cart->getOrderTotal(), $currency), 2, '.', '')));

    return $this->display(__FILE__, 'payment_execution.tpl');
  }
  
  private function getPremiumLink($cart)
  {
    $isPayment = $this->isPayment();
    if($isPayment !== true){
      return $this->l($isPayment);
    }

    $addressInvoice = new Address(intval($cart->id_address_invoice));
    $addressDelivery = new Address(intval($cart->id_address_delivery));
    $customer = new Customer(intval($cart->id_customer));
    $currency = new Currency(intval($cart->id_currency));
    $countryInvoice = new Country(intval($addressInvoice->id_country));
    $countryDelivery = new Country(intval($addressDelivery->id_country));
    $lang = Language::getIsoById(intval($cart->id_lang));

    if (!Validate::isLoadedObject($addressInvoice) || !Validate::isLoadedObject($addressDelivery) 
      || !Validate::isLoadedObject($customer) || !Validate::isLoadedObject($currency)){
      return $this->l($this->displayName.' Error: (invalid address or customer)');
    }

    $timestamp = time();
    $premiumLink = Configuration::get('CLICKANDBUY_TRANS_LINK');

    $params = array(
      'price' => number_format(Tools::convertPrice($cart->getOrderTotal(), $currency), 2, '.', ''), 
      'cb_currency' => $currency->iso_code, 
      'externalBDRID' => $cart->id,
      'cb_content_name_utf' => $this->l('CartId:').' '.$timestamp.intval($cart->id),
      'cb_content_info_utf' => $customer->firstname.' '.ucfirst(strtolower($customer->lastname)),
      'lang' => $lang,
      'cb_billing_FirstName' => $addressInvoice->firstname,
      'cb_billing_LastName' => $addressInvoice->lastname,
      'cb_billing_Street' => $addressInvoice->address1,
      'cb_billing_City' => $addressInvoice->city,
      'cb_billing_ZIP' => $addressInvoice->postcode,
      'cb_billing_Nation' => $countryInvoice->iso_code,
      'cb_shipping_FirstName' => $addressDelivery->firstname,
      'cb_shipping_LastName' => $addressDelivery->lastname,
      'cb_shipping_Street' => $addressDelivery->address1,
      'cb_shipping_City' => $addressDelivery->city,
      'cb_shipping_ZIP' => $addressDelivery->postcode,
      'cb_shipping_Nation' => $countryDelivery->iso_code,
    );

    $query="";
    foreach($params AS $k => $v){
      $query .= $k."=".urlencode($v)."&";
    }

    $url = $premiumLink.'?'.substr($query,0,-1);
    if(Configuration::get('CLICKANDBUY_MD5_KEY')){
      $url.='&fgkey='.md5(Configuration::get('CLICKANDBUY_MD5_KEY') . "/" . basename($url));
    }
    
    return $url;
  }
}

?>