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
function jsrunner_shortcode( $atts, $content = null )
{
	$postid=get_the_ID();
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
	<div id="result#postid#" class="row"></div>
	<ul id="testcases#postid#"><?echo $testcases_li;?></ul>
	<script language="javascript" defer>
	jQuery( document ).ready(function() {
		jsrunner_init(#postid#,'<?php echo $ajaxurl;?>');
	});
	</script>
	<?php
	$ret=ob_get_clean();
	$ret=str_replace('#postid#',$postid,$ret);
	return $ret;
}

function jsrunner_ajax()
{
	//inputs
	$postid = $_POST['post'];
	$notification = $_POST['notification'];
	$userid = get_current_user_id();
	if (!in_array($notification,array('view','attempt','solved')))
		exit;

	$key='jsrunner_'.$notification;
	//add to post meta
	$users = get_post_meta( $postid, $key, true );
	if (!in_array($userid,explode(',',$users)))
	{
		if ($users=='')
			$users=$userid;
		else
			$users=$users.','.$userid;
	}
	if ( ! update_post_meta ($postid, $key, $users, true) ) { 
		add_post_meta($postid, $key, $userid, true );	
	};
	//add to user meta
	$posts = get_user_meta( $postid, $key, true );
	if (!in_array($postid,explode(',',$posts)))
	{
		if ($posts=='')
			$posts=$postid;
		else
			$posts=$posts.','.$postid;
	}
	if ( ! update_user_meta ($userid, $key, $posts, true) ) { 
		add_user_meta($userid, $key, $postid, true );	
	};
	exit;
}

/*---------------HOOKS------------------*/
add_action( 'wp_enqueue_scripts', 'jsrunner_include' ); //include scripts in template
add_action( 'wp_ajax_jsrunner', 'jsrunner_ajax' ); //respond to ajax
add_shortcode( 'jsrunner', 'jsrunner_shortcode' ); //use shortcodes
add_shortcode( 'hint', 'jsrunner_hint_shortcode' ); //hint shortcodes
?>