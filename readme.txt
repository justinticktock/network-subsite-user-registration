=== Plugin Name ===
Contributors: justinticktock, keycapability
Tags: signup, register, user, mu, multisite, network, multi-network, login, user registration
Requires at least: 4.7
Tested up to: 6.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allow the public to register user accounts on Subsites within a Network (MultiSite) installation.

== Description ==

The 'Network Subsite User Registration' (NSUR) plugin removes the WordPress Multisite restriction that registration is on the Network main site, subsite Administrators can now allow user registration for their site only.

WordPress Network (Multisite) installations by default only allow user registration for the whole Network, e.g. users can only register for the main site and not the other sites on the network.  The ‘Network Subsite User Registration’ plugin allows local admins of sub-sites within the Network/Multisite the ability to enable user registration themselves for their site.

The role by default that a new user receives is 'subscriber', however, there is a setting which allows you to define a different initial role (per sub-site) that a user receives after registration.

@Developers - If you want to use your own template you can override the template used for the ../local-signup page by creating a template with the file 'page-signup.php' and add this to either the parent or child theme.

= Plugin site =

[https://justinandco.com/plugins/network-subsite-user-registration/](https://justinandco.com/plugins/network-subsite-user-registration/)

= GitHub - Development =

[https://github.com/justinticktock/network-subsite-user-registration](https://github.com/justinticktock/network-subsite-user-registration)



== Installation ==

1. Go to site dashboard ‘My Sites’ > ‘Network Admin’ > ‘Plugins’
2. Click on the ‘Add New’ button
3. search for ‘Network Subsite User Registration’ and install
4. Network Activate the plugin

To Manually install follow these steps..

1. [Download](https://wordpress.org/plugins/network-subsite-user-registration/) the plugin.zip file 
2. Go to site dashboard ‘My Sites’ > ‘Network Admin’ > ‘Plugins’
3. Click on the ‘Add New’ button
4. Click on the ‘Upload Plugin’ button
5. follow instructions to upload the zip file and install
6. Network Activate the plugin


Once installed to allow the public to register with a site within the Network..

1. Set Network wide user registration within the Dashboard goto [Network Admin] > Settings > under 'Registration Settings' configure to allow User accounts to be registered.
2. Within the Dashboard/Admin of each site that you wish to enable user registration to [Site Admin] > [Users] > Registration and enable the setting.
3. The Public will now be able to register and login with only the sites that you set within the step (2) above.


== Frequently Asked Questions ==

Q1) In sub site user registration, I have found that users get redirected the registration page of main site to register, will this happen with this plugin?

A1) This plugin will give the user the experience of remaining on the subsite that they are registering on.  The activation of the new user does continue to be on the Network but this happens behind the scenes all they will see is they belong to the subsite.  If you register on the site at <a href="https://justinandco.com/demo/">justinandco.com/demo/</a> you'll see it in action you will be given access as subscriber to the demo subsite only and not to the Network Main site "justinandco.com.  If you wish to see it in action for a second sub-site on the same Network (optionally logout first and..) register also on the site at <a href="https://justinandco.com/demo2/">justinandco.com/demo2/</a> 

Q2) Currently standard multisite functionality when a user attempts to register an account on a second subsite they get a message saying that the username and email already exists even though in their minds they have never been to this site before.  Can the plugin handle registration on a second, third... subsite?

A2) Yes the plugin handles registration on more than one sub-site within the same network.  

So for the case where a user has already registered on subsite1 of the Network and they register on subsite2 with the same username and a different email address they will see the message "<em>Sorry, that username already exists!</em>".  

This is since the plugin uses the email address as the uniqueness and proof that it's the same user.  However, if they register on subsite2 using the same email address for registration then they are automatically given an account on subsite2.  Also once registered all subsites that they belong to on the Network are then listed on the screen (e.g. subsite1, subsite2 ...etc) as confirmation that they are now also a registered user on subsite2.


Note: If the username given for subsite2 registration is different from their subsite1 username then the username provided is ignored so that subsite1 and subsite2 have the same original username (which is associated with the email address already).  


=  I just can't seem to find a way to get SMTP working with this plugin, what can I do? =

This has been seen a few times in the forum, one proven solution is:
* use  [plugin wp-mail-smtp](https://wordpress.org/plugins/wp-mail-smtp/)
* Do NOT network activate SMTP and you don't need to add anything extra to the wp-config file


== Screenshots ==

1. The Settings Screen for user registration.
2. An example of Network Settings allowing users to be registered.
3. An example of Network Settings allowing both sites and users to be registered.

== Changelog ==

Change log is maintained on [the plugin website]( https://justinandco.com/plugins/network-subsite-user-registration-change-log/ "Network Subsite User Registration Change Log" )

== Upgrade Notice ==
