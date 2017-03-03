<?php
/*
Template Name: An old Custom WordPress Signup Page
*/

// ref http://www.tutorialstag.com/create-custom-wordpress-registration-page.html

/*
Template Name: Custom WordPress Signup Page
*/
require_once(ABSPATH . WPINC . '/registration.php');
global $wpdb, $user_ID;
//Check whether the user is already logged in
if (!$user_ID) {

	if($_POST){
		//We shall SQL escape all inputs
		$username = $wpdb->escape($_REQUEST['username']);
		if(empty($username)) {
			echo "User name should not be empty.";
			exit();
		}
		$email = $wpdb->escape($_REQUEST['email']);
		if(!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/", $email)) {
			echo "Please enter a valid email.";
			exit();
		}

			$random_password = wp_generate_password( 12, false );
			$status = wp_create_user( $username, $random_password, $email );
			if ( is_wp_error($status) )
				echo "Username already exists. Please try another one.";
			else {
				$from = get_option('admin_email');
		                $headers = 'From: '.$from . "\r\n";
		                $subject = "Registration successful";
		                $msg = "Registration successful.\nYour login details\nUsername: $username\nPassword: $random_password";
		                wp_mail( $email, $subject, $msg, $headers );
				echo "Please check your email for login details.";
			}

		exit();

	} else {
		get_header();
?>
<!-- <script src="http://code.jquery.com/jquery-1.4.4.js"></script> -->
<!-- Remove the comments if you are not using jQuery already in your theme -->
<div id="container">
<div id="content">
<?php if(get_option('users_can_register')) { 
//Check whether user registration is enabled by the administrator ?>

<h1><?php the_title(); ?></h1>
<div id="result"></div> <!-- To hold validation results -->
<form action="" method="post">
<label>Username</label>
<input type="text" name="username" class="text" value="" /><br />
<label>Email address</label>
<input type="text" name="email" class="text" value="" /> <br />
<input type="submit" id="submitbtn" name="submit" value="SignUp" />
</form>

<script type="text/javascript">
//<![CDATA[

$("#submitbtn").click(function() {

$('#result').html('<img src="<?php bloginfo('template_url') ?>/images/loader.gif" class="loader" />').fadeIn();
var input_data = $('#wp_signup_form').serialize();
$.ajax({
type: "POST",
url:  "",
data: input_data,
success: function(msg){
$('.loader').remove();
$('<div>').html(msg).appendTo('div#result').hide().fadeIn('slow');
}
});
return false;

});
//]]>
</script>

<?php } else echo "Registration is currently disabled. Please try again later."; ?>
</div>
</div>
<?php

    get_footer();
    
    
 } //end of if($_post)

} else {
	wp_redirect( home_url() ); exit;
}
?>
