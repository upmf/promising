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
    /**
    * Requires and includes
    */
    include_once '../../lib/uploadlib.php';

/// Controller
	if ($work == 'add' || $work == 'update'){
		 include 'edit_deliverable.php';
/// Group operation form *********************************************************

	} elseif ($work == "groupcmd") {
		echo $pagebuffer;
	    $ids = required_param('ids', PARAM_INT);
	    $cmd = required_param('cmd', PARAM_ALPHA);
    ?>
    <center>
    <?php echo $OUTPUT->heading(get_string('groupoperations', 'promising')); ?>
    <?php echo $OUTPUT->heading(get_string("group$cmd", 'promising'), 'h3'); ?>
    <script type="text/javascript">
    //<![CDATA[
    function senddata(cmd){
        document.forms['groupopform'].work.value="do" + cmd;
        document.forms['groupopform'].submit();
    }
    function cancel(){
        document.forms['groupopform'].submit();
    }
    //]]>
    </script>
    <form name="groupopform" method="get" action="view.php">
    <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
    <input type="hidden" name="work" value="" />
    <?php
            foreach($ids as $anId){
                echo "<input type=\"hidden\" name=\"ids[]\" value=\"{$anId}\" />\n";
            }
            if (($cmd == 'move')||($cmd == 'copy')){
                echo get_string('to', 'promising');
                if (@$project->projectusesrequs) $options['requs'] = get_string('requirements', 'promising');
                if (@$project->projectusesspecs) $options['specs'] = get_string('specifications', 'promising');
                $options['tasks'] = get_string('tasks', 'promising');
                echo html_writer::select($options, 'to', '', 'choose');
            }
    ?>
    <input type="button" name="go_btn" value="<?php print_string('continue') ?>" onclick="senddata('<?php p($cmd) ?>')" />
    <input type="button" name="cancel_btn" value="<?php print_string('cancel') ?>" onclick="cancel()" />
    </form>
    </center>
    <?php
	} else {
		if ($work){
			include 'deliverables.controller.php';
		}
		echo $pagebuffer;
    ?>
    <script type="text/javascript">
    //<![CDATA[
    function sendgroupdata(){
        document.forms['groupopform'].submit();
    }
    //]]>
    </script>
    <form name="groupopform" method="post" action="view.php">
    <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
    <input type="hidden" name="work" value="groupcmd" />
    <?php
        if ($USER->editmode == 'on' && has_capability('mod/promising:changedelivs', $context) && $project->etat==0) {
    		echo "<br/><a href='view.php?id={$cm->id}&amp;work=add&amp;fatherid=0'>".get_string('adddeliv','promising')."</a>&nbsp; ";
    	}
    	promising_print_deliverables($project, $currentGroupId, 0, $cm->id);
        if ($USER->editmode == 'on' && has_capability('mod/promising:changedelivs', $context) && $project->etat==0) {
    		echo "<br/><a href='view.php?id={$cm->id}&amp;work=add&amp;fatherid=0'>".get_string('adddeliv','promising')."</a>&nbsp; ";
    		//promising_print_group_commands();
    	}
    ?>
    </form>
    <?php
    }
?>