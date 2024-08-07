<?php
if(!defined('ABSPATH')) exit;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Manage user columns
 */
add_filter( 'manage_users_columns', 'gwslau_user_table_new_column');
function gwslau_user_table_new_column( $column ) {
	$options = get_option( 'gwslau_loginas_options');
	if(!gwslau_user_conditional($options)){
		if(isset($options['gwslau_loginas_for']) && !empty($options['gwslau_loginas_for'])){
            if(in_array("users_page", $options['gwslau_loginas_for'])){
				$column['login-as'] = __('Login As','login-as-users');
			}
        }
	}
	return $column;
}

/**
 * Return content of custom user columns
 */
add_filter( 'manage_users_custom_column', 'gwslau_user_table_new_value', 10, 3 );
function gwslau_user_table_new_value( $val, $column_name, $user_id ) {
	switch ($column_name) {
		case 'login-as' :
			$options = get_option( 'gwslau_loginas_options' ,array());
			if(!gwslau_user_conditional($options)){
				$user_info = get_userdata($user_id);
				if($user_info) {
					if($user_id == get_current_user_id()){
						return __('This user','login-as-users');
					}
					$user_roles=$user_info->roles;
					if(in_array('administrator', $user_roles)){
						return __('Administrator user','login-as-users');
					}
					$user_name_type = $options['gwslau_loginas_name_show'];
					$user_name_data = gwslau_get_display_name($user_id, $user_name_type);

					$link = user_switcher::maybe_switch_url( $user_info );

					$links = sprintf('<a href="%s" class="page-title-action gwslau-login-as-btn" data-user-id="%d" data-admin-id="%d">%s</a>', $link, absint($user_id),absint(get_current_user_id()), __( 'Login as <span>'.$user_name_data.'</span>', 'login-as-users' ));
					return __($links,'login-as-users');
				}else{
					return __('User not exist','login-as-users');
				}
			}
		break;
	}
	return $val;
}

/**
 * Creates woocommerce order list custom column 
 */
add_filter( 'manage_edit-shop_order_columns', 'gwslau_order_table_new_column');
add_filter( 'woocommerce_shop_order_list_table_columns', 'gwslau_order_table_new_column');
function gwslau_order_table_new_column( $columns ) {
	$options = get_option( 'gwslau_loginas_options' );
	$reordered_columns = array();
	foreach( $columns as $key => $column){
		$reordered_columns[$key] = $column;
		if( $key ==  'order_status' ){
			if(!gwslau_user_conditional($options)){
				if(!gwslau_user_conditional($options)){
					if(isset($options['gwslau_loginas_for']) && !empty($options['gwslau_loginas_for'])){
						if(in_array("orders_page", $options['gwslau_loginas_for'])){
							$reordered_columns['Login-as'] = __( 'Login As','login-as-users');
						}
					}
				}
			}
		}
	}
	return $reordered_columns;
}

add_action( 'manage_shop_order_posts_custom_column' , 'gwslau_post_orders_column_content' );
function gwslau_post_orders_column_content($colname) {
	global $the_order; // the global order object

    if ($colname == 'Login-as') {
        gwslau_orders_column_content($the_order);
    }
}

add_action( 'woocommerce_shop_order_list_table_custom_column',  'gwslau_hpos_shop_order_column', 10, 2 );
function gwslau_hpos_shop_order_column($column, $order) {
	if ( 'Login-as' !== $column )		return;

    gwslau_orders_column_content($order);
}

/**
 * Returns content of woocommerce order list custom column 
 */
function gwslau_orders_column_content( $order ){
	$options = get_option( 'gwslau_loginas_options' );
	if(gwslau_user_conditional($options)){
		return;
	}
	
	// $order = wc_get_order($order);
	$user_id = $order->get_user_id();
	if($user_id != 0){
		if($user_id == get_current_user_id()){
			return _e('This user','login-as-users');
		}
		$user_info = get_userdata($user_id);
		if($user_info) {
			$user_roles=$user_info->roles;
			$user_roles = array_filter(array_map('trim', $user_roles));
			if(in_array('administrator', $user_roles)){
				return  _e('Administrator user','login-as-users');
			}
			$user_name_type = $options['gwslau_loginas_name_show'];
			$user_name_data = gwslau_get_display_name($user_id, $user_name_type);
			if(!empty($user_info)){
				$link = user_switcher::maybe_switch_url( $user_info );

				$links = sprintf('<a href="%s" class="page-title-action gwslau-login-as-btn" data-user-id="%d" data-admin-id="%d">%s</a>', $link, absint($user_id),absint(get_current_user_id()), __( 'Login as <span>'.$user_name_data.'</span>', 'login-as-users' ));
				return _e($links,'login-as-users');
			}
		}else{
			return _e('User not exist.');
		}
	}
	else{
		_e('Visitor','login-as-users');
	}
}

/**
 * Login as link inside user edit page
 */
add_action('personal_options', 'gwslau_add_personal_options');
function gwslau_add_personal_options( WP_User $user ) 
{
	$options = get_option( 'gwslau_loginas_options' );
	if(!gwslau_user_conditional($options)){
		if(isset($options['gwslau_loginas_for']) && !empty($options['gwslau_loginas_for'])){
			if(in_array("users_profile_page", $options['gwslau_loginas_for'])){
				if (get_current_user_id() != $user->ID && !empty($user->user_login))
				{
					$user_name_type = $options['gwslau_loginas_name_show'];
					$user_name_data = gwslau_get_display_name($user->ID, $user_name_type);
					
					$link = user_switcher::maybe_switch_url( $user );

					$links = sprintf('<a href="%s" class="page-title-action gwslau-login-as-btn" data-user-id="%d" data-admin-id="%d">%s</a>', $link, absint($user->ID),absint(get_current_user_id()), __( 'Login as <span>'.$user_name_data.'</span>', 'login-as-users' ));
					return _e($links,'login-as-users');
				}
			}
		}
	}
}

/**
 * Login as link inside woocommerce order edit page
 */
add_action('add_meta_boxes', 'gwslau_add_login_as_user_metabox');
function gwslau_add_login_as_user_metabox()
{
	$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id( 'shop-order' )
        : 'shop_order';

	$options = get_option( 'gwslau_loginas_options' );
	if(!gwslau_user_conditional($options)){
		if(isset($options['gwslau_loginas_for']) && !empty($options['gwslau_loginas_for'])){
			if(in_array("order_edit_page", $options['gwslau_loginas_for'])){
				add_meta_box( 'login_as_user_metabox', __( 'Login as User' ), 'gwslau_login_as_user_metabox', $screen, 'side', 'low');
			}
		}
	}
}

/**
 * Metabox for Login as link inside order edit page
 */
function gwslau_login_as_user_metabox($object){
	$order = is_a( $object, 'WP_Post' ) ? wc_get_order( $object->ID ) : $object;
	
	// $order = wc_get_order($post_or_order_object->ID);
	$user_id = $order->get_user_id();
	$options = get_option( 'gwslau_loginas_options' );
	if($user_id != 0){
		if($user_id == get_current_user_id()){
			return _e('This user','login-as-users');
		}
		$user_info = get_userdata($user_id);
		if($user_info) {
			$user_roles=$user_info->roles;
			if(in_array('administrator', $user_roles)){
				return _e('Administrator user','login-as-users');
			}
			if(!empty($user_info)){
				$user_name_type = $options['gwslau_loginas_name_show'];
				$user_name_data = gwslau_get_display_name($user_id, $user_name_type);
				$link = user_switcher::maybe_switch_url( $user_info );

				$links = sprintf('<a href="%s" class="page-title-action gwslau-login-as-btn" data-user-id="%d" data-admin-id="%d">%s</a>', $link, absint($user_id),absint(get_current_user_id()), __( 'Login as <span>'.$user_name_data.'</span>', 'login-as-users' ));
				return _e($links,'login-as-users');
			}
		}else{
			return _e('User not exist.','login-as-users');
		}
	}
	else{
		return _e('Visitor','login-as-users');
	}
}