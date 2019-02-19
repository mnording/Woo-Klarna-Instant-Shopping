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
    function __construct(){
        $wooTranslate = new KlarnaWooTranslator();
        add_action("woocommerce_before_add_to_cart_form",array($this,'InitAndRender'));
        add_action( 'rest_api_init', function () {
            register_rest_route( 'klarna-instant-shopping', '/order/', array(
              'methods' => 'POST',
              'callback' => array($this,'HanleOrderPostBack'),
            ) );
          } );
    }
    function enqueScripts(){
        wp_enqueue_script("woo_klarna_instant-shopping","https://x.klarnacdn.net/instantshopping/lib/v1/lib.js");
    }
    function generateButtonKey(){
        $client = new GuzzleHttp\Client();
            $res = $client->request('POST', $this->baseUrl.'/instantshopping/v1/buttons',['verify' => false,'auth' => ['PK04149_9ef50d19b0e3', 'S3Pl4Di5ovDw0711'], 'json' => [
                "merchant_urls" => [
                    "terms"=> "https://wwww.example.com/terms",
                    "notification" => "https://wwww.example.com/notify",
                    "confirmation"=> "https://wwww.example.com/",
                    "push"=> "https://wwww.example.com/push",
                    "update"=> "https://wwww.example.com/place-order",
                    "place_order"=> "https://wwww.example.com/place-order"
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
       $button= $this->generateButtonKey();
       $this->enqueScripts();
       $this->renderButton($button);
       $this->InitiateButton();
    }
    function GetOrderDetailsFromKlarna($authToken){
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $this->baseUrl.'/instantshopping/v1/authorizations/'.$authToken);
        echo $res->getBody();
    }


    function HanleOrderPostBack($auth){
        $klarnaorder = $this->GetOrderDetailsFromKlarna($auth);
        if($this->VerifyOrder($klarnaorder)){
            $this->CreateWcOrder($klarnaorder);
            $this->PlaceOrder($auth);
        }
        else {
            $this->DenyOrder($auth,"other","Could not place order");
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
        echo $res->getBody();
    }

    function PlaceOrder($auth,$order){
        $client = new GuzzleHttp\Client();
        $res = $client->request('POST', $this->baseUrl.'/instantshopping/v1/authorizations/'.$auth.'/orders/',
        ['json'=>$order]);
        echo $res->getBody();
        
    }
    function VerifyOrder($klarnaOrder){
        $this->verifyStockLevels();
        $this->verifyShipping();
    }
    function CreateWcOrder($klarnaOrderObject){
        //https://gist.github.com/stormwild/7f914183fc18458f6ab78e055538dcf0
        global $woocommerce;

        $address = $wooTranslate->GetWooAdressFromKlarnaOrder($klarnaOrderObject);

        // Now we create the order
        $order = wc_create_order();

        // The add_product() function below is located in /plugins/woocommerce/includes/abstracts/abstract_wc_order.php
        $order->add_product( get_product('275962'), 1); // This is an existing SIMPLE product
        $order->set_address( $address, 'billing' );
        //
        $order->calculate_totals();
        $order->update_status("processing", 'Imported order', TRUE);  
    }
    
}
$t = new KlarnaShoppingButton();