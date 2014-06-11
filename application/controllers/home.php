<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Home extends CI_Controller {
	//Pagina principal
	public function index(){
		redirect(base_url('ingresos'),'refresh');
	}
	public function autho(){
		if($this->plantillas->getUser()) redirect(base_url(),'refresh');
		$data['title']='Autentificacion';
		$data['top']['cssf'][]['href']=base_url('lib/css/view/home/autho.css');
		$data['top']['scripts'][]['src']=base_url('lib/js/view/home/autho.js');
		$data['top']['scripts'][]['src']=base_url('lib/js/jquery.md5.js');
		$this->plantillas->show_tpl('home/autho',$data);
	}
	public function session(){
		$user= strip_tags(strtolower($this->input->post('user')));
		$user= str_replace(" ", "", $user);
		$pass= strip_tags($this->input->post('pass'));
		$return= "home/autho";
		
		$sql= "SELECT COUNT(*) as count,id as iduser,username,password,production FROM users WHERE username='$user' AND password='$pass' AND status=1";
		$query = $this->db->query($sql);
		$user=$query->row_array();
		
		if($user['count']){
			unset($user['count']);
			unset($user['password']);
			$this->session->set_userdata($user);
			$this->plantillas->set_message("Bienvenido {$user['username']}",'information');
			$return= "ingresos";
		} else
			$this->plantillas->set_message(104);
		redirect(base_url($return),'refresh');
	}
	public function logout(){
		$this->session->sess_destroy();
		redirect(base_url('home/autho'),'refresh');
	}
	/*Muestra errores de la session, mediante AJAX*/
	public function getmessage(){
		$data=$this->plantillas->show_message();
		$this->output
		->set_content_type('application/json')
		->set_output(json_encode($data));
	}
}
