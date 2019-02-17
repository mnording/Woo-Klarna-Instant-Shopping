<?php 
class KlarnaWooTranslator{
    function GetWCLineItemsFromKlarnaOrder($klarnaOrder){
        $newlineitems = array();
        foreach($klarnaOrder["order_lines"] as $orderline){
            if($orderline["type"] != "shipping_fee"){
            $newlineitems[] = array(
                "name" =>$orderline["name"],
                "product_id" => json_decode($orderline["merchant_data"])->prod_id,
                "variation_id" => json_decode($orderline["merchant_data"])->variation_id,
               "quantity" => $orderline["quantity"],
               "price" => (int)($orderline["unit_price"] / 100),
               "sku" => $orderline["reference"]
            );
        }
        }
        return $newlineitems;
    }
    function GetWCShippingLinesFromKlarnaOrder($klarnaOrder){
        $newlineitems = array();
        foreach($klarnaOrder["order_lines"] as $orderline){
            if($orderline["type"] == "shipping_fee"){
            $newlineitems[] = array(
                "name" =>$orderline["name"],
            "quantity" => $orderline["quantity"],
            "price" => (int)($orderline["unit_price"] / 100),
            "sku" => $orderline["reference"]
            );
        }
        }
        return $newlineitems;
    }
    function ConvertWCOrderLineToKlarna($orderlines){
        foreach($orderlines as $cartitem ){
            $price = (int)$cartitem["price"] * 100;
            $vat = $price / 5;
        $klarnaOrderLines[] =[
                    "type" => "physical",
                    "reference" => $cartitem["sku"],
                    "name" => $cartitem["name"],
                    "quantity" => $cartitem["quantity"],
                    "quantity_unit" => "pc",
                    "unit_price" => $price,
                    "tax_rate" => 2500,
                    "total_amount" => $price*$cartitem["quantity"],
                    "total_tax_amount" => $vat*$cartitem["quantity"],
                    "merchant_data" => json_encode([
                        "prod_id"=>$cartitem["product_id"],
                    "variation_id" => $cartitem["variation_id"]
                    ]
                    )
            ];
        };
        return $klarnaOrderLines;
    }
}
?>