<?php
require 'vendor/autoload.php';
class ButtonGenerator
{
    private $username;
    private $pass;
    private $baseUrl;
    function __construct($user, $pass, $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->username = $user;
        $this->pass = $pass;
    }
    function generateButtonKey()
    {
        $client = new \GuzzleHttp\Client();
      
        $res = $client->post( $this->baseUrl . '/instantshopping/v1/buttons', ['verify' => true, 'auth' => [$this->username, $this->pass], 'json' => [
            "merchant_urls" => [
                "place_order" => get_site_url() . "/wp-json/klarna-instant-shopping/place-order"
            ]
        ], 'headers' => [
            'User-Agent' => 'Mnording Instant Shopping WP-Plugin',
        ]]);
        $buttonUrl =  $res->getHeader('Location');
       
        $matches = array();

        preg_match('/.*\/buttons\/([a-z0-9-]*)/', $buttonUrl, $matches);
        $buttonID = $matches[1];
        return $buttonID;
    }
}
?>