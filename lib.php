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
//Libreria del plugin
//Se incluye automaticamente al llamar config.php
//function library, automatically included with by config.php

//Definir aqui las funciones que se usaran en varias paginas
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/formslib.php");
require_once(dirname(__FILE__) . '/../../config.php'); //obligatorio

//Usada por Diego
function hora_modulo($modulo) {
	global $DB,$USER;
	//en formato HH:MM:SS (varchar)
	//Define las horas para misreservas.php

	$modulos=$DB->get_record('reservasalas_modulos',array("id"=>$modulo));
	
	$inicio=explode(":",$modulos->hora_inicio);
	$fin=explode(":",$modulos->hora_fin);
	
	$a=$inicio[0];$b=$inicio[1];$c=00; //hora;minuto;segundo
	$d=$fin[0];$e=(int)$fin[1];$f=00; //hora;minuto;segundo
	$minutos = str_replace(' ', '',$e);
	
	$ModuloInicia = new DateTime();
	// Se deja hora y minutos en 0 (medianoche)
	$ModuloInicia->setTime($a,$b,0);
	$ModuloTermina = new DateTime();
	// Se deja hora y minutos en 0 (medianoche)
	$ModuloTermina->setTime($d,$e,0);
$hora=array(
		$ModuloInicia,
		$ModuloTermina
);
//var_dump($hora);
	return $hora;
	//devuelve $hora[incio] y $hora[fin]
}
function modulo_hora($unixtime, $factor = null){

	$hora = date('G', $unixtime);
	$minuto = date('i', $unixtime);
	$segundo = $hora*60*60 + $minuto*60;
	if($factor== null){
		$factor = 15*60;
	}
	
	if($segundo > 19*60*60 + 10*60 +$factor){
		return 8;
	}else if($segundo > 15*60*60 + 40*60 + $factor){
		return 7;
	}else if($segundo > 16*60*60 + 10*60 + $factor){
		return 6;
	}else if($segundo > 14*60*60 + 10*60 + $factor){
		return 5;
	}else if($segundo > 12*60*60 + 40*60 + $factor){
		return 4;
	}else if($segundo > 11*60*60 + 30*60 + $factor){
		return 3;
	}else if($segundo > 10*60*60 + 0*60 + $factor){
		return 2;
	}else if($segundo > 8*60*60 + 30*60 + $factor){
		return 1;
	}else{
		return 0;
	}
}

//returns the daily and weekly bookings of the user
function booking_availability($date){
	global $DB,$USER,$CFG;
	//format YYYY-MM-DD
	$today = date('Y-m-d',time());
	if(!$DB->get_record('reservasalas_bloqueados', array("alumno_id"=>$USER->id, 'estado'=>1))){
		
		$sqlWeekBookings = "SELECT *
						FROM {reservasalas_reservas}
						WHERE fecha_reserva >= ?
						AND fecha_reserva <= ADDDATE(?, 7)
						AND alumno_id = ? AND activa = 1";
	
		$weekBookings = $DB->get_records_sql($sqlWeekBookings, array($today, $today, $USER->id));
		$todayBookings = $DB->count_records ( 'reservasalas_reservas', array (
				'alumno_id' => $USER->id,
				'fecha_reserva' => date('Y-m-d',$date),
				'activa' => 1));
	
		$books= array(count($weekBookings),$todayBookings);

	}else{
		$books = array($CFG->reservasSemana,$CFG->reservasDia);
	}
	return $books;
}

//returns success
function block($student_id, $book_id, $reason) {
	global $DB;
	
    //unblock if blocked
    if($block = is_blocked($student_id)) {
        $block->estado = 0;
        $DB->update_record("reservasalas_bloqueados", $block);
    }
    
    //create new block
    $block = new stdClass();
    $block->fecha_bloqueo = date("Y-m-d", time());
    $block->id_reserva = $book_id;
    $block->estado = 1;
    $block->comentarios = $reason;
    $block->alumno_id = $student_id;
    
	if ($DB->insert_record("reservasalas_bloqueados", $block)) 
	{
		return true;
	} 
	else 
	{
		return false;
    }
}

//returns the entire block object
function is_blocked($student) {
	global $DB;

	$table = 'reservasalas_bloqueados';
	$conditions = array("alumno_id" => $student, "estado" => 1);
	if($block = $DB->get_record($table, $conditions)) {
		return $block;
	}
	else {
		return false;
	} 
}

function block_update_all() 
{
	global $DB;

	//get all users currently blocked for unblocking
	$users_blocked = $DB->get_records("reservasalas_bloqueados", array("estado" => 1));

	//get all non-confirmed books for blocking
	//$non_confirmed_books = $DB->get_records("reservasalas_reservas", array("confirmado" => 0, "activa" => 1));

    $sqlnonconfirmedbooks = "SELECT *
						FROM {reservasalas_reservas}
						WHERE confirmado >= ?
						AND activa = ?
						AND DATEDIFF(CURDATE(), from_unixtime(fecha_creacion)) <= ?
                        AND curdate() > fecha_reserva";
    $non_confirmed_books = $DB->get_records_sql($sqlnonconfirmedbooks, array(0, 1, 3));


	$ids = array();

	foreach ($users_blocked as $user_blocked) {
		$id = $user_blocked->id;
		if(!in_array($id, $ids)) {
			$ids[] = $id;
		}
	}

	foreach($non_confirmed_books as $non_confirmed_book) {
		$id = $non_confirmed_book->alumno_id;
		if(!in_array($id, $ids)) {
			$ids[] = $id;
		}
	}

	//check all ids
	foreach($ids as $id) {
		block_update($id);
	}
}

//update the block status of the user
//returns either true (if blocked) or an array of the daily and weekly books
function block_update($user_id)
{
	//we can do one of 3 things
	//block user (add record to blocks)
	//	-done when missed
	//unblock user (remove record)
	//	-3 days after block
	global $DB;

	$currentTime = time();

	$table = "reservasalas_reservas";
	$conditions = array("alumno_id" => $user_id, "confirmado" => 0, "activa" => 1);
	$books_exist = $DB->record_exists($table, $conditions);

	$table = "reservasalas_bloqueados";
	$conditions = array("alumno_id" => $user_id, "estado" => 1);
	$block_exists = $DB->record_exists($table, $conditions);

	$blocked = false;

	//if currently unblocked
	//check if needs to be blocked
	if ($books_exist) 
	{
		$table = "reservasalas_reservas";
		$conditions = array("alumno_id" => $user_id, "confirmado" => 0);
		$sort = "fecha_reserva ASC";
		$books = $DB->get_records($table, $conditions, $sort);

		$block = false;

		/*
		the idea here is to check from the oldest book to the newest book (active) and block only for the latest
		while disabling all others in the way
		*/

		foreach ($books as $book) {
			//get the time of the reserve
			$module = $book->modulo;
			$book_time = $DB->get_record("reservasalas_modulos", array("id" => $module));
		
			$time = $book_time->hora_inicio;
			$date = $book->fecha_reserva;

			$unixtime = strtotime($date . " " . $time);

			//if more than 15m since the book have passed (the book is not confirmed)
			//disable 
			//if less than 3d since the book
			//and if there isnt a block already for that book
			//then block and disable all other bookings
			if ($unixtime + (15 * 60) < $currentTime and
				$unixtime + (3 * 24 * 60 * 60) > $currentTime and
				!$DB->get_record("reservasalas_bloqueados", array("id_reserva" => $book->id))
			) {
				//add block
				$block = $book->id;
			}
		}

		if($block) {
			block($user_id, $block, get_string("no-confirm", "local_reservasalas"));
			//disable all books when blocking
			foreach ($books as $book) {
				if($book->activa == 1) {
					$book->activa = 0;
					$DB->update_record("reservasalas_reservas", $book);
				}
			}
		}
	}
	//unblock user, if user is blocked currently
	//update user, if blocked currently and reblocked
	else if ($block_exists) {
		$table = 'reservasalas_bloqueados';
		$conditions = array("alumno_id" => $user_id, "estado" => 1);
		$block = $DB->get_record($table, $conditions);

		$block_date = $block->fecha_bloqueo;

		//if 3 days have passed
		if (strtotime($block_date) + (3 * 24 * 60 * 60) < $currentTime) {
			$block->estado = 0;
			$DB->update_record($table, $block);
		}
	}
}