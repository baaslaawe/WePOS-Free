<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class ReportSOProfit extends MY_Controller {
	
	public $table;
		
	function __construct()
	{
		parent::__construct();
		$this->prefix_apps = config_item('db_prefix');
		$this->prefix = config_item('db_prefix2');
		$this->load->model('model_salesorder', 'm');
		$this->load->model('model_salesorderdetail', 'm2');
	}
	
	public function print_reportSOProfit(){
		
		$this->table = $this->prefix.'salesorder';
		$this->table2 = $this->prefix.'salesorder_detail';
		
		$session_user = $this->session->userdata('user_username');					
		$user_fullname = $this->session->userdata('user_fullname');					
		
		if(empty($session_user)){
			die('User Session Expired, Please Re-Login!');
		}
		
		extract($_GET);
		
		
		if(empty($storehouse_id)){
			die('Select Warehouse!');
			//$storehouse_id = $this->stock->get_primary_storehouse();
			//$storehouse_id = -1;
		}		
		
		if($storehouse_id == "null"){
			die('Select Warehouse!');
		}
		
		$storehouse_name = '-';
		if($storehouse_id == -1){
			$storehouse_name = 'Semua Gudang';
		}		
		
		
		$data_post = array(
			'do'	=> '',
			'report_data'	=> array(),
			'report_place_default'	=> '',
			'report_name'	=> 'SALES ORDER PROFIT REPORT',
			'date_from'	=> $date_from,
			'date_till'	=> $date_till,
			'user_fullname'	=> $user_fullname,
			'storehouse_name'	=> $storehouse_name
		);
		
		$get_opt = get_option_value(array('report_place_default'));
		if(!empty($get_opt['report_place_default'])){
			$data_post['report_place_default'] = $get_opt['report_place_default'];
		}
		
		if(empty($date_from) OR empty($date_till)){
			die('Sales Order Not Found!');
		}else{
				
			if(empty($date_from)){ $date_from = date('Y-m-d'); }
			if(empty($date_till)){ $date_till = date('Y-m-d'); }
			
			$mktime_dari = strtotime($date_from);
			$mktime_sampai = strtotime($date_till);
						
			$qdate_from = date("Y-m-d",strtotime($date_from));
			$qdate_till = date("Y-m-d",strtotime($date_till));
			
			$add_where = "(a.so_date >= '".$qdate_from."' AND a.so_date <= '".$qdate_till."')";
			
			$this->db->select("a.*, i.storehouse_name");
			$this->db->from($this->table." as a");
			$this->db->join($this->prefix.'storehouse as i','i.id = a.so_from','LEFT');
			$this->db->where("a.so_status IN ('done')");
			$this->db->where("a.is_deleted", 0);
			$this->db->where($add_where);
			
			if(!empty($storehouse_id)){
				if($storehouse_id > 0){
					$this->db->where('a.so_from', $storehouse_id);
				}
			}
			
			$this->db->order_by("so_date","ASC");
			$get_dt = $this->db->get();
			if($get_dt->num_rows() > 0){
				$data_post['report_data'] = $get_dt->result_array();				
			}
						
			$all_so_id = array();
			$newData = array();
			if(!empty($data_post['report_data'])){
				foreach ($data_post['report_data'] as $s){
					
					if(!empty($storehouse_id)){
						if($storehouse_id > 0 AND $storehouse_name == '-'){
							$storehouse_name = $s['storehouse_name'];
						}
					}
					
					$s['created_date'] = date("d-m-Y H:i",strtotime($s['created']));					
					$s['so_date'] = date("d-m-Y",strtotime($s['so_date']));
					
					if(!in_array($s['id'], $all_so_id)){
						$all_so_id[] = $s['id'];
					}		
										
					$s['so_discount'] = $s['so_discount']*-1;
					$s['so_dp'] = $s['so_dp']*-1;
					$s['so_sub_total_show'] = priceFormat($s['so_sub_total']);
					$s['so_discount_show'] = priceFormatAcc($s['so_discount']);
					$s['so_tax_show'] = priceFormat($s['so_tax']);
					$s['so_shipping_show'] = priceFormat($s['so_shipping']);
					$s['so_dp_show'] = priceFormatAcc($s['so_dp']);
					$s['so_total_price_show'] = priceFormat($s['so_total_price']);
										
					$s['payment_note'] = ucfirst($s['so_payment']);

					$s['so_total_price_cash'] = 0;
					$s['so_total_price_credit'] = 0;
					if($s['so_payment'] == 'cash'){
						$s['so_total_price_cash'] = $s['so_total_price'];
					}else{
						$s['so_total_price_credit'] = $s['so_total_price'];
					}

					$s['so_total_price_cash_show'] = priceFormat($s['so_total_price_cash']);
					$s['so_total_price_credit_show'] = priceFormat($s['so_total_price_credit']);
					
					$s['total_item'] = 0;
					$s['total_qty'] = 0;
					$s['total_hpp'] = 0;
					$s['total_sales'] = 0;
					$s['total_potongan'] = 0;
					$s['subtotal'] = 0;
					
					$newData[$s['id']] = $s;
					//array_push($newData, $s);
					
				}
			}
			
			//so_detail
			$data_item_so = array();
			if(!empty($all_so_id)){
				$all_so_id_txt = implode(",", $all_so_id);
				$this->db->select("sod_qty as total_qty, item_id, so_id, item_hpp, sales_price, sod_potongan, sod_total");
				$this->db->from($this->table2);
				$this->db->where("so_id IN (".$all_so_id_txt.")");	
				$get_det = $this->db->get();
				if($get_det->num_rows() > 0){										
					foreach($get_det->result_array() as $dt){
						$newData[$dt['so_id']]['total_qty'] += $dt['total_qty'];
						$newData[$dt['so_id']]['total_hpp'] += ($dt['item_hpp'] * $dt['total_qty']);
						$newData[$dt['so_id']]['total_sales'] += ($dt['sod_total']);
						$newData[$dt['so_id']]['total_potongan'] += ($dt['sod_potongan']);
						$newData[$dt['so_id']]['subtotal'] += ($dt['sod_total'] - $dt['sod_potongan']);
						
						if(empty($data_item_so[$dt['so_id']])){
							$data_item_so[$dt['so_id']] = array();
						}
						
						if(!in_array($dt['item_id'], $data_item_so[$dt['so_id']])){
							$data_item_so[$dt['so_id']][] = $dt['item_id'];
							$newData[$dt['so_id']]['total_item'] += 1;
						}
					}
				}
			}
	
			$data_post['report_data'] = $newData;
		}
		
		$data_post['storehouse_name'] = $storehouse_name;
		
		//DO-PRINT
		if(!empty($do)){
			$data_post['do'] = $do;
		}else{
			$do = '';
		}
		
		$useview = 'print_reportSOProfit';
		if($do == 'excel'){
			$useview = 'excel_reportSOProfit';
		}
				
		$this->load->view('../../sales_order/views/'.$useview, $data_post);	
	}
	

}