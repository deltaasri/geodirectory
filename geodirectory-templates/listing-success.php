<?php
// call header
get_header(); 

###### WRAPPER OPEN ######
// this adds the opening html tags to the primary div, this required the closing tag below :: ($type='',$id='',$class='')
do_action( 'geodir_wrapper_open', 'success-page', 'geodir-wrapper','');

	###### TOP CONTENT ######
	// action called before the main content and the page specific content
	do_action('geodir_top_content', 'success-page');
	// template specific, this can add the sidebar top section and breadcrums
	do_action('geodir_success_before_main_content');
	// action called before the main content
	do_action('geodir_before_main_content', 'success-page');
	
			###### MAIN CONTENT WRAPPERS OPEN ######
			// this adds the opening html tags to the content div, this required the closing tag below :: ($type='',$id='',$class='')
			do_action( 'geodir_wrapper_content_open', 'success-page', 'geodir-wrapper-content','');
			
			
					###### MAIN CONTENT ######
					// this call the main page content
					geodir_get_template_part('preview','success'); 
					
		
			###### MAIN CONTENT WRAPPERS CLOSE ######
			// action called after the main content
			do_action('geodir_after_main_content');
			// this adds the closing html tags to the wrapper_content div :: ($type='')
			do_action( 'geodir_wrapper_content_close', 'details-page');
			
			
    
        ###### SIDEBAR ######
		do_action('geodir_detail_sidebar');
		
    
###### WRAPPER CLOSE ######	
// this adds the closing html tags to the wrapper div :: ($type='')
do_action( 'geodir_wrapper_close', 'success-page');

get_footer();   