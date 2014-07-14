function jsrunner_notify(post_id,ajax_url,notify_type,notify_msg)
{
	jQuery.post(ajax_url,
	{
		action : 'jsrunner',
		post : post_id,
		notification : notify_type,
		message : notify_msg,
	},
	function( response )
	{}
	);
}
function jsrunner_checkall(testcases)
{
	for (var i = 0; i < testcases.length; ++i)
	{
		if (JSON.stringify(testcases[i].result)!=JSON.stringify(testcases[i].output))
			return false;
	}
	return true;
}
function jsrunner_hint(post_id)
{
  	var hint_div=jQuery('#hintdiv'+post_id);
	if (hint_div.is(':hidden'))
	{
	  hint_div.slideDown('slow');
	  jQuery('#hintbtn'+post_id).removeClass('pull-left').addClass('pull-right').children('i').removeClass('fa-angle-double-right').addClass('fa-angle-double-left');
	}
  	else
	{
	  hint_div.fadeOut(100);
	  jQuery('#hintbtn'+post_id).removeClass('pull-right').addClass('pull-left').children('i').removeClass('fa-angle-double-left').addClass('fa-angle-double-right');
	}
}
function jsrunner_format_results(testcases,success_message,post_id)
{
	var successes=0;
	var first_mistake=-1;
	var display={};
	for (var i = 0; i < testcases.length; ++i)
	{
		if (JSON.stringify(testcases[i].result)==JSON.stringify(testcases[i].output))
			successes++;
		else
		  first_mistake=(first_mistake<0?i:first_mistake);
	}
	var grade=(100*successes/testcases.length);
	var retVal='<div class="progress"><div class="progress-bar" role="progressbar" aria-valuenow="'+grade+'" aria-valuemin="0" aria-valuemax="100" style="width: '+grade+'%;"><span class="sr-only">'+grade+'%</span></div></div>';
	if (first_mistake<0)
	{
		retVal+='<div class="alert alert-success">';
		retVal+=success_message;
		if (post_id>0)
			retVal+='<p><button class="btn btn-info btn-lg pull-right" data-toggle="modal" data-target="#modal'+post_id+'"><i class="fa fa-check"></i> Submit Your Solution</button></p>';
		retVal+='</div>';
	}
	else
	{
		if (testcases[first_mistake]['display']!=undefined)
		{
			display.input=testcases[first_mistake].display(testcases[first_mistake].input);
			display.output=testcases[first_mistake].display(testcases[first_mistake].output);
			display.result=testcases[first_mistake].display(testcases[first_mistake].result);
		}
		else
		{
			display.input=JSON.stringify(testcases[first_mistake].input);
			display.output=JSON.stringify(testcases[first_mistake].output);
			display.result=JSON.stringify(testcases[first_mistake].result);
		}
		retVal+='<div class="alert alert-warning">';
		retVal+= "[Test #"+(first_mistake+1)+"] Input was '"+display.input+"', Expected output is '"+display.output+"', But was '"+display.result+"'";
		retVal+='</div>';
	}
	return retVal;
}
function jsrunner_init(post_id,ajax_url,show_submit)
{
	var obj_Editor = ace.edit("edit"+post_id);
	obj_Editor.setTheme("ace/theme/textmate");
	obj_Editor.session.setMode("ace/mode/javascript");
	var obj_Result=jQuery('#result'+post_id);
	var obj_Error=jQuery('#error'+post_id);
	var obj_RunButton=jQuery('#run'+post_id);
	var obj_TestCases=jQuery('#testcases'+post_id);
	var obj_ApplyButton=jQuery('#apply'+post_id);
	
	jsrunner_notify(post_id,ajax_url,'view','');
	
	var success_message=obj_Result.html();
	obj_Result.html('');
	
	obj_TestCases.hide();
	var testcases=jQuery.map(obj_TestCases.children(),function (obj) {
		return JSON.parse(jQuery(obj).html(),function (k,v) {
				if (!isNaN(parseFloat(v)) && isFinite(v)) 
					return v;
				if ((k!='') && (window[k] != undefined))
					return window[k];
				if ((v.toString().indexOf('jsrunner_')==0) && (window[v.toString()] != undefined))
					return window[v.toString()];
				return v;
		});
	});
	
	obj_RunButton.on('click',function()
	{
		jsrunner_notify(post_id,ajax_url,'attempt','');
		var code=obj_Editor.getSession().getValue();
		code='function run(input) {"use strict";var output;'+code+'return output;}'
		obj_Error.html('');
	  	obj_Error.removeClass('alert-danger');
		obj_Result.html('');
		try
		{
			eval (code);
			for (var i = 0; i < testcases.length; ++i)
			{
				var code_start = new Date().getMilliseconds();
				testcases[i].result=run (testcases[i].input);
				var code_end = new Date().getMilliseconds();
				testcases[i].time=code_end - code_start;
			}
		}
		catch (e)
		{
			var err = e.constructor(e.message);
			err.lineNumber = e.lineNumber - err.lineNumber+12;
			obj_Error.html('Error in Line ('+err.lineNumber+'):\n'+err.message);
		  	obj_Error.addClass('alert-danger');
		}
		if (!obj_Error.hasClass('alert-danger'))
		{
			if (show_submit)
				obj_Result.html(jsrunner_format_results(testcases,success_message,post_id));
			else
				obj_Result.html(jsrunner_format_results(testcases,success_message,0));
			if (jsrunner_checkall(testcases))
				jsrunner_notify(post_id,ajax_url,'solved',code);
		}
	});
	
	obj_ApplyButton.on('click',function()
	{
		var application_details= ''+jQuery('#linkedin'+post_id).val()+"\n"+jQuery('#notes'+post_id).val();
		jsrunner_notify(post_id,ajax_url,'apply',application_details);
	});
}