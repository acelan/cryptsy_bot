<?php
require_once "cryptsy_lib.php";
require_once "cryptsy_bot.php";

include "config_3.php";

class my_doge_bot extends cryptsy_bot
{
	public function my_doge_bot($config)
	{
		$this->profit_percent = isset($config['profit_percent']) ? $config['profit_percent'] : 1.02;
		$this->stop_lost_percent = isset($config['stop_lost_percent']) ? $config['stop_lost_percent'] : 0.98;
		$this->order_proportion = isset($config['order_proportion']) ? $config['order_proportion'] : 0.7;
		$this->sell_proportion = isset($config['sell_proportion']) ? $config['sell_proportion'] : 0.7;

		$this->priv = array("DOGE/BTC" => array("data" => array(), "buy_count" => 0, "sell_count" => 0),
				    "LTC/BTC" => array("data" => array(), "buy_count" => 0, "sell_count" => 0),
				    "DOGE/LTC" => array("data" => array(), "buy_count" => 0, "sell_count" => 0));

		$this->log_filename = '';
		if (isset($config['log_filename']))
			$this->log_filename = $config['log_filename'];

		// we set doge/btc here, buy we'll change it later when needed
		$this->set_key($config['public_key'] , $config['private_key'], "DOGE/BTC");
	}

	protected function init($data)
	{
		// initialize
		// read history data
		// train your parmaters
		// "DOGE/BTC" , "LTC/BTC" , "DOGE/LTC"
		$this->priv["DOGE/BTC"]["data"] = $data;

		$this->set_label("LTC/BTC");
		$this->priv["LTC/BTC"]["data"] = $this->update_data();

		$this->set_label("DOGE/LTC");
		$this->priv["DOGE/LTC"]["data"] = $this->update_data();

		$this->set_label("DOGE/BTC");

		$labels = array("LTC/BTC","DOGE/BTC","DOGE/LTC");
		foreach($labels as $label)
			$this->update_pseudo_order($label);

		$this->show_status();
	}

	/*
	 * main algorithm
	 */
	protected function tick($data)
	{
		// "DOGE/BTC" , "LTC/BTC" , "DOGE/LTC"
		$this->priv["DOGE/BTC"]["data"] = $data;

		$this->set_label("LTC/BTC");
		$this->priv["LTC/BTC"]["data"] = $this->update_data();

		$this->set_label("DOGE/LTC");
		$this->priv["DOGE/LTC"]["data"] = $this->update_data();

		$this->set_label("DOGE/BTC");

		$my_btc = $data["my_wallet"]["BTC"];
		$my_doge = $data["my_wallet"]["DOGE"];
		$my_ltc = $data["my_wallet"]["LTC"];

		static $trade_tick = 0;
		if(sizeof($data["my_trade"] != 0))
		{
			$trade_tick++;
			if( $trade_tick >= 10)
			{
				// TODO: this is so stupid
				$labels = array("LTC/BTC","DOGE/BTC","DOGE/LTC");
				foreach($labels as $label)
				{
					$this->set_label($label);
					$this->cancel_market_orders();
				}
				$trade_tick = 0;
			}
		}
		else
			$trade_tick = 0;

		if( $this->place_order() == 1)
		{
			$this->show_status();
			$trade_tick = 0;
		}

		$ltc_buy_price = $this->priv["LTC/BTC"]["data"]["cur_buy_price"];
		$doge_buy_price = $this->priv["DOGE/BTC"]["data"]["cur_buy_price"];
		$doge_ltc_buy_price = $this->priv["DOGE/LTC"]["data"]["cur_buy_price"];
		$dt = new DateTime();
		$dt->setTimezone(new DateTimeZone('EST'));
		$datetime = $dt->format('Y\-m\-d\ h:i:s');
		print("$datetime - current price: ltc/btc = $ltc_buy_price , doge/btc = $doge_buy_price , doge/ltc = $doge_ltc_buy_price\r");
	}
	protected function place_order()
	{
		$my_btc = $this->priv["DOGE/BTC"]["data"]["my_wallet"]["BTC"];
		$my_ltc = $this->priv["DOGE/BTC"]["data"]["my_wallet"]["LTC"];
		$my_doge = $this->priv["DOGE/BTC"]["data"]["my_wallet"]["DOGE"];
		$ltc_buy_price = $this->priv["LTC/BTC"]["data"]["cur_buy_price"];
		$ltc_sell_price = $this->priv["LTC/BTC"]["data"]["cur_sell_price"];
		$doge_buy_price = $this->priv["DOGE/BTC"]["data"]["cur_buy_price"];
		$doge_sell_price = $this->priv["DOGE/BTC"]["data"]["cur_sell_price"];
		$doge_ltc_buy_price = $this->priv["DOGE/LTC"]["data"]["cur_buy_price"];
		$doge_ltc_sell_price = $this->priv["DOGE/LTC"]["data"]["cur_sell_price"];

		$ret = 0;
		$labels = array("LTC/BTC","DOGE/BTC","DOGE/LTC");
		foreach($labels as $label)
		{
			$my_money = $this->priv["DOGE/BTC"]["data"]["my_wallet"][explode("/",$label)[1]];
			$my_coin = $this->priv["DOGE/BTC"]["data"]["my_wallet"][explode("/",$label)[0]];
			$buy_price = $this->priv[$label]["data"]["cur_buy_price"];
			$sell_price = $this->priv[$label]["data"]["cur_sell_price"];

			// Buy order
			if( $this->pseudo_orders[$label][0]["price"] >= $sell_price)
			{
				$my_trade = $this->pseudo_orders[$label][0];
				// TODO: we should track the order status
				$this->set_label($label);
				$this->create_buy_order( $my_trade["price"], $my_trade["quantity"]);
				$this->set_label("DOGE/BTC");

				$this->priv[$label]["buy_count"]++;
				$this->priv[$label]["sell_count"] = 0;

				$output = $label." ".$my_trade["ordertype"]." ".$my_trade["quantity"]." at price ".$my_trade["price"]." , total btc ".$my_trade["total"]."\n";
				print("\n".$output);

				$file = $this->log_filename;
				if($file != "")
					file_put_contents($file, $output); 

				$this->update_pseudo_order($label);

				$ret = 1;
			}
			// Sell order
			else if( $this->pseudo_orders[$label][1]["price"] <= $buy_price)
			{
				$my_trade = $this->pseudo_orders[$label][1];
				// TODO: we should track the order status
				$this->set_label($label);
				$this->create_sell_order( $my_trade["price"], $my_trade["quantity"]);
				$this->set_label("DOGE/BTC");

				$this->priv[$label]["buy_count"] = 0;
				$this->priv[$label]["sell_count"]++;

				$output = $label." ".$my_trade["ordertype"]." ".$my_trade["quantity"]." at price ".$my_trade["price"]." , total btc ".$my_trade["total"]."\n";
				print("\n".$output);

				$file = $this->log_filename;
				if($file != "")
					file_put_contents($file, $output); 

				$this->update_pseudo_order($label);

				$ret = 1;
			}
		}
		return $ret;
	}
	protected function update_pseudo_order($label)
	{
		$my_btc = $this->priv["DOGE/BTC"]["data"]["my_wallet"]["BTC"];
		$my_ltc = $this->priv["DOGE/BTC"]["data"]["my_wallet"]["LTC"];
		$my_doge = $this->priv["DOGE/BTC"]["data"]["my_wallet"]["DOGE"];

		$my_money = $this->priv["DOGE/BTC"]["data"]["my_wallet"][explode("/",$label)[1]];
		$my_coin = $this->priv["DOGE/BTC"]["data"]["my_wallet"][explode("/",$label)[0]];
		$buy_price = $this->priv[$label]["data"]["cur_buy_price"];
		$sell_price = $this->priv[$label]["data"]["cur_sell_price"];

		$this->pseudo_orders[$label] = array();
		$this->pseudo_orders[$label][0]["ordertype"] = "Buy";
		if( $label == "DOGE/BTC")
			$this->pseudo_orders[$label][0]["quantity"] = floor($my_money/$sell_price*$this->order_proportion);
		else
			$this->pseudo_orders[$label][0]["quantity"] = $my_money/$sell_price*$this->order_proportion;
		$this->pseudo_orders[$label][0]["price"] = $sell_price*pow($this->stop_lost_percent,$this->priv[$label]["buy_count"]+1);
		$this->pseudo_orders[$label][0]["total"] = $this->pseudo_orders[$label][0]["quantity"]*$this->pseudo_orders[$label][0]["price"];

		$this->pseudo_orders[$label][1]["ordertype"] = "Sell";
		// we don't want to sell too much if we just bought more than 1 time
		if( $label == "DOGE/BTC")
			$this->pseudo_orders[$label][1]["quantity"] = floor($my_coin*pow($this->sell_proportion,$this->priv[$label]["buy_count"]+1));
		else
			$this->pseudo_orders[$label][1]["quantity"] = $my_coin*pow($this->sell_proportion,$this->priv[$label]["buy_count"]+1);
		$this->pseudo_orders[$label][1]["price"] = $buy_price*$this->profit_percent;
		$this->pseudo_orders[$label][1]["total"] = $this->pseudo_orders[$label][1]["quantity"]*$this->pseudo_orders[$label][1]["price"];
	}
	protected function show_status()
	{
		$my_btc = $this->priv["DOGE/BTC"]["data"]["my_wallet"]["BTC"];
		$my_ltc = $this->priv["DOGE/BTC"]["data"]["my_wallet"]["LTC"];
		$my_doge = $this->priv["DOGE/BTC"]["data"]["my_wallet"]["DOGE"];
		$ltc_buy_price = $this->priv["LTC/BTC"]["data"]["cur_buy_price"];
		$ltc_sell_price = $this->priv["LTC/BTC"]["data"]["cur_sell_price"];
		$doge_buy_price = $this->priv["DOGE/BTC"]["data"]["cur_buy_price"];
		$doge_sell_price = $this->priv["DOGE/BTC"]["data"]["cur_sell_price"];
		$doge_ltc_buy_price = $this->priv["DOGE/LTC"]["data"]["cur_buy_price"];
		$doge_ltc_sell_price = $this->priv["DOGE/LTC"]["data"]["cur_sell_price"];

		$dt = new DateTime();
		$dt->setTimezone(new DateTimeZone('EST'));
		$datetime = $dt->format('Y\-m\-d\ h:i:s');

		$total_btc = $my_btc + $my_ltc*$ltc_buy_price + $my_doge*$doge_buy_price;
		$output = "";
		$output .= "\n=================================================================================\n";
		$output .= $datetime." - total btc=".$total_btc." , btc = ". $my_btc ." ltc = ".$my_ltc." doge = ".$my_doge."\n";

		$labels = array("LTC/BTC","DOGE/BTC","DOGE/LTC");
		foreach($labels as $label)
		{
			foreach($this->pseudo_orders[$label] as $order)
				$output .= "   my pseudo orders - $label ".$order["ordertype"]." ".$order["quantity"]." at price ".$order["price"]." , total btc ".$order["total"]."\n";
		}

		print($output);

		if($this->log_filename != "")
		{
			$file = $this->log_filename;
			file_put_contents($file, $output);
		}
	}

	private $profit_percent;
	private $stop_lost_percent;
	private $order_proportion;
	private $sell_proportion;
	private $priv;
	private $pseudo_orders;
	private $log_filename;
}

$my_bot = new my_doge_bot($config);
$my_bot->run();

?>
