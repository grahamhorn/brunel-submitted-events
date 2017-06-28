<?php
/*
 * Plugin Name: Submitted Events
 * Description: Autogenerate events from submitted walk forms
 * Version: 1.2
 * Author: Graham S. Horn
 */
global $submitted_events_db_version;
$submitted_events_db_version = '1.2';

// install the database table
function submitted_events_install() {
	global $submitted_events_db_version;
	
	// Only update database on version update
	if ($submitted_events_db_version != get_option ( 'submitted_events_version' )) {
		
		global $wpdb;
		
		$table_name = $wpdb->prefix . "submitted_events";
		
		error_log ( 'Submitted Events Plugin activated', 0 );
		
		// This is a harsh way of changing the database - all previous data will be lost!
		// But dbDelta wasn't doing the updates as intended
		$wpdb->query( "DROP TABLE IF EXISTS " . $table_name );
		
		$charset_collate = $wpdb->get_charset_collate ();
		
		// Max field lengths assumed, e.g.
		// date will be YYYY-MM-DD
		// packedlunch should be Yes or No but could be blank
		// altmeetpttime could be : or HH:MM
		$sql = "CREATE TABLE " . $table_name . "(
  	id mediumint(9) NOT NULL AUTO_INCREMENT,
  	name VARCHAR(50) NOT NULL,
	date VARCHAR(10) NOT NULL, 
	title VARCHAR(150) NOT NULL,
	brunelgroup VARCHAR(30) NOT NULL,
	level VARCHAR(30) NOT NULL,
	length VARCHAR(30) NOT NULL,
	starttime VARCHAR(20) NOT NULL,
	meetpt VARCHAR(30) NOT NULL,
	altmeetpt VARCHAR(150) NOT NULL,
	altmeetgridref VARCHAR(20) NOT NULL,
	altmeetpttime VARCHAR(8) NOT NULL,
	altmeetcontactleader VARCHAR(4) NOT NULL,		
	mapurl VARCHAR(200) NOT NULL,
	email VARCHAR(50) NOT NULL,
	phone VARCHAR(30) NOT NULL,
	packedlunch VARCHAR(4) NOT NULL,
	postwalk VARCHAR(20) NOT NULL,
	description text NOT NULL,
	coleader VARCHAR(50) NOT NULL,
	coleaderphone VARCHAR(30) NOT NULL,		
	carshareorganiser VARCHAR(4) NOT NULL,	
	eventgenerated boolean NOT NULL DEFAULT FALSE,
  	PRIMARY KEY  (id)	
	) " . $charset_collate . ";";
		
		require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
		
		// This isn't allowing updates to the table - error table exists
		// see http://wordpress.stackexchange.com/questions/41293/dbdelta-failing-with-error-wordpress-database-error-table-wp-2-myplugin-alre
		dbDelta ( $sql );
		
		// WordPress Options Hooks
		update_option ( 'submitted_events_db_version', $submitted_events_db_version );
	}
}

register_activation_hook ( __FILE__, 'submitted_events_install' );

// function submitted_events_install_test_data() {
// 	global $wpdb;
	
// 	$table_name = $wpdb->prefix . "submitted_events";
	
// 	$wpdb->insert ( $table_name, array (
// 			'name' => 'test user',
// 			'starttime' => '10am',
// 			'date' => '2017-01-15',
// 			'packedlunch' => 'Yes',
// 			'description' => 'a short walk' 
// 	) );
// } 

// register_activation_hook( __FILE__, 'submitted_events_install_test_data' );


function submitted_events_update_db_check() {
	global $submitted_events_db_version;
	if (get_site_option ( 'submitted_events_db_version' ) != $submitted_events_db_version) {
		submitted_events_install ();
	}
}

add_action ( 'plugins_loaded', 'submitted_events_update_db_check' );

// helper function
function submitted_events_starts_with($haystack, $needle) {
	$length = strlen ( $needle );
	return (substr ( $haystack, 0, $length ) === $needle);
}

// Determine the type of event
function submitted_events_walk_level($submission) {
	if (submitted_events_starts_with ( $submission->level, '3 Boot' )) {
		return '3 Boot';
	} elseif (submitted_events_starts_with ( $submission->level, '2 Boot' )) {
		return '2 Boot';
	} elseif (submitted_events_starts_with ( $submission->level, '1 Boot' )) {
		return '1 Boot';
	} elseif (submitted_events_starts_with ( $submission->level, 'Social' )) {
		return 'Social';
	} elseif (submitted_events_starts_with ( $submission->level, 'Evening' )) {
		return 'Evening Walk';
	} else {
		return 'Other';
	}
}

// Create text for the group for the event
function submitted_events_group($submission, $level) {
	if (submitted_events_starts_with ( $submission->brunelgroup, 'Joint' )) {
		if ($level === 'Social' || $level === 'Other') {
			return 'Joint Event';
		} else {
			return 'Joint Walk';
		}
	} elseif (submitted_events_starts_with ( $submission->brunelgroup, '20' )) {
		return '20s & 30s';
	} elseif (submitted_events_starts_with ( $submission->brunelgroup, '40' )) {
		return 'Brunel Plus';
	} else {
		return 'Unknown';
	}
}

// determine which calendar(s) to put the event into
function submitted_events_calendar($submission) {
	if (submitted_events_starts_with ( $submission->brunelgroup, 'Joint' )) {
		return array (
				23,
				24 
		);
	} elseif (submitted_events_starts_with ( $submission->brunelgroup, '20' )) {
		return array (
				23 
		);
	} elseif (submitted_events_starts_with ( $submission->brunelgroup, '40' )) {
		return array (
				24 
		);
	} else {
		return array ();
	}
}

// Only call this function if we know the submission is a walk
function submitted_events_postwalk($submission) {
	if (submitted_events_starts_with ( $submission->postwalk, 'Public House' )) {
		return 'Pub to finish.';
	} elseif (submitted_events_starts_with ( $submission->postwalk, 'Tea Shop' )) {
		return 'After the walk we\'ll go to a tea shop.';
	} else {
		return '';
	}
}

// Create text about lunch
function submitted_events_packed_lunch($submission) {
	if (submitted_events_starts_with ( $submission->packedlunch, 'Yes' )) {
		return 'Bring a packed lunch. ';
	} else {
		return '';
	}
}

// Create text about lift share coordination
function submitted_events_car_share_organiser($submission) {
	if (submitted_events_starts_with($submission->carshareorganiser, 'Yes')) {
		return '<p>If you would be prepared to organise car shares on the day at the meeting point, please contact the walk leader.</p>';
	}
	else {
		return '';
	}
}

// get the details of the pending submissions
function submitted_events_get_pending_submissions() {
	global $wpdb;
	// Get all submissions that have not had events generated for them
	$table_name = $wpdb->prefix . "submitted_events";
	return $wpdb->get_results ( "SELECT * FROM " . $table_name . " WHERE eventgenerated = 0" );
}

// Create an event for each pending submission
function submitted_events_process_pending_submissions($pending_submissions) {
	foreach ( $pending_submissions as $submission ) {
		submitted_events_create_event ( $submission );
	}
	// tell the user how many events were created
	
	if (sizeof ( $pending_submissions ) == 0) {
		echo '<h2>No pending submissions - no events created.</h2>';
	} else {
		$events_created_text;
		if (sizeof ( $pending_submissions ) == 1) {
			$events_created_text = 'one new event';
		} else {
			$events_created_text = sizeof ( $pending_submissions ) . ' new events';
		}
		
		echo '<h2>Created ' . $events_created_text . ' - please edit and publish when ready.</h2>
		<p>Hopefully this has saved you some work!</p>';
	}
}

// only show the initial for the submitter's surname (assumes at most 2 words in the name)
function submitted_events_strip_surname($name) {
	$parts = explode ( ' ', $name );
	if (sizeof ( $parts ) > 1) {
		return $parts [0] . ' ' . substr ( $parts [1], 0, 1 );
	} else {
		return $name;
	}
}

// create a walk event
function submitted_events_create_walk($submission, $level) {
	// create event title
	// Note walk title might be empty
	$title = $submission->starttime;
	if (strlen ( $title ) > 0) {
		$title .= ' ';
	}
	$title .= $level;
	if (strlen ( $submission->title ) > 0) {
		$title .= ': ' . $submission->title;
	}
	$title .= ' (' . submitted_events_group ( $submission, $level ) . ')';
	
	$content = '';
	if (strlen ( $submission->length ) > 0) {
		$content = '<p>' . $submission->length . '</p>';
	}
	
	if (strlen ( $submission->description ) > 0) {
		$content .= '<p>' . $submission->description . '</p>';
	}
	
	$content .= '<p>' . submitted_events_packed_lunch ( $submission ) . submitted_events_postwalk ( $submission ) . '</p>';
	
	if (strlen ( $submission->coleader ) > 0) {
		$content .= '<p>Walk Leaders: ' . submitted_events_strip_surname ( $submission->name ); 
		$content .= ' (' . trim ( $submission->phone ) . ') and ';
		$content .= submitted_events_strip_surname ( $submission->coleader );
		$content .= ' (' . trim ( $submission->coleaderphone ) . ')</p>';
	} else {
		$content .= '<p>Walk Leader: ' . submitted_events_strip_surname ( $submission->name ) . ' (' . trim ( $submission->phone ) . ')</p>';
	}
	
	$content .= "<p>Meet: " . $submission->starttime . " at ";
	
	// if meeting point is not a normal one, then only the alt meeting point is to be used
	if (submitted_events_starts_with ( $submission->meetpt, '--' ) || submitted_events_starts_with ( $submission->meetpt, 'Other' )) {
		if (strlen ( $submission->altmeetpt ) > 0) {
			$content .= $submission->altmeetpt;
		}
		$content .= submitted_events_process_mapurl ( $submission, $content );
		$content .= '</p>';
	} else {
		$content .= $submission->meetpt . '</p>';
		$content .= submitted_events_car_share_organiser($submission);
		
		if (strlen ( $submission->altmeetpt ) > 0) {
			// append details for alternative meeting point
			$content .= '<p>Alternatively meet at ' . $submission->altmeetpttime . ' at ' . $submission->altmeetpt;
			
			if (submitted_events_starts_with ( $submission->altmeetcontactleader, 'Yes')) {
				$content .= '<br>Please contact the walk leader if you will be meeting them there.';
			}
			$content .= submitted_events_process_mapurl ( $submission );
			$content .= '</p>';
		}
	}
	
	submitted_events_insert_post ( $submission, $title, $content );
}

// Handle the supplied map url
function submitted_events_process_mapurl($submission) {
	if (strlen ( $submission->mapurl ) > 0) {
		if (submitted_events_starts_with ( $submission->mapurl, "http" )) {
			return '<br><a href=\"' . $submission->mapurl . '\">Map here</a>.';
		} else {
			return '<br>' . $submission->mapurl;
		}
	}
}

// create the event in the posts database
function submitted_events_insert_post($submission, $title, $content) {
	// post meta data needed
	$meta_data = array (
			'event-date' => $submission->date,
			'event-time' => $submission->starttime,
			'event-days' => 1,
			'event-repeat' => 0,
			'event-end' => 0 
	);
	
	$postarr = array (
			'post_title' => $title,
			'post_type' => 'event-list-cal',
			'post_content' => $content,
			'post_category' => submitted_events_calendar ( $submission ),
			'meta_input' => $meta_data 
	);
	
	$post_id = wp_insert_post ( $postarr );
	
	// echo ID of created event?
	echo '<p>Created event ID ' . $post_id . '</p>';
}

// Create a social or other event
function submitted_events_create_non_walk($submission, $level) {
	$title = $submission->starttime;
	if (strlen ( $title ) > 0) {
		$title .= ' ';
	}
	$title .= $submission->title . ' (' . submitted_events_group ( $submission, $level ) . ')';
	
	$content = '' . $submission->description . 'Host: ' . submitted_events_strip_surname ( $submission->name ) . ' ' . $submission->phone . '</p>';
	
	submitted_events_insert_post ( $submission, $title, $content );
}

// For each submission, create an event
function submitted_events_create_event($submission) {
	// first determine if this is a walk or not
	$level = submitted_events_walk_level ( $submission );
	if ($level === 'Social' || $level === 'Other') {
		submitted_events_create_non_walk ( $submission, $level );
	} else {
		submitted_events_create_walk ( $submission, $level );
	}
	
	// Set eventgenerated to TRUE in the database
	global $wpdb;
	$table_name = $wpdb->prefix . "submitted_events";
	$result = $wpdb->update ( $table_name, array (
			'eventgenerated' => 1 
	), array (
			'ID' => $submission->id 
	), array (
			'%d' 
	), array (
			'%d' 
	) );
	// echo $submission->id . ' result = ' . $result;
}

// Create the menu
function submitted_events_menu() {
	add_menu_page ( 'Submitted Events', 'Submitted Events', 'publish_posts', 'submitted_events', 'submitted_events_create_pending_submissions_page', 'dashicons-calendar', 22 );
	add_submenu_page ( 'submitted_events', 'Auto Generate Events from Submissions', 'Auto Generate', 'publish_posts', 'submitted_events_autogenerate', 'submitted_events_create_generation_page' );
	add_submenu_page ( 'submitted_events', 'Submitted Events', 'About', 'publish_posts', 'submitted_events_about', 'submitted_events_create_about_page' );
}

add_action ( 'admin_menu', 'submitted_events_menu' );

// create the About page
function submitted_events_create_about_page() {
	echo '
	<div class="wrap">
		<h2>How to use the Submitted Events plugin</h2>
		<p>Plugin created by Graham S. Horn (graham.s.horn@gmail.com) in November 2016.</p>
		<p>(Optional: If you click on <strong>Submitted Events</strong> you can see how many pending submissions there are.)</p>
		<p>Click on <strong>Auto Generate</strong> and Events will be automatically added for all pending submissions. These will have <code>draft</code> status. When you have finished editing the event you should click on the <strong>publish</strong> button to make the event visible (Previewing your changes is a good idea). You may want to switch to the visual editor if the generated HTML is hard to read.</p>
		<p>This plugin was written specifically for the Brunel <strong>Walk Submit Form</strong> in the Form Maker plugin (form version as of 5 November 2016); if this form is changed in any way then this plugin is likely to break. Contact the author for support.</p>
	</div>	
	';
}
// create the page to show pending submissions
function submitted_events_create_pending_submissions_page() {
	$pending_submissions = submitted_events_get_pending_submissions ();
	$num_pending = sizeof ( $pending_submissions );
	if ($num_pending == 0) {
		echo '<h2>No pending submissions</h2>';
	} elseif ($num_pending == 1) {
		echo '<h2>There is one pending submission</h2>';
	} else {
		echo '<h2>There are ' . $num_pending . ' pending submissions</h2>';
	}
}
// create the page that does the event generation
function submitted_events_create_generation_page() {
	$pending_submissions = submitted_events_get_pending_submissions ();
	submitted_events_process_pending_submissions ( $pending_submissions );
}

?>
