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
if (isguestuser()){
    die();
}

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
		<div id="message"></div>
		<div id="grids"></div>
		
		<script>
		<!--Add 0 to hours for comparison-->
		function addZero(i) {
		    if (i < 10) {
		        i = "0" + i;
		    }
		    return i;
		}
		<!--Wait for the document to be loaded before executing.-->
		$( document ).ready(function() {
			//Today date to string
			var today = new Date().toDateString();
			//Form date to string
			var thisdate = new Date($('#buttonsRooms').attr('initialDate')*1000).toDateString();

			var gridcell = null;
			//Initial ajax call for the grid to be loaded
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
				      'days' : $('#buttonsRooms').attr('selectDays'),
				      'frequency' : $('#buttonsRooms').attr('weeklyFrequencyBookings')
			    	},
			    success: function (response) {
				    var modulos = response.values.Modulos;
				    var salas = response.values.Salas;
				  	//Today date
				    var d = new Date(); 
				  	//Format the date to get the hour and minutes.
				    var date = addZero(d.getHours())+":"+addZero(d.getMinutes());
				    //var to save the html content for the grid
				    var content = "";
				   	for (var i = 0; i <= salas.length; i++) {
			            for (var j = 0; j <= modulos.length; j++) {
				            //First Column, first row
			                if (j==0 && i == 0){
			                	content += "<table class='table table-bordered  table-hover table-light' style='text-align: center;>";
	                			content += "<thead>";
            					content += "<th scope='col'></th>";
            					content += "<th scope='col'></th>";
			                }
							//First row, last column
			                else if (i == 0 && j == modulos) {
			                	content += "<th scope='col'>Modulo: "+ modulos[j-1].name +"</th>";
			                	content += "</tr>";
			                	content += "</thead>";
			                	content += "<tbody>";
			                }
							//First row, every other column
			                else if (i == 0) {
			                	content += "<th scope='col'>Modulo: "+ modulos[j-1].name +"</th>";
					        }
							//First column
					        else if (j == 0) {
			                	content += "<tr><th scope='row' >Sala: "+salas[i-1].nombresala +"</th>";
				            } 
				            //Last column
				            else if (j === modulos) {
					            //Check availability
					            if(salas[i-1].disponibilidad[j-1].ocupada == 1 || date > modulos[j-1].horaFin && today === thisdate){
					            	content += "<td class='alert-danger disabled'>";
					            }else{
			                    	content += "<td class='alert-success' data-toggle='modal' data-target='#myModal' id='"+modulos[j-1].id+salas[i-1].salaid+"' moduloid='" +modulos[j-1].id+"' modulo='" +modulos[j-1].name+"' sala='"+ salas[i-1].nombresala +"' salaid='"+ salas[i-1].salaid +"'>";
					            }
								content += "<b>"+ salas[i-1].nombresala +"</b>";
		                    	content += "<i>";
		                    	content += "<small>["+modulos[j-1].horaInicio + " - " +modulos[j-1].horaFin+"]</small>";
		                    	content += "</i>";
		                    	content += "</td>";
		                    	content += "</tr>";
			                } 
			                //Every other row x cloumn
			                else {
				                //Check availability
			                	if(salas[i-1].disponibilidad[j-1].ocupada == 1 || date > modulos[j-1].horaFin && today === thisdate){
					            	content += "<td  class='alert-danger disabled'>";
			                	}else{
			                    	content += "<td class='alert-success' data-toggle='modal' data-target='#myModal' id='"+modulos[j-1].id+salas[i-1].salaid+"' moduloid='" +modulos[j-1].id+"' modulo='" +modulos[j-1].name+"' sala='"+ salas[i-1].nombresala +"' salaid='"+ salas[i-1].salaid +"'>";
			                	}
				            	content += "<b>"+ salas[i-1].nombresala +"</b>";
				            	content += "<i>";
				            	content += "<small>["+modulos[j-1].horaInicio + " - " +modulos[j-1].horaFin+"]</small>";
				            	content += "</i>";
				            	content += "</td>";
			                }
			            }
			        }
			        content += "</tbody>";
			        content += "</table>";
			        //End of grid content
			        //Modal html content
			        content += "<div class='modal fade' id='myModal' role='dialog'>";
			        	content += "<div class='modal-dialog'>";
			        		content += "<div class='modal-content'>";
			        			content += "<div class='modal-header'>";
			        				content += "<h4 class='modal-title'>Confirmar Reserva</h4>";
			        			content += "</div>";
			        			content += "<div class='modal-body'></div>";
			        			content += "<div class='modal-footer'></div>";
			        				content += "<button type='button' id='confirmar' class='btn btn-primary'>Confirmar</button>";
			        				content += "<button type='button' class='btn btn-default' data-dismiss='modal'>Cancelar</button>";
			        			content += "</div>";
			        		content += "</div>";
			        	content += "</div>";
			        content += "</div>";
			        //End modal content
			        //Load content
			        $("#grids").html(content);
			        $("#grids").on("click", ".alert-success", function() {
			        	gridcell = $(this);
					    //Save &(this) for code efficiency
					    //Dinamically add content to modal
				        $("div.modal-body").html("¿Desea reservar la Sala:"+ $(this).attr('sala')+" para el modulo:" + $(this).attr('modulo') + 
					        					"? <br><br>Nombre del evento:<input id=nombreevento type='text' name='member' value=''> "+
					        					"<br><br>Numero de participantes(2-6):<select id='numeroparticipantes'>"+
					        					  "<option value='2'>2</option>"+
					        					  "<option value='3'>3</option>"+
					        					  "<option value='4'>4</option>"+
					        					  "<option value='5'>5</option>"+
					        					  "<option value='6'>6</option>"+
					        					"</select>"+
					        					"<input id=modalmoduloid type='hidden' value='"+$(this).attr('moduloid')+"'>"+
					        					"<input id=modalsalaid type='hidden' value='"+$(this).attr('salaid')+"'>");
				    });
					//Confirmation ajax
	            	$("#confirmar").on("click", function() {
	    	        	if($('#nombreevento').val() != ''){
	    	        		$('#myModal').modal('hide');
	        		    	$.ajax({
	        				    type: 'GET',
	        				    url: 'ajax/data.php',
	        				    dataType: "json",
	        				    data: {
	    					      	'action' : 'submission',
	    					      	'room' : $('#modalsalaid').val(),
	    					      	'moduleid' : $('#modalmoduloid').val(),
	        		    			'date' : $('#buttonsRooms').attr('initialDate'),
	        		    			'campusid' : $('#buttonsRooms').attr('campus'),
	        		    			'multiply' : $('#buttonsRooms').attr('advOptions'),
	        		    			'finalDate' : $('#buttonsRooms').attr('endDate'),
	        		    			'days' : $('#buttonsRooms').attr('selectDays'),
	        		    			'frequency' : $('#buttonsRooms').attr('weeklyFrequencyBookings'),
	        		    			'event' : $('#nombreevento').val(),
	    	    					'asistants' : $('#numeroparticipantes').val()
	        				    	},
	        				    success: function (response) {
	            				    console.log(response);
	            				    //Check if successfully saved
	            				    if(response.error.length > 0 && $('#buttonsRooms').attr('advOptions') == 0){
	            				    	$('#message').addClass('alert alert-danger');
	                				    $('#message').html("No puedes realizar más reservas.");
	            				    }else{
	            				    	gridcell.removeClass('alert-success');
	            				    	gridcell.addClass('alert-danger');
	            				    	gridcell.removeAttr('data-toggle');
	            				    	gridcell.removeAttr('data-target');
	                				    $('#message').addClass('alert alert-success');
	                				    $('#message').html("Reserva realizada correctamente.");
	            				    }
	            				    
	        				    }
	        				});
	    	        	}else{
	    					$('#nombreevento').addClass('alert-danger');
	    	        	}
					});
			    }
			});
		});
		</script>
		<?php 
}
echo $OUTPUT->footer (); 
?>
