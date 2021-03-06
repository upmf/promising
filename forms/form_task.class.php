<?php

require_once($CFG->libdir.'/formslib.php');

class Task_Form extends moodleform {

	var $mode;
	var $project;
	var $current;
	var $descriptionoptions;

	function __construct($action, &$project, $mode, $taskid){
		global $DB;
		
		$this->mode = $mode;
		$this->project = $project;
		if ($taskid){
			$this->current = $DB->get_record('promising_task', array('id' => $taskid));
		}

		parent::__construct($action);
	}
    	
	function definition(){
		global $COURSE, $DB, $USER, $OUTPUT;

    	$mform = $this->_form;
    	
    	$currentGroup = 0 + groups_get_course_group($COURSE);

    	$modcontext = context_module::instance($this->project->cmid);

		$maxfiles = 99;                // TODO: add some setting
		$maxbytes = $COURSE->maxbytes; // TODO: add some setting	
		$this->descriptionoptions = array('trusttext' => true, 'subdirs' => false, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes, 'context' => $modcontext);
    	
    	$mform->addElement('hidden', 'id');
    	$mform->addElement('hidden', 'fatherid');
    	$mform->addElement('hidden', 'taskid');
    	$mform->addElement('hidden', 'work');
    	$mform->setDefault('work', $this->mode);
    	
    	$mform->addElement('text', 'abstract', get_string('tasktitle', 'promising'), array('size' => "100%"));

    	$mform->addElement('editor', 'description_editor', get_string('description', 'promising'), null, $this->descriptionoptions);		    	

        $milestones = $DB->get_records_select('promising_milestone', "projectid = ? AND groupid = ? ", array($this->project->id, $currentGroup), 'ordering ASC', 'id, abstract, ordering');
        $milestonesoptions = array();
        $milestonesoptions[0] = get_string('nomilestone', 'promising');
        if ($milestones){
            foreach($milestones as $aMilestone){
                $milestonesoptions[$aMilestone->id] = $aMilestone->abstract;
            }
        }
    	$mform->addElement('select', 'milestoneid', get_string('milestone', 'promising'), $milestonesoptions);
		$mform->addHelpButton('milestone', 'task_to_miles', 'promising');

        $ownerstr = $USER->lastname . " " . $USER->firstname . " ";
        $ownerstr .= $OUTPUT->user_picture($USER); 

		$mform->addElement('static', 'owner_st', get_string('owner', 'promising'), $ownerstr);
		$mform->addElement('hidden', 'owner');
		$mform->setDefault('owner', $USER->id);

        $assignees = promising_get_group_users($this->project->course, $this->project->cm, $currentGroup);
        if($assignees){
            $assignoptions = array();
            foreach($assignees as $anAssignee){
                $assignoptions[$anAssignee->id] = $anAssignee->lastname . ' ' . $anAssignee->firstname;
            }
	    	$mform->addElement('select', 'assignee', get_string('assignee', 'promising'), $assignoptions);
			// $mform->addHelpButton('assignee', 'assignee', 'promising');
        } else {
	    	$mform->addElement('static', 'assignee', get_string('assignee', 'promising'), get_string('noassignees', 'promising'));
        }

    	$mform->addElement('date_time_selector', 'taskstart', get_string('from'), array('optional' => true));
    	$mform->addElement('date_time_selector', 'taskend', get_string('to'), array('optional' => true));

        $worktypes = promising_get_options('worktype', $this->project->id);
        $worktypeoptions = array();
        foreach($worktypes as $aWorktype){
            $worktypeoptions[$aWorktype->code] = '['. $aWorktype->code . '] ' . $aWorktype->label;
        }
    	$mform->addElement('select', 'worktype', get_string('worktype', 'promising'), $worktypeoptions);
		$mform->addHelpButton('worktype', 'worktype', 'promising');

        $statusses = promising_get_options('taskstatus', $this->project->id);
        $statussesoptions = array();
        foreach($statusses as $aStatus){
            $statussesoptions[$aStatus->code] = '['. $aStatus->code . '] ' . $aStatus->label;
        }
    	$mform->addElement('select', 'status', get_string('status', 'promising'), $statussesoptions);
		$mform->addHelpButton('status', 'status', 'promising');

    	$mform->addElement('text', 'costrate', get_string('costrate', 'promising'), array('size' => 6, 'onchange' => " task_update('quoted');task_update('spent') "));
    	$mform->addElement('text', 'planned', get_string('planned', 'promising'), array('size' => 6, 'onchange' => " task_update('quoted') "));

    	$mform->addElement('static', 'quoted', get_string('quoted', 'promising'), "<span id=\"quoted\">".@$this->current->quoted."</span> ".$this->project->costunit);
        $mform->addHelpButton('quoted', 'quoted', 'promising'); 

		if (@$this->project->useriskcorrection){
	        $risks = promising_get_options('risk', $this->project->id);
	        $risksesoptions = array();
	        foreach($risks as $aRisk){
	            $risksoptions[$aRisk->code] = '['. $aRisk->code . '] ' . $aRisk->label;
	        }
	    	$mform->addElement('select', 'risk', get_string('risk', 'promising'), $risksoptions);
	    }

    	$mform->addElement('text', 'done', get_string('done', 'promising'), array('size' => 6));
    	$mform->addElement('text', 'used', get_string('used', 'promising'), array('size' => 6, 'onchange' => " task_update('spent') "));
    	$mform->addElement('static', 'spent', get_string('spent', 'promising'), "<span id=\"spent\">".@$this->current->spent."</span> ".$this->project->costunit);
        // $mform->addHelpButton('spent', 'spent', 'promising'); 

        $tasks = promising_get_tree_options('promising_task', $this->project->id, $currentGroup);
        $selection = $DB->get_records_select_menu('promising_task_dependency', "slave = ? ", array(@$this->current->id), 'master,slave');
        $uptasksoptions = array();
        foreach($tasks as $aTask){
            $aTask->abstract = format_string($aTask->abstract);
            if ($aTask->id == $this->current->id) continue;
            $parentid = $DB->get_field('promising_task', 'fatherid', array('id' => $this->current->id));
            if ($aTask->id == $parentid) continue;
            if (promising_check_task_circularity($this->current->id, $aTask->id)) continue;
            $uptasksoptions[$aTask->id] = $aTask->ordering.' - '.shorten_text($aTask->abstract, 90);
        }
    	$select = &$mform->addElement('select', 'taskdependency', get_string('taskdependency', 'promising'), $uptasksoptions, array('size' => 8));
    	$select->setMultiple(true);

		if ($this->project->projectusesspecs && $this->mode == 'update'){
	        $specifications = promising_get_tree_options('promising_specification', $this->project->id, $currentGroup);
	        $selection = $DB->get_records_select_menu('promising_task_to_spec', "taskid = ? ", array($this->current->id), 'specid, taskid');
	        $specs = array();
	        if (!empty($specifications)){
	            foreach($specifications as $aSpecification){
	                $specs[$aSpecification->id] = $aSpecification->ordering .' - '.shorten_text(format_string($aSpecification->abstract), 90);
	            }
	        }
			$select = &$mform->addElement('select', 'tasktospec', get_string('tasktospec', 'promising'), $specs, array('size' => 8));
			$select->setMultiple(true);
			$mform->addHelpButton('tasktospec', 'task_to_spec', 'promising');
		}

		if ($this->project->projectusesdelivs && $this->mode == 'update'){
	        $deliverables = promising_get_tree_options('promising_deliverable', $this->project->id, $currentGroup);
	        $selection = $DB->get_records_select_menu('promising_task_to_deliv', "taskid = ? ", array($this->current->id), 'delivid, taskid');
	        $delivs = array();
	        if (!empty($deliverables)){
	            foreach($deliverables as $aDeliverable){
	                $delivs[$aDeliverable->id] = $aDeliverable->ordering .' - '.shorten_text(format_string($aDeliverable->abstract), 90);
	            }
	        }
			$select = &$mform->addElement('select', 'tasktodeliv', get_string('tasktodeliv', 'promising'), $delivs, array('size' => 8));
			$select->setMultiple(true);
			$mform->addHelpButton('tasktodeliv', 'task_to_deliv', 'promising');
		}
		
		$this->add_action_buttons(true);
    }

    function set_data($defaults){

		$context = context_module::instance($this->project->cmid);

		$draftid_editor = file_get_submitted_draft_itemid('description_editor');
		$currenttext = file_prepare_draft_area($draftid_editor, $context->id, 'mod_promising', 'description_editor', $defaults->id, array('subdirs' => true), $defaults->description);
		$defaults = file_prepare_standard_editor($defaults, 'description', $this->descriptionoptions, $context, 'mod_promising', 'taskdescription', $defaults->id);
		$defaults->description = array('text' => $currenttext, 'format' => $defaults->descriptionformat, 'itemid' => $draftid_editor);

    	parent::set_data($defaults);
    }
}
