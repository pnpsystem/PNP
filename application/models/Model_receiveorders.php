<?php 

class Model_receiveorders extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
	}



	/* get the orders data */
	public function getOrdersData($id = null)
	{
		
		if($id) {
			$sql = "SELECT a.*,b.supplier_name, b.supplier_address, b.supplier_phone
			FROM receive a left join suppliers b on b.id = a.supplier_id WHERE a.id = ?";
			$query = $this->db->query($sql, array($id));
			return $query->row_array();
		}

		$sql = "SELECT a.id, a.bill_no, a.supplier_id, a.date_time, a.paid_status, a.net_amount,
		b.supplier_name, b.supplier_phone 
		FROM receive a left join suppliers b on b.id = a.supplier_id ORDER BY a.id DESC";
		$query = $this->db->query($sql);
		return $query->result_array();
	}

	public function getOrdersDatav2($id = null)
	{
		
		if($id) {
			$sql = "SELECT a.*,b.supplier_name, b.supplier_address, b.supplier_phone
			FROM receivev2 a left join suppliers b on b.id = a.supplier_id WHERE a.id = ?";
			$query = $this->db->query($sql, array($id));
			return $query->row_array();
		}

		$sql = "SELECT a.id, a.bill_no, a.supplier_id, a.date_time, a.paid_status,a.discount, a.net_amount,
		b.supplier_name, b.supplier_phone,b.supplier_address
		FROM receivev2 a left join suppliers b on b.id = a.supplier_id ORDER BY a.id DESC";
		$query = $this->db->query($sql);
		return $query->result_array();
	}

	public function getOrdersItems($order_id = null)
	{
		$sql = "SELECT r.*,i.id ITEM_ID,l.location_name,i.item item_name,c.name category_name FROM receive_item r
			left join productsv2 i on r.product_id = i.id 
			left join product_location l on r.location_id = l.id
			left join categories c on i.product_category_id = c.id
			WHERE r.receive_id = ?";
		$query = $this->db->query($sql, array($order_id));
		return $query->result_array();
	}

	// get the orders item data
	public function getOrdersItemData($order_id = null)
	{
		if(!$order_id) {
			return false;
		}

		$sql = "SELECT * FROM receive_item WHERE receive_id = ?";
		$query = $this->db->query($sql, array($order_id));
		return $query->result_array();
	}

	public function create()
	{
		$user_id = $this->session->userdata('id');
		$bill_no = 'BILPR-'.strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
    	$data = array(
    		'bill_no' => $bill_no,
    		'supplier_id' => $this->input->post('supplier'),
    		'date_time' => strtotime( $this->input->post('order_date') . date("h:i:s") ),
    		'gross_amount' => $this->input->post('gross_amount_value'),
    		'service_charge_rate' => $this->input->post('service_charge_rate'),
    		'service_charge' => ($this->input->post('service_charge_value') > 0) ?$this->input->post('service_charge_value'):0,
    		'vat_charge_rate' => $this->input->post('vat_charge_rate'),
    		'vat_charge' => ($this->input->post('vat_charge_value') > 0) ? $this->input->post('vat_charge_value') : 0,
    		'net_amount' => $this->input->post('net_amount_value'),
    		'discount' => $this->input->post('discount'),
    		'paid_status' => 2,
    		'user_id' => $user_id
    	);

		$insert = $this->db->insert('receive', $data);
		$order_id = $this->db->insert_id();

		$this->load->model('model_products');

		$count_product = count($this->input->post('product'));
    	for($x = 0; $x < $count_product; $x++) {
    		$items = array(
    			'receive_id' => $order_id,
    			'product_id' => $this->input->post('product')[$x],
    			'qty' => $this->input->post('qty')[$x],
    			'rate' => $this->input->post('rate_value')[$x],
    			'amount' => $this->input->post('amount_value')[$x],
    		);

    		$this->db->insert('receive_item', $items);

    		// now increase the stock from the product
    		$product_data = $this->model_products->getProductData($this->input->post('product')[$x]);
    		$qty = (int) $product_data['qty'] + (int) $this->input->post('qty')[$x];

    		$update_product = array('qty' => $qty);


    		$this->model_products->update($update_product, $this->input->post('product')[$x]);
    	}

		return ($order_id) ? $order_id : false;
	}

	public function countOrderItem($order_id)
	{
		if($order_id) {
			$sql = "SELECT * FROM receive_item WHERE receive_id = ?";
			$query = $this->db->query($sql, array($order_id));
			return $query->num_rows();
		}
	}

	public function update($id)
	{
		if($id) {
			$user_id = $this->session->userdata('id');
			// fetch the order data 

			$data = array(
				'supplier_id' => $this->input->post('supplier'),
				'date_time' => strtotime( $this->input->post('order_date') . date("h:i:s") ),
	    		'gross_amount' => $this->input->post('gross_amount_value'),
	    		'service_charge_rate' => $this->input->post('service_charge_rate'),
	    		'service_charge' => ($this->input->post('service_charge_value') > 0) ? $this->input->post('service_charge_value'):0,
	    		'vat_charge_rate' => $this->input->post('vat_charge_rate'),
	    		'vat_charge' => ($this->input->post('vat_charge_value') > 0) ? $this->input->post('vat_charge_value') : 0,
	    		'net_amount' => $this->input->post('net_amount_value'),
	    		'discount' => $this->input->post('discount'),
	    		'paid_status' => $this->input->post('paid_status'),
	    		'user_id' => $user_id
	    	);

			$this->db->where('id', $id);
			$update = $this->db->update('receive', $data);

			// now the order item 
			// first we will replace the product qty to original and subtract the qty again
			$this->load->model('model_products');
			$get_order_item = $this->getOrdersItemData($id);
			foreach ($get_order_item as $k => $v) {
				$product_id = $v['product_id'];
				$qty = $v['qty'];
				// get the product 
				$product_data = $this->model_products->getProductData($product_id);
				$update_qty = $product_data['qty'] - $qty;
				$update_product_data = array('qty' => $update_qty);
				
				// update the product qty
				$this->model_products->update($update_product_data, $product_id);
			}

			// now remove the order item data 
			$this->db->where('receive_id', $id);
			$this->db->delete('receive_item');

			// now decrease the product qty
			$count_product = count($this->input->post('product'));
	    	for($x = 0; $x < $count_product; $x++) {
	    		$items = array(
	    			'receive_id' => $id,
	    			'product_id' => $this->input->post('product')[$x],
	    			'qty' => $this->input->post('qty')[$x],
	    			'rate' => $this->input->post('rate_value')[$x],
	    			'amount' => $this->input->post('amount_value')[$x],
	    		);
	    		$this->db->insert('receive_item', $items);

	    		// now increase the stock from the product
	    		$product_data = $this->model_products->getProductData($this->input->post('product')[$x]);
	    		$qty = (int) $product_data['qty'] + (int) $this->input->post('qty')[$x];

	    		$update_product = array('qty' => $qty);
	    		$this->model_products->update($update_product, $this->input->post('product')[$x]);
	    	}

			return true;
		}
	}



	public function remove($id)
	{
		if($id) {
			$this->db->where('id', $id);
			$delete = $this->db->delete('receive');

			$this->db->where('receive_id', $id);
			$delete_item = $this->db->delete('receive_item');
			return ($delete == true && $delete_item) ? true : false;
		}
	}

	public function removev2($id)
	{
		if($id) {
			$this->db->where('id', $id);
			$delete = $this->db->delete('receivev2');

			$this->db->where('receive_id', $id);
			$delete_item = $this->db->delete('receive_item');

			return ($delete == true && $delete_item) ? true : false;
		}
	}

	public function countTotalPaidOrders()
	{
		$sql = "SELECT * FROM receive WHERE paid_status = ?";
		$query = $this->db->query($sql, array(1));
		return $query->num_rows();
	}


	public function saveMaster($ReceiveMasterData)
	{
		if($ReceiveMasterData) {
			$insert = $this->db->insert('receivev2', $ReceiveMasterData);
			return ($insert == true) ? true : false;
		}
	}

	public function saveDetails($item_table,$masterID)
	{
		for ($x=0; $x <count($item_table) ; $x++) { 
			$data[] = array(
				'receive_id' =>$masterID,
				'location_id' =>$item_table[$x]['location_id'],
				'product_id' =>$item_table[$x]['product_id'],
				'qty' =>$item_table[$x]['quantity'],
				'rate' =>$item_table[$x]['rate'],
				'amount' =>$item_table[$x]['rate'],
			);
		}

		try {
			//insert to db
			for ($x=0; $x <count($item_table) ; $x++) { 
				$this->db->insert('receive_item', $data[$x]);
			}
			return 'success';
		} catch (Exception $e) {
			return 'failed';
		}
	}



}