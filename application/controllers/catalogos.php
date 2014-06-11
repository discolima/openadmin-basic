<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Catalogos extends CI_Controller {
	function __construct(){
		parent::__construct();
		$this->plantillas->is_session();
		$this->load->model('catalogos_model','catalogo');
	}
	//Pagina principal
	public function index(){
		$data['top']['title']='Lista de contactos';
		$data['top']['cssf'][]['href']=base_url('lib/css/view/catalogos/contactos.css');
		$data['top']['scripts'][]['src']=base_url('lib/js/jquery.html5form-1.5-min.js');
		$data['top']['scripts'][]['src']=base_url('lib/js/view/catalogos/contactos.js');
		$data['top']['main'][]=array('click'=>'buscar()','class'=>'search','label'=>'Buscar');
		$data['top']['main'][]=array('click'=>'edit()','class'=>'contactEdit','label'=>'Editar');
		$data['top']['main'][]=array('click'=>'add()','class'=>'contactoAdd','label'=>'Nuevo');
		$data['top']['main'][]=array('click'=>'eliminar()','class'=>'page_delete','label'=>'Eliminar');
		$this->plantillas->show_tpl('catalogos/showcontacs',$data);
	}
	public function jsonContacts(){
		$page = $this->input->post('page');
		$page = (!$page)?1:$page;
		$limit = $this->input->post('rows');
		$limit = (!$limit)?12:$limit;
		$sidx =$this->input->post('sidx'); 
		$sidx = (!$sidx)?'name':$sidx; 
		$sord = $this->input->post('sord');
		$sord = (!$sord)?"":$sord;
		$search = $this->input->post('_search');
		$searchField = $this->input->post('searchField');
		$searchString = $this->input->post('searchString');
		$searchOper = $this->input->post('searchOper');
		$user=$this->session->userdata('username');
		
		if($search=='true' && !empty($search)){
			$where = $this->getWhereClause($searchField,$searchOper,$searchString);
			$where .= " AND user ='$user'";
		} else
			$where = " WHERE user ='$user'";
		
		$sql="SELECT COUNT(*) AS count FROM contactos$where";
		$query = $this->db->query($sql);
		$row = $query->row_array();
		$count = (int)$row['count'];
		if( $count > 0 )
			$total_pages = ceil($count/$limit);
		else
			$total_pages = 0;
	
		if ($page > $total_pages)$page=$total_pages;
		if($page==0)$page=1;
		$start = $limit*$page - $limit;
		
		$sql="SELECT * FROM contactos$where ORDER BY $sidx $sord LIMIT $start,$limit";
		$query = $this->db->query($sql);
		$responce = (object) array();
		$responce->page = $page; 
		$responce->total = $total_pages; 
		$responce->records = $count;
		$i=0;
		foreach($query->result_array() as $row){
			if(isset($row['id'])) $responce->rows[$i]['id']=$row['rfc'];
			foreach($row as $key=>$val){
				if($key==='domicilioFiscal'){
					$df = json_decode($val,true);
					$namedom = $df['calle'];
					$namedom .= (!empty($df['noExterior']))?" ".$df['noExterior']:" SN";
					$namedom .= (!empty($df['noInterior']))?" (" .$df['noInterior'].")":"";
					$responce->rows[$i]['cell'][$key]=$namedom;
				} else $responce->rows[$i]['cell'][$key]=$val;
			}
			$i++; 
		}
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($responce));
	}
	public function verContacto($rfc=''){
		if(!empty($rfc)){
			$sql="SELECT * FROM contactos WHERE rfc='$rfc'";
			$query = $this->db->query($sql);
			$data['row']=$query->row_array();
			if(count($data['row'])){
				$data['top']['title']=$data['row']['name'];
				$data['top']['cssf'][]['href']=base_url('lib/css/view/catalogos/contacto.css');
				$data['top']['scripts'][]['src']=base_url('lib/js/jquery.html5form-1.5-min.js');
				$data['top']['scripts'][]['src']=base_url('lib/js/view/catalogos/contacto.js');
				$data['top']['main'][]=array('click'=>'back()','class'=>'left','label'=>'Volver');
				$data['top']['main'][]=array('click'=>'eliminar()','class'=>'page_delete','label'=>'Eliminar');
				$this->plantillas->show_tpl('catalogos/verContacto',$data);
			} else {
				$this->plantillas->set_message(102,"El RFC no se encontro.");
				redirect("catalogos", 'refresh');
			}
		} else {
			 $this->plantillas->set_message(102,"Error: no se enviaron parametros.");
			 redirect("catalogos", 'refresh');
		}
	}
	public function jsonContacSearch(){
		$rfc = $this->input->post('rfc');
		if(empty($rfc)) return json_encode('');
		$sql="SELECT name,rfc,domicilioFiscal FROM contactos WHERE rfc LIKE '$rfc%' OR name LIKE '$rfc%'";
		$query=$this->db->query($sql);
		$i=0;
		foreach($query->result_array() as $row){
			$responce[$i]['label'] = $row['rfc'].'-'.$row['name'];
			$responce[$i]['value'] = $row['rfc'];
			$responce[$i]['row'] = $row;
			$i++;
		}
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($responce));
	}
	public function saveContacto(){
		$post=$this->input->post();
		$post['oper']=(isset($post['oper']))?$post['oper']:'';
		$return['return']=false;
		
		if($post['oper']=="del"){
			unset($post['oper']);
			$post['rfc']=(string)$post['id'];
			unset($post['id']);
			
			$sql="SELECT COUNT(*) AS count FROM ingresos WHERE receptor LIKE '%{$post['rfc']}%'";
			$query = $this->db->query($sql);
			$row=$query->row_array();
			if(!$row['count']){
				$result=$this->catalogo->delete('contactos','rfc',$post);
				if($result)
					$this->plantillas->set_message('Contacto eliminado del catalogo','success');
				$return['return']=$result;
			} else
				$this->plantillas->set_message(6002,"Este contacto tiene facturas generadas");
			if(isset($post['goto']) && $post['goto']=="true")
				redirect("catalogos", 'refresh');
			else {
				$this->output
				->set_content_type('application/json')
				->set_output(json_encode($return));
			}
		} else {
			if(isset($post['domicilio'])){
				foreach($post['domicilio'] as $key => $val)
					$post['domicilio'][$key]= htmlentities($val);
				$post['domicilioFiscal']=json_encode($post['domicilio']);
				unset($post['domicilio']);
			}
			$post['rfc']=(!empty($post['rfc']))?htmlentities($post['rfc']):'XAX000000XAX';
			$post['name']=htmlentities($post['name']);
			
			if($post['oper']=="add"){
				unset($post['oper']);
				$post['user']=$this->session->userdata('username');
				$post['id']=0;
				$result=$this->catalogo->save('contactos','id',$post);
				if($result)
					$this->plantillas->set_message('Contacto agregado a catalogo','success');
				redirect("catalogos/verContacto/{$post['rfc']}", 'refresh');
					
			} elseif($post['oper']=="edit"){
				unset($post['oper']);
				$result=$this->catalogo->save('contactos','rfc',$post);
				if($result)
					$this->plantillas->set_message('Contacto actualizado en catalogo','success');
				redirect("catalogos", 'refresh');
			}
		}
	}
	private $ops = array(
		'eq'=>'=', //equal
		'ne'=>'<>',//not equal
		'lt'=>'<', //less than
		'le'=>'<=',//less than or equal
		'gt'=>'>', //greater than
		'ge'=>'>=',//greater than or equal
		'bw'=>'LIKE', //begins with
		'bn'=>'NOT LIKE', //doesn't begin with
		'in'=>'LIKE', //is in
		'ni'=>'NOT LIKE', //is not in
		'ew'=>'LIKE', //ends with
		'en'=>'NOT LIKE', //doesn't end with
		'cn'=>'LIKE', // contains
		'nc'=>'NOT LIKE'  //doesn't contain
	);
	private function getWhereClause($col, $oper, $val){
        $ops = $this->ops;
        if($oper == 'bw' || $oper == 'bn') $val .= '%';
        if($oper == 'ew' || $oper == 'en' ) $val = '%'.$val;
        if($oper == 'cn' || $oper == 'nc' || $oper == 'in' || $oper == 'ni') $val = '%'.$val.'%';
        return " WHERE $col {$ops[$oper]} '$val'";
    }
}
