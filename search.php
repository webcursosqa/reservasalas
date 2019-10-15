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
$reservaid = null;
$startdate = null;
$enddate = null;
$responsable = null;
$campus = null;
$eventtype = null;
$roomsname = null;

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
	$reservaid = optional_param("reservaid", null, PARAM_INT);
    
    if($reservaid == null){
        print_error(get_string('invalidid','Reserva_Sala'));
        
    }
    if(!has_capability('local/reservasalas:delete', $context)) {
        print_error(get_string('INVALID_ACCESS','Reserva_Sala'));
        
    }
    if($DB->get_record('reservasalas_reservas',array('id' => $reservaid))){
        $DB->delete_records('reservasalas_reservas', array ('id'=>$reservaid));
        echo html_writer::div(get_string('reserveseliminated','local_reservasalas'), 'alert alert-success');
        
    }else{
        print_error(get_string('invalidid','Reserva_Sala'));
        
    }
    $action = 'ver';
}

if($action == "ver") {
    $buscador = new roomSearch();
    $buscador->display();
	$condition = '0';
    if($fromform = $buscador->get_data()){
        $startdate  = $fromform->startdate;
        $enddate = $fromform->enddate;
		$responsable = $fromform->responsable;
        $eventtype = $fromform->eventType;
        $roomsname = $fromform->roomsname;
	}
    if($startdate != null && $enddate != null){
        $params = Array();
        $date=date("Y-m-d",$startdate);
    	$endDate=date("Y-m-d",$enddate);
    
    	$select =" fecha_reserva >= ? AND fecha_reserva <= ? ";
    	$params = array(
    	    $date,
    	    $endDate
    	);
    	if($responsable != null or $responsable != "user@alumnos.uai.cl"){
    		// search by user email		
    		$userselect= $DB->sql_like('username', ':search1' , $casesensitive = false, $accentsensitive = false).'
                        OR '.$DB->sql_like('firstname', ':search2' , $casesensitive = false, $accentsensitive = false).'
						OR '.$DB->sql_like('lastname', ':search3' , $casesensitive = false, $accentsensitive = false);
						
    		$userparams = array(
    		    'search1'=>$fromform->responsable,
    		    'search2'=>$fromform->responsable,
    		    'search3'=>$fromform->responsable
			);

    		if($users=$DB->get_fieldset_select("user",'id', $userselect, $userparams)) {
				var_dump($users);

				list ( $usersqlin, $userparams ) = $DB->get_in_or_equal ( $users );
    		    $select.="AND alumno_id $usersqlin";
				$params = array_merge($params,$userparams);
    		}	
    	}
    	
    	if($campus > 0){
    	    // set the buildings ids ready for the query
    	    list ( $edificiosqlin, $edificioparams ) = $DB->get_in_or_equal ( $fromform->campus );
    	    
    	    if($eventtype==0 && $roomsname==null){
                $salas=$DB->get_fieldset_select('reservasalas_salas','id','edificios_id '.$edificiosqlin,$edificioparams);
                
            }
            else if($eventtype!=0 && $roomsname==null){
                $salasparam = array_merge($edificioparams,array($fromform->eventType));
                $salas=$DB->get_fieldset_select('reservasalas_salas','id','edificios_id '.$edificiosqlin.' AND tipo = ?',$salasparam);
                
            }
            else if($eventtype!=0 && $roomsname!=null){
                $salasselect = 'edificios_id '.$edificiosqlin.' 
                                AND tipo = ? 
                                AND '.$DB->sql_like('nombre', '?' , $casesensitive = false, $accentsensitive = false, $notlike = false);
                
                $salasparam = array_merge($edificioparams,array($fromform->eventType,"%$fromform->roomsname%"));
                $salas=$DB->get_fieldset_select('reservasalas_salas','id',$salasselect ,$salasparam);
                
            }
            else if($eventtype && $roomsname!=null){
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
    	else if($fromform->eventType != 0){
    	    
        	if($fromform->roomsname != null){
        	    $salasselect = 'tipo = ?
                                AND '.$DB->sql_like('nombre', '?' , $casesensitive = false, $accentsensitive = false, $notlike = false);
        	    $salas=$DB->get_fieldset_select('reservasalas_salas','id',$salasselect,array($fromform->eventType,"%$fromform->roomsname%"));
        	    
        	} else {
        	    $salas=$DB->get_fieldset_select('reservasalas_salas','id','tipo = ?',array($fromform->eventType));
        	}
        	
        	if (!empty($salas)){
        	    list ( $salassqlin, $salasparam ) = $DB->get_in_or_equal ( $salas );
        	    $select.="AND salas_id $salassqlin ";
        	    $params = array_merge($params,$salasparam);
        	    
			} else {
        	    $condition = '1';
        	}
        }
    	else if($fromform->roomsname!=null){
    	    $salasselect = $DB->sql_like('nombre', '?' , $casesensitive = false, $accentsensitive = false, $notlike = false);
    	    $salas=$DB->get_fieldset_select('reservasalas_salas','id',$salasselect,array("%$fromform->roomsname%"));
    		
    	    if (!empty($salas)) {
    	        list ( $salassqlin, $salasparam ) = $DB->get_in_or_equal ( $salas );
    	        $select.="AND salas_id $salassqlin ";
    	        $params = array_merge($params,$salasparam);
    	        
    	    } else {
    	        $condition = '1';
    	    }
		}
		
    	$select.="AND activa=1";
		
		$result = $DB->get_fieldset_select('reservasalas_reservas','id', $select, $params);
		
    	if(empty($result) || $condition == 1) { // $condition=1 significa que no hay salas
    		echo '<h5>'.get_string('noreservesarefound', 'local_reservasalas').'</h5>';
		} 
		else {
    	    $table = new html_table();
    	    $table->head = array(
    	        get_string('campus', 'local_reservasalas'),
    	        get_string('building', 'local_reservasalas'),
    	        get_string('room', 'local_reservasalas'),
    	        get_string('event', 'local_reservasalas'),
    	        get_string('reservedate', 'local_reservasalas'),
    	        get_string('createdate', 'local_reservasalas'),
    	        get_string('usercharge', 'local_reservasalas'),
    	        get_string('module', 'local_reservasalas'),
    	        get_string('actions', 'local_reservasalas')
    	    );
    	    list($sqlin, $tableinfoparams) = $DB->get_in_or_equal($result);
    	    $tableinfoquery = "SELECT rr.id as id,
                            rr.nombre_evento as nombre,
                            rr.fecha_reserva as reserva,
                            rr.fecha_creacion as creacion,
                            rr.asistentes as asistentes,
                            rss.nombre as sede,
                            re.nombre as edificio,
                            rs.nombre as sala,
                            u.firstname as firstname,
                            u.lastname as lastname,
                            rm.nombre_modulo as modulo
                            FROM {reservasalas_reservas} AS rr
                            INNER JOIN {reservasalas_salas} AS rs ON (rr.salas_id = rs.id)
                            INNER JOIN {reservasalas_edificios} AS re ON (rs.edificios_id = re.id)
                            INNER JOIN {reservasalas_sedes} AS rss ON (re.sedes_id = rss.id)
                            INNER JOIN {user} AS u ON (u.id = rr.alumno_id)
                            INNER JOIN {reservasalas_modulos} as rm ON (rm.id = rr.modulo)
                            WHERE rr.id $sqlin";
    	    $data = $DB->get_records_sql($tableinfoquery,$tableinfoparams);
    	    $url = new moodle_url('/local/reservasalas/search.php');
    	    foreach($data as $info){
    	        $table->data[] = array(
    	            $info->sede,
    	            $info->edificio,
    	            $info->sala,
    	            $info->nombre,
    	            $info->reserva,
    	            date("Y-m-d",$info->creacion),
    	            $info->firstname.' '.$info->lastname,
    	            $info->modulo,
    	            $OUTPUT->single_button(new moodle_url($url, array('action'=>'remove','reservaid'=>$info->id)), get_string('remove','local_reservasalas'))
    	        );
    	    }
    	    $table->size = array('8%', '8%','8%','23%','10%','10%','20%','5%','3%');
    	    echo html_writer::table($table);
    	}
	}
}
//TODO
else if($action == "edit") {

}

//what is this even?
//aparently for changing the reserve or something but I dont know what exactly

/*else if($action=="swap"){

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
}*/
echo $OUTPUT->footer(); //imprime el footer

