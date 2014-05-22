<?php
class Cryptsy
{
// public
	public function Cryptsy($key, $secret)
	{
		$this->key = $key;
		$this->secret = $secret;
		$this->price_timestamp = 0;
		$this->markets = $this->api_query("getmarkets");
		if(sizeof($this->markets) == 0) { print_r($this->log); exit(0); }
	}
	/*
	 * [balances_available] => Array
	 *     (
	 *         [LTC] => 0.00000000
	 *         [BTC] => 0.00000000
	 *         [FTC] => 0.00000000
	 *         [LEAF] => 0.00000000
	 *         [MEOW] => 0.00000000
	 *         [CASH] => 0.00000000
	 *         [VTC] => 0.00000000
	 *     )
	 *
	 * [balances_hold] => Array
	 *     (   
	 *         [DOGE] => 17959.00590124
	 *         [BTC] => 0.10226463
	 *     )
	 *
	 * [servertimestamp] => 1391874006
	 * [servertimezone] => EST
	 * [serverdatetime] => 2014-02-08 10:40:06
	 * [openordercount] => 2
	 */
	public function account_info() { return $this->api_query("getinfo"); }
	public function get_wallet() { return $this->api_query("getinfo")["balances_available"]; }
	/*
	 * Array(
    	 *	 [sell] => Array
	 *	        (
	 *	           [0] => Array
	 *	                (
	 *	                    [0] => 0.00000164
	 *	                    [1] => 8936213.83007033
	 *	                )
	 *	            [1] => Array
	 *	                (
	 *	                    [0] => 0.00000165
	 *	                    [1] => 18369599.20648742
	 *	                )
	 *		)
	 *	    [buy] => Array
	 *	        (
	 *	            [0] => Array
	 *	                (
	 *	                    [0] => 0.00000163
	 *	                    [1] => 14550439.67234082
	 *	                )
	 *	            [1] => Array
	 *	                (
	 *	                    [0] => 0.00000162
	 *	                    [1] => 13633815.19371455
	 *	                )
	 *		)
	 *	)
	 */
	public function get_depth($label) { return $this->api_query("depth", array("marketid" => $this->get_marketid($label))); }
	public function get_buy_price($label) { return $this->get_price("buy",$label); }
	public function get_sell_price($label) { return $this->get_price("sell",$label); }
	public function get_price($type,$label)
	{
		static $prev_label = "";
		$id = $this->get_marketid($label);
		if( $id == 0)
		{
			print("No such currency label - ".$label."\n");
			return "";
		}
		// TODO: should check $type as well

		// cache the data for 5 seconds
		if(((time()-$this->price_timestamp) > 5) || ($prev_label != $label))
		{
			do
			{
				$price_data = $this->api_query("marketorders", array("marketid" => $id));
				if(sizeof( $price_data) == 0)
					continue;
				if(sizeof($price_data[$type."orders"]) == 0)
				{
					print(".");
					sleep(1);
					continue;
				}
			} while(0);
			$this->price_timestamp = time();
			$this->price_data = $price_data;
			$prev_label = $label;
		}

		return $this->price_data[$type."orders"][0][$type."price"];
	}
	public function cal_order_fee($act, $label, $price, $quantity) {return 0;}
	public function create_buy_order($label, $price, $quantity) { return $this->api_query("createorder", array("marketid" => $this->get_marketid($label) , "ordertype" => "Buy" , "price" => $price, "quantity" => $quantity)); }
	public function create_sell_order($label, $price, $quantity) { return $this->api_query("createorder", array("marketid" => $this->get_marketid($label) , "ordertype" => "Sell" , "price" => $price, "quantity" => $quantity)); }
	public function cancel_order($no) { return $this->api_query("cancelorder", array("orderid" => $no)); }
	/*
	 * Array
	 * (
	 *     [0] => Array
	 *         (
	 *             [orderid] => 39960050
	 *             [created] => 2014-02-09 04:46:27
	 *             [ordertype] => Buy
	 *             [price] => 0.00000163
	 *             [quantity] => 43123.00000000
	 *             [orig_quantity] => 43123.00000000
	 *             [total] => 0.07029049
	 *         )   
	 *     [1] => Array
	 *         (
	 *             [orderid] => 39960053
	 *             [created] => 2014-02-09 04:46:28
	 *             [ordertype] => Sell
	 *             [price] => 0.00000169
	 *             [quantity] => 12571.00000000
	 *             [orig_quantity] => 12571.00000000
	 *             [total] => 0.02124499
	 *         )   
	 * )
	 */
	public function get_orders($label) { return $this->api_query("myorders", array("marketid" => $this->get_marketid($label))); }
	/*
	 * Array
	 * (
	 *     [0] => Your order #39348003 has been cancelled.
	 *     [1] => Your order #34387162 has been cancelled.
	 * )
	 */
	public function cancel_market_orders($label) { return $this->api_query("cancelmarketorders", array("marketid" => $this->get_marketid($label))); }
	public function cancel_all_orders() { return $this->api_query("cancelallorders"); }
	public function get_mytrades($label) { return $this->api_query("mytrades", array("marketid" => $this->get_marketid($label),"limit" => 10)); }
	/*
	 * Array
	 *   (
	 *       [markets] => Array
	 *           (
	 *
	 * 		[ALF/BTC] => Array
	 * 		              (
	 * 		                  [marketid] => 57
	 * 		                  [label] => ALF/BTC
	 * 		                  [lasttradeprice] => 0.00000188
	 * 		                  [volume] => 302606.55553025
	 * 		                  [lasttradetime] => 2014-03-17 02:42:51
	 * 		                  [primaryname] => AlphaCoin
	 * 		                  [primarycode] => ALF
	 * 		                  [secondaryname] => BitCoin
	 * 		                  [secondarycode] => BTC
	 * 		                  [recenttrades] => Array
	 * 		                      (
	 * 		                          [0] => Array
	 * 		                              (
	 * 		                                  [id] => 30975581
	 * 		                                  [time] => 2014-03-17 02:42:51
	 * 		                                  [price] => 0.00000188
	 * 		                                  [quantity] => 72.64195657
	 * 		                                  [total] => 0.00013657
	 * 		                              )
	 *
	 * 		                          [1] => Array
	 * 		                              (
	 * 		                                  [id] => 30975508
	 * 		                                  [time] => 2014-03-17 02:42:04
	 * 		                                  [price] => 0.00000188
	 * 		                                  [quantity] => 106.30371910
	 * 		                                  [total] => 0.00019985
	 * 		                              )
	 */
	public function get_marketdata()
	{
		static $ch2 = null;
		if (is_null($ch2)) {
			$ch2 = curl_init();
			curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Cryptsy API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
		}
		curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch2, CURLOPT_URL, 'http://pubapi.cryptsy.com/api.php?method=marketdatav2');
		curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, FALSE);

		// run the query
		$res = curl_exec($ch2);
		$dec = json_decode($res, true);
		if($dec["success"] == "1")
			if(array_key_exists("return",$dec))
				return $dec["return"];
		return "";
	}

// protected
	protected function api_query($method, array $req = array())
	{
		$this->query_count++;

		$mt = explode(' ', microtime());
		$req['nonce'] = $mt[1];
		$req['method'] = $method;
		
		// generate the POST data string
		$post_data = http_build_query($req, '', '&');
		
		$sign = hash_hmac("sha512", $post_data, $this->secret);
		
		// generate the extra headers
		$headers = array(
			'Sign: '.$sign,
			'Key: '.$this->key,
		);
		
		// our curl handle (initialize if required)
		static $ch = null;
		if (is_null($ch)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Cryptsy API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_URL, 'https://api.cryptsy.com/api');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		// run the query
		$res = curl_exec($ch);
		
		// re-try 10 times if failed
		if(($res === false) && ($this->query_count < 10))
		{
			return $this->api_query($method,$req);
		}
		$dec = json_decode($res, true);
		if(!$dec && ($this->query_count <10))
		{
			return $this->api_query($method,$req);
		}

		$this->log = $dec;

		// TODO: sometimes it complains no 'return' when 'success' == 1
		$ret = $dec;
		if($dec["success"] == "1")
		{
			if(array_key_exists("return",$dec))
				$ret = $dec["return"];
			else
			{
				// find out which method will lead to no 'return' key
				// createorder cancelmarketorders
				//print("no 'return' in array, method = ".$method."\n");
				//print_r($dec);
			}
		}
		else
		{
			if($this->query_count < 10)
				return $this->api_query($method,$req);

			// re-try 10 times and then tell user what happened
			print($dec["error"]);

		}
		$this->query_count = 0;

		return $ret;
	}
	protected function get_marketid($label)
	{
		foreach( $this->markets as $market)
			if( $market['label'] == $label)
				return $market['marketid'];
		return 0;
	}

// private
	private $key;
	private $secret;
	private $markets;
	private $log;
	private $query_count;
	private $price_timestamp;
	private $price_data;
};

?>
