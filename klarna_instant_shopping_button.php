<?php
require 'vendor/autoload.php';

class KlarnaShoppingButton {
    private $baseUrl = "https://api.playground.klarna.com";
    function generateButtonKey(){
        $client = new GuzzleHttp\Client();
            $res = $client->request('POST', $this->baseUrl.'/instantshopping/v1/buttons',['auth' => ['PK04149_9ef50d19b0e3', 'S3Pl4Di5ovDw0711'], 'json' => [
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
        echo '<script src="https://x.klarnacdn.net/instantshopping/lib/v1/lib.js" async></script>';
       
    }
    function InitiateButton(){
        ?>

            <script>
            window.klarnaAsyncCallback = function () {
                Klarna.InstantShopping.load({
                "purchase_country": "SE",
                "purchase_currency": "SEK",
                "locale": "sv-se",
                "merchant_urls": {
                    "terms": "https://test.com"
                },
                "order_lines": [{
                    "type": "physical",
                    "reference": "ref",
                    "name": "testname",
                    "quantity": 1,
                    "unit_price": 10000,
                    "tax_rate": 0,
                    "total_amount": 10000,
                    "total_discount_amount": 0,
                    "total_tax_amount": 0,
                    "image_url": "https://test.com"
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
                    console.log('Klarna.InstantShopping.load callback with data:' + JSON.stringify(response))
                })
            };
            </script>
        <?php
    }
    function TestRender(){
       $button= $this->generateButtonKey();
       $this->renderButton($button);
       $this->InitiateButton();
    }
    function GetOrderDetailsFromKlarna($authToken){
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $this->baseUrl.'/instantshopping/v1/authorizations/'.$authToken);
        echo $res->getBody();
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
}
