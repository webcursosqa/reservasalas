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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package local
 * @subpackage reservasalas
 * @copyright 2014 Francisco García Ralph (francisco.garcia.ralph@gmail.com)
 *            Nicolás Bañados Valladares (nbanados@alumnos.uai.cl)
 *            Hans Jeria Díaz (hansjeria@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Page booking for users
require_once (dirname ( __FILE__ ) . '/../../config.php');
require_once ($CFG->dirroot . '/local/reservasalas/forms.php');
require_once ($CFG->dirroot . '/local/reservasalas/lib.php');
require_once ($CFG->dirroot . '/local/reservasalas/tablas.php');

global $DB, $USER, $CFG;

require_login (); 

$baseurl = new moodle_url ( '/local/reservasalas/reservar.php' ); 
$context = context_system::instance (); 
$PAGE->set_context ( $context );
$PAGE->set_url ( $baseurl );
$PAGE->set_pagelayout ( 'standard' );
$PAGE->set_title ( get_string ( 'reserveroom', 'local_reservasalas' ) );
$PAGE->set_heading ( get_string ( 'reserveroom', 'local_reservasalas' ) );
$PAGE->requires->jquery();
$PAGE->navbar->add ( get_string ( 'roomsreserve', 'local_reservasalas' ) );
$PAGE->navbar->add ( get_string ( 'reserverooms', 'local_reservasalas' ), 'reservar.php' );
echo $OUTPUT->header (); 
echo $OUTPUT->heading ( get_string ( 'reserveroom', 'local_reservasalas' ) );

//Rules for reservasalas
echo html_writer::tag('p', get_string("rules_content", "local_reservasalas"), array("class" => "text-error"));

$form_buscar = new formBuscarSalas();

echo $form_buscar->display ();

if ($form_buscar->is_cancelled()) {

	redirect($baseurl);

} else if ($fromform = $form_buscar->get_data ()) {	
		if (! has_capability ( 'local/reservasalas:typeroom', context_system::instance () )) {
			//roomtype = {1: class room, 2: study room, 3: reunion room}
			$fromform->roomstype = 2;
		}
		if (! has_capability ( 'local/reservasalas:advancesearch', context_system::instance () )) {
			$fromform->addmultiply = 0;
			$fromform->enddate = $fromform->fecha;
			$fromform->size = "1-25";
			$fromform->fr ['frequency'] = 1;
		}		
				
		$days = "";
		if ( has_capability ( 'local/reservasalas:advancesearch', context_system::instance () )) {		
			if($fromform->addmultiply == 0){
				$fromform->enddate = $fromform->fecha;
			}
			//Concatenated string with all the selected days
			if ($fromform->ss ['monday'] == 1)
				$days = $days . "L";
			if ($fromform->ss ['tuesday'] == 1)
				$days = $days . "M";
			if ($fromform->ss ['wednesday'] == 1)
				$days = $days . "W";
			if ($fromform->ss ['thursday'] == 1)
				$days = $days . "J";
			if ($fromform->ss ['friday'] == 1)
				$days = $days . "V";
			if ($fromform->ss ['saturday'] == 1)
				$days = $days . "S";
			if (! isset ( $fromform->size ))
				$fromform->size = "1-25";
			if (! isset ( $fromform->fr ['frequency'] ))
				$fromform->fr ['frequency'] = 1;
		}
		
		list($weekBookings,$todayBookings) = booking_availability($fromform->fecha);
		
		$moodleurl = $CFG->wwwroot . '/local/reservasalas/ajax/data.php';
		
		//Booking preferences for basic users
		if ($CFG->reservasDia == null)
			$CFG->reservasDia = 2;
		if ($CFG->reservasSemana == null)
			$CFG->reservasSemana = 6;
		
		//Javascript,CSS and DIV for GWT
		?>
		
					<style type = "text/css">
.gridborder {
    background: #FFFFFF;
    color: #000000;
    border: 1px solid #878787;
    border-radius: 35px;
    flex: 1;
    text-align: center;
}
.grid {
    background: #69db46;
    color: #000000;
    border: 1px solid #878787;
    border-radius: 35px;
    flex: 1;
    text-align: center;
}
.gridocupado {
    background: #ed7d7d;
    color: #000000;
    border: 1px solid #878787;
    border-radius: 35px;
    flex: 1;
    text-align: center;
}
.gridblank {
    background: #FFFFFF;
    color: #FFFFFF;
    border: 1px solid #FFFFFF;
    border-radius: 35px;
    flex: 1;
}
.table-success:hover {
    cursor: pointer;
}
			</style>
			
		<div			
			id="buttonsRooms"
			class = "tableClass"
			moodleurl = " <?php echo $moodleurl; ?>"	
			initialDate = "<?php echo $fromform->fecha; ?>"
			typeRoom= "<?php echo $fromform->roomstype; ?>"
			campus = "<?php echo $fromform->SedeEdificio; ?>"
			userDayReservations = "<?php echo $todayBookings; ?>"
			userWeeklyBooking = "<?php echo $weekBookings; ?>"
			maxDailyBookings = "<?php echo $CFG->reservasDia; ?>"
			maxWeeklyBookings = "<?php echo $CFG->reservasSemana; ?>"	
			size = "<?php echo $fromform->size; ?>"
 			endDate = "<?php echo $fromform->enddate; ?>"
 			selectDays = "<?php echo $days; ?>"
 			weeklyFrequencyBookings = "<?php echo $fromform->fr['frequency']; ?>"
 			advOptions = "<?php echo $fromform->addmultiply; ?>" >
			

		</div>
		<div id="grids"></div>
		
		<script>
			$( document ).ready(function() {
				var today = new Date().toDateString();
				var thisdate = new Date($('#buttonsRooms').attr('initialDate')*1000).toDateString();
				$.ajax({
				    type: 'GET',
				    url: 'ajax/data.php',
				    dataType: "json",
				    data: {
					      'action' : 'getbooking',
					      'type' : $('#buttonsRooms').attr('typeRoom'),
					      'campusid' : $('#buttonsRooms').attr('campus'),
					      'date' : $('#buttonsRooms').attr('initialDate'),
					      'multiply' : $('#buttonsRooms').attr('advOptions'),
					      'size' : $('#buttonsRooms').attr('size'),
					      'finalDate' : $('#buttonsRooms').attr('endDate'),
					      'days' : $('#buttonsRooms').attr('days'),
					      'frequency' : $('#buttonsRooms').attr('weeklyFrequencyBookings')
				    	},
				    success: function (response) {
					    var modulos = response.values.Modulos;
					    var salas = response.values.Salas;
					    var d = new Date(); // for now
					    var date = d.getHours()+":"+d.getMinutes();
						var num = 1;
					    var content = "";
					   	for (var i = 0; i <= salas.length; i++) {
				            for (var j = 0; j <= modulos.length; j++) {
				                if (j==0 && i == 0){
				                	content += "<table class='table table-bordered  table-hover table-light' style='text-align: center;><thead '><tr><th scope='col'></th>";
				                }else if (i == 0 && j == modulos) {
				                	content += "<th scope='col'>Modulo: "+ modulos[j-1].name +"</th></tr></thead><tbody>";
				                }else if (i == 0) {
				                	content += "<th scope='col'>Modulo: "+ modulos[j-1].name +"</th>";
						        }else if (j == 0) {
				                	content += "<tr><th scope='row' >Sala: "+salas[i-1].nombresala +"</th>";
					            } 
					            else if (j === modulos) {
						            if(salas[i-1].disponibilidad[j-1].ocupada == 1 || date > modulos[j-1].horaInicio && today === thisdate){
						            	content += "<td class='table-danger disabled'><b>"+ salas[i-1].nombresala +"</b><i><small>["+modulos[j-1].horaInicio + " - " +modulos[j-1].horaFin+"]</small></i></td></tr>";
							        }else{
				                    	content += "<td class='table-success' data-toggle='modal' data-target='#myModal' moduloid='" +modulos[j-1].id+"' modulo='" +modulos[j-1].name+"' sala='"+ salas[i-1].nombresala +"' salaid='"+ salas[i-1].salaid +"'><b>"+ salas[i-1].nombresala +"</b><i><small>["+modulos[j-1].horaInicio + " - " +modulos[j-1].horaFin+"]</small></i></td></tr>";
							        }
				                } else {
				                	if(salas[i-1].disponibilidad[j-1].ocupada == 1 || date > modulos[j-1].horaInicio && today === thisdate){
						            	content += "<td  class='table-danger disabled'><b>"+ salas[i-1].nombresala +"</b><i><small>["+modulos[j-1].horaInicio + " - " +modulos[j-1].horaFin+"]</small></i></td>";
							        }else{
				                    	content += "<td class='table-success' data-toggle='modal' data-target='#myModal' moduloid='" +modulos[j-1].id+"' modulo='" +modulos[j-1].name+"' sala='"+ salas[i-1].nombresala +"' salaid='"+ salas[i-1].salaid +"'><b>"+ salas[i-1].nombresala +"</b><i><small>["+modulos[j-1].horaInicio + " - " +modulos[j-1].horaFin+"]</small></i></td>";
							        }
				                }
				            }
				        }
				        content += "</tbody></table><div class='modal fade' id='myModal' role='dialog'><div class='modal-dialog'><div class='modal-content'><div class='modal-header'><h4 class='modal-title'>Confirmar Reserva</h4> </div> <div class='modal-body'> </div> <div class='modal-footer'> <button type='button' id='confirmar' class='btn btn-primary' data-dismiss='modal'>Confirmar</button><button type='button' class='btn btn-default' data-dismiss='modal'>Close</button> </div> </div></div></div>";
				        $("#grids").html(content);
				    }
				});
		    $("#grids").on("click", ".table-success", function() {
			    var grid = $(this);
		        $("div.modal-body").html("¿Desea reservar la Sala:"+ $(this).attr('sala')+" para el modulo:" + $(this).attr('modulo') + "?")

		          $("#confirmar").click(function() {
    		    	$.ajax({
    				    type: 'GET',
    				    url: 'ajax/data.php',
    				    dataType: "json",
    				    data: {
					      	'action' : 'submission',
					      	'room' : grid.attr('salaid'),
					      	'moduleid' : grid.attr('moduloid'),
    		    			'date' : $('#buttonsRooms').attr('initialDate'),
    		    			'campusid' : $('#buttonsRooms').attr('campus'),
    		    			'multiply' : $('#buttonsRooms').attr('advOptions'),
    		    			'finalDate' : $('#buttonsRooms').attr('endDate'),
    		    			'days' : $('#buttonsRooms').attr('days'),
    		    			'frequency' : $('#buttonsRooms').attr('weeklyFrequencyBookings')
    				    	},
    				    success: function (response) {
    console.log(response);
    console.log(grid.attr('salaid'));
    console.log(grid.attr('moduloid'));
    				    }
    				});
		    });
		    });
		  
		});
		</script>
		<?php 
}
echo $OUTPUT->footer (); 
?>
