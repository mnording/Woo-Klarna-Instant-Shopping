<?php
/*
Plugin Name: Woo Klarna Instant Shopping
Plugin URI: https://wordpress.org/plugins/woo-klarna-shopping/
Description: Adds Klarna Instant shopping button to your product pages
Version: 0.1.0
Author: mnording10
Author URI: https://mnording.com
Text Domain: woo-klarna-instant-shopping
Domain Path: /languages
*/
require 'vendor/autoload.php';
require 'klarna-woo-translator.php';
require 'woo-settings-page.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
class KlarnaShoppingButton {
    private $baseUrl = "https://api.playground.klarna.com";
    private $wooTranslate;
    private $logContext;
    private $logger;
    private $username;
    private $pass;
    private $settingspage;
    function __construct(){
        $this->wooTranslate = new KlarnaWooTranslator();
        $this->settingspage = new WooKlarnaInstantShoppingSettingsPage();
        $this->username = $this->settingspage->getmid();
        $this->pass=$this->settingspage->getpass();
        add_action( 'woocommerce_init',  array($this,'init') ); 
        add_action("woocommerce_before_add_to_cart_form",array($this,'InitAndRender'));
        add_action( 'rest_api_init', function () {
            register_rest_route( 'klarna-instant-shopping', '/place-order/', array(
              'methods' => 'POST',
              'callback' => array($this,'HandleOrderPostBack'),
            ) );
          } );

          add_action('admin_menu', array($this,'CreateOptionsPage'));
          
    }
   
        function CreateOptionsPage() {
        add_options_page('Klarna Instant Shopping', 'Klarna Instant Shopping', 'manage_options', 'woo-klarna-instant-shopping', array($this->settingspage,'RenderKlarnaSettingsPage'));
        }

    function init(){
        $this->logger  = wc_get_logger();
      $this->logContext = array( 'source' => 'woo-klarna-instant-shopping' );
    }
    function enqueScripts(){
        wp_enqueue_script("woo_klarna_instant-shopping","https://x.klarnacdn.net/instantshopping/lib/v1/lib.js");
    }
    function generateButtonKey(){
        $client = new GuzzleHttp\Client();
            $res = $client->request('POST', $this->baseUrl.'/instantshopping/v1/buttons',['verify' => true,'auth' => [$this->username, $this->pass], 'json' => [
                "merchant_urls" => [
                    "place_order"=> get_site_url()."/wp-json/klarna-instant-shopping/place-order"
                ]
            ]]);
            echo $res->getBody();
            $buttonUrl =  $res->getHeader('Location')[0];
            $matches = array();
            
            preg_match('/.*\/buttons\/([a-z0-9-]*)/', $buttonUrl, $matches);
            $buttonID = $matches[1];
            return $buttonID;
    }
    function renderButton($buttonID){
        $testmode = $this->settingspage->getTestmode();
        $enviournment = $testmode ?"playground": "production";
        echo '<klarna-instant-shopping data-key="'.$buttonID.'" data-environment="'.$enviournment.'" data-region="eu"></klarna-instant-shopping>';       
    }

    function InitiateButton(){
        global $product;
        $id = $product->get_id();
        $image = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'woocommerce_thumbnail' );
                $imageUlr = ($image[0]);
                $productPrice = $product->get_price();
                WC()->cart->empty_cart();
                WC()->cart->add_to_cart( $id );
                foreach(WC()->cart->get_cart() as $cartitem){
                    
                    $klarnaTaxAmount= ($cartitem["line_tax_data"]["subtotal"][1]);
                }
                $vat = $klarnaTaxAmount / ($productPrice-$klarnaTaxAmount);
                $shippingMethods = $this->GetShippingMethodsFroKlarna($productPrice,$vat);
        if($product->get_type() == "simple"){
               $this->LoadJSForSimple($product,$klarnaTaxAmount,$imageUlr,$vat,$shippingMethods);
            } 
            if($product->get_type() == "variable"){   
            $this->LoadJsForVariable($product,$shippingMethods);
            }    
    }
    function LoadJSForSimple($product,$klarnaTaxAmount,$imageUlr,$vat,$shippingMethods){    
        wp_add_inline_script('woo_klarna_instant-shopping','window.klarnaAsyncCallback = function () {
            Klarna.InstantShopping.load({
            "purchase_country": "SE",
            "purchase_currency": "'.get_woocommerce_currency().'",
            "locale": "sv-se",
            "merchant_urls": {
            "terms": "'.rtrim(get_permalink( woocommerce_get_page_id( "terms" ) ),'/').'",  
            },
            "order_lines": [{
                "type": "physical",
                "reference": "'.$product->get_sku().'",
                "name": "'.$product->get_name().'",
                "quantity": 1,
                "merchant_data": "{\"prod_id\":'.$product->get_id().'}",
                "unit_price": '.intval($product->get_price()*100).',
                "tax_rate": '.intval($vat*10000).',
                "total_amount": '.intval($product->get_price()*100).',
                "total_discount_amount": 0,
                "total_tax_amount": '.intval($klarnaTaxAmount*100).',
                "image_url": "'.$imageUlr.'"
            }],
            "shipping_options": 
                '.json_encode($shippingMethods).'
                
            }, function (response) {
                console.log("Klarna.InstantShopping.load callback with data:" + JSON.stringify(response))
            })
        };','before');
    }
    function LoadJsForVariable($product,$shippingMethods){    
        //TODO: Solve tax for variations
        wp_add_inline_script('woo_klarna_instant-shopping','jQuery( ".single_variation_wrap" ).on( "show_variation", function ( event, variation ) {
            console.log(variation);
            var extraprodname = "";
            for (var key in variation.attributes) {
                extraprodname += variation.attributes[key]+" ";
            }
            Klarna.InstantShopping.load({
                "purchase_country": "SE",
                "purchase_currency": "'.get_woocommerce_currency().'",
                "locale": "sv-se",
                "merchant_urls": {
                "terms": "'.rtrim(get_permalink( woocommerce_get_page_id( "terms" ) ),'/').'",  
                },
                "order_lines": [{
                "type": "physical",
                "reference": variation.sku,
                "name": "'.$product->get_name().' "+extraprodname,
                "quantity": 1,
                "merchant_data": "{\"prod_id\":'.$product->get_id().',\"variation_id\":"+variation.variation_id+"}",
                "unit_price": variation.display_price*100,
                "tax_rate": 0,
                "total_amount": variation.display_price*100,
                "total_discount_amount": 0,
                "total_tax_amount": 0,
                "image_url": variation.image.src
            }],
            "shipping_options": 
                    '.json_encode($shippingMethods).'
                
            }, function (response) {
                console.log("Klarna.InstantShopping.load callback with data:" + JSON.stringify(response))
            });
        } );','after');
    }
    function GetShippingOptionsForProduct($prod){
        $prod->get_shipping_class_id();
    }
    function InitAndRender(){
       $button= "050252a7-4c34-4c49-a9d7-f2ea9307d71a";// $this->generateButtonKey(); 
       $this->logger->debug( 'Rendering button with buttonId '.$button, $this->logContext );
       
       $this->enqueScripts();
       $this->renderButton($button);
       $this->InitiateButton();
    }
    function GetOrderDetailsFromKlarna($authToken){
        $client = new GuzzleHttp\Client();
        
        $res = $client->request('GET', $this->baseUrl.'/instantshopping/v1/authorizations/'.$authToken,['auth' => [$this->username, $this->pass]]);
        $this->logger->debug( 'Got order details from klarna ', $this->logContext );
        $this->logger->debug( $res->getBody(), $this->logContext );
        return json_decode($res->getBody());
    }


    function HandleOrderPostBack($request_data){
        $this->logger->debug( 'Got postback with successfull auth ', $this->logContext );
        $this->logger->debug($request_data->get_body(), $this->logContext );
        $req = json_decode($request_data->get_body());

        $klarnaorder = $this->GetOrderDetailsFromKlarna($req->authorization_token);
        $this->logger->debug( 'Got Klarna Order Details', $this->logContext );
        if($this->VerifyOrder($klarnaorder)){
            
           $WCOrder =  $this->CreateWcOrder($klarnaorder);
           $WCOrderId = $WCOrder->get_id();
           $this->logger->debug( 'Created WC order '.$WCOrderId, $this->logContext );
           $klarnaorderID = $this->PlaceOrder($req->authorization_token,$klarnaorder,$WCOrder);
           $this->logger->debug( 'Created Klarna order '.$klarnaorderID, $this->logContext );
           $this->UpdateWCOrder($WCOrderId,$klarnaorderID);
            $this->logger->debug( 'Updated WC Order '.$WCOrderId.' with klarna order id '.$klarnaorderID, $this->logContext );
        }
        else {
            $this->DenyOrder($req->authorization_token,"other","Could not place order");
        }
    }
    /*
    - address_error when there are problems with the provided address,
    - item_out_of_stock when the item has gone out of stock,
    - consumer_underaged when the product has limitations based on the age of the consumer,
    - unsupported_shipping_address when there are problems with the shipping address. You don’t need to specify a deny_message for the above codes.
    - other for which you may specify a deny_message which will be shown to the consumer. It is important that the language of the message matches the locale of the Instant Shopping flow
    */
    function DenyOrder($auth,$code,$message){
        $client = new GuzzleHttp\Client();
        $res = $client->request('DELETE', $this->baseUrl.'/instantshopping/v1/authorizations/'.$auth,
        ['json'=>[
            "deny_code"=> $code, 
            "deny_message"=> $message],'auth' => [$this->username, $this->pass]]);
        
    }

    function PlaceOrder($auth,$order,$wcorder){
        $client = new GuzzleHttp\Client();
       
        $wcorderUrl = $wcorder->get_checkout_order_received_url();
       $order->merchant_urls->confirmation = $wcorderUrl;
       
        echo "befor placing order towards klarna";
        $res = $client->request('POST', $this->baseUrl.'/instantshopping/v1/authorizations/'.$auth.'/orders/',
        ['json'=>$order,'auth' => [$this->username, $this->pass]]);
        
        $order = json_decode($res->getBody());
        return $order->order_id; 
    }
    function VerifyOrder($klarnaOrder){
        $this->verifyStockLevels($klarnaOrder);
        $this->verifyShipping($klarnaOrder);
        return true;
    }
    function verifyStockLevels($klarnaOrder){

    }
    function verifyShipping($klarnaOrder){
        
    }
    function GetShippingMethodsFroKlarna($productPrice,$vat){
        $shippingMethods=array();
        foreach($this->GetShippingMethodsForAmount($productPrice,"SE") as $methods){
            $shippingPrice = intval(round($methods["price"])*100);
            $shippingMethods[] = array(
                    "id"=> $methods["id"],
                    "name"=> $methods["name"],
                    "description"=> "",
                    "price"=> $shippingPrice + intval($shippingPrice*$vat) ,
                    "tax_amount"=> intval($shippingPrice*$vat),
                    "tax_rate"=> intval($vat*10000),
                    "preselected"=> true,
                    "shipping_method"=> "PickUpPoint");
                };
                return $shippingMethods;
    }
    function GetShippingMethodsForAmount($amount,$country) {

        $active_methods   = array();
        $values = array ('country' => $country,
                         'amount'  => $amount);
    
    
        // Fake product number to get a filled card....
        WC()->cart->add_to_cart('1');
    
        WC()->shipping->calculate_shipping($this->get_shipping_packages($values));
        $shipping_methods = WC()->shipping->packages;
    
        foreach ($shipping_methods[0]['rates'] as $id => $shipping_method) {
            $active_methods[] = array(  'id'        => $shipping_method->method_id,
                                        'type'      => $shipping_method->method_id,
                                        'provider'  => $shipping_method->method_id,
                                        'name'      => $shipping_method->label,
                                        'price'     => number_format($shipping_method->cost, 2, '.', ''));
        }
        return $active_methods;
    }
    function get_shipping_packages($value) {

        // Packages array for storing 'carts'
        $packages = array();
        $packages[0]['contents']                = WC()->cart->cart_contents;
        $packages[0]['contents_cost']           = $value['amount'];
        $packages[0]['applied_coupons']         = WC()->session->applied_coupon;
        $packages[0]['destination']['country']  = $value['country'];
        $packages[0]['destination']['state']    = '';
        $packages[0]['destination']['postcode'] = '';
        $packages[0]['destination']['city']     = '';
        $packages[0]['destination']['address']  = '';
        $packages[0]['destination']['address_2']= '';
    
    
        return apply_filters('woocommerce_cart_shipping_packages', $packages);
    }
    function UpdateWCOrder($orderid,   $klarnaId){
      $order =  wc_get_order( $orderid);
      $order->payment_complete($klarnaId);
      $order->set_payment_method("kco");
      $order->set_payment_method_title("Klarna");
      $order->update_status("processing", 'Got Klarna OK', TRUE);  
      $order->save();
    }
    function CreateWcOrder($klarnaOrderObject){
        //https://gist.github.com/stormwild/7f914183fc18458f6ab78e055538dcf0
        global $woocommerce;
        
        $address = $this->wooTranslate->GetWooAdressFromKlarnaOrder($klarnaOrderObject);
        $this->logger->debug( 'Got address from klarna object ', $this->logContext );
        $this->logger->debug( json_encode($address), $this->logContext );
        $orderlines = $this->wooTranslate->GetWCLineItemsFromKlarnaOrder($klarnaOrderObject);
        $this->logger->debug( 'Got line items from klarna object ', $this->logContext );
        $this->logger->debug( json_encode($orderlines), $this->logContext );
        $shippinglines = $this->wooTranslate->GetWCShippingLinesFromKlarnaOrder($klarnaOrderObject);
        $this->logger->debug( 'Got shipping lines from klarna object ', $this->logContext );      
        $this->logger->debug( json_encode($shippinglines), $this->logContext );
        // Now we create the order
        try {
                $order = wc_create_order();
                $item = new WC_Order_Item_Shipping();

                $item->set_method_title( $shippinglines["name"] ); 
                $item->set_method_id( $shippinglines["id"] ); 
                $item->set_total( $shippinglines["price"]);
                $order->add_item( $item );
               foreach($orderlines as $line){
                   if($line["variation_id"]){
                    $membershipProduct = new WC_Product_Variable($line["product_id"]);
                    $theMemberships = $membershipProduct->get_available_variations();
                    
                    $variationsArray = array();
                    
                    foreach ($theMemberships as $membership) {
                        if ($membership['variation_id'] == $line["variation_id"]) {
                            $variationID = $membership['variation_id'];
                            $variationsArray['variation'] = $membership['attributes'];
                        }
                    }
                    
                    if ($variationID) {
                        $varProduct = new WC_Product_Variation($variationID);
                        $order->add_product($varProduct, 1, $variationsArray);
                    }
                   }
                   else {
                    $order->add_product( get_product($line["product_id"]), $line["quantity"]); 
                   }
                
               }
                $order->set_address( $address, 'billing' );
                $order->set_address( $address, 'shipping' );
                $order->calculate_totals();
                $order->update_status("pending", 'Imported order', TRUE);  
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        return $order;
        
    }
    
}
$t = new KlarnaShoppingButton();