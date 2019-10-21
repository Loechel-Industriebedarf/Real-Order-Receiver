<?php
	require_once 'settings.php';
	require 'vendor/autoload.php';

	use Hitmeister\Component\Api\ClientBuilder;
	
	$client = ClientBuilder::create()
		->setClientKey($api_client)
		->setClientSecret($api_secret)
		->build();
	
	
	
	if(!file_exists($csvPath)){
		getNewOrders($client);
	}
	else{
		echo "CSV file was not processed yet!";
	}

	
function getNewOrders($client){
	$order = array();

	$now = gmdate("Y-m-d\TH:i:s", time()+7200);
	
	//Push headline
			array_push($order, array(
				'Mail',
				'Bestellungs-ID',
				'Rechnungsfirma 1',
				'Fechnungsfirma 2',
				'Rechnungsstrasse',
				'RechnungsPLZ',
				'Rechnungsort',
				'Rechnungsland',
				'Rechnungstelefon',
				'Versandfirma 1',
				'Versandfirma 2',
				'Versandstrasse',
				'VersandPLZ',
				'Versandort',
				'Versandland',
				'Versandtelefon',
				'Artikelnummer',
				'Preis',
				'Versandkosten',
				'Nebenkosten',
				'Notiz',
				'ID_OFFER???',
				'ID_ORDER_UNIT???'
			));	
	
	foreach ($client->orderUnits()->find() as $orderUnit) {
		$last_order_date = date_create($orderUnit->ts_created);
		$last_execution = file_get_contents('last.txt');
		$last_execution_date = new DateTime($last_execution);
		
		
		echo "LAST ORDER: " . $last_order_date->format('Y-m-d H:i:s') . 
		"<br>LAST EXECUTION: " . $last_execution_date->format('Y-m-d H:i:s') . 
		"<br>CURRENT TIME: " . $now . "<br><br>";
		
		if($last_order_date > $last_execution_date){
			$shipping_costs = $orderUnit->shipping_rate / 100;
			
			$price = $orderUnit->price / 100;
			$revenue_gross = $orderUnit->revenue_gross / 100;
			$costs = $price + ($shipping_costs / 119 * 100) - $revenue_gross; //Costs are weird, if shipping is included. Need to watch on future orders.
			
			//echo $price . " " . $revenue_gross . " " . $shipping_costs . " ".$costs . "<br>";
			
			
			
			$article_number = $orderUnit->id_offer;
			
			$billing_street = $orderUnit->billing_address->street . $orderUnit->billing_address->house_number;
			$shipping_street = $orderUnit->shipping_address->street . $orderUnit->shipping_address->house_number;
			
			$billing_company_name = $orderUnit->billing_address->company_name;
			if($billing_company_name == ""){
				$billing_firm1 = $orderUnit->billing_address->first_name . $orderUnit->billing_address->last_name;
				$billing_firm2 = "";
			}
			else{
				$billing_firm1 = $billing_company_name;
				$billing_firm2 = $orderUnit->billing_address->first_name . $orderUnit->billing_address->last_name;
			}
			
			$shipping_company_name = $orderUnit->shipping_address->company_name;
			if($shipping_company_name == ""){
				$shipping_firm1 = $orderUnit->shipping_address->first_name . $orderUnit->shipping_address->last_name;
				$shipping_firm2 = "";
			}
			else{
				$billing_firm1 = $shipping_company_name;
				$billing_firm2 = $orderUnit->shipping_address->first_name . $orderUnit->shipping_address->last_name;
			}
			  

			/* 
			*  Real seems to send each product as one position, and adds the shipping to the last. 
			*  Our ERP system doesn't like this. It wants shipping at all positions or a seperate shipping position.
			*  Sooo... We create an extra shipping position.
			*/
			$shipping = 0; 
			if($shipping_costs > 0) { $shipping = 1;}
			for($i = 0; $i <= $shipping; $i++){
				if($i == 1){
					$article_number = "VERSAND-1955_LAGER";
					$price = $shipping_costs;
				}
				array_push($order, array(
				$orderUnit->buyer->email,
				$orderUnit->id_order,
				$billing_firm1,
				$billing_firm2,
				$billing_street,
				$orderUnit->billing_address->postcode,
				$orderUnit->billing_address->city,
				$orderUnit->billing_address->country,
				$orderUnit->billing_address->phone,
				$shipping_firm1,
				$shipping_firm2,
				$shipping_street,
				$orderUnit->shipping_address->postcode,
				$orderUnit->shipping_address->city,
				$orderUnit->shipping_address->country,
				$orderUnit->shipping_address->phone,
				$article_number,
				$price,
				0,
				$costs,
				$orderUnit->note,
				$orderUnit->item->id_item,
				$orderUnit->id_order_unit
			));
			}
			
			
			writeToCsv($order);
			
		}		
	}
	
	writeLast($now);
}   
   
   
function writeToCsv($order){
	$fp = fopen('realOrder.csv', 'w');
	for ($i = 0; $i < count($order); $i++) {
		fputcsv($fp, $order[$i], ';');
	}
	fclose($fp);
}   

function writeLast($time){
	$fp = fopen('last.txt', 'w+');
	fwrite($fp, $time);
	fclose($fp);
}
	