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

if($action=="ver"){
echo $OUTPUT->heading(get_string('searchroom', 'local_reservasalas'));
$buscador = new roomSearch();
$buscador->display();
$condition = '0';
if($fromform = $buscador->get_data()){
    $params = Array();
    $DB->set_debug(true);
	$date=date("Y-m-d",$fromform->startdate);
	$endDate=date("Y-m-d",$fromform->enddate);

	$select =" fecha_reserva >= ? AND fecha_reserva <= ? ";
	$params = array(
	    $date,
	    $endDate
	);
	if($fromform->responsable){
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
	
	if(isset($fromform->campus)){
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
	
	$table = tablas::searchRooms($result);
	
	echo html_writer::tag('form','',array('name'=>'search','method'=>'POST'));
	
	echo html_writer::table($table);
	if(has_capability('local/reservasalas:delete', $context)) {
	echo'<input type="submit" name="action" value="' .get_string('remove', 'local_reservasalas'). '" onClick="return ComfirmDeleteOrder();">';
	}
	if(has_capability('local/reservasalas:changewith', $context)) {
	echo'<input type="submit" name="action" value="' .get_string('swap', 'local_reservasalas'). '">';
	}
	
	echo html_writer::end_tag('form');
	}
}
}
else if($action=="remove"){
	
	echo $OUTPUT->heading(get_string('reserveseliminated', 'local_reservasalas').'!');
	
	
	if(!has_capability('local/reservasalas:delete', $context)) {
		print_error(get_string('INVALID_ACCESS','Reserva_Sala'));
	}
	
	
	$check_list=$_REQUEST['check_list'];
	$table = new html_table();
	$table->head = array(get_string('campus', 'local_reservasalas'), get_string('building', 'local_reservasalas'),get_string('room', 'local_reservasalas'), get_string('event', 'local_reservasalas'), get_string('reservedate', 'local_reservasalas'), get_string('createdate', 'local_reservasalas'), get_string('usercharge', 'local_reservasalas'),get_string('module', 'local_reservasalas'));
	foreach($check_list as $check){
		
        $data = $DB->get_record('reservasalas_reservas', array ('id'=>$check));
        $room = $DB->get_record('reservasalas_salas', array ('id'=>$data->salas_id));
        $building = $DB->get_record('reservasalas_edificios', array('id'=>$room->edificios_id));
        $campus = $DB->get_record('reservasalas_sedes', array('id'=>$building->sedes_id));
        $module = $DB->get_record('reservasalas_modulos', array('id'=>$data->modulo));	
        $responsable = $DB->get_record('user', array('id'=>$data->alumno_id));
        $table->data[] = array($campus->nombre, $building->nombre, $room->nombre, $data->nombre_evento, $data->fecha_reserva, date("Y-m-d",$data->fecha_creacion), $responsable->firstname.' '.$responsable->lastname, $module->nombre_modulo);
		$DB->delete_records('reservasalas_reservas', array ('id'=>$check)) ;
	}
	$table->size = array('8%', '8%','8%','23%','10%','10%','20%','5%','3%');
	echo html_writer::table($table);
	echo $OUTPUT->single_button('search.php', get_string('return', 'local_reservasalas'));
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
else if ($form->is_cancelled()) {
	echo get_string('nochanges', 'local_reservasalas').'<br/><br/>'.$OUTPUT->single_button('search.php', get_string('return', 'local_reservasalas'));
}
else{
$form->display();

	

}
}
echo $OUTPUT->footer(); //imprime el footer
?>
<script>
function ComfirmDeleteOrder()
{
  var r=confirm("¿Esta seguro que quiere eliminar las reservas seleccionadas?");
  if(r == true){
   return true;
  }else{
   return false;
  }
}



function checkAll(){
	var check = document.getElementById("check").checked
	var value = document.getElementById("check").value
	
		
		var inputs = document.getElementsByClassName("check");
	

	if(check){
		
			for(var i = 0; i < inputs.length; i++)

		    if(inputs[i].type == "checkbox"){
			
				inputs[i].checked = true;
			}
			
			
		    }
	if(!check){
		for(var i = 0; i < inputs.length; i++)

		    if(inputs[i].type == "checkbox"){
			
				inputs[i].checked = false;
			}
			
			
        }
    
	}
	
 
</script>

