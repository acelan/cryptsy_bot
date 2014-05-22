<?php
require_once "cryptsy_lib.php";
require_once "cryptsy_bot.php";
require_once "algo/acelan.php";

include "config_drk.php";

class my_drk_bot extends cryptsy_bot
{
	public function my_drk_bot($config)
	{

		$this->config['profit_percent'] = isset($config['profit_percent']) ? $config['profit_percent'] : 1.02;
		$this->config['stop_lost_percent'] = isset($config['stop_lost_percent']) ? $config['stop_lost_percent'] : 0.98;
		$this->config['order_proportion'] = isset($config['order_proportion']) ? $config['order_proportion'] : 0.7;
		$this->config['sell_proportion'] = isset($config['sell_proportion']) ? $config['sell_proportion'] : 0.7;
		$this->config['round'] = isset($config['round']) ? $config['round'] : 0;

		$this->config['buy_count'] = 0;
		$this->config['sell_count'] = 0;
		$this->config['init_buy_sell_count'] = 0;
		if (isset($config['init_buy_count'])) {
			$this->config['buy_count'] = $config['init_buy_count'];
			$this->config['init_buy_sell_count'] = 1;
		}
		if (isset($config['init_sell_count'])) {
			$this->config['sell_count'] = $config['init_sell_count'];
			$this->config['init_buy_sell_count'] = 1;
		}

		$this->config['log_filename'] = '';
		if (isset($config['log_filename']))
			$this->config['log_filename'] = $config['log_filename'];

		$this->config['base_coin'] = 'BTC';
		$this->config['target_coin'] = 'DRK';

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
	 *			[datetime] => 2014-02-09 04:46:27
	 *			[tradetype] => Buy
	 *			[tradeprice] => 0.00000163
	 *			[quantity] => 43123.00000000
	 *			[total] => 0.07029049
	 *		)
	 *	)
	 */
	protected function init($data)
	{
		// initialize
		if(function_exists("my_init"))
			my_init($data,$this->config);

		// read history data
		// train your parmaters
		$this->show_status($data);
	}

	/*
	 * main algorithm
	 */
	protected function tick($data)
	{
		$this->data = $data;
		$action = my_algo($data,$this->config);

		return $action;
	}

	protected function done($data)
	{
		if(sizeof($data["my_orders"]) != 0)
			$this->show_status($data);

		if(function_exists("my_done"))
			my_done($data,$this->config);
	}

	protected function show_status($data)
	{
		$this->data = $data;
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
		print($datetime." - total btc=".$total_btc." , cur_price=".$cur_buy_price.", my_btc=".$my_btc." , my_drk=".$my_drk." , b_c=".$this->config['buy_count']." , s_c=".$this->config['sell_count']."\n");
		foreach($my_orders as $order)
			print("   my orders - ".$order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total btc ".$order["total"]."\n");

		if($this->config["log_filename"] != "")
		{
			$file = $this->config["log_filename"];
			file_put_contents($file, "=================================================================================\n", FILE_APPEND);
			file_put_contents($file,$datetime." - total btc=".$total_btc." , cur_price=".$cur_buy_price.", my_btc=".$my_btc." , my_drk=".$my_drk." , b_c=".$this->config['buy_count']." , s_c=".$this->config['sell_count']."\n", FILE_APPEND);
			foreach($my_orders as $order)
				file_put_contents($file, "   my orders - ".$order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total btc ".$order["total"]."\n", FILE_APPEND);
		}
	}

	private $config;
	private $data;
	private $log_filename;
}

$my_bot = new my_drk_bot($config);
$my_bot->run();

?>
