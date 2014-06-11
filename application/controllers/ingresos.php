<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class ingresos extends CI_Controller {
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
    function __construct(){
		parent::__construct();
		$this->load->model('ingresos_model','ingresos');
		$this->plantillas->is_session();
	}
	//Pagina principal
	public function index(){
		$data['top']['title']='Ingresos';
		$data['top']['cssf'][]['href']=base_url('lib/css/view/ingresos/home.css');
		$data['top']['scripts'][]['src']=base_url('lib/js/view/ingresos/home.js');
		$data['top']['main'][]=array('click'=>'buscar()','class'=>'search','label'=>'Buscar');
		$data['top']['main'][]=array('click'=>'add()','class'=>'pagenew','label'=>'Nuevo');
		$data['top']['main'][]=array('click'=>'eliminar()','class'=>'page_delete','label'=>'Eliminar');
		$data['top']['main'][]=array('click'=>'cancelar()','class'=>'cancelar','label'=>'Cancelar');
		$data['mes']=date('m');
		$data['anio']=date('Y');
		$data['start'] = "2014";
		$more = strtotime("+1 year", time());
		$data['end'] = date("Y", $more);
		
		$this->plantillas->show_tpl('ingresos/home',$data);
	}
	//Formulario de nuevo ingreso
	public function formIngreso(){
		$data['top']['title']='Ingresos';
		$data['top']['cssf'][]['href']=base_url('lib/css/view/ingresos/form.css');
		$data['top']['scripts'][]['src']=base_url('lib/js/view/ingresos/form.js');
		$data['top']['main'][]=array('click'=>'back()','class'=>'left','label'=>'Cacelar');
		$data['anio']=date('Y');
		$sf = $this->input->post('sf');
		if($sf) $sfa = explode("_",$sf);
		else $sfa = array();
		$data['sf']=$sfa;
		$user=$this->session->userdata('username');
		
		if(count($sfa)){
			$query=$this->db->query("SELECT * FROM ingresos WHERE folio={$sfa[1]}");
			$data['row']=$query->row_array();
			$data['sequence']=0;
			$data['row']['receptor']=(isset($data['row']['receptor']))?json_decode($data['row']['receptor'],true):array();
		} else {
			$sql="SELECT COUNT(*) AS id FROM ingresos";
			$query=$this->db->query($sql);
			$row=$query->row_array();
			$data['sequence']=(isset($row['id']))?$row['id']+1:1;
			$data['row']=array();
		}
		$this->plantillas->show_tpl('ingresos/formIngresos',$data);
	}
	public function save(){
		$post= $this->input->post();
		if(isset($post['factura']['fecha_expedicion']))
			$post['factura']['fecha_expedicion']=date("Y-m-d H:i:s",strtotime($post['factura']['fecha_expedicion']));
		else
			$post['factura']['fecha_expedicion']=date("Y-m-d H:i:s");
		$post['fecha'] = date("Y-m-d",strtotime($post['factura']['fecha_expedicion']));
		$post['serie'] = $post['factura']['serie'];
		$post['folio'] = $post['factura']['folio'];
		$post['subtotal'] = $post['factura']['subtotal'];
		$post['total'] = $post['factura']['total'];
		$post['factura'] = json_encode($post['factura']);
		unset($post['domicilio']);
		$post['receptor']['Domicilio'] = json_decode($post['receptor']['Domicilio'],true);
		$post['receptor'] = json_encode($post['receptor']);
		if(isset($post['impuestos']['retenidos']) && count($post['impuestos']['retenidos'])){
			foreach($post['impuestos']['retenidos'] as $key=>$val)
				$post['impuestos']['retenidos'][$key]['importe'] = $val['importe']*-1;
		}
		$post['impuestos'] = (isset($post['impuestos']))?json_encode($post['impuestos']):'{}';
		$post['user']=$this->session->userdata('username');
		$post['status']="error";
		$result = $this->ingresos->save('ingresos','folio',$post);
		
		if($result) $this->timbrar($post['folio']);
		else {
			$this->plantillas->set_message(5001,"Almacenando en DB el XML");
			redirect("ingresos", 'refresh');
		}
	}
	public function editRows($anio=''){
		$anio = (empty($anio))?date('Y'):$anio;
		$oper = $this->input->post('oper');
		$id = $this->input->post('id');
		if($id) $sf = explode("_",$id);
		else $sf = array();
		$post['folio']=(int)$sf[1];
		$r['data']=0;
		if($oper=='del'){
			$result=$this->ingresos->delete('ingresos','folio',$post);
			if($result){
				$this->plantillas->set_message('Factura eliminada','success');
				$r['data']=1;
			} else $this->plantillas->set_message(5002,'Al eliminar factura SQLite');
		}
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($r));
	}
	public function jsonRows($anio=''){
		$mes = $this->input->post('mes');
		if(empty($mes)) $mes=date('m');
		$page = $this->input->post('page');
		$page = (!$page)?1:$page;
		$limit = $this->input->post('rows');
		$limit = (!$limit)?12:$limit;
		$sidx =$this->input->post('sidx'); 
		$sidx = (!$sidx)?'fecha':$sidx; 
		$sord = $this->input->post('sord');
		$sord = (!$sord)?"":$sord;
		$anio = (empty($anio))?date('Y'):$anio;
		$search = $this->input->post('_search');
		$searchField = $this->input->post('searchField');
		$searchString = $this->input->post('searchString');
		$searchOper = $this->input->post('searchOper');
		$catid = $this->input->post('catid');
		
		if($search=='true' && !empty($search)){
			$where = $this->getWhereClause($searchField,$searchOper,$searchString);
			if(!empty($where)) $where .= " AND MONTH(fecha)='$mes'";
		} elseif(!empty($catid))
			$where = " WHERE catid='$catid' AND MONTH(fecha)='$mes'";
		else
			$where = " WHERE MONTH(fecha)='$mes'";
		$where.=" AND user='".$this->session->userdata('username')."'";
		
		$query = $this->db->query("SELECT COUNT(*) AS count FROM ingresos$where");
		$row = $query->row_array();
		$count = $row['count'];
		if( $count >0 )
			$total_pages = ceil($count/$limit);
		else
			$total_pages = 0;
	
		if($page > $total_pages)$page=$total_pages;
		if($page==0)$page=1;
		$start = $limit*$page - $limit; // do not put $limit*($page - 1)
		
		$sql="SELECT * FROM ingresos$where ORDER BY $sidx $sord LIMIT $start,$limit";
		$query = $this->db->query($sql);
		
		$responce = (object) array();
		$responce->page = $page; 
		$responce->total = $total_pages; 
		$responce->records = $count;
		$i=0;
		foreach($query->result_array() as $row){
			$responce->rows[$i]['id']=$row['serie'].'_'.$row['folio'];
			foreach($row as $key=>$val){
				if($key=='receptor'){
					$receptor = json_decode($val,true);
					$responce->rows[$i]['cell']['nombre']=$receptor['nombre'];
				} elseif($key=='impuestos'){
					$responce->rows[$i]['cell']['impuestos']=(float)$row['total']-$row['subtotal'];
				} elseif($key=='status'){
					$responce->rows[$i]['cell']['status']=(empty($row['status']))?'sin timbrar':$row['status'];
				} elseif($key=='subtotal' || $key=='total') {
					$responce->rows[$i]['cell'][$key]=(float)$val;
				} else
					$responce->rows[$i]['cell'][$key]=$val;
			}
			$responce->rows[$i]['cell']['id']=$row['serie'].'_'.$row['folio'];
			$i++; 
		}
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($responce));
	}
	public function timbrar($id=''){
		$id=(empty($id))?(int)$this->input->post('folio'):$id;
		if($id<1){
			$this->plantillas->set_message(5001,"EL folio ($id), a timbrar no es correcto");
			redirect("ingresos", 'refresh');
		}
		$sql="SELECT * FROM ingresos WHERE folio=$id";
		$query = $this->db->query($sql);
		$row=$query->row_array();
		if(!count($row)){
			$this->plantillas->set_message(5001,"EL folio ($id), no se encontro en ls DB");
			redirect("ingresos", 'refresh');
		}
		$data['factura'] = json_decode($row['factura'],true);
		if(!isset($data['factura']['descuento'])) $data['factura']['descuento'] = 0.0;
		if(isset($data['factura']['fecha_expedicion']))
			$data['factura']['fecha_expedicion']=date("Y-m-d H:i:s",strtotime($data['factura']['fecha_expedicion']));
		else
			$data['factura']['fecha_expedicion']=date("Y-m-d H:i:s");
		$data['receptor'] = json_decode($row['receptor'],true);
		foreach($data['receptor']['Domicilio'] as $key => $val)
			$data['receptor']['Domicilio'][$key]= htmlentities($val);
		$data['conceptos'] = json_decode($row['conceptos'],true);
		$data['impuestos'] = json_decode($row['impuestos'],true);
		//Emisor
		$sql="SELECT value FROM config WHERE name='emisor'";
		$query = $this->db->query($sql);
		$row=$query->row_array();
		$data['emisor']= json_decode($row['value'],true);
		$data['factura']['RegimenFiscal'] = (isset($data['emisor']['RegimenFiscal']))?$data['emisor']['RegimenFiscal']:'';
		unset($data['emisor']['RegimenFiscal']);
		$data['factura']['LugarExpedicion'] = $data['emisor']['ExpedidoEn']['municipio'].', '.$data['emisor']['ExpedidoEn']['estado'];
		if(empty($data['emisor']['DomicilioFiscal']['noInterior'])) unset($data['emisor']['DomicilioFiscal']['noInterior']);
		if(empty($data['emisor']['ExpedidoEn']['noInterior'])) unset($data['emisor']['ExpedidoEn']['noInterior']);
		//PAC
		$sql="SELECT value FROM config WHERE name='PAC'";
		$query = $this->db->query($sql);
		$row=$query->row_array();
		$user = $this->session->userdata('username');
		$ruta = "lib".DS."keycer".DS.$user.DS;
		$data['PAC']= json_decode($row['value'],true);
		$data['conf']['cer'] = $ruta.$data['PAC']['cer'];
		unset($data['PAC']['cer']);
		$data['conf']['key'] = $ruta.$data['PAC']['key'];
		unset($data['PAC']['key']);
		$data['conf']['pass'] = $data['PAC']['SAT']['pass'];
		unset($data['PAC']['SAT']);
		$this->load->library('xml',$data);
		
		$file = $this->xml->cfdi_generar_xml();
		if($file['return']){
			$res = $this->xml->cfdi_timbrar($file['xml']);
			if($res['codigo_mf_numero']==0){
				$xml = simplexml_load_string((string)$res['cfdi']);
				$ns = $xml->getNamespaces(true);
				$xml->registerXPathNamespace('c', $ns['cfdi']);
				$xml->registerXPathNamespace('t', $ns['tfd']);
				$sat = $xml->xpath('//t:TimbreFiscalDigital');	
				foreach($sat[0]->attributes() as $key => $val)
					$timbre[$key]=(string)$val[0];
				$jt = json_encode($timbre);
				/*
				if(isset($data['impuestos']['retenidos']) && count($data['impuestos']['retenidos'])){
					foreach($data['impuestos']['retenidos'] as $key=>$val){
						$data['impuestos']['retenidos'][$key]['importe'] = $val['importe']*-1;
					}
				}
				*/
				$db = array(
					'folio'=>$data['factura']['folio'],
					'uuid'=>$res['uuid'],
					'status'=>'timbrada',
					'sat'=>$jt
				);
				$result=$this->ingresos->save('ingresos','folio',$db);
				if($result) $this->plantillas->set_message($res['codigo_mf_texto'],'success');
			} else $this->plantillas->set_message(6000,$res['codigo_mf_texto']);
		} else $this->plantillas->set_message(6000,"Fallo al crear XML");
		redirect("ingresos", 'refresh');
	}
	//Cancelar factura
	public function cancelar(){
		$data = $this->input->post();
		$sql="SELECT COUNT(*) AS count,status,uuid FROM ingresos WHERE folio={$data['folio']}";
		$query = $this->db->query($sql);
		$row=$query->row_array();
		if($row['count'] && $row['status']=='timbrada'){
			//PAC
			$sql="SELECT value FROM config WHERE name='PAC'";
			$query = $this->db->query($sql);
			$pac = $query->row_array();
			$data['PAC']= json_decode($pac['value'],true);
			$user = $this->session->userdata('username');
			$ruta = "lib".DS."keycer".DS.$user.DS;
			$data['conf']['cer'] = $ruta.$data['PAC']['cer'];
			unset($data['PAC']['cer']);
			$data['conf']['key'] = $ruta.$data['PAC']['key'];
			unset($data['PAC']['key']);
			$data['conf']['pass'] = $data['PAC']['SAT']['pass'];
			unset($data['PAC']['SAT']);
			$data['cfdi']=ROOT."files".DS.$data['anio'].DS.$data['mes'].DS."ingresos".DS.$row['uuid'].".xml";
			$fileZip = str_replace('xml','zip',$data['cfdi']);	

			if(file_exists($data['cfdi']) || file_exists($fileZip)){
				$this->load->library('xml',$data);
				$error=array();
				$res=$this->xml->cfdi_cancelar((string)$row['uuid']);
				if(isset($res['return'])) preg_match('/^[0-9]*/',$res['return'],$error, PREG_OFFSET_CAPTURE);
				
				if(!count($error) || $error[0]=="402"){
					$db = array(
						'folio'=>$data['folio'],
						'status'=>'cancelada',
					);
					$result=$this->ingresos->save($db);
					$msg = (isset($res['codigo_mf_texto']))?$res['codigo_mf_texto']:'';
					if($result) $this->plantillas->set_message($msg,'success');
				} else $this->plantillas->set_message(6001,$res['return']);
			} else $this->plantillas->set_message(6002,"EL archivo CFDI no existe");
		} else $this->plantillas->set_message(6002,"EL CFDI no esta registrado");
		redirect("ingresos", 'refresh');
	}
	//Descarga un registro en PDF
	public function toPdf($id,$file){
		if(empty($id) || empty($file)) die('Parametros no enviados');
		$sql="SELECT * FROM ingresos WHERE folio=$id";
		$query = $this->db->query($sql);
		$row=$query->row_array();
		if(!count($row)) die('Error: Folio no encontrado en la DB.');
		$factura = json_decode($row['factura'],true);
		$receptor = json_decode($row['receptor'],true);
		$conceptos = json_decode($row['conceptos'],true);
		$impuestos = json_decode($row['impuestos'],true);
		$timbre = json_decode($row['sat'],true);
		
		$sql="SELECT value FROM config WHERE name='emisor'";
		$query = $this->db->query($sql);
		$conf=$query->row_array();
		$emisor = json_decode($conf['value'],true);
		
        $this->load->library('mypdf');
        $this->mypdf->AddPage();
        $this->mypdf->AliasNbPages();
        $this->mypdf->SetFont('Arial','B',10);
        $this->mypdf->SetTextColor(0);
        $this->mypdf->SetFillColor(247,246,240);
		$this->mypdf->SetDrawColor(247,246,240);
		$this->mypdf->SetLineWidth(.3);
        //Expedido en
		$this->mypdf->Cell(0,0,'Expedido en','',0,'R');
		$this->mypdf->Ln(4);
        if(!count($emisor['ExpedidoEn']) && count($emisor['DomicilioFiscal']))
        $emisor['ExpedidoEn']=$emisor['DomicilioFiscal'];
        
        $this->mypdf->SetFont('Arial','I',8);
        $calle = (!empty($emisor['ExpedidoEn']['calle']))?$emisor['ExpedidoEn']['calle']:'Domicilio conocido';
        $calle .= ($calle=='Domicilio conocido' || empty($emisor['ExpedidoEn']['noExterior']))?'':" #{$emisor['ExpedidoEn']['noExterior']}";
        $calle .= ($calle=='Domicilio conocido' || empty($emisor['ExpedidoEn']['noInterior']))?'':" INT. {$emisor['ExpedidoEn']['noInterior']}";
        $this->mypdf->Cell(0,0,utf8_decode($calle),'',0,'R');
        $this->mypdf->Ln(3);
        $colonia = (!empty($emisor['ExpedidoEn']['colonia']))?"COL. {$emisor['ExpedidoEn']['colonia']}  ":'';
        $colonia .= (!empty($emisor['ExpedidoEn']['CodigoPostal']))?"C.P. {$emisor['ExpedidoEn']['CodigoPostal']}":'';
		if(!empty($colonia)){	
			$this->mypdf->Cell(0,0,utf8_decode($colonia),'',0,'R');
			$this->mypdf->Ln(3);
		}
		$localidad="";
		if($emisor['ExpedidoEn']['localidad']!=$emisor['ExpedidoEn']['municipio'])
			$localidad = (!empty($emisor['ExpedidoEn']['localidad']))?"LOC. {$emisor['ExpedidoEn']['localidad']}  ":'';
		$localidad .= (!empty($emisor['ExpedidoEn']['municipio']))?"{$emisor['ExpedidoEn']['municipio']}":'';
		if(!empty($localidad)){		
			$this->mypdf->Cell(0,0,utf8_decode($localidad),'',0,'R');
			$this->mypdf->Ln(3);
		}
		$estado = (!empty($emisor['ExpedidoEn']['estado']))?"{$emisor['ExpedidoEn']['estado']}":'';
		$estado .= (!empty($estado) && !empty($emisor['ExpedidoEn']['pais']))?", {$emisor['ExpedidoEn']['pais']}":$emisor['ExpedidoEn']['pais'];
        if(!empty($localidad)){		
			$this->mypdf->Cell(0,0,utf8_decode($estado),'',0,'R');
		}
		$this->mypdf->Ln(-8);
        
        $serie=(empty($row['serie']))?"-":$row['serie']." ";
		$folio=(empty($row['folio']))?"-":$row['folio'];
        $this->mypdf->SetFont('Arial','',10);
        $this->mypdf->Cell(20,0,'Serie: '.$serie);
        $this->mypdf->Cell(0,0,'Folio: '.$folio);
		$this->mypdf->Ln(11);
		
        $this->mypdf->SetFont('Arial','B',12);
        $this->mypdf->Cell(0,0,"Resumen");
        $this->mypdf->Ln(4);
        $this->mypdf->SetFont('Arial','B',10);
        $this->mypdf->Cell(12,7,'UUID','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $this->mypdf->Cell(75,7,strtoupper($row['uuid']),'TBLR',0,'L',0);
        $this->mypdf->SetFont('Arial','B',10);
        $this->mypdf->Cell(13,7,'Fecha','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $this->mypdf->Cell(30,7,$row['fecha'],'TBLR',0,'L',0);
        $this->mypdf->SetFont('Arial','B',10);
		$this->mypdf->Cell(30,7,'T. comprobante','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $this->mypdf->Cell(30,7,utf8_decode($factura['tipocomprobante']),'TBLR',0,'L',0);
        $this->mypdf->Ln(8);
        $this->mypdf->SetFont('Arial','B',10);
		$this->mypdf->Cell(20,7,'T. cambio','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $tcambio=(empty($factura['tipocambio']))?"1.00":number_format((float)$factura['tipocambio'],2);
        $this->mypdf->Cell(13,7,$tcambio,'TBLR',0,'L',0);
        $this->mypdf->SetFont('Arial','B',10);
		$this->mypdf->Cell(20,7,'F. de pago','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $this->mypdf->Cell(50,7,strtolower(utf8_decode($factura['forma_pago'])),'TBLR',0,'L',0);
        $this->mypdf->SetFont('Arial','B',10);
		$this->mypdf->Cell(20,7,'M. de pago','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $this->mypdf->Cell(30,7,strtolower(utf8_decode($factura['metodo_pago'])),'TBLR',0,'L',0);
        $this->mypdf->SetFont('Arial','B',10);
		$this->mypdf->Cell(12,7,'N. cta','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $ncta = (empty($factura['NumCtaPago']))?"-":str_pad($factura['NumCtaPago'],16,'*',STR_PAD_LEFT);
        $this->mypdf->Cell(25,7,utf8_decode($ncta),'TBLR',0,'L',0);
        $this->mypdf->Ln(11);
		/////////////EMISOR//////////////////////
		$this->mypdf->SetFont('Arial','B',12);
        $this->mypdf->Cell(0,0,"Receptor");
        $this->mypdf->Ln(4);
        $this->mypdf->SetFont('Arial','B',10);
		$this->mypdf->Cell(10,7,'RFC','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $this->mypdf->Cell(30,7,strtoupper(utf8_decode($receptor['rfc'])),'TBLR',0,'L',0);
        $this->mypdf->SetFont('Arial','B',10);
		$this->mypdf->Cell(17,7,'Nombre','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $this->mypdf->Cell(0,7,strtoupper(utf8_decode($receptor['nombre'])),'TBLR',0,'L',0);
        $this->mypdf->Ln(8);
        $this->mypdf->SetFont('Arial','B',10);
		$this->mypdf->Cell(30,7,'Domicilio fiscal','TBLR',0,'L',1);
        $this->mypdf->SetFont('Arial','I',8);
        $street=(empty($receptor['Domicilio']['calle']))?"Domicilio conocido":html_entity_decode($receptor['Domicilio']['calle']);
		$street.=(empty($receptor['Domicilio']['noExterior']))?" SN":" ".html_entity_decode($receptor['Domicilio']['noExterior']);
		$street.=(empty($receptor['Domicilio']['noInterior']))?"":" (".html_entity_decode($receptor['Domicilio']['noInterior']).")";
		$street.=(empty($receptor['Domicilio']['colonia']))?"":", ".html_entity_decode($receptor['Domicilio']['colonia']);
		$street.=(empty($receptor['Domicilio']['localidad']))?"":", Loc. ".html_entity_decode($receptor['Domicilio']['localidad']);
		$street.=(empty($receptor['Domicilio']['municipio']))?"":", ".html_entity_decode($receptor['Domicilio']['municipio']);
		$street.=(empty($receptor['Domicilio']['estado']))?"":", ".html_entity_decode($receptor['Domicilio']['estado']);
		$street.=(empty($receptor['Domicilio']['pais']))?"":", ".html_entity_decode($receptor['Domicilio']['pais']);
		$street.=(empty($receptor['Domicilio']['codigoPostal']))?"":", C.P.".html_entity_decode($receptor['Domicilio']['codigoPostal']);
        $this->mypdf->Cell(0,7,strtolower(utf8_decode($street)),'TBLR',0,'L',0);
        $this->mypdf->Ln(11);
		$this->mypdf->SetFont('Arial','B',12);
        $this->mypdf->Cell(0,0,"Conceptos");
        $this->mypdf->Ln(3);
        $this->mypdf->SetFillColor(247,246,240);
		$this->mypdf->SetDrawColor(247,246,240);
        $this->mypdf->SetFont('Arial','TBL',10);
        $this->mypdf->Cell(15,7,'CANT','TBL',0,'L','1');
        $this->mypdf->Cell(17,7,'UNIDAD','TBL',0,'R','1');
        $this->mypdf->Cell(96,7,'DESCRIPCION','TBL',0,'L','1');
        $this->mypdf->Cell(31,7,'UNITARIO','TBL',0,'R','1');
        $this->mypdf->Cell(31,7,'IMPORTE','TBR',0,'R','1');
        $this->mypdf->Ln(8);
        $importe=0;
        $moneda=(empty($factura['Moneda']))?"MXN":$factura['Moneda'];
        $moneda=(strlen($moneda)>3)?"MXN":$moneda;
		$this->mypdf->SetFont('Arial','',8);
		// Datos
        foreach($conceptos as $item){
			$importe+=(float)$item['importe'];
            $this->mypdf->Cell(15,5,number_format((float)$item['cantidad'],2),'LBR',0,'L');
            $this->mypdf->Cell(17,5,utf8_decode($item['unidad']),'BR',0,'R');
            $this->mypdf->Cell(100,5,utf8_decode($item['descripcion']),'BR',0,'L');
            $this->mypdf->Cell(29,5,money_format('%.2n',(float)$item['valorunitario']) ." ".$moneda,'BR',0,'R');
            $this->mypdf->Cell(29,5,money_format('%.2n',(float)$item['importe']) ." ".$moneda,'BR',0,'R');
            $this->mypdf->Ln(5);
		}
		//Footer de conceptos
		$this->mypdf->SetFillColor(247,246,240);
		$this->mypdf->SetFont('Arial','B',9);
		$this->mypdf->Cell(161,7,"Subtotal",'LBR',0,'R',1);
		$this->mypdf->Cell(0,7,money_format('%.2n',(float)$importe)." ".$moneda,'LBR',0,'R',1);
		$this->mypdf->Ln(7);
		$impuesto=0;
		foreach($impuestos as $key=>$val){
			if(!count($val)) continue;
			$this->mypdf->Cell(161,7,"Impuesto $key",'LB',0,'R',1);
			$this->mypdf->Cell(0,7,"",'BR',0,'',1);
			$this->mypdf->Ln(7);
			foreach($val as $imp){
				if($key=='retenidos')
					$impuesto+=(float)$imp['importe'] * -1;
				else
					$impuesto+=(float)$imp['importe'];
				$this->mypdf->Cell(161,7,"{$imp['impuesto']}: {$imp['tasa']}%",'LBR',0,'R',1);
				$this->mypdf->Cell(0,7,money_format('%.2n',(float)$imp['importe'])." ".$moneda,'BR',0,'R',1);
				$this->mypdf->Ln(7);
			}
		}
		$this->mypdf->Cell(161,7,"Total",'LBR',0,'R',1);
		$this->mypdf->Cell(0,7,money_format('%.2n',$importe+$impuesto)." ".$moneda,'LBR',0,'R',1);
		$this->mypdf->Ln(8);
		$this->mypdf->SetFont('Arial','',12);
		$this->mypdf->Cell(0,7,num2letras(number_format((float)$importe+$impuesto,2),$moneda),'',0,'C');
		if($row['status']=='timbrada'){
			$this->mypdf->Ln(10);
			$img = str_replace('pdf','png',$file);
			if(file_exists($img)){
				$this->mypdf->Image($img);
				$this->mypdf->Ln(-47);
			} else die("No se encontro el archivo PNG:<br/>$img");
			$this->mypdf->SetFont('Arial','B',10);
			$this->mypdf->Cell(50,6,"");
			$this->mypdf->Cell(0,6,"Sello digital del CFDI:");
			$this->mypdf->Ln(6);
			$this->mypdf->SetFont('Arial','',9);
			$this->mypdf->Cell(50,4,"");
			$this->mypdf->MultiCell(0,4,$timbre['selloCFD']);
			///////
			$this->mypdf->Ln(3);
			$this->mypdf->SetFont('Arial','B',10);
			$this->mypdf->Cell(50,6,"");
			$this->mypdf->Cell(0,6,"Sello del SAT:");
			$this->mypdf->Ln(6);
			$this->mypdf->SetFont('Arial','',9);
			$this->mypdf->Cell(50,4,"");
			$this->mypdf->MultiCell(0,4,$timbre['selloSAT']);
			/////
			$this->mypdf->Ln(4);
			$this->mypdf->SetFont('Arial','B',10);
			$this->mypdf->Cell(62,6,"No de Serie del Certificado del SAT:");
			$this->mypdf->SetFont('Arial','',10);
			$this->mypdf->MultiCell(0,6,$timbre['noCertificadoSAT']);
			///////
			$this->mypdf->Ln(1);
			$this->mypdf->SetFont('Arial','B',10);
			$this->mypdf->Cell(40,6,"Fecha de certificacion:");
			$this->mypdf->SetFont('Arial','',10);
			$this->mypdf->MultiCell(0,6,dateLong(date("Y-m-d",strtotime(str_replace("T", " ",$timbre['FechaTimbrado'])))));
			$this->mypdf->SetY(-30);
			$this->mypdf->SetFont('Arial','',11);
			$this->mypdf->Cell(0,0,"Representacion impresa del XML {$row['uuid']}",0,'','C');
		}
		if($row['status']!='timbrada'){
			//Si esta timbrada
			$this->mypdf->SetFont('Arial','B',50);
			$this->mypdf->SetTextColor(255,192,203);
			$this->mypdf->RotatedText(60,190,'N O    V A L I D A',45);
		}
		
		
        if(!empty($row['uuid'])) $out="F"; else $out="I";
        $this->mypdf->Output($file,$out);
        return (file_exists($file))? $file : false;
	}
	public function download(){
		$id=$this->input->post('folio');
		$mes=$this->input->post('mes');
		$anio=$this->input->post('anio');
		if(empty($id) || empty($mes) || empty($anio)) die('Parametros no enviados');
		$sql="SELECT uuid FROM ingresos WHERE folio=$id";
		$query = $this->db->query($sql);
		$row=$query->row_array();
		$pdf=ROOT."files/$anio/$mes/ingresos/".$row['uuid'].".pdf";
		$xml = str_replace('pdf','xml',$pdf);
		$fileZip = str_replace('pdf','zip',$pdf);
		$png = str_replace('pdf','png',$pdf);
		
		if(!file_exists($pdf) && !file_exists($fileZip)){
			$pdf=$this->toPdf($id,$pdf);
			if(!$pdf) die('Error: al crear el archivo PDF');
		}
		
		if(!file_exists($fileZip)){
			if(!file_exists($xml)) die('Error: Se perdio el archivo XML');
			$zip = new ZipArchive();
			if($zip->open($fileZip,ZIPARCHIVE::CREATE) === true) {
				$zip->addFile($pdf,$row['uuid'].'.pdf');
				$zip->addFile($xml,$row['uuid'].'.xml');
				$zip->addFile($png,$row['uuid'].'.png');
				$zip->close();
				unlink($pdf);
				unlink($xml);
				unlink($png);
			} else die('Error: al crear archivo ZIP');
		}
		header("Content-type:application/zip");
		header("Content-Disposition: attachment; filename={$row['uuid']}.zip");
		header("Content-Transfer-Encoding: binary");
		readfile($fileZip);
	}
}
