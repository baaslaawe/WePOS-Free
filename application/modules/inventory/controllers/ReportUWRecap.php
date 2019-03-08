<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class reportUWRecap extends MY_Controller {
	
	public $table;
		
	function __construct()
	{
		parent::__construct();
		$this->prefix_apps = config_item('db_prefix');
		$this->prefix = config_item('db_prefix2');
		$this->load->model('model_usagewaste', 'm');
	}	
	
	public function print_reportUWRecap(){

		$this->table = $this->prefix.'usagewaste';
		$this->table2 = $this->prefix.'usagewaste_detail';
		
		$session_user = $this->session->userdata('user_username');					
		$user_fullname = $this->session->userdata('user_fullname');					
		
		if(empty($session_user)){
			die('Sesi Login sudah habis, Silahkan Login ulang!');
		}
		
		extract($_GET);
		
		if(empty($date_from)){ $date_from = date('Y-m-d'); }
		if(empty($date_till)){ $date_till = date('Y-m-d'); }
		
		$data_post = array(
			'do'	=> '',
			'report_data'	=> array(),
			'report_place_default'	=> '',
			'report_name'	=> 'USAGE & WASTE REPORT (RECAP)',
			'date_from'	=> $date_from,
			'date_till'	=> $date_till,
			'user_fullname'	=> $user_fullname
		);
		
		$get_opt = get_option_value(array('report_place_default'));
		if(!empty($get_opt['report_place_default'])){
			$data_post['report_place_default'] = $get_opt['report_place_default'];
		}
		
		if(empty($date_from) OR empty($date_till)){
			die('Purchase Not Found!');
		}else{
				
			if(empty($date_from)){ $date_from = date('Y-m-d'); }
			if(empty($date_till)){ $date_till = date('Y-m-d'); }
			
			$mktime_dari = strtotime($date_from);
			$mktime_sampai = strtotime($date_till);
						
			$qdate_from = date("Y-m-d",strtotime($date_from));
			$qdate_till = date("Y-m-d",strtotime($date_till));
			
			$add_where = "(a.uw_date >= '".$qdate_from."' AND a.uw_date <= '".$qdate_till."')";
			
			$this->db->select("a.*");
			$this->db->from($this->table." as a");
			$this->db->where("a.uw_status", 'done');
			$this->db->where("a.is_deleted", 0);
			$this->db->where($add_where);
			$this->db->order_by("uw_date","ASC");
			$get_dt = $this->db->get();
			if($get_dt->num_rows() > 0){
				$data_post['report_data'] = $get_dt->result_array();				
			}
						
			$all_uw_id = array();
			$all_uw_id_date = array();
			$newData = array();
			if(!empty($data_post['report_data'])){
				foreach ($data_post['report_data'] as $s){
					$s['created_date'] = date("d-m-Y H:i",strtotime($s['created']));					
					$s['uw_date'] = date("d-m-Y",strtotime($s['uw_date']));
					
					if(!in_array($s['id'], $all_uw_id)){
						$all_uw_id[] = $s['id'];
					}		
										
					if(empty($newData[$s['uw_date']])){
						$newData[$s['uw_date']] = array(
							'date'			=> $s['uw_date'],
							'total_usage'		=> 0,
							'total_item'	=> 0,
							'total_qty'		=> 0	
						);
					}
					
					$newData[$s['uw_date']]['total_usage'] += 1;
					//array_push($newData, $s);
					
					if(empty($all_uw_id_date[$s['id']])){
						$all_uw_id_date[$s['id']] = $s['uw_date'];
					}
					
				}
			}
						
			//uw_detail
			$data_item_dis = array();
			if(!empty($all_uw_id)){
				$all_uw_id_txt = implode(",", $all_uw_id);
				$this->db->select("uwd_qty as total_qty, item_id, uw_id");
				$this->db->from($this->table2);
				$this->db->where("uw_id IN (".$all_uw_id_txt.")");
				$get_det = $this->db->get();
				if($get_det->num_rows() > 0){										
					foreach($get_det->result_array() as $dt){
						if(!empty($all_uw_id_date[$dt['uw_id']])){
							$getDate = $all_uw_id_date[$dt['uw_id']];
							
							$newData[$getDate]['total_qty'] += $dt['total_qty'];							

							if(empty($data_item_dis[$dt['uw_id']])){
								$data_item_dis[$dt['uw_id']] = array();
							}
						
							if(!in_array($dt['item_id'], $data_item_dis[$dt['uw_id']])){
								$data_item_dis[$dt['uw_id']][] = $dt['item_id'];
								$newData[$getDate]['total_item'] += 1;
							}
							
						}
					}
				}
			}
			
			$data_post['report_data'] = $newData;
		}
		
		//DO-PRINT
		if(!empty($do)){
			$data_post['do'] = $do;
		}else{
			$do = '';
		}
		
		$useview = 'print_reportUWRecap';
		if($do == 'excel'){
			$useview = 'excel_reportUWRecap';
		}
				
		$this->load->view('../../inventory/views/'.$useview, $data_post);	
	}
	

}