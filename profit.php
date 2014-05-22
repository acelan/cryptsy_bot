<?php
$file = "log/bot_drk.log.json";
$str_log = file_get_contents($file);
$json_log = (array)json_decode($str_log);
$buy_amount = 0;
$sell_amount = 0;
for( $i = 0; $i < sizeof($json_log); $i++)
{
	$record = (array)$json_log[$i];
	if($record["tradetype"] == "Buy")
		$buy_amount += $record["price"] * $record["quantity"];
	else if($record["tradetype"] == "Sell")
		$sell_amount += $record["price"] * $record["quantity"];
	
	print "profit = sell_amount - buy_amount = ".$sell_amount." - ".$buy_amount." = ".($sell_amount - $buy_amount)."\n";
}

?>

