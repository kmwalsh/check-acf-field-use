<?php

/**
 * Check ACF Field Use
 *
 * Plugin Name: Check ACF Field Use
 * Plugin URI:  https://github.com/kmwalsh/check-acf-field-use/
 * Description: Check how many times an ACF field is used across your site, get useful data, post links, etc.
 * Version:     1.0.0
 * Author:      KMW
 * Author URI:  https://github.com/kmwalsh/check-acf-field-use/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: check-acf-field-use
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation. You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * @link https://github.com/bueltge/WordPress-Admin-Style Thank you.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

class CheckACFFieldUse {
	
	/**
	 * Constructor function.
	 * 
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_post_check_acf_page_use', [ $this, 'form_action' ] );
	}

	/**
	 * Adds an admin menu.
	 * 
	 * @return void
	 */
	public function add_menu_page() {
		add_menu_page(
			'Check ACF Field Use',
			'Check ACF Field Use',
			'manage_options',
			'check-acf-field-use',
			[ $this, 'check_acf_field_use_page' ],
			'dashicons-list-view',
			99
		);
	}

	/**
	 * Warning for ACF being inactive.
	 * 
	 * @return void
	 */
	public static function no_acf_found() {
			if ( ! function_exists( 'get_field' ) ) : ?>
				<div class="notice notice-warning inline">
				<p>
					<?php echo esc_html_e( 'It doesn\'t appear Advanced Custom Fields is activated. This plugin can still show you data from your database created with ACF, but if ACF is inactive, it may not be in active use on the front end of your site.', 'check-acf-field-use' );
					?>
				</p>
			</div>
			<?php endif;
	}

	/**
	 * Form action for ACF.
	 * 
	 * @return void
	 */
	public function form_action() {

		$url = parse_url( wp_get_referer() );
		parse_str( $url['query'], $query );
		unset($query['no-field']);
		unset($query['field']);
		
		if( isset( $_POST['field'] ) && ( ! empty( $_POST['field'] ) ) ) :
			$query['field'] = sanitize_key( $_POST['field'] );
		else :
			$query['no-field'] = true;
		endif;

		$http_query = http_build_query( $query );
		wp_safe_redirect( esc_url( untrailingslashit( home_url() ) . $url['path'] . '?' . $http_query ) );
		exit();
	}


	/**
	 * Admin page for checking an ACF field use.
	 * 
	 * @return void
	 */
	public static function check_acf_field_use_page() {
		?>
		<div id="wp_strip_image_metadata" class="wrap">
			<h1><?php esc_html_e( 'Check ACF Field Use', 'check-acf-field-use' ); ?></h1>
			
			<?php self::no_acf_found(); ?>

			<p>Check how many times an ACF field is used across your site, get useful data, post links, etc. Useful for building reports, verifying a field is not in public/active use before deleting it, or just seeing how widely used a given ACF field is on your site.</p>

			<hr>
			
			<p>Enter the <strong>name</strong> of the field you wish to check. Go into ACF, click into your field group, then get your name from the column. Don't use field keys.</p>

			<?php
				$nonce = wp_create_nonce( 'nonce' );
				$http_query = http_build_query( 
						[
							'nonce' => $nonce, 
							'page' => 'check-acf-field-use',
						]
				);
			?>
			
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) . '?' . $http_query ); ?>" method="post">
				<input type="hidden" name="action" value="check_acf_page_use" />
				<label for="acf-field-to-check">ACF Field to Check (<strong>name</strong> of field)</label>
				<input id="acf-field-to-check" name="field" type="text" value="<?php echo esc_attr( ( isset( $_REQUEST['field'] ) && ! empty( $_REQUEST['field'] ) ) ? sanitize_key( $_REQUEST['field'] ): null ); ?>" placeholder="ACF Field Name" class="regular-text" />
				<?php submit_button(
					'Submit', $type = 'primary', $name = 'submit', $wrap = FALSE, $other_attributes = NULL
				); ?>
			</form>
			<hr>

			<?php if( isset( $_GET['no-field'] ) && $_GET['no-field'] == 1 ) : ?>
				<div class="notice notice-alt notice-error inline">
				<p>You have to input a field. Without a field, there's nothing to check.</p>
			</div>
			<?php endif; ?>

			<?php if( isset( $_GET['field'] ) ) : ?>
				<?php self::show_field_data( sanitize_key( $_GET['field'] ) ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get the field data from ACF.
	 * 
	 * @return array $field_uses An array of ACF field uses across a site.
	 */
	public static function get_field_data( $field ) {
		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT DISTINCT ID, post_title, meta_value from `{$wpdb->base_prefix}posts`
			LEFT JOIN `{$wpdb->base_prefix}postmeta` ON `post_id` = `ID`
			WHERE `post_status` = 'publish' AND `meta_key` LIKE %s AND `meta_value` NOT LIKE %s AND meta_value IS NOT NULL AND meta_value <> '';",
			'%' . $wpdb->esc_like($field) . '%',
			'%field_%'
		);
		$field_uses = $wpdb->get_results( $sql );

		return $field_uses;
	}

	/**
	 * Show the field data from ACF.
	 * 
	 * @return void
	 */
	public static function show_field_data( $field_uses ) {
		$field_uses = self::get_field_data( $field_uses );
		?>
		<br/>
		<div id="col-container">

			<div id="col-left">

				<div class="col-wrap">
					<h2><?php esc_attr_e( 'Field Use Count', 'check-acf-field-use' ); ?></h2>
					<div class="inside">
						<?php $count = count( $field_uses ); ?>
						<p>Your field is used <?php echo $count; ?> times.</p>
					</div>

				</div>

			</div>

			<div id="col-right">

				<div class="col-wrap"></div>

			</div>

		</div>
		<hr>
		<table class="widefat">
			<thead>
			<tr>
				<th class="row-title"><strong><?php esc_attr_e( 'Post', 'check-acf-field-use' ); ?></strong></th>
				<th><strong><?php esc_attr_e( 'Post Permalink', 'check-acf-field-use' ); ?></strong></th>
				<th><strong><?php esc_attr_e( 'Post ID', 'check-acf-field-use' ); ?></strong></th>
				<th><strong><?php esc_attr_e( 'Field Value', 'check-acf-field-use' ); ?></strong></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $field_uses as $field_use ) :
				?>
				
					<tr>
						<td class="row-title"><label for="tablecell"><a href="<?php echo esc_url( get_edit_post_link( $field_use->ID) ); ?>"><?php echo esc_html( $field_use->post_title ); ?></a></label></td>
						<td><a href="<?php echo esc_url( get_permalink( $field_use->ID) ); ?>"><?php echo esc_url( get_permalink( $field_use->ID) ); ?></a></td>
						<td><?php echo esc_html( $field_use->ID ); ?></td>
						<td><?php echo esc_html( $field_use->meta_value ); ?></td>
					</tr>

				<?php
			endforeach;
			?>
			<tfoot>
			<tr>
				<th class="row-title"><strong><?php esc_attr_e( 'Post', 'check-acf-field-use' ); ?></strong></th>
				<th><strong><?php esc_attr_e( 'Post Permalink', 'check-acf-field-use' ); ?></strong></th>
				<th><strong><?php esc_attr_e( 'Post ID', 'check-acf-field-use' ); ?></strong></th>
				<th><strong><?php esc_attr_e( 'Field Value', 'check-acf-field-use' ); ?></strong></th>
			</tr>
			</tfoot>
			</table>

		<?php
	}

}

new CheckACFFieldUse();