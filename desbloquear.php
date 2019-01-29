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
 * @copyright  2013 Marcelo Epuyao
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php'); //obligatorio
require_once($CFG->dirroot.'/local/reservasalas/forms.php');
require_once($CFG->dirroot.'/local/reservasalas/tablas.php');


global $PAGE, $OUTPUT, $DB;
//Verifica que el usuario que accese a la página este logeado en el sistema
require_login();
if (isguestuser()){
    die();
}
$action = optional_param("action", "view", PARAM_TEXT);
$id = optional_param("id", 0, PARAM_INT);
$search = optional_param("search", null, PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 30;
$url = new moodle_url('/local/reservasalas/desbloquear.php'); 
$context = context_system::instance();//context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

//Capabilities
//Valida la capacidad del usuario de poder ver el contenido
//En este caso solo administradores del módulo pueden ingresar
if(!has_capability('local/reservasalas:blocking', $context)) {
		print_error(get_string('INVALID_ACCESS','Reserva_Sala'));
}
//Migas de pan
$PAGE->navbar->add(get_string('roomsreserve', 'local_reservasalas'));
$PAGE->navbar->add(get_string('users', 'local_reservasalas'));
$PAGE->navbar->add(get_string('unblockstudent', 'local_reservasalas'));

$title = get_string('unblockstudent', 'local_reservasalas');
$PAGE->set_title($title);
$PAGE->set_heading($title);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

if($action == 'unblock'){
    if(!$id > 0){
        print_error(get_string('invalidid','local_reservasalas'));
    }
    $userblock = new stdClass();
    $userblock->id = $id;
    $userblock->estado = 0;
    if($unblock = $DB->update_record('reservasalas_bloqueados',$userblock)){
        echo html_writer::div(get_string('unblocked','local_reservasalas'), 'alert alert-success');
        $action = 'view';
    }else{
        print_error(get_string('failtounblock','local_reservasalas'));
    }
}
if($action == 'view'){
    $form = new desbloquearAlumnoForm();
    if($data = $form->get_data()){
        $search = $data->search;
    }
    $like='';
    $query = 'Select rb.id,u.username,u.firstname, u.lastname, rb.fecha_bloqueo 
                from {reservasalas_bloqueados} as rb 
                inner join {user} as u on (u.id = rb.alumno_id) 
                where estado = :estado 
                AND ('.$DB->sql_like('username', ':search1' , $casesensitive = false, $accentsensitive = false, $notlike = false).' 
                OR '.$DB->sql_like('firstname', ':search2' , $casesensitive = false, $accentsensitive = false, $notlike = false).'
                OR '.$DB->sql_like('lastname', ':search3' , $casesensitive = false, $accentsensitive = false, $notlike = false).')';
    if($bloqueados = $DB->get_records_sql($query, array("estado" => 1, "search1" => "%$search%","search2" => "%$search%","search3" => "%$search%"), $page * $perpage, $perpage)){
        $countblock = count($bloqueados);
        $table = new html_table();
        $table->head = array(
            '#',
            get_string('date','local_reservasalas'),
            get_string('name','local_reservasalas'),
            get_string('lastname','local_reservasalas'),
            get_string('email','local_reservasalas'),
            get_string('action','local_reservasalas')
        );
        $counter = $page * $perpage + 1;
        foreach($bloqueados as $bloqueado){
            $table->data[] = array(
                $counter,
                $bloqueado->fecha_bloqueo,
                $bloqueado->firstname,
                $bloqueado->lastname,
                $bloqueado->username,
                $OUTPUT->single_button(new moodle_url($url, array('action'=>'unblock', 'id'=>$bloqueado->id)), get_string('unblock','local_reservasalas'))
            );
            $counter++;
        }
        $dom = $form->display();
        $dom .= html_writer::table($table);
        $dom .= $OUTPUT->paging_bar(round($countblock/$perpage), $page, $perpage,
            $CFG->wwwroot . '/local/reservasalas/desbloquear.php?action=' . $action . '&search=' . $search . '&page=');
    }else{
        $dom = $form->display();
        $dom .= html_writer::div(get_string('noblocked','local_reservasalas'), 'alert alert-warning');
    }
    
    //Se carga la página, ya sea el título, head y migas de pan.
    
    echo $dom;
    echo $OUTPUT->footer();
}
