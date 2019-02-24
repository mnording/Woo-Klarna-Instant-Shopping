<?php 
class KlarnaWooTranslator{
    function GetWCLineItemsFromKlarnaOrder($klarnaOrder){
        echo "about to get orderlines";
       
        $newlineitems = array();
        foreach($klarnaOrder->order_lines as $orderline){
            
            if($orderline->type != "shipping_fee"){
                $newline = array(
                    "name" =>$orderline->name,
                    "product_id" => json_decode($orderline->merchant_data)->prod_id,
                   "quantity" => $orderline->quantity,
                   "price" => (int)($orderline->unit_price / 100)   ,
                   "sku" => $orderline->reference
                );
                if(json_decode($orderline->merchant_data)->variation_id){
                    $newline["variation_id"] = json_decode($orderline->merchant_data)->variation_id;
                }
            $newlineitems[] = $newline;
            
        }
        }
        return $newlineitems;
    }
    function GetWCShippingLinesFromKlarnaOrder($klarnaOrder){
        $newlineitems = array(); 
        foreach($klarnaOrder->order_lines as $orderline){           
            if($orderline->type == "shipping_fee"){
            $newlineitems[] = array(
                "name" =>$orderline->name,
               "quantity" => $orderline->quantity,
               "price" => (int)($orderline->unit_price / 100) - (int)($orderline->total_tax_amount / 100)   ,
               "id" => $orderline->reference
            );
        }
        }
        return $newlineitems[0];
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
    function GetWooAdressFromKlarnaOrder($klarnaOrder){
        
       $adress= array(
            'first_name' => $klarnaOrder->billing_address->given_name,
            'last_name'  => $klarnaOrder->billing_address->family_name, 
            'email'      => $klarnaOrder->billing_address->email,
            'phone'      => $klarnaOrder->billing_address->phone,
            'address_1'  => $klarnaOrder->billing_address->street_address,
            'city'       => $klarnaOrder->billing_address->city,          
            'postcode'   => $klarnaOrder->billing_address->postal_code,
            'country'    => $klarnaOrder->billing_address->country,
        );
        return $adress;
    }
    
}
?>