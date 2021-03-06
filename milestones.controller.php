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
$context = context_module::instance($cm->id);
	if ($work == 'dodelete') {
        $milestoneid = required_param('milestoneid', PARAM_INT);
    	promising_tree_delete($milestoneid, 'promising_milestone', 0); // uses list option switch
    	// cleans up any assigned task
    	$query = "
    	   UPDATE
    	      {promising_task}
    	   SET
    	      milestoneid = NULL
    	   WHERE
    	      milestoneid = $milestoneid
    	";
    	$DB->execute($query);

    	// cleans up any assigned deliverable
    	$query = "
    	   UPDATE
    	      {promising_deliverable}
    	   SET
    	      milestoneid = NULL
    	   WHERE
    	      milestoneid = $milestoneid
    	";
    	$DB->execute($query);
        add_to_log($course->id, 'promising', 'changemilestone', "view.php?id=$cm->id&view=milestone&group={$currentGroupId}", 'delete', $cm->id);
    } elseif ($work == 'doclearall') {
        // delete all records. POWERFUL AND DANGEROUS COMMAND.
		$DB->delete_records('promising_milestone', array('projectid' => $project->id));

        // do reset all milestone assignation in project
    	$query = "
    	   UPDATE
    	      {promising_task}
    	   SET
    	      milestoneid = NULL
    	   WHERE
    	      projectid = {$project->id} AND
    	      groupid = {$currentGroupId}
    	";
    	$DB->execute($query);

        // do reset all milestone assignation in project
    	$query = "
    	   UPDATE
    	      {promising_deliverable}
    	   SET
    	      milestoneid = NULL
    	   WHERE
    	      projectid = {$project->id} AND
    	      groupid = {$currentGroupId}
    	";
    	$DB->execute($query);
        add_to_log($course->id, 'promising', 'changemilestones', "view.php?id=$cm->id&view=milestone&group={$currentGroupId}", 'clear', $cm->id);
	} elseif ($work == 'up') {
        $milestoneid = required_param('milestoneid', PARAM_INT);
    	promising_tree_up($project, $currentGroupId,$milestoneid, 'promising_milestone', 0);
    } elseif ($work == 'down') {
        $milestoneid = required_param('milestoneid', PARAM_INT);
    	promising_tree_down($project, $currentGroupId,$milestoneid, 'promising_milestone', 0);
    } elseif ($work == 'sortbydate'){
        $milestones = array_values($DB->get_records_select('promising_milestone', "projectid = {$project->id} AND groupid = {$currentGroupId}"));

        function sortByDate($a, $b){
            if ($a->deadline == $b->deadline) return 0;
            return ($a->deadline > $b->deadline) ? 1 : -1 ; 
        }

        usort($milestones, 'sortByDate');
        // reorders in memory and stores back
        $ordering = 1;
        foreach($milestones as $aMilestone){
            $aMilestone->ordering = $ordering;
            $DB->update_record('promising_milestone', $aMilestone);
            $ordering++;
        }
    } elseif ($work == 'valider') {//Valider une étape
	/*
	 * Statuts :
	 * 0 => en travaux
	 * 1 => en cours de validation
	 * 2 => en révision
	 * 3 => validée
	 *	
	*/
		if(has_capability('mod/promising:validatemilestone', $context)){
			$milestoneid = required_param('milestoneid', PARAM_INT);
			$query = "
			   UPDATE
				  {promising_milestone}
			   SET
				  statut = 3
			   WHERE
				  id = $milestoneid
			";
			$DB->execute($query);
			promising_notify_milestone_change($project, $milestoneid, 3 , $cm->id, $currentGroupId);//Alerte email aux étudiants que l'étape a été validé
			$url = $CFG->wwwroot.'/mod/promising/view.php?view=milestones&id='.$cm->id;
		}
		redirect($url);
		
    } elseif ($work == 'refuser') {//demande de corrections pour une étape
        if(has_capability('mod/promising:validatemilestone', $context)){
			$milestoneid = required_param('milestoneid', PARAM_INT);
			$query = "
			   UPDATE
				  {promising_milestone}
			   SET
				  statut = 2
			   WHERE
				  id = $milestoneid
			";
			$DB->execute($query);
			
			$milestone = $DB->get_record('promising_milestone', array('id' => $milestoneid));
			
			//Création de la discution dans partie messages
			$newMessage = new StdClass;
			$newMessage->id = $cm->id;
			$newMessage->groupid = $currentGroupId;
			$newMessage->projectid = $project->id;
			$newMessage->abstract = get_string('milestone', 'promising')." ".$milestone->ordering.", ".$milestone->abstract." : ".get_string('revisionask', 'promising')." pour la version ".$milestone->numversion;
			$newMessage->message = '';
			$newMessage->messageformat = FORMAT_HTML;
			$newMessage->parent = 0;
			$newMessage->userid = $USER->id;
			$newMessage->created = time();
			$newMessage->modified = time();
			$newMessage->lastuserid = $USER->id;
			$newMessage->ordering = promising_tree_get_max_ordering_message($project->id, $currentGroupId, 'promising_messages', true, 0) + 1;
			$returnid = $DB->insert_record('promising_messages', $newMessage);
			
			promising_notify_milestone_change($project, $milestoneid, 2 , $cm->id, $currentGroupId);//Alerte email aux étudiants que l'étape a été mise en révision
			//$url = $CFG->wwwroot.'/mod/promising/view.php?view=milestones&id='.$cm->id;
			$url = $CFG->wwwroot."/mod/promising/view.php?id={$cm->id}&amp;work=update&amp;messageid={$returnid}&amp;view=messages";//redirection vers la discussion correspondante
		}
		//redirect($url);
		
    }elseif($work == 'askvalider'){//Demande de validation d'une étape
        if(has_capability('mod/promising:askvalidatemilestone', $context)){
			$milestoneid = required_param('milestoneid', PARAM_INT);
			$milestone = $DB->get_record('promising_milestone', array('id' => $milestoneid));
			$numVersion = (int)$milestone->numversion+1;
			
			$query = "
			   UPDATE
				  {promising_milestone}
			   SET
				  statut = 1
			   WHERE
				  id = $milestoneid
			";
			$DB->execute($query);
			$query = "
			   UPDATE
				  {promising_milestone}
			   SET
				  numversion = $numVersion
			   WHERE
				  id = $milestoneid
			";
			$DB->execute($query);
			
			//Création de la discution dans partie messages
			$newMessage = new StdClass;
			$newMessage->id = $cm->id;
			$newMessage->groupid = $currentGroupId;
			$newMessage->projectid = $project->id;
			$newMessage->abstract = get_string('milestone', 'promising')." ".$milestone->ordering.", ".$milestone->abstract." : ".get_string('validationask', 'promising')." version ".$numVersion;
			$newMessage->message = '';
			$newMessage->messageformat = FORMAT_HTML;
			$newMessage->parent = 0;
			$newMessage->userid = $USER->id;
			$newMessage->created = time();
			$newMessage->modified = time();
			$newMessage->lastuserid = $USER->id;
			$newMessage->ordering = promising_tree_get_max_ordering_message($project->id, $currentGroupId, 'promising_messages', true, 0) + 1;
			$returnid = $DB->insert_record('promising_messages', $newMessage);
			
			promising_notify_milestone_change($project, $milestoneid, 1 , $cm->id, $currentGroupId);//Alerte email aux enseignants que une demande de validation est faite pour l'étape
			
			// zip generation
			$zip = new ZipArchive();
			$numDigit = str_pad($numVersion,3, "0",STR_PAD_LEFT);//passage du numéro de version sur 3 digit
			$archiveName = "M".$milestone->ordering."-".$numDigit.".zip";
			$folderName ="M".$milestone->ordering."-".$numDigit;
			if($zip->open($CFG->tempdir.'/'.$archiveName, ZipArchive::CREATE) === true)
			{
				$zip->addEmptyDir($folderName);
				
				$fs = get_file_storage();
				$deliverables = $DB->get_records('promising_deliverable', array('milestoneid' => $milestoneid, 'projectid' => $project->id,'typeelm' => 1,'groupid' => $currentGroupId), '', 'abstract,localfile,url,id');
				foreach($deliverables as $deliverable){
					if($deliverable->localfile){//Si le livrable est un fichier
						$files = $fs->get_area_files($context->id, 'mod_promising', 'deliverablelocalfile', $deliverable->id, 'sortorder DESC, id ASC', false);
						if(!empty($files)){
							$file = reset($files);
							//$path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_promising/deliverablelocalfile/'.$file->get_itemid().$file->get_filepath().$file->get_filename());
							//$zip->addFile($path, $folderName.'/'.$file->get_filename());
							$zip->addFromString($folderName.'/'.$file->get_filename(),$file->get_content());
						}			
					}elseif($deliverable->url!=''){//Si le livrable est un lien (url)
						$zip->addFromString(makeAlias($deliverable->abstract), 'Lien du livrable :\n'.$deliverable->url);
					}
				}
				
				// Et on referme l’archive.
				$zip->close();
				
				//Enregistrement de l'archive dans moodle lié au context des étapes
				$file_record = array('contextid'=>$context->id, 'component'=>'mod_promising', 'filearea'=>'deliverablearchive',
						 'itemid'=>$milestoneid, 'filepath'=>'/', 'filename'=>$archiveName,
						 'timecreated'=>time(), 'timemodified'=>time());
				$fs->create_file_from_pathname($file_record, $CFG->tempdir.'/'.$archiveName);
				// Envoi en téléchargement.
				//header('Content-Transfer-Encoding: binary'); //Transfert en binaire (fichier).
				//header('Content-Disposition: attachment; filename="'.$archiveName.'"'); //Nom du fichier.
				//header('Content-Length: '.filesize($CFG->tempdir.'/'.$archiveName)); //Taille du fichier.
			}
			
			
			//$url = $CFG->wwwroot.'/mod/promising/view.php?view=milestones&id='.$cm->id;
			$url = $CFG->wwwroot."/mod/promising/view.php?id={$cm->id}&amp;work=update&amp;messageid={$returnid}&amp;view=messages";//redirection vers la discussion correspondante
		}
		//echo $OUTPUT->confirm('Are you sure?', $url, $CFG->wwwroot."/mod/promising/view.php?id={$cm->id}&amp;view=milestones");
		//redirect($url);
	}
	
	function makeAlias($string){
		$alias = strtolower($string);
		$alias = str_replace(" ", "-", $alias);
		$alias = preg_replace('/[-]{2,}/', '-', $alias);
		$alias = trim($alias, '-');
		return $alias;
	}
