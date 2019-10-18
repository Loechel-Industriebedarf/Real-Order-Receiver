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

	$now = gmdate("Y-m-d\TH:i:s", time());
	
	foreach ($client->orderUnits()->find() as $orderUnit) {
		$last_order_date = $orderUnit->ts_created;
		$last_execution = file_get_contents('last.txt');
		$last_execution_date = new DateTime($last_execution);
		
		
		echo "LAST ORDER: " . $last_order_date . 
		"<br>LAST EXECUTION: " . $last_execution_date . 
		"<br>CURRENT TIME: " . $now . "<br>";
		
		if($last_order_date > $last_execution_date){
			$price = $orderUnit->price;
			$revenue_gross = $orderUnit->revenue_gross;
			$costs = $price - $revenue_gross;
			
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
			  
			//Push data
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
				$orderUnit->item->id_item,
				$price,
				$orderUnit->shipping_rate,
				$costs,
				$orderUnit->note,
				$orderUnit->id_offer,
				$orderUnit->id_order_unit
			));
			
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
	