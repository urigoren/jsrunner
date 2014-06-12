JSRunner, made by Uri Goren
=============================
JSRunner is a tool to run, test, and edit javascript within a wordpress post.
It was developed with programming-riddles in mind.

Shortcodes
========================
1 [jsrunner]
Consider a string reversing function,
The shortcode can be used in two ways:
1.1 With attributes
[jsrunner test0_input='123' test0_output='321' test1_input='2232' test1_output='2322']
/*Sample JS Code*/
[/jsrunner]
1.2 With Custom Fields
Set the `jsrunner_php` custom field to be:
	$input=array('1234','56');
	$output=array_map('strrev',$input);

[jsrunner]
/*Sample JS Code*/
[/jsrunner]

* [hint]
A shortcode that hides its content from unregistered users
[hint]use the force[/hint]
will be displayed only to registered users, unregistered users will be asked to login or register

Custom Fields
=======================
The plugin saves the following information on the post's and user's meta data
2.1 Post meta
jsrunner_view = a comma delimited string of user ids that viewed the code
jsrunner_attempt = a comma delimited string of user ids that attempted to solve the code
jsrunner_solved = a comma delimited string of user ids that solved the code
2.2 User meta
jsrunner_view = a comma delimited string of post ids that the user viewed
jsrunner_attempt = a comma delimited string of post ids that the user attempted to solve
jsrunner_solved = a comma delimited string of post ids that the user solved