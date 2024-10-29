<?php
/**
 * Plugin Name: Achievement Shortcode Add-On for GamiPress
 * Plugin URI: https://wordpress.org/plugins/achievement-shortcode-add-on-for-gamipress
 * Description: This GamiPress Add-on adds a shortcode to show or hide content depending on the user having earned a specific achievement
 * Tags: gamipress, restrict, shortcode
 * Author: konnektiv
 * Version: 1.0.0
 * Author URI: https://konnektiv.de/
 * License: GNU AGPLv3
 * Text Domain: achievement-shortcode-for-gamipress
 */
/*
 * Copyright © 2012 LearningTimes, LLC; Konnektiv
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General
 * Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/agpl-3.0.html>;.
*/

class GamiPress_Achievement_Shortcode {

	function __construct() {

		// Define plugin constants
		$this->basename       = plugin_basename( __FILE__ );
		$this->directory_path = plugin_dir_path( __FILE__ );
		$this->directory_url  = plugin_dir_url(  __FILE__ );

		// Load translations
		load_plugin_textdomain( 'achievement-shortcode-for-gamipress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// If GamiPress is unavailable, deactivate our plugin
		add_action( 'admin_notices', array( $this, 'maybe_disable_plugin' ) );
		add_action( 'plugins_loaded', array( $this, 'actions' ) );

	}

	public function actions() {
		if ( $this->meets_requirements() ) {
			add_action( 'init', array( $this, 'register_gamipress_shortcodes' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 99 );
		}
	}

	public function register_gamipress_shortcodes() {
		gamipress_register_shortcode('user_earned_achievement', array(
			'name'            => __( 'User earned achievement', 'achievement-shortcode-for-gamipress' ),
			'description'     => __( 'Show or hide content depending on the user having earned a specific achievement.', 'achievement-shortcode-for-gamipress' ),
			'output_callback' => array( $this, 'shortcode' ),
			'fields'     	  => array(
				'id' => array(
					'name'        => __( 'Achievement ID', 'achievement-shortcode-for-gamipress' ),
					'description' => __( 'The ID of the achievement the user must have earned.', 'achievement-shortcode-for-gamipress' ),
					'type'	      => 'select',
					'classes'     => 'gamipress-post-selector',
					'attributes'  => array(
						'data-post-type'   => implode( ',',  gamipress_get_achievement_types_slugs() ),
						'data-placeholder' => __( 'Select an achievement', 'gamipress' ),
					),
					'default'     => '',
					'options_cb'  => 'gamipress_options_cb_posts'
				),
				'before' => array(
					'name'        => __( 'Date', 'achievement-shortcode-for-gamipress' ),
					'description' => __( 'Date before the achievement must have been earned in the form Y/m/d (optional).', 'achievement-shortcode-for-gamipress' ),
					'type'        => 'text_date',
					'date_format' => 'Y/m/d',
				),
				'not' => array(
					'name'        => __( 'Achievement not earned', 'achievement-shortcode-for-gamipress' ),
					'description' => __( 'Specify to show content if the user has NOT yet earned the achievement.', 'achievement-shortcode-for-gamipress' ),
					'type' 	      => 'checkbox',
					'classes'     => 'gamipress-switch',
				),
			),
		) );
	}

	/**
	 * Enqueue and localize relevant admin_scripts.
	 *
	 * @since  1.0.0
	 */
	public function admin_scripts() {
		wp_enqueue_script( 'rangyinputs-jquery', $this->directory_url . 'js/rangyinputs-jquery-src.js', array( 'jquery' ), '', true );
		wp_enqueue_script( 'gamipress-achievement-shortcode-embed', $this->directory_url . 'js/achievement-shortcode-embed.js', array( 'rangyinputs-jquery' ), '', true );
	}

	public function shortcode( $atts, $content = null ) {
		$atts = shortcode_atts( array(
			'id' 	 => false,    // achievement
			'before' => false,
			'not'	 => false,
		), $atts );

		$achievement	= $atts['id'];
		$before		= $atts['before'];
		$not 		= $atts['not'] === 'true' || $atts['not'] === '1' || $atts['not'] === 'yes';

		$user_id = get_current_user_id();
		$before  = $before ? DateTime::createFromFormat( 'Y/m/dHis', "{$before}235959" ) : false;

		//error_log("Before: " . )
		$user_has_achievement = false;

		if ( $user_id && $achievement ) {
			$earned_achievements = gamipress_get_user_achievements(
				array( 'user_id' => absint( $user_id ),
					   'achievement_id' =>  absint( intval( $achievement ) ) ) );

			if ( $before && is_array( $earned_achievements ) && ! empty( $earned_achievements ) ) {

				foreach ( $earned_achievements as $key => $achievement ) {
					// Drop any achievements after our before timestamp
					if ( $before->getTimestamp() < $achievement->date_earned )
						unset( $earned_achievements[$key] );
				}
			}

			$user_has_achievement = ! empty( $earned_achievements );
			$user_has_achievement = apply_filters( 'gamipress_has_user_earned_achievement', $user_has_achievement, $achievement, $user_id );
		}

		$return = '';

		if ( ! $achievement ) {
			$return = '<div class="error">' . __( 'You have to specify a valid achievement id in the "id" parameter!', 'achievement-shortcode-for-gamipress' ) . '</div>';
		} elseif ( ( ! $not && $user_has_achievement ) || ( $not && ! $user_has_achievement ) ) {
			$return = do_shortcode( $content );
		}

		return $return;
	}

	/**
	 * Check if GamiPress is available
	 *
	 * @since  1.0.0
	 * @return bool True if GamiPress is available, false otherwise
	 */
	public function meets_requirements() {

		if ( class_exists( 'GamiPress' ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Generate a custom error message and deactivates the plugin if we don't meet requirements
	 *
	 * @since 1.0.0
	 */
	public function maybe_disable_plugin() {
		if ( ! $this->meets_requirements() ) {
			// Display our error
			echo '<div id="message" class="error">';
			echo '<p>' . sprintf( __( 'Achievement Shortcode Add-On for GamiPress requires GamiPress and has been <a href="%s">deactivated</a>. Please install and activate GamiPress and then reactivate this plugin.', 'achievement-shortcode-for-gamipress' ), admin_url( 'plugins.php' ) ) . '</p>';
			echo '</div>';

			// Deactivate our plugin
			deactivate_plugins( $this->basename );
		}
	}

}

new GamiPress_Achievement_Shortcode();
