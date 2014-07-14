<?php
/**
 * @package jsrunner
 * @version 1
 */
/*
Plugin Name: jsrunner
Description: Requires Bootstrap and JQuery, [jsrunner test_input=1 test_output=2]output=2*input;[/jsrunner]
Author: Uri Goren
Author URI: http://www.goren4u.com
Usage:   [jsrunner test_input=1 test_output=2]output=2*input;[/jsrunner], supports unlimited number of tests
*/
function jsrunner_install()
{
	global $wpdb;
	$table_name = $wpdb->prefix . "jsrunner";
	$sql="CREATE TABLE $table_name (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL DEFAULT '0',
  user_id bigint(20) unsigned NOT NULL DEFAULT '0',
  action tinytext NOT NULL,
  text text NOT NULL,
  time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY id (id)
    );";
	//$wpdb->query($sql);
	
   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );

}

function jsrunner_include()
{
	wp_enqueue_style( 'jsrunner-name', plugins_url() . '/jsrunner/js/runner.css' );
	wp_enqueue_script( 'ace', plugins_url() . '/jsrunner/js/ace/src/ace.js', array(), '1.1.3', true );
	wp_enqueue_script( 'jsrunner', plugins_url() . '/jsrunner/js/runner.js', array(), '1.0.0', true );
}
function jsrunner_hint_shortcode( $atts, $content = null )
{
	$postid=get_the_ID();
	$hint='';
	if ( is_user_logged_in() && !is_null( $content ) && !is_feed() )
	{
		$hint=do_shortcode($content);
	}
	else
	{
		$hint= 'Only for registered users, <a href="'.wp_login_url( get_permalink() ).'" title="Login">Login</a> or <a href="'.wp_registration_url().'" title="Register">Register</a>';
	}
	ob_start();?>
	<div class="row">
		<div class="col-sm-9 alert alert-success" id="hintdiv#postid#" style="display:none;"><?php echo $hint;?></div>
		<div class="col-sm-3"><button id="hintbtn#postid#" class="btn btn-large btn-info pull-left" onclick="jsrunner_hint(#postid#);"><i class="fa fa-angle-double-right"></i>
Hint</button></div>
	</div><br />
	<?php
	$ret=ob_get_clean();
	$ret=str_replace('#postid#',$postid,$ret);
	return $ret;
}

function jsrunner_shortcode_parse_attributes_to_testcases( $atts)
{
	//build test-cases from attributes
	$testcases=array();
	foreach ($atts as $attribute => $value)
	{
		if (stripos($attribute,'input')>0)
		{
			$key=str_ireplace('input','',$attribute);
			if (array_key_exists($key,$testcases))
			{
				$testcases[$key]=str_replace('#input#',$value,$testcases[$key]);
			}
			else
			{
				$testcases[$key]='{"input": '.$value.', "output": #output#}';
			}
		}
		elseif (stripos($attribute,'output')>0)
		{
			$key=str_ireplace('output','',$attribute);
			if (array_key_exists($key,$testcases))
			{
				$testcases[$key]=str_replace('#output#',$value,$testcases[$key]);
			}
			else
			{
				$testcases[$key]='{"input": #input#, "output": '.$value.'}';
			}
		}
	}
	return $testcases;
}
function jsrunner_shortcode_generate_testcases_from_php( $phpcode)
{
	$ev=eval($phpcode);
	if (($ev===FALSE)||(!isset($input))||(!isset($output)))
	{
		//an error occurred
		return array();
	}
	$num=count($input);
	$testcases=array();
	for ($i=0;$i<$num;$i++)
	{
		$testcases[$i]='{"input": '.(is_numeric($input[$i])?$input[$i]:json_encode($input[$i])).', "output": '.(is_numeric($output[$i])?$output[$i]:json_encode($output[$i])).'}';
	}
	return $testcases;
}
function jsrunner_apply_modal()
{
	$postid=get_the_ID();
	$content='';
	if ( is_user_logged_in() && !is_null( $content ) && !is_feed() )
	{
		$user_info = get_userdata(get_current_user_id());
		$content='<form>';
		$content.='<div class="form-group"><label for="linkedin#postid#">Linkedin Profile: </label><input type="text" id="linkedin#postid#" value="'.$user_info->user_url.'" /></div>';
		$content.='<div class="form-group"><label for="notes#postid#">Notes: </label><textarea id="notes#postid#"></textarea></div>';
		$content.='</form>';
	}
	else
	{
		$content= 'Only for registered users, <a href="'.wp_login_url( get_permalink() ).'" title="Login">Login</a> or <a href="'.wp_registration_url().'" title="Register">Register</a>';
	}
	ob_start();?>	
<div class="modal fade" id="modal#postid#" tabindex="-1" role="dialog" aria-labelledby="modal_label#postid#" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title" id="model_label#postid#">Tell us about you</h4>
      </div>
      <div class="modal-body">
        <?php echo $content;?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" id="apply#postid#" class="btn btn-primary" data-dismiss="modal">Submit</button>
      </div>
    </div>
  </div>
</div>
	<?php
	$ret=ob_get_clean();
	//$ret=str_replace('#postid#',$postid,$ret);
	return $ret;
}
function jsrunner_shortcode( $atts, $content = null )
{
	//--------------READ POST META--------------
	$postid=get_the_ID();
	$show_submit = get_post_meta( $postid, 'jsrunner_show_submit', true );
	$show_submit=(empty($show_submit)?0:$show_submit);
	$success_message = get_post_meta( $postid, 'jsrunner_success_message', true );
	$success_message=empty($success_message)?'Great job !!!':$success_message;
	
	//--------------GET THE TEST CASES--------------
	$testcases = get_post_meta( $postid, 'jsrunner_testcases', true );
	if (empty($testcases))
	{
		$php_generated_testcases = get_post_meta( $postid, 'jsrunner_php', true );
		// check if the custom field has a value
		if(!empty($php_generated_testcases))
		{
			$testcases=jsrunner_shortcode_generate_testcases_from_php($php_generated_testcases);
		} 
		else
		{
			$testcases=jsrunner_shortcode_parse_attributes_to_testcases( $atts);
		}
	}
	else
	{
		$testcases=explode("\n",$testcases);
	}
	//--------------GENERATE HTML--------------
	$testcases_li='';
	foreach ($testcases as $json)
	{
		$testcase=str_replace('#input#','0',$json);
		$testcase=str_replace('#output#','0',$testcase);
		$testcases_li.="<li>$testcase</li>";
	}
	//generate html
	$ajaxurl=admin_url('admin-ajax.php');
	ob_start();
	?>
	<div class="row">
		<div id="edit#postid#"><?php echo trim(strip_tags($content),' \t\n');?></div>
	</div>
	<div class="row">
		<div id="error#postid#" class="col-sm-9 alert">
		</div>
		<div class="col-sm-3">
			<button id="run#postid#" class="btn btn-large btn-primary pull-right"><i class="fa fa-play"></i> Run</button>
		</div>
	</div>
	<div id="result#postid#" class="row"><?php echo $success_message;?></div>
	<ul id="testcases#postid#"><?echo $testcases_li;?></ul>
	<?php 
	if ($show_submit)
	{
		echo jsrunner_apply_modal();
	}
	?>
	<script language="javascript" defer>
	jQuery( document ).ready(function() {
		jsrunner_init(#postid#,'<?php echo $ajaxurl;?>',<?php echo $show_submit;?>);
	});
	</script>
	<?php
	$ret=ob_get_clean();
	$ret=str_replace('#postid#',$postid,$ret);
	return $ret;
}

function jsrunner_ajax()
{
	global $wpdb;
	$db_table = $wpdb->prefix . "jsrunner";
	//inputs
	$postid = (int)($_POST['post']);
	$notification = $_POST['notification'];
	$message = $_POST['message'];
	$userid = get_current_user_id();
	if (!in_array($notification,array('view','attempt','solved','hint','apply')))
		exit;

		
	$wpdb->insert( 
	$db_table, 
		array( 
			'action' => $notification, 
			'post_id' => $postid,
			'user_id' => $userid,
			'text'=>$message,
		), 
		array( 
			'%s', 
			'%d',
			'%d',
			'%s',
		) 
	);
	exit;
}

/*---------------HOOKS------------------*/
add_action( 'wp_enqueue_scripts', 'jsrunner_include' ); //include scripts in template
add_action( 'wp_ajax_jsrunner', 'jsrunner_ajax' ); //respond to ajax
add_shortcode( 'jsrunner', 'jsrunner_shortcode' ); //use shortcodes
add_shortcode( 'hint', 'jsrunner_hint_shortcode' ); //hint shortcodes
register_activation_hook( __FILE__, 'jsrunner_install' );//build db on activation
?>