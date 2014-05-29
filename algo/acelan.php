<?php
/*
 * my_init() is to initilize variable you will need to use in the algorithm - optional
 * my_algo() is the main trading algorithm
 * my_done() will be call when all actions are all done - optional
 */

static $json_log = array();

function my_init($data,&$config)
{
	global $json_log;

	$file = $config['log_filename'];
	if($file != "")
	{
		$file .= ".json";
		if(!file_exists($file))
			file_put_contents($file, "");

		$str_log = file_get_contents($file);
		$json_log = (array)json_decode($str_log);

		// set buy_count and sell_count from log
		if($config["init_buy_sell_count"] == 0)
		{
			if(sizeof($json_log) != 0)
			{
				$config_log = (array)$json_log[sizeof($json_log)-1];
				$config["buy_count"] = $config_log["buy_count"];
				$config["sell_count"] = $config_log["sell_count"];
				if($config_log["tradetype"] == "Buy")
				{
					$config["buy_count"]++;
					$config["sell_count"]--;
					if($config["sell_count"] < 0)
						$config["sell_count"] = 0;
				}
				else if($config_log["tradetype"] == "Sell")
				{
					$config["buy_count"] = 0;
					$config["sell_count"]++;
				}

			}
		}
	}
}

function my_done($data,&$config)
{
}

function my_algo($data,&$config)
{
	global $json_log;
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
				{
					file_put_contents($file, $my_trade["tradetype"]." ".$my_trade["quantity"]." at price ".$my_trade["tradeprice"]." , total ".$config['base_coin']." ".$my_trade["total"]."\n", FILE_APPEND);

					$file .= ".json";
					$mydata = array();
					$mydata["date"] = $my_trade["datetime"];
					$mydata["label"] = $config["target_coin"]."/".$config["base_coin"];
					$mydata["tradetype"] = $my_trade["tradetype"];
					$mydata["price"] = $my_trade["tradeprice"];
					$mydata["quantity"] = $my_trade["quantity"];
					$mydata["total"] = $my_trade["total"];
					$mydata["buy_count"] = $config["buy_count"];
					$mydata["sell_count"] = $config["sell_count"];
					$json_log[sizeof($json_log)] = $mydata;

					file_put_contents($file, json_encode($json_log));
				}

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
					$config['buy_count'] -= 1;
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
		$buy_price = $cur_buy_price*pow($config['stop_lost_percent'],$config['buy_count']+1);
		if( isset($config['min_target_coin']) && ($my_target_coin < $config['min_target_coin'])) // try to buy some coins in low profit rate
			$buy_price = $cur_buy_price*0.99;
		$quantity = $my_base_coin/$cur_buy_price*$config['order_proportion'];
		if($config['round'])
			$quantity = floor($quantity);

		$action['create_buy_order'][0]['price'] = $buy_price;
		$action['create_buy_order'][0]['quantity'] = $quantity;
	}
	if( $my_target_coin > 0)
	{
		$sell_price = $cur_sell_price*$config['profit_percent'];
		// we don't want to sell too much if we just bought more than 1 time
		if( $config['buy_count'] == 0)
			$quantity = $my_target_coin*pow($config['sell_proportion'], 1);
		else
			$quantity = $my_target_coin*pow($config['sell_proportion'],$config['buy_count']);
		if($config['round'])
			$quantity = floor($quantity);

		$action['create_sell_order'][0]['price'] = $sell_price;
		$action['create_sell_order'][0]['quantity'] = $quantity;
	}

	return $action;
}
?>
