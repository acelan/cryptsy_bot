<?php
require_once "cryptsy_lib.php";
require_once "cryptsy_bot.php";

include "config_drk.php";

class my_drk_bot extends cryptsy_bot
{
	public function my_drk_bot($config)
	{

		$this->profit_percent = isset($config['profit_percent']) ? $config['profit_percent'] : 1.02;
		$this->stop_lost_percent = isset($config['stop_lost_percent']) ? $config['stop_lost_percent'] : 0.98;
		$this->order_proportion = isset($config['order_proportion']) ? $config['order_proportion'] : 0.7;
		$this->sell_proportion = isset($config['sell_proportion']) ? $config['sell_proportion'] : 0.7;

		if (isset($config['init_buy_count'])) {
			$this->buy_count = $config['init_buy_count'];
			$this->sell_count = 0;
		} else if (isset($config['init_sell_count'])) {
			$this->buy_count = 0;
			$this->sell_count = $config['init_sell_count'];
		} else {
			$this->buy_count = 0;
			$this->sell_count = 0;
		}

		$this->log_filename = '';
		if (isset($config['log_filename']))
			$this->log_filename = $config['log_filename'];

		// It's a bot to sell/buy DRK and BTC only
		$this->set_key($config['public_key'] , $config['private_key'], "DRK/BTC");
	}

	/*
	 * [cur_buy_price] => 0.00000209
	 * [cur_sell_price] => 0.00000210
	 * [my_wallet] => Array
	 * 	(
	 * 		[LTC] => 0.00000000
	 * 		[BTC] => 0.00000000
	 * 		[FTC] => 0.00000000
	 * 		[LEAF] => 0.00000000
	 * 		[MEOW] => 0.00000000
	 * 		[CASH] => 0.00000000
	 * 		[VTC] => 0.00000000
	 * 	)
	 * [my_orders] => Array
	 * 	(
	 *		[0] => Array
	 *		(
	 *			[orderid] => 39960050
	 *			[created] => 2014-02-09 04:46:27
	 *			[ordertype] => Buy
	 *			[price] => 0.00000163
	 *			[quantity] => 43123.00000000
	 *			[orig_quantity] => 43123.00000000
	 *			[total] => 0.07029049
	 *		)
	 *		[1] => Array
	 *		(
	 *			[orderid] => 39960053
	 *			[created] => 2014-02-09 04:46:28
	 *			[ordertype] => Sell
	 *			[price] => 0.00000169
	 *			[quantity] => 12571.00000000
	 *			[orig_quantity] => 12571.00000000
	 *			[total] => 0.02124499
	 *		)
	 *	)
	 * [my_trade] => Array
	 * 	(
	 *		[0] => Array
	 *		(
	 *			[orderid] => 39960050
	 *			[created] => 2014-02-09 04:46:27
	 *			[ordertype] => Buy
	 *			[price] => 0.00000163
	 *			[quantity] => 43123.00000000
	 *			[orig_quantity] => 43123.00000000
	 *			[total] => 0.07029049
	 *		)
	 *	)
	 */
	protected function init($data)
	{
		// initialize
		// read history data
		// train your parmaters
		$this->show_status();
	}

	/*
	 * main algorithm
	 */
	protected function tick($data)
	{
		$this->data = $data;

		$cur_buy_price = $data["cur_buy_price"];
		$cur_sell_price = $data["cur_sell_price"];
		$my_orders = $data["my_orders"];
		$my_btc = $data["my_wallet"]["BTC"];
		$my_drk = $data["my_wallet"]["DRK"];

		// ATTENTION!!! only support 1 buy and 1 sell order
		if( sizeof($my_orders) == 0)
		{
			$this->place_order();
			$this->show_status();
			return;
		}
		else if(sizeof($my_orders) == 1) // bought or sold an order, cancel old order and re-create all orders
		{
			if(sizeof($data["my_trade"]) != 0)
			{
				static $order_id = 0;
				$my_trade = $data["my_trade"][0];
				if($my_trade["order_id"] != $order_id) // make sure we've made a new trade
				{
					print($my_trade["tradetype"]." ".$my_trade["quantity"]." at price ".$my_trade["tradeprice"]." , total btc ".$my_trade["total"]."\n");

					$file = $this->log_filename;
					if($file != "")
						file_put_contents($file, $my_trade["tradetype"]." ".$my_trade["quantity"]." at price ".$my_trade["tradeprice"]." , total btc ".$my_trade["total"]."\n", FILE_APPEND);

					$order_id = $my_trade["order_id"];
				}

				if( $my_trade["tradetype"] == "Buy")
				{
					$this->buy_count++;
					$this->sell_count = 0;
				}
				else if( $my_trade["tradetype"] == "Sell")
				{
					$this->sell_count++;

					// don't set buy_count to 0 if we have bought more than 2 times
					if( $this->buy_count > 1)
						$this->buy_count -= 2;
					else
						$this->buy_count = 0;
				}
			}

			$this->cancel_market_orders();

			return;
		}
		else if( sizeof($my_orders) > 2)
		{
			// something wrong here
			$this->cancel_market_orders();
			return;
		}

		$dt = new DateTime();
		$dt->setTimezone(new DateTimeZone('EST'));
		$datetime = $dt->format('Y\-m\-d\ h:i:s');
		print("$datetime - current price: $cur_buy_price\r");
	}
	protected function place_order()
	{
		$my_btc = $this->data["my_wallet"]["BTC"];
		$my_drk = $this->data["my_wallet"]["DRK"];
		$cur_buy_price = $this->data["cur_buy_price"];
		$cur_sell_price = $this->data["cur_sell_price"];

		if( $my_btc > 0)
		{
			$buy_price = $cur_sell_price*pow($this->stop_lost_percent,$this->buy_count+1);
			if( $my_drk < 1) // try to buy some coins in low profit rate
				$buy_price = $cur_sell_price*0.99;
			$this->create_buy_order($buy_price, $my_btc/$cur_sell_price*$this->order_proportion);
		}
		if( $my_drk > 0)
		{
			// we don't want to sell too much if we just bought more than 1 time
			$quantity = $my_drk*pow($this->sell_proportion,$this->buy_count+1);
			$this->create_sell_order($cur_buy_price*$this->profit_percent, $quantity);
		}
	}
	protected function show_status()
	{
		$this->data = $this->update_data();
		$my_btc = $this->data["my_wallet"]["BTC"];
		$my_drk = $this->data["my_wallet"]["DRK"];
		$cur_buy_price = $this->data["cur_buy_price"];
		$cur_sell_price = $this->data["cur_sell_price"];
		$my_orders = $this->data["my_orders"];


		$myorder_btc = 0;
		$myorder_drk = 0;
		foreach($my_orders as $order)
		{
			if($order["ordertype"] == "Buy")
				$myorder_btc = $order["total"];
			if($order["ordertype"] == "Sell")
				$myorder_drk = $order["quantity"];
		}
		if(sizeof($my_orders) == 0)
		{
			$dt = new DateTime();
			$dt->setTimezone(new DateTimeZone('EST'));
			$datetime = $dt->format('Y\-m\-d\ h:i:s');
		}
		else
			$datetime = $my_orders[0]["created"];

		$total_btc = $my_btc + $myorder_btc + ($my_drk+$myorder_drk)*$cur_buy_price;
		print("=================================================================================\n");
		print($datetime." - total btc=".$total_btc." , cur_price=".$cur_buy_price.", my_btc=".$my_btc." , my_drk=".$my_drk." , b_c=".$this->buy_count." , s_c=".$this->sell_count."\n");
		foreach($my_orders as $order)
			print("   my orders - ".$order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total btc ".$order["total"]."\n");

		if($this->log_filename != "")
		{
			$file = $this->log_filename;
			file_put_contents($file, "=================================================================================\n", FILE_APPEND);
			file_put_contents($file,$datetime." - total btc=".$total_btc." , cur_price=".$cur_buy_price.", my_btc=".$my_btc." , my_drk=".$my_drk." , b_c=".$this->buy_count." , s_c=".$this->sell_count."\n", FILE_APPEND);
			foreach($my_orders as $order)
				file_put_contents($file, "   my orders - ".$order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total btc ".$order["total"]."\n", FILE_APPEND);
		}
	}

	private $profit_percent;
	private $stop_lost_percent;
	private $order_proportion;
	private $sell_proportion;
	private $buy_count;
	private $sell_count;
	private $data;
	private $log_filename;
}

$my_bot = new my_drk_bot($config);
$my_bot->run();

?>
