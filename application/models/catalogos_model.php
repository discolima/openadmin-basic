<?php if ( ! defined('BASEPATH')) exit('No se permite el acceso directo al script');

class Catalogos_model extends CI_Model {
    function __construct(){
        parent::__construct();
    }
    //Almacenamos en base de datos
	function save($table='',$id='',$post=array()){
		if(!count($post)) return false;
		if(empty($table)) return false;
		if(empty($id)) return false;
		if($post[$id]=='_empty' || $post[$id]=='null') $post[$id]='';
		
		$sql="SELECT COUNT(*) AS count FROM $table WHERE $id='{$post[$id]}'";
		$query = $this->db->query($sql);
		$row=$query->row_array();
		$count = (int)$row['count'];
		$return = true;
		if(!$count){
			unset($post[$id]);
			if(!$this->db->insert($table,$post)){
				$this->plantillas->set_message(5001,"Fallo en catalogos");
				$return = false;
			} 
		} else {
			$this->db->where($id,$post[$id]);
			if(!$this->db->update($table,$post)){
				$this->plantillas->set_message(5001,"Fallo en catalogos");
				$return = false;
			} 
		}
		return $return;
	}
	//Elimina de la base de datos
	function delete($table='',$id='',$post=array()){
		if(!count($post)) return false;
		if(empty($table)) return false;
		if(empty($id)) return false;
		if($post[$id]=='_empty' || !isset($post[$id]) || $post[$id]=='null') $post[$id]='';
		if($this->db->delete($table,array($id=>$post[$id]))){
			$return = true;
		} else {
			$this->plantillas->set_message(5002,"Fallo en catalogos");
			$return = false;
		}
		return $return;
	}
	//Buscamos registro
	function row($table='',$id='',$post=array()){
		if(!count($post)) return false;
		if(empty($table)) return false;
		if(empty($id)) return false;
		if($post[$id]=='_empty' || !isset($post[$id])) $post[$id]=0;
		
		$this->db->where($id,$post[$id]);
		$query=$this->db->select($table);
		return $query->result_array();
	}
}
