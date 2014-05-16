<?php
function my_algo($data,&$config)
{
	static $show_status = 0;
	$cur_buy_price = $data["cur_buy_price"];
	$cur_sell_price = $data["cur_sell_price"];
	$my_orders = $data["my_orders"];
	$my_base_coin = $data["my_wallet"][$config['base_coin']];
	$my_target_coin = $data["my_wallet"][$config['target_coin']];
	$action = array();

	$show_status++;
	$show_status=$show_status%(15*120);

	// ATTENTION!!! only support 1 buy and 1 sell order
	if( sizeof($my_orders) == 0)
	{
		$action = place_order($data,$config);
	}
	else if(sizeof($my_orders) == 1) // bought or sold an order, cancel old order and re-create all orders
	{
		if(sizeof($data["my_trade"]) != 0)
		{
			print("\n");
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
				$config['buy_count'] = 0;
			}
		}

		$action['cancel_market_orders'] = 1;
	}
	else if($show_status % 12 == 0)// nothing a long time
	{
		//$this->show_status();
		//printf("%s ",$data["cur_buy_price"]*100000000);
		if(sizeof($my_orders) > 2)
		  printf("U");
		else
		  printf(".");
		
		if( $show_status == 0 )
		  printf("\n");
	}
	return $action;
}

function place_order($data,&$config)
{
	$my_base_coin = $data["my_wallet"][$config['base_coin']];
	$my_target_coin = $data["my_wallet"][$config['target_coin']];
	$cur_buy_price = $data["cur_buy_price"];
	$cur_sell_price = $data["cur_sell_price"];
	$action = array();
	printf("my_".$config['base_coin'].":%s my_".$config['target_coin'].":%s cur_b_p:%s cur_s_p:%s\n", $my_base_coin, $my_target_coin, $cur_buy_price, $cur_sell_price);
	if( $my_base_coin > 0)
	{
  		$buy_percent= min( pow(2,$config['buy_count'])*$config['order_proportion'] , 0.9);
  		$buy_price=round($cur_buy_price*pow($config['stop_lost_percent'],$config['buy_count']+1)-0.000000005,8);
		$action['create_buy_order'][0]['price'] = $buy_price;
		$action['create_buy_order'][0]['quantity'] = $my_base_coin/$cur_sell_price*$buy_percent;
	}
  	if( $my_target_coin > 0)
  	{
  		$sell_percent= min( pow(2,$config['sell_count'])*$config['sell_proportion'] , 0.9);
  		$quantity = $my_target_coin*$sell_percent;
  		$sell_price=round($cur_sell_price*pow($config['profit_percent'],$config['sell_count']+1)+0.000000004,8);
		$action['create_sell_order'][0]['price'] = $sell_price;
		$action['create_sell_order'][0]['quantity'] = $quantity;
	}

	return $action;
}
?>
