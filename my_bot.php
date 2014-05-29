<?php
require_once "cryptsy_lib.php";
require_once "cryptsy_bot.php";
require_once "algo/acelan.php";

include "config.php";

class my_bot extends cryptsy_bot
{
	public function my_bot($config)
	{
		if(!isset($config['base_coin']) || !isset($config['target_coin']))
		{
			print("Should have base_coin and target_coin set up in config file.\n");
			exit(0);
		}
		$this->config['base_coin'] = $config['base_coin'];
		$this->config['target_coin'] = $config['target_coin'];

		$this->config['profit_percent'] = isset($config['profit_percent']) ? $config['profit_percent'] : 1.02;
		$this->config['stop_lost_percent'] = isset($config['stop_lost_percent']) ? $config['stop_lost_percent'] : 0.98;
		$this->config['order_proportion'] = isset($config['order_proportion']) ? $config['order_proportion'] : 0.7;
		$this->config['sell_proportion'] = isset($config['sell_proportion']) ? $config['sell_proportion'] : 0.7;
		$this->config['round'] = isset($config['round']) ? $config['round'] : 0;
		$this->config['min_target_coin'] = isset($config['min_target_coin']) ? $config['min_target_coin'] : 0;

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


		$this->set_key($config['public_key'] , $config['private_key'], strtoupper($config['target_coin']."/".$config['base_coin']));
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
		$my_base = $this->data["my_wallet"][strtoupper($this->config["base_coin"])];
		$my_target = $this->data["my_wallet"][strtoupper($this->config["target_coin"])];
		$cur_buy_price = $this->data["cur_buy_price"];
		$cur_sell_price = $this->data["cur_sell_price"];
		$my_orders = $this->data["my_orders"];


		$myorder_base = 0;
		$myorder_target = 0;
		foreach($my_orders as $order)
		{
			if($order["ordertype"] == "Buy")
				$myorder_base = $order["total"];
			if($order["ordertype"] == "Sell")
				$myorder_target = $order["quantity"];
		}
		if(sizeof($my_orders) == 0)
		{
			$dt = new DateTime();
			$dt->setTimezone(new DateTimeZone('EST'));
			$datetime = $dt->format('Y\-m\-d\ h:i:s');
		}
		else
			$datetime = $my_orders[0]["created"];

		$total_base = $my_base + $myorder_base + ($my_target+$myorder_target)*$cur_buy_price;
		$output_str = "";
		$output_str .= "=================================================================================\n";
		$output_str .= $datetime." - total ".strtolower($this->config["base_coin"])."=".$total_base." , cur_price=".$cur_buy_price.", my_".strtolower($this->config["base_coin"])."=".$my_base." , my_".strtolower($this->config["target_coin"])."=".$my_target." , b_c=".$this->config['buy_count']." , s_c=".$this->config['sell_count']."\n";
		foreach($my_orders as $order)
			$output_str .= "   my orders - ".$order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total ".strtolower($this->config["base_coin"])." ".$order["total"]."\n";
		print($output_str);

		if($this->config["log_filename"] != "")
		{
			$file = $this->config["log_filename"];
			file_put_contents($file, $output_str, FILE_APPEND);
		}
	}

	private $config;
	private $data;
	private $log_filename;
}

$my_bot = new my_bot($config);
$my_bot->run();

?>
