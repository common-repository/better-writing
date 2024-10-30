<?php
/*
Plugin Name: Better Writing
Plugin URI: http://madebymike.com.au/better-writing-for-wordpress/
Description: Helps you write better with readability scores and preferred terms as you type.
Version: 1.4
Author: Mike Riethmuller
Author URI: http://madebymike.com.au
License: GPL2

*/

// Some definition stuff

define( 'BW_PUGIN_NAME', 'Better Writing');
define( 'BW_ROOT', dirname(__FILE__ ));
define( 'BW_URL', plugins_url( '/', __FILE__ ));
define( 'BW_VERSION', '1.3' );

// Some required stuff

require_once( BW_ROOT . '/class.readability.php');
require_once( BW_ROOT . '/bw_settings.php');


// Some keeping WP Db clean stuff

register_activation_hook(__FILE__, 'bw_activate');
register_deactivation_hook(__FILE__, 'bw_deactivate');
register_uninstall_hook(__FILE__, 'bw_uninstall');


function bw_activate() {
	add_option('readability_method', 'flesch');
	add_option('readability_column', 'readability_column');
	add_option('readability_target', 50);
	add_option('readability_required_pages', 'readability_required');
	add_option('readability_required_posts', 'readability_required');
	
}

function bw_deactivate() {
	// Being deactivated is so depressing.
	// Just wait, they might be back... maybe.
}

function bw_uninstall() {

	global $table_prefix, $wpdb;
	$table_name = $table_prefix . "options";
	$wpdb->query("DELETE FROM $table_name WHERE `option_name` LIKE 'readability_required_%'"); // To do: add interval to options
	
	global $table_prefix, $wpdb;
	$table_name = $table_prefix . "postmeta";
	$wpdb->query("DELETE FROM $table_name WHERE `meta_key` LIKE 'bw_readability_score_%'"); // To do: add interval to options
	
	delete_option('readability_method');
	delete_option('readability_column');
	delete_option('word_count_column');
	delete_option('readability_target');
	delete_option('write_better_preferred');
	delete_option('readability_required_pages');
	delete_option('readability_required_posts');
}

// Some scripts and styles enqueue stuff

add_action( 'admin_enqueue_scripts', 'bw_load_admin_scripts');
add_action( 'admin_enqueue_scripts', 'bw_load_admin_styles');

function bw_load_admin_styles() {
	wp_register_style( 'bw_admin_styles', plugins_url('bw_styles.css', __FILE__) );
	wp_enqueue_style( 'bw_admin_styles' );
}

function bw_load_admin_scripts() {
	wp_enqueue_script('jquery');
	wp_enqueue_script('bw_scripts', BW_URL .'bw_scripts.js', array( 'jquery' ));
}

// Some manage edit view stuff
// This section adds the readability score and word count columns, makes them sortable and displays according to user options

add_action( 'init', 'bw_add_readability_columns' );

function bw_add_readability_columns(){

	if(get_option('readability_required_posts')  == 'readability_required'){
		if ( get_option( 'word_count_column' ) ) {
			add_filter( 'manage_posts_columns', 'bw_add_word_count_column' );
			add_action( 'manage_posts_custom_column', 'bw_populate_word_count_column' );
			add_filter( 'manage_edit-post_sortable_columns', 'bw_sort_word_count_column' );
			add_filter( 'pre_get_posts', 'sort_column_by_word_count' );
		}
		if ( get_option( 'readability_column' ) ) {
			add_filter( 'manage_posts_columns', 'bw_add_readability_column' );
			add_action( 'manage_posts_custom_column', 'bw_populate_readability_column' );
			add_filter( 'manage_edit-post_sortable_columns', 'bw_sort_readability_column' );
			add_filter( 'pre_get_posts', 'sort_column_by_readability' );
		}
	}
	
	if(get_option('readability_required_pages')  == 'readability_required'){
		if ( get_option( 'word_count_column' ) ) {
			add_filter( 'manage_pages_columns', 'bw_add_word_count_column' );
			add_action( 'manage_pages_custom_column', 'bw_populate_word_count_column' );
			add_filter( 'manage_edit-page_sortable_columns', 'bw_sort_word_count_column' );
			add_filter( 'pre_get_posts', 'sort_column_by_word_count' );
		}
		if ( get_option( 'readability_column' ) ) {
			add_filter( 'manage_pages_columns', 'bw_add_readability_column' );
			add_action( 'manage_pages_custom_column', 'bw_populate_readability_column' );
			add_filter( 'manage_edit-page_sortable_columns', 'bw_sort_readability_column' );
			add_filter( 'pre_get_posts', 'sort_column_by_readability' );
		}
	}
	
	$args=array(
	  'public'   => true,
	  '_builtin' => false
	);
	$custom_post_types=get_post_types($args,'names'); 
	foreach ($custom_post_types as $custom_post_type ) {
		if(get_option('readability_required_'.$custom_post_type)  == 'readability_required'){
			if ( get_option( 'word_count_column' ) ) {
				add_filter( 'manage_edit-'.$custom_post_type.'_columns', 'bw_add_word_count_column' );
				add_action( 'manage_'. $custom_post_type .'_posts_custom_column', 'bw_populate_word_count_column' );
				add_filter( 'manage_edit-'. $custom_post_type .'_sortable_columns', 'bw_sort_word_count_column' );
				add_filter( 'pre_get_posts', 'sort_column_by_word_count' );
			}
			if ( get_option( 'readability_column' ) ) {
				add_filter( 'manage_edit-'.$custom_post_type.'_columns', 'bw_add_readability_column' );
				add_action( 'manage_'. $custom_post_type .'_posts_custom_column', 'bw_populate_readability_column' );
				add_filter( 'manage_edit-'. $custom_post_type .'_sortable_columns', 'bw_sort_readability_column' );
				add_filter( 'pre_get_posts', 'sort_column_by_readability' );
			}
		}
	}	
}

function bw_add_word_count_column($columns) {
	 $columns[ 'bw_word_count' ] = __( 'Word Count', 'bw_lang' );
	 return $columns;
}

function bw_populate_word_count_column($col) {
	global $post;
	if ( $col == 'bw_word_count' ) {
		$word_count = get_post_meta( $post->ID, 'bw_word_count', true );
		if ( !$word_count ) {
			// Need to re calculate
			$word_count = str_word_count($post->post_content);
			update_post_meta($post->ID, 'bw_word_count', $word_count);
		}
		echo $word_count;
	}
}

function bw_sort_word_count_column( $columns ) {
	$columns['bw_word_count'] = 'bw_word_count';
	return $columns;
}

function sort_column_by_word_count( $query ){
	if( ! is_admin() ) {
		return;  
	}
	$orderby = $query->get('orderby'); 
	if( 'bw_word_count' == $orderby ) {
		$query->set('meta_key','bw_word_count');
		$query->set('orderby','meta_value_num');  
	}
}
function sort_column_by_readability( $query ){
	if( ! is_admin() ) {
		return;  
	}
	$orderby = $query->get('orderby'); 
	$readability_method = get_option('readability_method');
	if( 'bw_readability' == $orderby ) {
		if($readability_method == 'flesch'){
			$query->set('meta_key','bw_readability_score_flesch'); 
		} elseif ($readability_method == 'coleman') {
			$query->set('meta_key','bw_readability_score_coleman'); 
		} elseif ($readability_method == 'gunning') {
			$query->set('meta_key','bw_readability_score_gunning'); 
		} elseif ($readability_method == 'smog') {
			$query->set('meta_key','bw_readability_score_smog'); 
		} elseif ($readability_method == 'ari') {
			$query->set('meta_key','bw_readability_score_ari'); 
		}
		$query->set('orderby','meta_value_num');  
	}
}

function bw_sort_readability_column( $columns ) {
	$columns['bw_readability'] = 'bw_readability';
	return $columns;
}
function bw_add_readability_column( $defaults ) {
	 $defaults[ 'bw_readability' ] = __( 'Readability', 'bw_lang' );
	 return $defaults;
}

function bw_populate_readability_column( $col ) {
	global $post;
	if ( $col == 'bw_readability' ) {
		$readability_target = get_option('readability_target'); 
		$readability_method = get_option('readability_method');
		$readability_score = get_post_meta( $post->ID, 'bw_readability_score_'.$readability_method, true );

		if ( !$readability_score ) {
			// Need to re calculate
			// Could time-out for large numbers of pages
			bw_save_post($post->ID);
			$readability_score = get_post_meta( $post->ID, 'bw_readability_score_'.$readability_method, true );
		}
		
		if(isset($readability_target) && $readability_target != 0){
			$readability_target_met = 'bw_target_not_met';
			
			if($readability_method == 'flesch'){
				if($readability_score >= $readability_target){
					$readability_target_met = 'bw_target_met';
				}
			} else{
				if($readability_score <= $readability_target){
					$readability_target_met = 'bw_target_met';
				}
			}
			echo '<span class="'.$readability_target_met.'">' . $readability_score . '</span>';
		} else {
			echo '<span>' . $readability_score . '</span>';
		}
	}
}

// That's the end of the readability column stuff

// Save post stuff
// This section ensures the readability score is calculated and added to post meta when a content item is saved.

add_action( 'save_post', 'bw_save_post' );

function bw_save_post( $id = false ) {
	if ( $id == false ) {
		global $post;
		if ( !$post->ID ) { return null; }
		$id = $post->ID;
	}
	$the_post = get_post( $id );
	if(get_option('readability_required_'.$the_post->post_type )  == 'readability_required' || ( $the_post->post_type == 'post' && get_option('readability_required_posts')  == 'readability_required') || ( $the_post->post_type == 'page' && get_option('readability_required_pages')  == 'readability_required') ){ //Lots of making sure we're only saving based on the users options

		$content = strip_shortcodes($the_post->post_content);
		
		$readability_method = get_option('readability_method');
		$readability = new TextStatistics;
		$readability_score = $readability->flesch_kincaid_reading_ease($content);
		update_post_meta( $id, 'bw_readability_score_flesch', $readability_score );
		$readability_score = $readability->coleman_liau_index($content);
		update_post_meta( $id, 'bw_readability_score_coleman', $readability_score );
		$readability_score = $readability->gunning_fog_score($content);
		update_post_meta( $id, 'bw_readability_score_gunning', $readability_score );
		$readability_score = $readability->smog_index($content); 
		update_post_meta( $id, 'bw_readability_score_smog', $readability_score );
		$readability_score = $readability->automated_readability_index($content);
		update_post_meta( $id, 'bw_readability_score_ari', $readability_score );
		
		$word_count = str_word_count($the_post->post_content);
		update_post_meta($id, 'bw_word_count', $word_count);
		
	}
}

// Some setting menu stuff
// This section configures settings groups and adds a sub menu item 'Readability' to the settings menu
// Display of the settings pages are controlled by the function 'bw_settings' 
// The 'bw_settings' function is in a separate file bw_settings.php that is included on line 23 of this file

add_action( 'admin_init', 'bw_register_settings' );
add_action( 'admin_menu', 'bw_create_menu' );

function bw_create_menu() {
	add_options_page(BW_PUGIN_NAME . __(' settings', 'bw_lang'), BW_PUGIN_NAME, 'manage_options',  'better-writing', 'bw_settings');
}

function bw_register_settings() {
	register_setting( 'bw-settings-group', 'readability_method' );
	register_setting( 'bw-settings-group', 'readability_target' );
	register_setting( 'bw-settings-group', 'readability_required_pages' );
	register_setting( 'bw-settings-group', 'readability_required_posts' );
	register_setting( 'bw-settings-group', 'readability_column' );
	register_setting( 'bw-settings-group', 'word_count_column' );

	$args=array(
	  'public'   => true,
	  '_builtin' => false
	); 
	$post_types=get_post_types($args,'names'); 
	foreach ($post_types as $post_type ) {
		register_setting( 'bw-settings-group', 'readability_required_'.$post_type );
	}
}
// That's the end of the setting menu section


// Some ajaxness stuff
// This section provides functions used by the tinyMCE plugin to update readability scores and show preferred terms while the user types

add_action( 'wp_ajax_get_preferred_terms', 'bw_ajax_get_preferred_terms' );
add_action( 'wp_ajax_get_score', 'bw_ajax_get_score' );

function bw_ajax_get_preferred_terms(){
	$pref_terms = get_option('write_better_preferred');
	if($pref_terms){
		foreach($pref_terms as $key => $option){
			if(($option['non_preferred'] != "" && $option['non_preferred'] != " ") && ($option['preferred'] != "" && $option['preferred'] != " ") ){
				$options[]=  $option;
			}
		}
	}
	echo json_encode($options);
	die();
}

function bw_ajax_get_score(){
	if(isset($_REQUEST['content'])){
		$content = $_REQUEST['content'];
	} else {
		die();
	}
	bw_readability_meta($content); // Returns the inner HTML of the Readability meta box
	die();
}
// That's the end of ajaxness

// Some TinyMCE stuff

add_action( 'init', 'bw_register_mce_plugin' );

function bw_register_mce_plugin(){
	if ( get_user_option( 'rich_editing' ) == 'true' ) {
		add_filter( 'tiny_mce_before_init', 'bw_mce_settings' );
		add_filter( 'mce_external_plugins', 'bw_add_mce_plugin' );
	}
}
function bw_mce_settings( $init_array ) {
	$init_array['bw_ajax_get_preferred_terms'] = admin_url( 'admin-ajax.php?action=get_preferred_terms' );
	$init_array['bw_ajax_get_score'] = admin_url( 'admin-ajax.php?action=get_score' );
	return $init_array;
}
function bw_add_mce_plugin( $plugin_array ) {
	$plugin_array['bwTerms'] = BW_URL . 'bw_mce_plugin.js';
	return $plugin_array;
}

// Some meta box stuff
//This section registers and generates the readability meta box based on user options

add_action( 'add_meta_boxes', 'bw_add_readability_meta' );

function bw_add_readability_meta() {
	global $post_type;

	if(get_option('readability_required_posts') == 'readability_required'){	
		add_meta_box('bw_readability_meta', __( "Readability", 'bw_lang' ), 'bw_readability_meta', 'post', "side", "high");
	}
	if(get_option('readability_required_pages') == 'readability_required'){	
		add_meta_box('bw_readability_meta', __( "Readability", 'bw_lang' ), 'bw_readability_meta', 'page', "side", "high");
	}
	$args=array(
		'public'   => true,
		'_builtin' => false
	);
	
	$custom_post_types=get_post_types($args,'names'); 
	
	foreach ($custom_post_types as $custom_post_type ) {
		if(get_option('readability_required_'.$custom_post_type)  == 'readability_required'){
			add_meta_box('bw_readability_meta', __( "Readability", 'bw_lang' ),'bw_readability_meta', $custom_post_type, "side", "high");
		}
	}
}

function bw_readability_meta($content) {

	$readability = new TextStatistics;
	
	if(isset($content->post_content)){
		$content = strip_shortcodes($content->post_content);
	} else {
		$content = strip_shortcodes($content);
	}

	$readability_method = get_option('readability_method'); 
	$readability_target = get_option('readability_target'); 

	$readability_flesch_score = $readability->flesch_kincaid_reading_ease($content);
	$readability_coleman_score = $readability->coleman_liau_index($content);
	$readability_gunning_score = $readability->gunning_fog_score($content);
	$readability_smog_score = $readability->smog_index($content); 
	$readability_ari_score = $readability->automated_readability_index($content); 
	$readability_average_grade = round((($readability_ari_score + $readability_smog_score + $readability_gunning_score + $readability_coleman_score)/4)*100)/100; 

	if($readability_method == 'flesch'){
		$readability_score = $readability_flesch_score;
		echo '<span class="bw_scoring_method">' . __('Using Flesch-Kincaid Reading Ease test', 'bw_lang') . '</span>';
	} elseif ($readability_method == 'coleman') {
		$readability_score = $readability_coleman_score;
		echo '<span class="bw_scoring_method">' . __('Using Coleman-Liau Index', 'bw_lang') . '</span>';
	} elseif ($readability_method == 'gunning') {
		$readability_score = $readability_gunning_score;
		echo '<span class="bw_scoring_method">' . __('Using Gunning-Fog Score', 'bw_lang') . '</span>';
	} elseif ($readability_method == 'smog') {
		$readability_score = $readability_smog_score;
		echo '<span class="bw_scoring_method">' . __('Using SMOG Index', 'bw_lang') . '</span>';
	} elseif ($readability_method == 'ari') {
		$readability_score = $readability_ari_score;
		echo '<span class="bw_scoring_method">' . __('Using Automated Readability Index', 'bw_lang') . '</span>';
	} else {
		$readability_score = __('Error finding score!', 'bw_lang');
		echo '<span class="bw_scoring_method">' . __('Something unexpected has happened!', 'bw_lang') . '</span>';
	}

	if($readability_method == 'flesch'){
		if($readability_score < 10){
			echo '<span class="bw_gauge_flesch bw_gauge_10">'.$readability_score.'</span>';
		} elseif ($readability_score < 20) {
			echo '<span class="bw_gauge_flesch bw_gauge_20">'.$readability_score.'</span>';
		} elseif ($readability_score < 30) {
			echo '<span class="bw_gauge_flesch bw_gauge_30">'.$readability_score.'</span>';
		} elseif ($readability_score < 40) {
			echo '<span class="bw_gauge_flesch bw_gauge_40">'.$readability_score.'</span>';
		} elseif ($readability_score < 50) {
			echo '<span class="bw_gauge_flesch bw_gauge_50">'.$readability_score.'</span>';
		} elseif ($readability_score < 60) {
			echo '<span class="bw_gauge_flesch bw_gauge_60">'.$readability_score.'</span>';
		} elseif ($readability_score < 70) {
			echo '<span class="bw_gauge_flesch bw_gauge_70">'.$readability_score.'</span>';
		} elseif ($readability_score < 80) {
			echo '<span class="bw_gauge_flesch bw_gauge_80">'.$readability_score.'</span>';
		} elseif ($readability_score < 90) {
			echo '<span class="bw_gauge_flesch bw_gauge_90">'.$readability_score.'</span>';
		}else{
			echo '<span class="bw_gauge_flesch bw_gauge_100">'.$readability_score.'</span>';
		}
	} else {
		if($readability_score < 1.2){
			echo '<span class="bw_gauge bw_gauge_10">'.$readability_score.'</span>';
		} elseif ($readability_score < 2.4) {
			echo '<span class="bw_gauge bw_gauge_20">'.$readability_score.'</span>';
		} elseif ($readability_score < 3.6) {
			echo '<span class="bw_gauge bw_gauge_30">'.$readability_score.'</span>';
		} elseif ($readability_score < 4.8) {
			echo '<span class="bw_gauge bw_gauge_40">'.$readability_score.'</span>';
		} elseif ($readability_score < 6) {
			echo '<span class="bw_gauge bw_gauge_50">'.$readability_score.'</span>';
		} elseif ($readability_score < 7.2) {
			echo '<span class="bw_gauge bw_gauge_60">'.$readability_score.'</span>';
		} elseif ($readability_score < 8.4) {
			echo '<span class="bw_gauge bw_gauge_70">'.$readability_score.'</span>';
		} elseif ($readability_score < 9.6) {
			echo '<span class="bw_gauge bw_gauge_80">'.$readability_score.'</span>';
		} elseif ($readability_score < 10.8) {
			echo '<span class="bw_gauge bw_gauge_90">'.$readability_score.'</span>';
		}else{
			echo '<span class="bw_gauge bw_gauge_100">'.$readability_score.'</span>';
		}
	}

	if(isset($readability_target) && $readability_target != 0){
		$readability_target_met = 'bw_target_not_met';
		
		if($readability_method == 'flesch'){
			if($readability_score >= $readability_target){
				$readability_target_met = 'bw_target_met';
			}
		} else{
			if($readability_score <= $readability_target){
				$readability_target_met = 'bw_target_met';
			}
		}
		echo '<p  class="bw_target_score">' . __('Your target score is: ', 'bw_lang') .' <span class="'.$readability_target_met.'">' . $readability_target. '</span></p>';
	}
	?>
	<div class="bw_more_stats"><span class="title">More stats</span> <span class="toggle">+</span>
		<div class="bw_toggle_panel">
			<p><?php _e('Word count: ', 'bw_lang'); echo  $readability->word_count($content);  ?></p>
			<p><?php _e('Sentence count: ', 'bw_lang'); echo  $readability->sentence_count($content);  ?></p>
			<p><?php _e('Avgerage words per sentence: ', 'bw_lang'); echo round($readability->average_words_per_sentence($content)*100)/100;  ?></p>
			<p><?php _e('Avgerage syllables per word: ', 'bw_lang'); echo  round($readability->average_syllables_per_word($content)*100)/100;  ?></p>
		</div>
		<div class="bw_toggle_panel">
		<?php
		echo '<hr>';
		echo '<ul>';
			echo '<li>' . __('Flesch-Kincaid Reading Ease: ', 'bw_lang') . $readability_flesch_score .'<li>';
		echo '</ul>';
		echo '<ul>';
			echo '<li>' . __('Gunning-Fog Score: ', 'bw_lang') . $readability_gunning_score .'<li>';
			echo '<li>' . __('Coleman-Liau Index: ', 'bw_lang') . $readability_coleman_score .'<li>';
			echo '<li>' . __('SMOG Index: ', 'bw_lang') . $readability_smog_score .'<li>';
			echo '<li>' . __('Automated Readability Index: ', 'bw_lang') . $readability_ari_score .'<li>';
			echo '<li>' . __('Average grade: ', 'bw_lang') . $readability_average_grade .'<li>';
		echo '</ul>';
		?>
		</div>
	</div>
	<?php
}

function bw_shortcode_score($atts, $content, $tag){
	global $post;
	if($tag == 'readability-score'){
		$readability_method = get_option('readability_method');
	}elseif($tag == 'readability-flesch'){
		$readability_method = 'flesch';
	}elseif($tag == 'readability-coleman'){
		$readability_method = 'coleman';
	}elseif($tag == 'readability-gunning'){
		$readability_method = 'gunning';
	}elseif($tag == 'readability-smog'){
		$readability_method = 'smog';
	}elseif($tag == 'readability-ari'){
		$readability_method = 'ari';
	}else{
		return;
	}
	$readability_score = get_post_meta( $post->ID, 'bw_readability_score_'.$readability_method, true );

	if ( !$readability_score ) {
		// Need to re calculate
		// Could time-out for large numbers of pages
		bw_save_post($post->ID);
		$readability_score = get_post_meta( $post->ID, 'bw_readability_score_'.$readability_method, true);
	}
	return $readability_score;
}

function bw_shortcode_method( ){
	$readability_method = get_option('readability_method');
	return $readability_method;
}
add_shortcode( 'readability-score', 'bw_shortcode_score' );
add_shortcode( 'readability-flesch', 'bw_shortcode_score' );
add_shortcode( 'readability-coleman', 'bw_shortcode_score' );
add_shortcode( 'readability-gunning', 'bw_shortcode_score' );
add_shortcode( 'readability-smog', 'bw_shortcode_score' );
add_shortcode( 'readability-ari', 'bw_shortcode_score' );
add_shortcode( 'readability-method', 'bw_shortcode_method' );

// That's the end of the shortcodes section and that's the my plugin!
?>
