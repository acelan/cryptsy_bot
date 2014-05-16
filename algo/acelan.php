<?php
function my_algo($data,&$config)
{
	$cur_buy_price = $data["cur_buy_price"];
	$cur_sell_price = $data["cur_sell_price"];
	$my_orders = $data["my_orders"];
	$my_base_coin = $data["my_wallet"][$config['base_coin']];
	$my_target_coin = $data["my_wallet"][$config['target_coin']];
	$action = array();

	// ATTENTION!!! only support 1 buy and 1 sell order
	if( sizeof($my_orders) == 0)
	{
		$action = place_order($data,$config);
		return $action;
	}
	else if(sizeof($my_orders) == 1) // bought or sold an order, cancel old order and re-create all orders
	{
		if(sizeof($data["my_trade"]) != 0)
		{
			static $order_id = 0;
			$my_trade = $data["my_trade"][0];
			if($my_trade["order_id"] != $order_id) // make sure we've made a new trade
			{
				print($my_trade["tradetype"]." ".$my_trade["quantity"]." at price ".$my_trade["tradeprice"]." , total ".$config['base_coin']." ".$my_trade["total"]."\n");

				$file = $config['log_filename'];
				if($file != "")
					file_put_contents($file, $my_trade["tradetype"]." ".$my_trade["quantity"]." at price ".$my_trade["tradeprice"]." , total ".$config['base_coin']." ".$my_trade["total"]."\n", FILE_APPEND);

				$order_id = $my_trade["order_id"];
			}

			if( $my_trade["tradetype"] == "Buy")
			{
				$config['buy_count']++;
				$config['sell_count'] = 0;
			}
			else if( $my_trade["tradetype"] == "Sell")
			{
				$config['sell_count']++;

				// don't set buy_count to 0 if we have bought more than 2 times
				if( $config['buy_count'] > 1)
					$config['buy_count'] -= 2;
				else
					$config['buy_count'] = 0;
			}
		}

		$action['cancel_market_orders'] = 1;
		return $action;
	}
	else if( sizeof($my_orders) > 2)
	{
		// something wrong here
		$action['cancel_market_orders'] = 1;
		return $action;
	}

	$dt = new DateTime();
	$dt->setTimezone(new DateTimeZone('EST'));
	$datetime = $dt->format('Y\-m\-d\ h:i:s');
	print("$datetime - current price: $cur_buy_price\r");

	return $action;
}

function place_order($data,&$config)
{
	$my_base_coin = $data["my_wallet"][$config['base_coin']];
	$my_target_coin = $data["my_wallet"][$config['target_coin']];
	$cur_buy_price = $data["cur_buy_price"];
	$cur_sell_price = $data["cur_sell_price"];
	$action = array();

	if( $my_base_coin > 0)
	{
		$buy_price = $cur_sell_price*pow($config['stop_lost_percent'],$config['buy_count']+1);
		if( isset($config['min_target_coin']) && ($my_target_coin < $config['min_target_coin'])) // try to buy some coins in low profit rate
			$buy_price = $cur_sell_price*0.99;
		$quantity = $my_base_coin/$cur_sell_price*$config['order_proportion'];
		if($config['round'])
			$quantity = floor($quantity);

		$action['create_buy_order'][0]['price'] = $buy_price;
		$action['create_buy_order'][0]['quantity'] = $my_base_coin/$cur_sell_price*$config['order_proportion'];
	}
	if( $my_target_coin > 0)
	{
		$sell_price = $cur_buy_price*$config['profit_percent'];
		// we don't want to sell too much if we just bought more than 1 time
		$quantity = $my_target_coin*pow($config['sell_proportion'],$config['buy_count']+1);
		if($config['round'])
			$quantity = floor($quantity);

		$action['create_sell_order'][0]['price'] = $sell_price;
		$action['create_sell_order'][0]['quantity'] = $quantity;
	}

	return $action;
}
?>
