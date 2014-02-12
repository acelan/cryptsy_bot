<?php
include "cryptsy_lib.php";
include "config.php";

$profit_percent = 1.02;
$stop_lost_percent = 0.98;
$order_proportion = 0.7;
$sell_proportion = 0.7;
$btc_min = 0.01;
$doge_min = 10000;

$cryptsy = new Cryptsy( $key, $secret);

$cur_buy_price = $cryptsy->get_buy_price("DOGE/BTC");
$my_buy_price = $cur_buy_price;
$old_orders = "";

$total_btc = 0;
$my_btc = 0;
$my_doge = 0;
$place_order = 1;
$sell_count = 0;
$buy_count = 0;
$prev_price = -1;
while( 1 )
{
	$cur_buy_price = $cryptsy->get_buy_price("DOGE/BTC");
	$cur_sell_price = $cryptsy->get_sell_price("DOGE/BTC");
	$my_orders = $cryptsy->get_orders("DOGE/BTC");
	$my_wallet = get_my_wallet($cryptsy);
	$my_btc = $my_wallet["BTC"];
	$my_doge = $my_wallet["DOGE"];


	// ATTENTION!!! only support 1 buy and 1 sell order
	if( sizeof($my_orders) == 0)
	{
		if( $my_btc > 0)
		{
			$cryptsy->create_buy_order("DOGE/BTC", $cur_sell_price*pow($stop_lost_percent,$buy_count+1), floor($my_btc/$cur_sell_price*$order_proportion));
			$my_buy_price = $cur_buy_price;
			$place_order = 1;
			sleep(3);
		}
		if( $my_doge > 0)
		{
			//$cryptsy->create_sell_order("DOGE/BTC", $cur_buy_price*pow($profit_percent,$sell_count+1), floor($my_doge*$sell_proportion));
			$cryptsy->create_sell_order("DOGE/BTC", $cur_buy_price*$profit_percent, floor($my_doge*$sell_proportion));
			$place_order = 1;
			sleep(3);
		}
	}
	else if( sizeof($my_orders) == 1) // bought or sold an order, cancel old order and re-create all orders
	{
		$cur_price = show_success_trade($my_orders,$old_orders);

		if( $cur_price == $prev_price)	// get the old data
		{
			$cryptsy->cancel_market_orders("DOGE/BTC");
			continue;
		}
		$prev_price = $cur_price;

		if( $my_orders[0]["ordertype"] == "Buy")
		{
			$sell_count++;
			$buy_count = 0;
		}
		else if( $my_orders[0]["ordertype"] == "Sell")
		{
			$buy_count++;
			$sell_count = 0;
		}

		$cryptsy->cancel_market_orders("DOGE/BTC");

		if( $my_btc < $btc_min)	// sell some doge to earn more btc
			$cryptsy->create_sell_order("DOGE/BTC", $cur_buy_price, floor($my_doge*pow($sell_proportion,$sell_count+1)));
		if( $my_doge < $doge_min) // buy some
			$cryptsy->create_buy_order("DOGE/BTC", $cur_sell_price, floor($my_btc/$cur_sell_price*pow($order_proportion,$buy_count+1)));

		if( $buy_count >= 3) // big drop now, sleep 1 hour
			sleep(60);

		continue;
	}

	if( $place_order == 1)
	{
		$my_wallet = get_my_wallet($cryptsy);
		$my_btc = $my_wallet["BTC"];
		$my_doge = $my_wallet["DOGE"];
		$counter = 0;
		do
		{
			$counter++;
			$my_orders = $cryptsy->get_orders("DOGE/BTC");
			if( (sizeof($my_orders) != 2) && ( $counter <= 3))
				continue;
		} while( 0 );
		$old_orders = $my_orders;
		$total_btc = $my_btc + $my_doge*$cur_buy_price + $my_orders[0]["quantity"]*$cur_buy_price + $my_orders[1]["quantity"]*$cur_buy_price;
		print("=================================================================================\n");
		print($my_orders[0]["created"]." - total btc=".$total_btc." , cur_price=".$cur_buy_price.", my_btc=".$my_btc." , my_doge=".$my_doge." , b_c=".$buy_count." , s_c=".$sell_count."\n");
		//print_r($my_orders);
		show_order($my_orders);
		$place_order = 0;
	}

	sleep(3);
}

function get_my_wallet($cryptsy)
{
	$my_wallet = $cryptsy->get_wallet();
	// something wrong if we can get the BTC and/or DOGE coins
	if(!array_key_exists("BTC", $my_wallet) || !array_key_exists("DOGE", $my_wallet))
	{
		sleep(1);
		return get_my_wallet($cryptsy);
	}

	return $my_wallet;
}

function show_order($my_orders)
{
	foreach($my_orders as $order)
		print("   my orders - ".$order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total btc ".$order["total"]."\n");
}

function show_success_trade($my_orders, $old_orders)
{
	static $prev_price = 0;
	$price = 0;
	if($my_orders[0]["ordertype"] == "Buy")
	{
		foreach($old_orders as $order)
			if($order["ordertype"] == "Sell")
			{
				$price = $order["price"];
				if( $price != $prev_price)
					print($order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total btc ".$order["total"]."\n");

			}
	}
	else if($my_orders[0]["ordertype"] == "Sell")	
	{
		foreach($old_orders as $order)
			if($order["ordertype"] == "Buy")
			{
				$price = $order["price"];
				if( $price != $prev_price)
					print($order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total btc ".$order["total"]."\n");
			}
	}

	$prev_price = $price;
	if( $price != 0)
		return $price;
	return 0;
}

?>
