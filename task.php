<?php

    /*
    *
    * Task operations
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

if ($work == "add" || $work == "update") {
	include 'edit_task.php';
/// Group operation form *********************************************************

} elseif ($work == "groupcmd") {
	echo $pagebuffer;
    $ids = optional_param('ids', array(), PARAM_INT);
    $cmd = required_param('cmd', PARAM_ALPHA);
    ?>
    <center>
    <?php 
    echo $OUTPUT->heading(get_string('groupoperations', 'promising'));
    echo $OUTPUT->heading(get_string("group$cmd", 'promising'), 3);
    if ($cmd == 'copy' || $cmd == 'move')
        echo $OUTPUT->box(get_string('groupcopymovewarning', 'promising'), 'center', '70%'); 
    ?>
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
    <form name="groupopform" method="post" action="view.php">
    <input type="hidden" name="id" value="<?php p($cm->id) ?>" />
    <input type="hidden" name="work" value="" />
    <?php
            foreach($ids as $anId){
                echo "<input type=\"hidden\" name=\"ids[]\" value=\"{$anId}\" />\n";
            }
			// special command post options
            if (($cmd == 'move')||($cmd == 'copy')){
                echo get_string('to', 'promising');
                if (@$project->projectusesrequs) $options['requs'] = get_string('requirements', 'promising');
                if (@$project->projectusesspecs) $options['specs'] = get_string('specifications', 'promising');
                if (@$project->projectusesspecs) $options['specswb'] = get_string('specificationswithbindings', 'promising');
                if (@$project->projectusesdelivs) $options['deliv'] = get_string('deliverables', 'promising');
                if (@$project->projectusesdelivs) $options['delivwb'] = get_string('deliverableswithbindings', 'promising');
                echo html_writer::select($options, 'to', '', array('choose'));
            }
            if ($cmd == 'applytemplate'){
				echo '<input type="checkbox" name="applyroot" value="1" /> '.get_string('alsoapplyroot', 'promising');
				echo '<br/>';
            }
			echo '<br/>';
    ?>
    <input type="button" name="go_btn" value="<?php print_string('continue') ?>" onclick="senddata('<?php p($cmd) ?>')" />
    <input type="button" name="cancel_btn" value="<?php print_string('cancel') ?>" onclick="cancel()" />
    </form>
    </center>
<?php
} else {
	if ($work) {
		include 'tasks.controller.php';
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
            if ($USER->editmode == 'on' && has_capability('mod/promising:changetasks', $context)) {
        		echo "<br/><a href='view.php?id={$cm->id}&amp;work=add&amp;fatherid=0'>".get_string('addroottask','promising')."</a> ";
    	    }
    		promising_print_tasks($project, $currentGroupId, 0, $cm->id);
            if ($USER->editmode == 'on' && has_capability('mod/promising:changetasks', $context)) {
	        	echo "<br/><a href='javascript:selectall(document.forms[\"groupopform\"])'>".get_string('selectall','promising')."</a>&nbsp;";
	        	echo "<a href='javascript:unselectall(document.forms[\"groupopform\"])'>".get_string('unselectall','promising')."</a>&nbsp;";
        		echo "<a href='view.php?id={$cm->id}&amp;work=add&amp;fatherid=0'>".get_string('addroottask','promising')."</a> ";
        		if (@$SESSION->promising->tasktemplateid){
	        		promising_print_group_commands(array('markasdone','fullfill','applytemplate'));
	        	} else {
	        		promising_print_group_commands(array('markasdone','fullfill'));
	        	}
        		echo "<br/><a href='view.php?id={$cm->id}&amp;work=recalc'>".get_string('recalculate','promising')."</a> ";
    	    }
    ?>
    </form>
<?php
	}
?>