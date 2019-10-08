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
 * @package mod
 * @subpackage emarking
 * @copyright 2014 Jorge Villalón {@link http://www.uai.cl}
 * @copyright 2014 Francisco García
 * @copyright 2015 Hans Jeria <hansjeria@gmail.com>
 * @copyright 2015 Eduardo Aguirrebeña <eaguirrebena@alumnos.uai.cl>
 * @copyright 2015 Mark Michaelsen <mmichaelsen678@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define("AJAX_SCRIPT", true);
define("NO_DEBUG_DISPLAY", true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config.php");
require_once("qry/querylib.php");
require_once($CFG->dirroot . "/local/reservasalas/lib.php");
global $CFG, $DB, $OUTPUT, $USER;
require_login();

$action = required_param("action", PARAM_TEXT);
$campusid = optional_param("campusid", 0, PARAM_INT);
$type = optional_param("type", 0, PARAM_INT);
$initialDate = optional_param("date", 1, PARAM_INT);
$multiply = optional_param("multiply", 0, PARAM_INT);
$size = optional_param("size", 0, PARAM_TEXT);
$moduleid = optional_param("moduleid", null, PARAM_TEXT);
$room = optional_param("room", null, PARAM_TEXT);
$enddate = optional_param("finalDate", 1, PARAM_INT);
$days = optional_param("days", null, PARAM_TEXT);
$frequency = optional_param("frequency", 0, PARAM_INT);
$roomname = optional_param("nombresala", null, PARAM_TEXT);
$event = optional_param("event", null, PARAM_TEXT);
$asistants = optional_param("asistants", null, PARAM_INT);
// Callback para from webpage
$callback = optional_param("callback", null, PARAM_RAW_TRIMMED);

// Headers
header("Content-Type: text/javascript");
header("Cache-Control: no-cache");
header("Pragma: no-cache");

if ($action == "getbooking") {
	$output = reservasalas_getBooking($type, $campusid, $initialDate, $multiply, $size, $enddate, $days, $frequency);
	$available = array();
	$modules = array();
	$rooms = array();
	$roombusy = array();
	$added = array();
	$modulesadded = array();
	$roomsadded = array();
	$contador = 0;
	foreach ($output as $availability) {
		if (!in_array($availability->moduloid, $added)) {
			$added[] = $availability->moduloid;
			$modules[] = array(
				"id" => $availability->moduloid,
				"name" => $availability->modulonombre,
				"horaInicio" => $availability->moduloinicio,
				"horaFin" => $availability->modulofin
			);
		}
		if ($contador > 0) {
			if ($anterior != $availability->salaid) {
				$rooms[] = array(
					"salaid" => $salaid,
					"nombresala" => $roomname,
					"capacidad" => $capacidad,
					"disponibilidad" => $roombusy
				);
				$roombusy = array();
			}
		}
		$contador++;
		if (!in_array($availability->salaid, $roomsadded)) {
			$anterior = $availability->salaid;
			$roomsadded[] = $availability->salaid;
			$salaid = $availability->salaid;
			$roomname = $availability->salanombre;
			$capacidad = $availability->capacidad;
		}
		$roombusy[] = array(
			"moduloid" => $availability->moduloid,
			"modulonombre" => $availability->modulonombre,
			"ocupada" => $availability->ocupada,
			"horaInicio" => $availability->moduloinicio,
			"horaFin" => $availability->modulofin
		);
	}

	$output->close();

	$rooms[] = array(
		"salaid" => $salaid,
		"nombresala" => $roomname,
		"capacidad" => $capacidad,
		"disponibilidad" => $roombusy
	);
	$final = array(
		"Modulos" => $modules,
		"Salas" => $rooms
	);
	$output = $final;
	$jsonOutputs = array(
		"error" => "",
		"values" => $output
	);
} else if ($action == "info") {
	// 0 = false, 1 = true
	$isAdmin = 0;
	if (has_capability("local/reservasalas:advancesearch", context_system::instance())) {
		$isAdmin = 1;
	}
	$infoUser = array(
		"firstname" => $USER->firstname,
		"lastname" => $USER->lastname,
		"email" => $USER->email,
		"isAdmin" => $isAdmin
	);
	$jsonOutputs = array(
		"error" => "",
		"values" => $infoUser
	);
} else if ($action == "submission") {
	if ($CFG->reservasDia == null)
		$CFG->reservasDia = 2;
	if ($CFG->reservasSemana == null)
		$CFG->reservasSemana = 6;

	$response = get_string("data-default-error");
	$validation = false;

	//if not admin
	if (!has_capability("local/reservasalas:advancesearch", context_system::instance())) {
		list($weekBookings, $todayBookings) = booking_availability($initialDate);

		if($reason = is_blocked($USER->id)) {
			$response = get_string("data-blocked-for-reason", "local_reservasalas"). $reason;
		}
		else if ($todayBookings == $CFG->reservasDia) {
			$response = get_string("data-max-daily-books", "local_reservasalas");
		} 
		else if ($weekBookings == $CFG->reservasSemana) {
			$response = get_string("data-max-weekly-books", "local_reservasalas");
		}
		//what the hell is this even? a weird way to check against the max books?
		else if (
			$CFG->reservasDia - $todayBookings - count($room) + 1 < 0 &&
			$CFG->reservasSemana - $weekBookings - count($room) + 1 < 0
		) {
			$response = get_string("data-internal-error", "local_reservasalas");
		} else {
			$validation = true;
		}
	} else { //if admin
		$validation = true;
	}

	//if its admin
	if ($multiply == 1 && has_capability("local/reservasalas:advancesearch", context_system::instance())) {
		$fechas = reservasalas_daysCalculator($initialDate, $enddate, $days, $frequency);
	} else {
		$fechas = array(date("Y-m-d", $initialDate));
	}

	$error = array();
	$values = array();

	if ($validation) {
		foreach ($fechas as $fecha) {
			//actually do the booking
			if (reservasalas_validationBooking($room, $moduleid, $fecha)) {
				$data = new stdClass();
				$data->fecha_reserva = $fecha;
				$data->modulo = $moduleid;
				$data->confirmado = 0;
				$data->activa = 1;
				$data->alumno_id = $USER->id;
				$data->salas_id = $room;
				$data->fecha_creacion = time();
				$data->nombre_evento = $event;
				$data->asistentes = $asistants;

				$lastinsertid = $DB->insert_record("reservasalas_reservas", $data, true);
				if ($lastinsertid > 0) {
					$response = "success";
					$values[] = array(
						"sala" => $room,
						"modulo" => $moduleid,
						"fecha" => $fecha
					);
				} else {
					$response = get_string("data-internal-error", "local_reservasalas");
					$error[] = array(
						"sala" => $room,
						"modulo" => $moduleid,
						"fecha" => $fecha
					);
				}
			} else {
				$response = get_string("data-already-booked", "reservasalas");
				$error[] = array(
					"sala" => $room,
					"modulo" => $moduleid,
					"fecha" => $fecha
				);
			}
		}
	}
	$context = context_system::instance();
	$PAGE->set_context($context);
	reservasalas_sendMail($values, $error, $USER->id, $asistants, $event, $campusid);

	$jsonOutputs = $response;
}
$jsonOutput = json_encode($jsonOutputs);
if ($callback) {
	$jsonOutput = $callback . "(" . $jsonOutput . ");";
}
echo $jsonOutput;
