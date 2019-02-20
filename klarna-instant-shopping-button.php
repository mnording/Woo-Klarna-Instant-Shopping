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
class KlarnaShoppingButton {
    private $baseUrl = "https://api.playground.klarna.com";
    private $wooTranslate;
    private $logContext;
    private $logger;
    function __construct(){
        $this->wooTranslate = new KlarnaWooTranslator();
        add_action( 'woocommerce_init',  array($this,'init') ); 
        add_action("woocommerce_before_add_to_cart_form",array($this,'InitAndRender'));
        add_action( 'rest_api_init', function () {
            register_rest_route( 'klarna-instant-shopping', '/place-order/', array(
              'methods' => 'POST',
              'callback' => array($this,'HandleOrderPostBack'),
            ) );
          } );
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
            $res = $client->request('POST', $this->baseUrl.'/instantshopping/v1/buttons',['verify' => true,'auth' => ['PK04149_9ef50d19b0e3', 'S3Pl4Di5ovDw0711'], 'json' => [
                "merchant_urls" => [
                    "terms"=> "https://instantshopping.mnording.com/",
                    "notification" => "https://instantshopping.mnording.com/notify",
                    "confirmation"=> "https://instantshopping.mnording.com/",
                    "push"=> "https://instantshopping.mnording.com/push",
                    "update"=> "https://instantshopping.mnording.com/wp-json/klarna-instant-shopping/update",
                    "place_order"=> "https://instantshopping.mnording.com/wp-json/klarna-instant-shopping/place-order"
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
        echo '<klarna-instant-shopping data-key="'.$buttonID.'" data-environment="playground" data-region="eu"></klarna-instant-shopping>';       
    }
    function InitiateButton(){
        global $product;
        $id = $product->get_id();

        $image = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'woocommerce_thumbnail' );
        $imageUlr = ($image[0]);
        wp_add_inline_script('woo_klarna_instant-shopping','window.klarnaAsyncCallback = function () {
    Klarna.InstantShopping.load({
    "purchase_country": "SE",
    "purchase_currency": "SEK",
    "locale": "sv-se",
    "merchant_urls": {
        "terms": "https://test.com"
    },
    "order_lines": [{
        "type": "physical",
        "reference": "'.$product->get_sku().'",
        "name": "'.$product->get_name().'",
        "quantity": 1,
        "unit_price": '.intval($product->get_price()*100).',
        "tax_rate": 0,
        "total_amount": '.intval($product->get_price()*100).',
        "total_discount_amount": 0,
        "total_tax_amount": 0,
        "image_url": "'.$imageUlr.'"
    }],
    "shipping_options": [{
        "id": "express",
        "name": "EXPRESS 1-2 Days",
        "description": "mandatory, helpful text, e.g. Delivery by 4:30 pm >",
        "price": 100,
        "tax_amount": 0,
        "tax_rate": 0,
        "preselected": true,
        "shipping_method": "PickUpPoint"
    }]
    }, function (response) {
        console.log("Klarna.InstantShopping.load callback with data:" + JSON.stringify(response))
    })
};','before');
            
    }
    function GetShippingOptionsForProduct($prod){
        $prod->get_shipping_class_id();
    }
    function InitAndRender(){
       $button= "c85062dc-5e9d-4209-a6ab-ce1e26c3aac0"; /* $this->generateButtonKey(); */
       $this->enqueScripts();
       $this->renderButton($button);
       $this->InitiateButton();
    }
    function GetOrderDetailsFromKlarna($authToken){
        $client = new GuzzleHttp\Client();
        
        $res = $client->request('GET', $this->baseUrl.'/instantshopping/v1/authorizations/'.$authToken,['auth' => ['PK04149_9ef50d19b0e3', 'S3Pl4Di5ovDw0711']]);
       
        return json_decode($res->getBody());
    }


    function HandleOrderPostBack($request_data){
        $this->logger->debug( 'Got postback with successfull auth ', $this->logContext );
        $this->logger->debug($request_data->get_body(), $this->logContext );
        $req = json_decode($request_data->get_body());

        $klarnaorder = $this->GetOrderDetailsFromKlarna($req->authorization_token);
        $this->logger->debug( 'Got Klarna Order Details', $this->logContext );
        if($this->VerifyOrder($klarnaorder)){
            echo "in verufy";
           $WCOrderId =  $this->CreateWcOrder($klarnaorder);
           $this->logger->debug( 'Created WC order '.$WCOrderId, $this->logContext );
           $klarnaorder = $this->PlaceOrder($req->authorization_token,$klarnaorder);
           $this->logger->debug( 'Created Klarna order '.$klarnaorder, $this->logContext );
           $this->UpdateWCOrder($WCOrderId,$klarnaorder);
            $this->logger->debug( 'Updated WC Order '.$WCOrderId.' with klarna order id '.$klarnaorder, $this->logContext );
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
            "deny_message"=> $message]]);
        
    }

    function PlaceOrder($auth,$order){
        $client = new GuzzleHttp\Client();
        echo "befor placing order towards klarna";
        $res = $client->request('POST', $this->baseUrl.'/instantshopping/v1/authorizations/'.$auth.'/orders/',
        ['json'=>$order,'auth' => ['PK04149_9ef50d19b0e3', 'S3Pl4Di5ovDw0711']]);
        
        $order = json_decode($res->getBody());
        return $order->order_id;
    }
    function VerifyOrder($klarnaOrder){
        $this->verifyStockLevels();
        $this->verifyShipping();
        return true;
    }
    function verifyStockLevels(){

    }
    function verifyShipping(){
        
    }
    function UpdateWCOrder($orderid,   $klarnaId){
      $order =   wc_update_order( array("order_id" => $orderid,"set_paid" =>true,"transaction_id" => $klarnaId, "payment_method"=>"Klarna"));
      $order->save();
    }
    function CreateWcOrder($klarnaOrderObject){
        //https://gist.github.com/stormwild/7f914183fc18458f6ab78e055538dcf0
        global $woocommerce;
        
        $address = $this->wooTranslate->GetWooAdressFromKlarnaOrder($klarnaOrderObject);
       
        // Now we create the order
        try {
                $order = wc_create_order();
               
                $order->add_product( get_product('9'), 1); 
                $order->set_address( $address, 'billing' );
                $order->calculate_totals();
                $order->update_status("pending", 'Imported order', TRUE);  
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        return $order->get_id();
        
    }
    
}
$t = new KlarnaShoppingButton();