<?php


    /**
    *
    * RSS feeds generation
    *
	*
	* @package mod-promising
	* @category mod
	* @author Yohan Thomas - W3C2i (support@w3c2i.com)
	* @date 30/09/2013
	* @version 3.0
	* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
	*
	*/
	
function promising_rss_get_feed($context, $args) {
    global $CFG, $DB, $USER;

    $status = true;

    //are RSS feeds enabled?
    /*if (empty($CFG->promising_enablerssfeeds)) {
        debugging('DISABLED (module configuration)');
        return null;
    }*/

    $promisingid  = clean_param($args[3], PARAM_INT);
    

    //the sql that will retreive the data for the feed and be hashed to get the cache filename
    list($sql, $params) = promising_rss_get_sql($promisingid);
	

    // Hash the sql to get the cache file name.
    $filename = rss_get_file_name($promisingid, $sql, $params);
    $cachedfilepath = rss_get_file_full_name('mod_promising', $filename);
    //Is the cache out of date?
    $cachedfilelastmodified = 0;
    if (file_exists($cachedfilepath)) {
        $cachedfilelastmodified = filemtime($cachedfilepath);
    }
    // Used to determine if we need to generate a new RSS feed.
    $dontrecheckcutoff = time()-60;
    // If it hasn't been generated we will need to create it, otherwise only update
    // if there is new stuff to show and it is older than the cut off date set above.
	if (($cachedfilelastmodified == 0) || (($dontrecheckcutoff > $cachedfilelastmodified) &&
        promising_rss_newstuff($promisingid, $cachedfilelastmodified))) {
        // Need to regenerate the cached version.
        $result = promising_rss_feed_contents($promisingid, $sql, $params);
		$status = rss_save_file('mod_promising', $filename, $result);
    }
    //return the path to the cached version
    return $cachedfilepath;
}

/**
 * Given a promising object, deletes all cached RSS files associated with it.
 *
 * @param stdClass $promising
 */
function promising_rss_delete_file($promisingid) {
    rss_delete_file('mod_promising', $promisingid);
}

///////////////////////////////////////////////////////
//Utility functions

/**
 * If there is new stuff in the promising since $time this returns true
 * Otherwise it returns false.
 *
 * @param stdClass $promising the promising object
 * @param stdClass $cm    Course Module object
 * @param int      $time  check for items since this epoch timestamp
 * @return bool True for new items
 */
function promising_rss_newstuff($promisingid, $time) {
    global $DB;
    list($sql, $params) = promising_rss_get_sql($promisingid, $time);
    return $DB->record_exists_sql($sql, $params);
}


/**
 * Generates the SQL query used to get the Discussion details from the promising table of the database
 *
 * @param stdClass $promising     the promising object
 * @param stdClass $cm        Course Module object
 * @param int      $newsince  check for items since this epoch timestamp
 * @return string the SQL query to be used to get the Discussion details from the promising table of the database
 */
function promising_rss_get_sql($promisingid, $newsince=0) {
    global $CFG, $DB, $USER;

    $timelimit = '';

    //$modcontext = null;

    $now = round(time(), -2);
    $params = array();

    $promisingsort = "timemodified DESC";
   // $postdata = "p.id AS postid, p.subject, p.created as postcreated, p.modified, p.discussion, p.userid, p.message as postmessage, p.messageformat AS postformat, p.messagetrust AS posttrust";

    $sql = "SELECT *
              FROM {promising}
             WHERE projectgrpid = {$promisingid} AND etat = 1
					
          ORDER BY $promisingsort";
    return array($sql, $params);
}

/**
 * This function return the XML rss contents about the promising
 * It returns false if something is wrong
 *
 * @param stdClass $promising the promising object
 * @param string $sql the SQL used to retrieve the contents from the database
 * @param array $params the SQL parameters used
 * @param object $context the context this promising relates to
 * @return bool|string false if the contents is empty, otherwise the contents of the feed is returned
 *
 * @Todo MDL-31129 implement post attachment handling
 */

function promising_rss_feed_contents($promisingid, $sql, $params) {
    global $CFG, $DB, $USER;

    $status = true;

	$recs = $DB->get_records('promising', array('projectgrpid' => $promisingid,'etat' => 1));
    //set a flag. Are we displaying discussions or posts?


	
	$roleprojectgrp = $DB->get_record('role', array('id' => $promisingid));
	
    $formatoptions = new stdClass();
    $items = array();
	$modulepromising = $DB->get_record('modules', array('name' => 'promising'));
    foreach ($recs as $rec) {
		$item = new stdClass();
		//$user = new stdClass();
		$item->title = format_string($rec->name);
		//$item->author = fullname($user);//mettre le nom du groupe projet
		$item->author = $roleprojectgrp->name;//mettre le nom du groupe projet
			//$formatoptions->trusted = $rec->posttrust;
		$contexttmp;
		if($cmtmp = get_coursemodule_from_instance('promising', $rec->id)){//parfois impossible de récuperer le cm associé au projet... et donc pas de lien possible
			$contexttmp = context_module::instance($cmtmp->id);
			$item->link = $CFG->wwwroot."/mod/promising/view.php?id=".$cmtmp->id."&view=description";
		}
		
		$description = strip_tags($rec->intro).'<br />';
		
		//AJOUT AFFICHAGE ARCHIVE DES ETAPES
		if($rec->projectconfidential==0){
			$milestones = $DB->get_records_select('promising_milestone', "projectid = ?", array($rec->id),'ordering ASC' );
			$fs = get_file_storage();
			$noArchive=true;
			foreach($milestones as $milestone){
				$files = $fs->get_area_files($contexttmp->id, 'mod_promising', 'deliverablearchive', $milestone->id, 'sortorder DESC, id ASC', false);
				if(!empty($files)){
					$noArchive=false;
					$file = array_pop($files);//on prend le dernier fichier archive
					$path = '/'.$contexttmp->id.'/mod_promising/deliverablearchive/'.$file->get_itemid().$file->get_filepath().$file->get_filename();
					$url = moodle_url::make_file_url('/pluginfile.php', $path, '');
					$lienArchive = html_writer::link($url, $file->get_filename());
					$listeArchives .= "<li>".$milestone->abstract." : ".$lienArchive."</li>";
				}
			}
			if(!$noArchive){
				$description .= "<span>Archives des étapes :</span><br />";
				$description .=  "<ul>";
				$description .=  $listeArchives;
				$description .=  "</ul>";
			}
		}
		//FIN AJOUT
		
		//AJOUT AFFICHAGE DE L EQUIPE
		$assignableroles = $DB->get_records('role', array(), '', 'id,name,shortname');
		$roles = array('projectetu','projectens','projectent');
		$rolesName = array('Etudiants Projet','Tuteurs enseignant','Tuteurs entreprise');
		$description .= "<span>Composition de l'équipe :</span><br />";
		for ($i=0;$i<3;$i++){
			$rolempty = true;
			$roleNom = $rolesName[$i];
			foreach ($assignableroles as $role) {
				if($role->shortname==$roles[$i]){
					$roleusers = '';
					if(isset($contexttmp->id)&& $contexttmp->id>0){
						$roleusers = get_role_users($role->id, $contexttmp, false, 'u.id, u.firstname, u.lastname, u.email');
						if (!empty($roleusers)) {
							$rolempty = false;
							$listeUsers ='';
							foreach ($roleusers as $user) {
								$listeUsers .= '<li>' . fullname($user) . '</li>';
							}
						}
					}
				}
			}
			if(!$rolempty){
				$description .= "<span>".$roleNom." :</span><br />";
				$description .=  "<ul>";
				$description .=  $listeUsers;
				$description .=  "</ul>";
			}
		}
		//FIN AJOUT
		$item->description = format_text($description, $rec->introformat, $formatoptions, $rec->course);

		$item->pubdate = $rec->timecreated;

		$items[] = $item;
	}
    //$recs->close();

    // Create the RSS header.
	$header = rss_standard_header(strip_tags(format_string('Ensemble des projets',true)),
                                  null,
                                  format_string("Retrouvez tous les projets terminées du groupe ".$roleprojectgrp->name,true));
    // Now all the RSS items, if there are any.
    $rssProjets = '';
    if (!empty($items)) {
        $rssProjets = rss_add_items($items);
    }
    // Create the RSS footer.
    $footer = rss_standard_footer();

    return $header . $rssProjets . $footer;
}
