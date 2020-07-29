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

/*

*/
function getNewOrders($client){
	$order = array();

	date_default_timezone_set("Europe/Berlin");
	$now = date("Y-m-d\TH:i:s", time());
	
	//Push headline
	array_push($order, array(
		'Mail',
		'Bestellungs-ID',
		'Rechnungsfirma 1',
		'Rechnungsfirma 2',
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
		'ID_OFFER',
		'ID_ORDER_UNIT',
		'Bestellzeitpunkt',
		'Updatezeitpunkt',
		'Abholzeitpunkt',
		'Versandkosten'
	));	
	
	$writeLog = true; //If true, it writes a line of log (last order, last execution, current time)
	$newOrders = 0; //If greater than 0, it writes the current date to the last.txt; Gets set to true, if realOrder.csv gets written
	
	$last_execution = file_get_contents('last.txt'); //When was the program last executed?
	$last_execution_date = new DateTime($last_execution);

	//Getting the shipping costs for each order
	$shipping_costs_array = array();
	foreach ($client->orderUnits()->find() as $orderUnit) {
		$orderNumberShip = $orderUnit->id_order;
		$shipping_costs_temp = $orderUnit->shipping_rate / 100;
		if($shipping_costs_temp > 0){ $shipping_costs_order = $shipping_costs_temp; }

		$shipping_costs_array[$orderNumberShip] = $shipping_costs_order;
	}
	
	//Getting the orders
	foreach ($client->orderUnits()->find() as $orderUnit) {
		/*
		echo "<pre>";
		var_dump($orderUnit);
		echo "</pre>";
		*/
		
		try{
			$last_order_date = date_create($orderUnit->ts_created); //ts_created sometimes outputs a wrong date (in the past) somehow? ts_updated seems to work better.
			$last_order_update = date_create($orderUnit->ts_updated); 
		
			//Write a little log line
			if($writeLog){
				echo "LAST ORDER: " . $last_order_date->format('Y-m-d H:i:s') . 
				" | LAST ORDER UPDATE: " . $last_order_update->format('Y-m-d H:i:s') . 
				" | LAST EXECUTION: " . $last_execution_date->format('Y-m-d H:i:s') . 
				" | CURRENT TIME: " . $now .
				" - ";
				$writeLog = false;
			}
						
			//If the orders is more recent than the last execution date, download it and write it to a csv file later on
			if($last_order_update > $last_execution_date && $orderUnit->status !== "sent"){
				//Don't import old orders again.
				if($last_order_date > $last_order_update->modify("-1 day")){
					$orderId = $orderUnit->id_order;
					$shipping_costs = $shipping_costs_array[$orderId];
				
					$price = $orderUnit->price / 100;
					$revenue_gross = $orderUnit->revenue_gross / 100;
					$costs = ($price + $shipping_costs - $revenue_gross) / 119 * 100; 
					
					//echo $price . " " . $revenue_gross . " " . $shipping_costs . " ".$costs . "<br>";
					
					
					
					$article_number = $orderUnit->id_offer;
					
					$billing_street = $orderUnit->billing_address->street . " " . $orderUnit->billing_address->house_number;
					$shipping_street = $orderUnit->shipping_address->street . " " .  $orderUnit->shipping_address->house_number;
					
					$billing_company_name = $orderUnit->billing_address->company_name;
					if($billing_company_name == ""){
						$billing_firm1 = $orderUnit->billing_address->first_name . " " . $orderUnit->billing_address->last_name;
						$billing_firm2 = "";
					}
					else{
						$billing_firm1 = $billing_company_name;
						$billing_firm2 = $orderUnit->billing_address->first_name . " " .  $orderUnit->billing_address->last_name;
					}
					
					$shipping_company_name = $orderUnit->shipping_address->company_name;
					if($shipping_company_name == ""){
						$shipping_firm1 = $orderUnit->shipping_address->first_name . " " .  $orderUnit->shipping_address->last_name;
						$shipping_firm2 = "";
					}
					else{
						$shipping_firm1 = $shipping_company_name;
						$shipping_firm2 = $orderUnit->shipping_address->first_name . " " .  $orderUnit->shipping_address->last_name;
					}	

					array_push($order, array(
						$orderUnit->buyer->email,
						$orderId,
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
						$shipping_costs,
						$costs,
						$orderUnit->note,
						$orderUnit->item->id_item,
						sprintf('%0.0F', $orderUnit->id_order_unit),
						$last_order_date->format('Y-m-d H:i:s'),
						$last_order_update->format('Y-m-d H:i:s'),
						$now
					));
					
					$newOrders++;				
				}
			}
			else{
				//No new orders, so no need to cycle throught the rest of them
				break;
			}			
		}
		catch(Exception $e){
			//Log error to txt
			echo "Error." . $e;
		}								
	}
	
	//There are new orders! Write them to csv and update the last.txt file. Also log the number of new orders.
	if($newOrders > 0){
		writeToCsv($order);	
		writeLast($now);
		echo $newOrders . " new order(s) got parsed!";
	}
	else{
		echo "No new orders.";
	}
	
}   
   
   
function writeToCsv($order){
	$fp = fopen('../realOrder.csv', 'w');
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
	