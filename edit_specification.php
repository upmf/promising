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

	require_once($CFG->dirroot."/mod/promising/forms/form_specification.class.php");
	
	$specid = optional_param('specid', '', PARAM_INT);
	
	$mode = ($specid) ? 'update' : 'add' ;
	
	$url = $CFG->wwwroot.'/mod/promising/view.php?id='.$id;
	$mform = new Specification_Form($url, $project, $mode, $specid);
	
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

		// editors pre save processing
		$draftid_editor = file_get_submitted_draft_itemid('description_editor');
		$data->description = file_save_draft_area_files($draftid_editor, $context->id, 'mod_promising', 'specificationdescription', $data->id, array('subdirs' => true), $data->description);
	    $data = file_postupdate_standard_editor($data, 'description', $mform->descriptionoptions, $context, 'mod_promising', 'specificationdescription', $data->id);

		if ($data->specid) {
			$data->id = $data->specid; // id is course module id
			$DB->update_record('promising_specification', $data);
            add_to_log($course->id, 'promising', 'changespecification', "view.php?id=$cm->id&view=specifications&group={$currentGroupId}", 'update', $cm->id);

    		$spectoreq = optional_param_array('spectoreq', null, PARAM_INT);
    		if (count($spectoreq) > 0){
    		    // removes previous mapping
    		    $DB->delete_records('promising_spec_to_req', array('projectid' => $project->id, 'groupid' => $currentGroupId, 'specid' => $data->id));
    		    // stores new mapping
        		foreach($spectoreq as $aRequ){
        		    $amap->id = 0;
        		    $amap->projectid = $project->id;
        		    $amap->groupid = $currentGroupId;
        		    $amap->reqid = $aRequ;
        		    $amap->specid = $data->id;
        		    $res = $DB->insert_record('promising_spec_to_req', $amap);
        		}
        	}
		} else {
			$data->created = time();
    		$data->ordering = promising_tree_get_max_ordering($project->id, $currentGroupId, 'promising_specification', true, $data->fatherid) + 1;
			unset($data->id); // id is course module id
			$data->id = $DB->insert_record('promising_specification', $data);
        	add_to_log($course->id, 'promising', 'addspecification', "view.php?id=$cm->id&view=specifications&group={$currentGroupId}", 'add', $cm->id);

       		if( $project->allownotifications){
       		    promising_notify_new_specification($project, $cm->id, $data, $currentGroupId);
           	}
		}
		redirect($url);
	}

	echo $pagebuffer;
	if ($mode == 'add'){
		$specification->fatherid = required_param('fatherid', PARAM_INT);
		$spectitle = ($specification->fatherid) ? 'addsubspec' : 'addspec';
		echo $OUTPUT->heading(get_string($spectitle, 'promising'));
		$specification->id = $cm->id;
		$specification->projectid = $project->id;
		$specification->descriptionformat = FORMAT_HTML;
		$specification->description = '';
	} else {
		if(! $specification = $DB->get_record('promising_specification', array('id' => $specid))){
			print_error('errorspecification','promising');
		}
		$specification->specid = $specification->id;
		$specification->id = $cm->id;
		
		echo $OUTPUT->heading(get_string('updatespec','promising'));
	}

	$mform->set_data($specification);
	$mform->display();	
		
	