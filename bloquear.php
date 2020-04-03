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


//Página para bloquear alumnos.
//Pruebas git
require_once(dirname(__FILE__) . '/../../config.php'); //obligatorio
require_once($CFG->dirroot.'/local/reservasalas/forms.php');
require_once($CFG->dirroot.'/local/reservasalas/tablas.php');


global $PAGE, $CFG, $OUTPUT, $DB;
require_login();
if (isguestuser()){
    die();
}

$action = optional_param("action", "view", PARAM_TEXT);
$id = optional_param("id", 0, PARAM_INT);
$search = optional_param("search", null, PARAM_TEXT);

$url = new moodle_url('/local/reservasalas/bloquear.php');
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
$PAGE->navbar->add(get_string('roomsreserve', 'local_reservasalas'),'reservar.php');
$PAGE->navbar->add(get_string('users', 'local_reservasalas'));
$PAGE->navbar->add(get_string('blockstudent', 'local_reservasalas'),'bloquear.php');

$title = get_string('blockstudent', 'local_reservasalas');
$PAGE->set_title($title);
$PAGE->set_heading($title);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

if($action == 'block'){
    //how would this even happen?
    if (!$id > 0) {
        print_error(get_string('invalidid', 'local_reservasalas'));
    }

    if (block($id, null, get_string("bloquear-comment", "local_reservasalas"))) 
    {
        echo html_writer::div(get_string('blocked', 'local_reservasalas'), 'alert alert-success');
        $action = 'view';
    } 
    else 
    {
        print_error(get_string('failtounblock', 'local_reservasalas'));
    }
}
if($action == 'view'){
    //Formulario para bloquear a un alumno
    $form = new buscadorUsuario(null);
    $dom = $form->display();
    if($fromform = $form->get_data()){
        $search = $fromform->email;

        $query = 'Select u.id, u.username, u.firstname, u.lastname, MAX(rb.estado) as estado
                    from mdl_user as u
                    left join mdl_reservasalas_bloqueados as rb on (u.id = rb.alumno_id)
                    where '.$DB->sql_like('username', ':search1' , $casesensitive = false, $accentsensitive = false, $notlike = false).'
                    OR '.$DB->sql_like('firstname', ':search2' , $casesensitive = false, $accentsensitive = false, $notlike = false).'
                    OR '.$DB->sql_like('lastname', ':search3' , $casesensitive = false, $accentsensitive = false, $notlike = false).'
                    group by u.id';
        //Bloquea al usuario en la base de datos
        if($usuarios = $DB->get_records_sql($query,array('search1'=>"%$search%", 'search2'=>"%$search%", 'search3'=>"%$search%"), 0, 30)){
            $countblock = count($usuarios);
            $table = new html_table();
            $table->head = array(
                '#',
                get_string('name','local_reservasalas'),
                get_string('lastname','local_reservasalas'),
                get_string('email','local_reservasalas'),
                get_string('action','local_reservasalas')
            );
            $counter = 1;
            foreach($usuarios as $usuario){
                if($usuario->estado == 1){
                    $action = '<strike>'.get_string('blocked','local_reservasalas').'</strike>';
                    $firstname = '<strike>'.$usuario->firstname.'</strike>';
                    $lastname = '<strike>'.$usuario->lastname.'</strike>';
                    $username = '<strike>'.$usuario->username.'</strike>';
                }else{
                    $action = $OUTPUT->single_button(new moodle_url($url, array('action'=>'block', 'id'=>$usuario->id, 'search' =>$search)), get_string('block','local_reservasalas'));
                    $firstname = $usuario->firstname;
                    $lastname = $usuario->lastname;
                    $username = $usuario->username;
                }
                $table->data[] = array(
                    $counter,
                    $firstname,
                    $lastname,
                    $username,
                    $action
                );

                
                //instead of updating everyone only update the 30 people shown
                //beware, if someone is actually updated it wont show on the table since its already loaded
                block_update($usuario->id);

                //show only first 30 people
                if($counter >= 30)
                {
                    break;
                }

                $counter++;
            }
            $dom .= html_writer::table($table);
        }else{
            $dom .= html_writer::div(get_string('nouser','local_reservasalas'), 'alert alert-warning');
        }
    }
    echo $dom;
    echo $OUTPUT->footer();
}