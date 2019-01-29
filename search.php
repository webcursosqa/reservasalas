<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * 
 *
 * @package    local
 * @subpackage reservasalas
 * @copyright  2014 Francisco García Ralph (francisco.garcia.ralph@gmail.com)
 * 					Nicolás Bañados Valladares (nbanados@alumnos.uai.cl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//Pagina de reserva para los usuarios
//capacidades de: reservar, modificar, cancelar, consultar
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/local/reservasalas/forms.php');
require_once($CFG->dirroot.'/local/reservasalas/lib.php');
require_once($CFG->dirroot.'/local/reservasalas/tablas.php');

global $DB,$USER;

$action = optional_param('action', 'ver', PARAM_TEXT);
$reservaid = optional_param('reservaid', null, PARAM_INT);
$responsable = optional_param('responsable', null, PARAM_TEXT);
$campus = optional_param('campus', 0, PARAM_INT);
$eventtype = optional_param('eventtype', 0, PARAM_INT);
$roomsname = optional_param('roomsname', null, PARAM_TEXT);

$baseurl = new moodle_url('/local/reservasalas/search.php'); //importante para crear la clase pagina
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($baseurl);

$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('reserveroom', 'local_reservasalas'));
$PAGE->set_heading(get_string('reserveroom', 'local_reservasalas'));
$PAGE->navbar->add(get_string('roomsreserve', 'local_reservasalas'));
$PAGE->navbar->add(get_string('searchroom', 'local_reservasalas'),'search.php');

echo  $OUTPUT->header(); //Imprime el header
echo $OUTPUT->heading(get_string('searchroom', 'local_reservasalas'));

if($action=="remove"){
    
    if($reservaid == null){
        print_error(get_string('invalidid','Reserva_Sala'));
        
    }
    if(!has_capability('local/reservasalas:delete', $context)) {
        print_error(get_string('INVALID_ACCESS','Reserva_Sala'));
        
    }
    if(count($DB->get_record('reservasalas_reservas',array('id' => $reservaid))) > 0){
        $DB->delete_record('reservasalas_reservas', array ('id'=>$reservaid));
        echo html_writer::div(get_string('reserveseliminated','local_reservasalas'), 'alert alert-success');
        
    }else{
        print_error(get_string('invalidid','Reserva_Sala'));
        
    }
    $action = 'ver';
}

if($action=="ver"){
    $buscador = new roomSearch();
    $buscador->display();
    $condition = '0';
    if($fromform = $buscador->get_data()){
        $responsable = $fromfrom->responsable;
        $campus = $formform->campus;
        $eventtype = $formform->eventType;
        $roomsname = $formform->roomsname;
    }
    if(isset($fromform)){
        $params = Array();
    	$date=date("Y-m-d",$fromform->startdate);
    	$endDate=date("Y-m-d",$fromform->enddate);
    
    	$select =" fecha_reserva >= ? AND fecha_reserva <= ? ";
    	$params = array(
    	    $date,
    	    $endDate
    	);
    	if($fromform->responsable != null){
    		// search by user email		
    		$userselect= $DB->sql_like('username', ':search1' , $casesensitive = false, $accentsensitive = false, $notlike = false).'
                        OR '.$DB->sql_like('firstname', ':search2' , $casesensitive = false, $accentsensitive = false, $notlike = false).'
                        OR '.$DB->sql_like('lastname', ':search3' , $casesensitive = false, $accentsensitive = false, $notlike = false);
    		$userparams = array(
    		    'search1'=>$fromform->responsable,
    		    'search2'=>$fromform->responsable,
    		    'search3'=>$fromform->responsable
    		);
    		if( $users=$DB->get_fieldset_select("user",'id',$userselect, $userparams) ){
    		    list ( $usersqlin, $userparams ) = $DB->get_in_or_equal ( $users );
    		    $select.="AND alumno_id $usersqlin";
    		    $params = array_merge($params,$userparams);
    		}	
    	}
    	
    	if($fromform->campus > 0){
    	    // set the buildings ids ready for the query
    	    list ( $edificiosqlin, $edificioparams ) = $DB->get_in_or_equal ( $fromform->campus );
    	    
            if($fromform->eventType==0 && $fromform->roomsname==null){
                $salas=$DB->get_fieldset_select('reservasalas_salas','id','edificios_id '.$edificiosqlin,$edificioparams);
                
            }
            else if($fromform->eventType!=0 && $fromform->roomsname==null){
                $salasparam = array_merge($edificioparams,array($fromform->eventType));
                $salas=$DB->get_fieldset_select('reservasalas_salas','id','edificios_id '.$edificiosqlin.' AND tipo = ?',$salasparam);
                
            }
            else if($fromform->eventType!=0 && $fromform->roomsname!=null){
                $salasselect = 'edificios_id '.$edificiosqlin.' 
                                AND tipo = ? 
                                AND '.$DB->sql_like('nombre', '?' , $casesensitive = false, $accentsensitive = false, $notlike = false);
                
                $salasparam = array_merge($edificioparams,array($fromform->eventType,"%$fromform->roomsname%"));
                $salas=$DB->get_fieldset_select('reservasalas_salas','id',$salasselect ,$salasparam);
                
            }
            else if($fromform->eventType==0 && $fromform->roomsname!=null){
                $salasselect = 'edificios_id '.$edificiosqlin.'
                                AND '.$DB->sql_like('nombre', '?' , $casesensitive = false, $accentsensitive = false, $notlike = false);
                
                $salasparam = array_merge($edificioparams,array("%$fromform->roomsname%"));
                $salas=$DB->get_fieldset_select('reservasalas_salas','id',$salasselect ,$salasparam);
                
            }
            
            if (!empty($salas)){
                list ( $salassqlin, $salasparam ) = $DB->get_in_or_equal ( $salas );
                $select.="AND salas_id $salassqlin ";
                $params = array_merge($params,$salasparam);
                
            }else{
                $condition = '1';
                
            }
    	}
    	elseif($fromform->eventType!=0){
    	    
        	if($fromform->roomsname!=null){
        	    $salasselect = 'tipo = ?
                                AND '.$DB->sql_like('nombre', '?' , $casesensitive = false, $accentsensitive = false, $notlike = false);
        	    $salas=$DB->get_fieldset_select('reservasalas_salas','id',$salasselect,array($fromform->eventType,"%$fromform->roomsname%"));
        	    
        	}else{
        	    $salas=$DB->get_fieldset_select('reservasalas_salas','id','tipo = ?',array($fromform->eventType));
        	}
        	
        		
        	if (!empty($salas)){
        	    list ( $salassqlin, $salasparam ) = $DB->get_in_or_equal ( $salas );
        	    $select.="AND salas_id $salassqlin ";
        	    $params = array_merge($params,$salasparam);
        	    
        	}else{
        	    $condition = '1';
        	    
        	}
        }
    	elseif($fromform->roomsname!=null){
    	    $salasselect = $DB->sql_like('nombre', '?' , $casesensitive = false, $accentsensitive = false, $notlike = false);
    	    $salas=$DB->get_fieldset_select('reservasalas_salas','id',$salasselect,array("%$fromform->roomsname%"));
    		
    	    if (!empty($salas)){
    	        list ( $salassqlin, $salasparam ) = $DB->get_in_or_equal ( $salas );
    	        $select.="AND salas_id $salassqlin ";
    	        $params = array_merge($params,$salasparam);
    	        
    	    }else{
    	        $condition = '1';
    	        
    	    }
    	}
    	$select.="AND activa=1";
    	//$result = $DB->get_records_select('reservasalas_reservas',$select);
    	$result = $DB->get_fieldset_select('reservasalas_reservas','id',$select,$params);
    	if(empty($result) || $condition == 1){ // $condition=1 significa que no hay salas
    		echo '<h5>'.get_string('noreservesarefound', 'local_reservasalas').'</h5>';
    		
    	}else{
    	   echo html_writer::table(tablas::searchRooms($result));
    	}
	}
}

else if($action=="swap"){

	echo $OUTPUT->heading(get_string('change', 'local_reservasalas'));
	
	if(!has_capability('local/reservasalas:changewith', $context)) {
		print_error(get_string('INVALID_ACCESS','Reserva_Sala'));
	}
	
	if(isset($_REQUEST['check_list'])){
		$check_list=$_REQUEST['check_list'];
	}else{
		$check_list="";
	}

	$form = new cambiarReserva(null,array('x'=>$check_list));
	
	
if($fromform = $form->get_data()){
	
	$info=json_decode($fromform->info);	
	$sala=$DB->get_record('reservasalas_salas',array('nombre'=>$fromform->name,'edificios_id'=>$fromform->campus));

foreach($info as $check){

	$reserva=$DB->get_record('reservasalas_reservas',array('id'=>$check));
		$modulo=$DB->get_record('reservasalas_modulos',array('id'=>$reserva->modulo));
		if(strpos($modulo->nombre_modulo, "|")){
			$siguiente=$reserva->modulo+1;
			$anterior=$reserva->modulo-1;
			
			$select="nombre_modulo LIKE '%|%' and id in('$siguiente','$anterior')";
			$results = $DB->get_records_select('reservasalas_modulos',$select);
			foreach($results as $result){
				$module=$result;
			}
		
		
		}else{
			$module= new stdClass;
			$module->id=0;
		}

		$select="modulo in ('$reserva->modulo','$module->id') AND fecha_reserva = '$reserva->fecha_reserva' AND
		salas_id='$sala->id'";
		$newResera = $DB->get_record_select('reservasalas_reservas',$select);
		
		if($newResera){
			
			$nuevaSala=$newResera->salas_id;
			$newResera->salas_id=$reserva->salas_id;
			$reserva->salas_id=$nuevaSala;
			
			$DB->update_record('reservasalas_reservas', $reserva);
			$DB->update_record('reservasalas_reservas', $newResera);
			echo get_string('ithasbeenchanged', 'local_reservasalas');
		}
		else{
			
			$reserva->salas_id=$sala->id;
		$DB->update_record('reservasalas_reservas',$reserva);
		echo get_string('ithasbeenadded', 'local_reservasalas');
		}
		
	}

	echo $OUTPUT->single_button('search.php', get_string('return', 'local_reservasalas'));
}
$form->display();
}
echo $OUTPUT->footer(); //imprime el footer

