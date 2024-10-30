<?php
/*
Plugin Name: LUL Me
Plugin URI: https://lockunlock.me/lulme-plugin-wordpress/
Description: Earn money, When any person unlock or reveal your content (e.g., Part of blog post, YouTube video, etc)
Version: 1.0.1
Author: Arun Kumar
Author URI: https://lockunlock.me/
Text Domain: lul-me
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} 

function lul_me_remove_extra_data( $data, $post, $context ) {
  if ( $context === 'view' || is_wp_error( $data ) ) {
    return $data;
  }
	
	unset( $data->data ['content'] );
	unset( $data->data ['excerpt'] );

  return $data;
}
add_filter( 'rest_prepare_product', 'lul_me_remove_extra_data', 12, 3 );

add_filter( 'the_content_feed', '__return_empty_string' );

add_filter( 'the_excerpt_rss', '__return_empty_string' );

function lul_me_ppv_shortcode( $atts , $content = null ) {
	
$nonce_s = wp_create_nonce( 'check-oiid-'.get_the_ID() );

if( !wp_verify_nonce( $nonce_s, 'check-oiid-'.get_the_ID() ) ) return;	

	$atts = shortcode_atts(
		array(
			'price' => '',
		),
		$atts,
		'ppv'
	);

if( !empty( get_post_meta( get_the_ID(), '_ppv_buyid', true ) ) ){	
	$ppv_buyid = get_post_meta( get_the_ID(), '_ppv_buyid', true );			
}	
	
if( !empty( $_POST['oiid'] ) ){	

	$oiid = wp_remote_post( 'https://lockunlock.me/api/check-purchase/', array(
		'method'      => 'POST',
		'timeout'     => 45,
		'headers'     => array(),	
		'sslverify'   => false,
		'body'		  =>	array(
			'oiid'    => sanitize_text_field( $_POST['oiid'] ),
		),
		'cookies'     => array(),	
	) );	

	$oiid = json_decode($oiid['body'], true);
	
	if( $oiid['oiid'] == $ppv_buyid && wp_get_raw_referer() == $oiid['ref'] ){	
	
	ob_start();

		return '<div class="ppv_content"><p><span style="color:red;">&check; Thank you for your purchase!</span> &bull; <a style="color:green;" target="_blank" href="https://lockunlock.me/purchased-products/">View purchased content</a></p><br/>'.do_shortcode( $content ).'</div><style>.ppv_content{ border: 1px solid #e6e6e6;border-style:dashed; } .ppv_content > :not(iframe):not(figure):not(img){ padding: 0 8px 0 8px; } .ppv_content br:first-of-type { display: none !important; }</style>';
	
	return ob_get_clean();
	
	} else{

		return '<div style="text-align:center;border:1px solid #e6e6e6;padding:8px;max-width:480px;margin:1em auto 1em auto;"><p>You need to purchase to see hidden premium content!</p><p><a class="button alt" href="https://lockunlock.me/checkout/?add-to-cart='.$ppv_buyid.'">Buy ppv item</a></p><p style="font-size:small;margin-top:8px;"><strong>Powered by:</strong> LockUnlock.ME</p></div>';		
		
	}
	
} else{
	return '<div style="text-align:center;border:1px solid #e6e6e6;padding:8px;max-width:480px;margin:1em auto 1em auto;"><p>You need to purchase to see hidden premium content!</p><p><a class="button alt" href="https://lockunlock.me/checkout/?add-to-cart='.$ppv_buyid.'">Buy ppv item</a></p><p style="font-size:small;margin-top:8px;"><strong>Powered by:</strong> LockUnlock.ME</p></div>';
}	
	
}
add_shortcode( 'ppv', 'lul_me_ppv_shortcode' );

add_filter( 'user_contactmethods', 'lul_me_modify_user_contact_methods' ); 
function lul_me_modify_user_contact_methods( $methods ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }	
	
	$methods['lul_apikey']   = __( 'LUL ME\'s API KEY:'   );
	return $methods;
}

function lul_me_field_placement_js() {
    $screen = get_current_screen();
    if ( ! current_user_can( 'manage_options' ) && $screen->id != "profile" && $screen->id != "user-edit" ) 
        return;
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            field = $('.user-lul_apikey-wrap').remove();
            field.insertBefore('.user-rich-editing-wrap');
        });
    </script>
    <?php
}
add_action( 'admin_head', 'lul_me_field_placement_js' );

add_action('save_post', 'lul_me_set_post_stuff', 10, 2);
function lul_me_set_post_stuff( $post_id, $post ) {
	
	$nonce = wp_create_nonce( 'check-update-meta-'.$post_id );
	
	if( ! wp_verify_nonce( $nonce, 'check-update-meta-'.$post_id ) ) return;
	
	if ( ! current_user_can( 'manage_options' ) && 'post' !== $post->post_type ) {
        return;
    }
	
	$shortcode_pattern = get_shortcode_regex( ['ppv'] );
	if ( preg_match_all( '/' . $shortcode_pattern . '/s', $post->post_content, $m ) ) {
		foreach( $m[3] as $atts_string ) {
			$atts = shortcode_parse_atts( $atts_string );
		}
	}	

	if( !empty( $atts['price'] ) ){	
		update_post_meta( $post_id, '_ppv_price', $atts['price'] );
	}
	
	if( !empty( get_post_meta( $post_id, '_ppv_price', true ) ) ){	
		$ppv_price = get_post_meta( $post_id, '_ppv_price', true );
	}
		
	if( !empty( get_the_author_meta( 'lul_apikey', $post->post_author ) ) ){
		$lul_apikey = get_the_author_meta( 'lul_apikey', $post->post_author );
	}	
	
	if( $ppv_price && $lul_apikey ){
		
		$ppv_title = $post->post_title;
		
		$remote_permalink = get_permalink( $post_id );
		
			if ( ! get_post_meta( $post_id, '_first_publish', true ) ) {	
  
			$ppv_buyid = wp_remote_post( 'https://lockunlock.me/api/publish/', array(
			'method'      => 'POST',
			'timeout'     => 45,
			'headers'     => array(),	
			'sslverify' => false,
			'body'	=>	array(
				'lul_apikey' => $lul_apikey,
				'ppv_price' => $ppv_price,
				'ppv_title' => $ppv_title,
				'remote_permalink' => $remote_permalink
			),
			'cookies'     => array()
			) );
				
			update_post_meta( $post_id, '_ppv_buyid', $ppv_buyid['body'] );
				
			update_post_meta( $post_id, '_first_publish', 'done' );
			
			} else{

					$ppv_id = get_post_meta( $post_id, '_ppv_buyid', true );
					
					$ppv_store = wp_remote_post( 'https://lockunlock.me/api/update/', array(
					'method'      => 'POST',
					'timeout'     => 45,
					'headers'     => array(),	
					'sslverify' => false,
					'body'	=>	array(
						'lul_apikey' => $lul_apikey,
						'iid' => $ppv_id,
						'ppv_price' => $ppv_price,
						'ppv_title' => $ppv_title,
						'remote_permalink' => $remote_permalink
					),
					'cookies'     => array()
					) );
			}
		
	}
	
}
