<?php
require_once "cryptsy_lib.php";

abstract class cryptsy_bot
{
// public section
	public function run()
	{
		if(!isset($this->key))
		{
			print("No public key and/or secret key are provided!\n");
			exit(0);
		}

		$this->cryptsy = new Cryptsy( $this->key, $this->secret);
		// get data
		$this->init($this->update_data());
		while(1)
		{
			sleep($this->tick_timeout);
			// prepare $data
			$this->tick($this->update_data());
		}
	}

// protected section
	protected abstract function init($data);
	protected abstract function tick($data);
	protected function set_key($key,$secret,$label) {$this->key=$key; $this->secret=$secret; $this->label=$label;}
	protected function create_buy_order($price, $quantity) {$this->cryptsy->create_buy_order($this->label,$price,$quantity);}
	protected function create_sell_order($price, $quantity) {$this->cryptsy->create_sell_order($this->label,$price,$quantity);}
	protected function cancel_market_orders() {$this->cryptsy->cancel_market_orders($this->label);}
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
	protected function update_data()
	{
		$cur_buy_price = $this->cryptsy->get_buy_price($this->label);
		$cur_sell_price = $this->cryptsy->get_sell_price($this->label);
		$my_orders = $this->cryptsy->get_orders($this->label);
		$my_wallet = $this->cryptsy->get_wallet();
		$my_trades = $this->cryptsy->get_mytrades($this->label);

		$data = array(
			'cur_buy_price' => $cur_buy_price,
			'cur_sell_price' => $cur_sell_price,
			'my_wallet' => $my_wallet,
			'my_orders' => $my_orders,
			'my_trade' => $my_trades,
			);
		return $data;
	}

	protected $tick_timeout;

// private section
	private $cryptsy;
	private $key;
	private $secret;
	private $label;
}
?>
