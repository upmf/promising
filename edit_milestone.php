<?php

/*
*
* @package mod-promising
* @category mod
* @author Yohan Thomas - W3C2i (support@w3c2i.com)
* @date 30/09/2013
* @version 3.0
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
*
*/

	require_once($CFG->dirroot."/mod/promising/forms/form_milestone.class.php");
	
	$mileid = optional_param('milestoneid', '', PARAM_INT);
	
	$mode = ($mileid) ? 'update' : 'add' ;
	
	$url = $CFG->wwwroot.'/mod/promising/view.php?id='.$id;
	$mform = new Milestone_Form($url, $project, $mode, $mileid);
	
	if ($mform->is_cancelled()){
		redirect($url);
	}
	
	if ($data = $mform->get_data()){
		$data->groupid = $currentGroupId;
		$data->projectid = $project->id;	
		$data->userid = $USER->id;
		$data->modified = time();
		$data->descriptionformat = $data->description_editor['format'];
		$data->description = $data->description_editor['text'];
		$data->lastuserid = $USER->id;
		$data->deadlineenable = ($data->deadline) ? 1 : 0;

		// editors pre save processing
		$draftid_editor = file_get_submitted_draft_itemid('description_editor');
		$data->description = file_save_draft_area_files($draftid_editor, $context->id, 'mod_promising', 'milestonedescription', $data->id, array('subdirs' => true), $data->description);
	    $data = file_postupdate_standard_editor($data, 'description', $mform->descriptionoptions, $context, 'mod_promising', 'milestonedescription', $data->id);

		if ($data->milestoneid) {
			$data->id = $data->milestoneid; // id is course module id
			$DB->update_record('promising_milestone', $data);
            add_to_log($course->id, 'promising', 'changemilestone', "view.php?id=$cm->id&view=milestones&group={$currentGroupId}", 'update', $cm->id);

		} else {
			$data->created = time();
    		$data->ordering = promising_tree_get_max_ordering($project->id, $currentGroupId, 'promising_milestone', false) + 1;
			unset($data->id); // id is course module id
			$data->id = $DB->insert_record('promising_milestone', $data);
        	add_to_log($course->id, 'promising', 'addmile', "view.php?id=$cm->id&view=milestones&group={$currentGroupId}", 'add', $cm->id);

       		if( $project->allownotifications){
       		    promising_notify_new_milestone($project, $cm->id, $data, $currentGroupId);
           	}
		}
		redirect($url);
	}

	echo $pagebuffer;
	if ($mode == 'add'){
		echo $OUTPUT->heading(get_string('addmilestone', 'promising'));
		$milestone->id = $cm->id;
		$milestone->projectid = $project->id;
		$milestone->descriptionformat = FORMAT_HTML;
		$milestone->description = '';
	} else {
		if(! $milestone = $DB->get_record('promising_milestone', array('id' => $mileid))){
			print_error('errormilestone','promising');
		}
		$milestone->milestoneid = $milestone->id;
		$milestone->id = $cm->id;
		
		echo $OUTPUT->heading(get_string('updatemilestone','promising'));
	}

	$mform->set_data($milestone);
	$mform->display();	
		
	