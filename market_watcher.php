<?php
require_once "cryptsy_lib.php";
require_once "config.php";

$cryptsy = new Cryptsy( $config['public_key'] , $config['private_key']);
$markets = array();

while(1)
{
	$data = $cryptsy->get_marketdata();
	$markets = array_merge((array)$data["markets"], $markets);
	foreach( $markets as $market)
	{
		$count = 0;
		$b_c = 0;
		$s_c = 0;
		$total_btc = 0;
		$trades = array_reverse($market["recenttrades"]);
		foreach( $trades as $trade)
		{
			if( $count == 0)
			{
				$buy_price = $trade["price"]*$config["profit_percent"];
				$sell_price = $trade["price"]*$config["stop_lost_percent"];
				$count++;
			}

			if( $trade["price"] >= $buy_price)
			{
				$b_c++;
				$buy_price = $trade["price"];
			}
			else if( $trade["price"] <= $sell_price)
			{
				$s_c++;
				$sell_price = $trade["price"];
			}

			$total_btc += $trade["price"]*$trade["quantity"];
		}
		// we like waves
		if((($b_c >= 2) && ($s_c >= 2) && ($market["recenttrades"][0]["price"] > 0.00000010)) || $total_btc > 10)
			print($market["label"]." - buy_count = ".$b_c." , sell_count = ".$s_c." total btc = ".$total_btc."\n");
	}
	print("=================================================================================\n");
	// get data once a minute
	sleep(60);
}

?>
