<?php

/**
 * Widget Form.
 *
 * @package FormyChat
 * @since 1.0.0
 */

// Namespace .
namespace FormyChat\Publics;

// Exit if accessed directly.
defined('ABSPATH') || exit;

// User models.
use FormyChat\Models\Lead;

if ( ! class_exists(__NAMESPACE__ . '\WidgetForm') ) {
	/**
	 * Widget Form.
	 *
	 * @package FormyChat
	 * @since 1.0.0
	 */
	class WidgetForm extends \FormyChat\Base {


		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */
		public function actions() {
			add_action('wp_footer', [ $this, 'print_widgets' ]);
			add_action('template_redirect', [ $this, 'template_redirect' ]);

			// Extended hooks.
			add_action('formychat_widget_not_found', [ $this, 'widget_not_found' ], 10, 1);
			add_action('formychat_form_not_found', [ $this, 'form_not_found' ], 10, 3);

			add_action('formychat_before_form', [ $this, 'before_form' ], 10, 3);
			add_action('formychat_form_content', [ $this, 'form_content' ], 10, 3);
			add_action('formychat_after_form', [ $this, 'after_form' ], 10, 3);
			add_action('formychat_footer', [ $this, 'footer' ], 10, 3);

			// Form Shortcodes.
			add_action('formychat_form_cf7', [ $this, 'form_cf7' ], 10, 1);
			add_action('formychat_form_wpforms', [ $this, 'form_wpforms' ], 10, 1);
			add_action('formychat_form_gravity', [ $this, 'form_gravity' ], 10, 1);
			add_action('formychat_form_fluentform', [ $this, 'form_fluentform' ], 10, 1);
			add_action('formychat_form_forminator', [ $this, 'form_forminator' ], 10, 1);
			add_action('formychat_form_formidable', [ $this, 'form_formidable' ], 10, 1);
			add_action('formychat_form_ninja', [ $this, 'form_ninja' ], 10, 1);
		}

		/**
		 * Add contact form to footer.
		 *
		 * @return void
		 */
		public function print_widgets() {

			// Bail if FORMYCHAT_FORM is defined.
			if ( defined('FORMYCHAT_FORM_PAGE') ) {
				return;
			}

			echo '<div id="formychat-widgets"></div>';
		}

		/**
		 * Template redirect.
		 *
		 * @return void
		 */
		public function template_redirect() {

			if ( ! isset($_GET['formychat-form']) ) {
				return;
			}

			define('FORMYCHAT_FORM_PAGE', true);

			// If 'admin' found in query.
			if ( isset($_GET['admin']) ) {
				define('FORMYCHAT_FORM_ADMIN', true);
			}

			// WordPress head.
			wp_head();
			add_filter('show_admin_bar', [ $this, 'show_admin_bar' ]);

			// FormyChat Data.
			$form = isset($_GET['form']) ? sanitize_text_field(wp_unslash($_GET['form'])) : 'cf7';
			$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
			$widget_id = isset($_GET['widget_id']) ? intval($_GET['widget_id']) : 0;

			$widget = \FormyChat\Models\Widget::find($widget_id);

			// Widget not found.
			if ( ! $widget ) {
				do_action('formychat_widget_not_found', $widget_id);
				wp_footer();
				exit;
			}

			// Form not found.
			if ( ! $form_id ) {
				do_action('formychat_form_not_found', $form, $form_id, $widget);
				wp_footer();
				exit;
			}

			// FormyChat head.
			do_action('formychat_head', $form, $form_id, $widget);

			$number = isset($_GET['number']) ? sanitize_text_field(wp_unslash($_GET['number'])) : '';
			echo '<div class="formychat-custom-form" style="padding: 15px;"
			data-whatsapp="' . esc_attr($number) . '"
			data-widget="' . esc_attr($widget_id) . '"
			>';

			// Before form.
			do_action('formychat_before_form', $form, $form_id, $widget);

			// The form.
			do_action('formychat_form_content', $form, $form_id, $widget);

			// After form.
			do_action('formychat_after_form', $form, $form_id, $widget);

			echo '</div>';

			// Form footer.
			do_action('formychat_footer', $form, $form_id, $widget);

			// WordPress footer.
			wp_footer();
			exit;
		}

		/**
		 * Widget not found.
		 *
		 * @return void
		 */
		public function widget_not_found( $widget_id ) {
			echo wp_kses_post(
				apply_filters(
					'formychat_widget_not_found_content',
					'<h2>No widget found</h2>',
					$widget_id
				)
			);
		}

		/**
		 * Form not found.
		 *
		 * @return void
		 */
		public function form_not_found( $form, $form_id, $widget ) {
			echo wp_kses_post(
				apply_filters(
					'formychat_form_not_found_content',
					'<h2>No form found</h2>',
					$form,
					$form_id,
					$widget
				)
			);
		}

		/**
		 * Before form.
		 *
		 * @return void
		 */
		public function before_form( $form, $form_id, $widget ) {
			// Print header.
			if ( isset($_GET['header']) && ! empty($_GET['header']) ) {
				echo '<div class="formychat-header">';
				echo wp_kses_post(sanitize_text_field(wp_unslash($_GET['header'])));
				echo '</div>';
			} elseif ( ! empty($widget->config['form']['subtitle']) ) {
				echo '<div class="formychat-header">';
				echo wp_kses_post($widget->config['form']['subtitle']);
				echo '</div>';
			}
		}

		/**
		 * Form content.
		 *
		 * @return void
		 */
		public function form_content( $form, $form_id, $widget ) {

			$forms = \FormyChat\App::get_forms();

			// Bail if forms is empty.
			if ( empty($forms) ) {
				do_action('formychat_forms_not_found', $widget);
				return;
			}

			// Bail if form not found.
			if ( ! isset($forms[ $form ]) ) {
				do_action('formychat_form_not_found', $form, $form_id, $widget);
				return;
			}

			do_action("formychat_form_{$form}", $form_id, $widget);
		}

		/**
		 * After form.
		 *
		 * @return void
		 */
		public function after_form( $form, $form_id, $widget ) {
			// Print footer.
			if ( isset($_GET['footer']) && ! empty($_GET['footer']) ) {
				echo '<div class="formychat-footer">';
				echo wp_kses_post(sanitize_text_field(wp_unslash($_GET['footer'])));
				echo '</div>';
			} elseif ( ! empty($widget->config['form']['footer']) ) {
				echo '<div class="formychat-footer">';
				echo wp_kses_post($widget->config['form']['footer']);
				echo '</div>';
			}
		}

		/**
		 * Footer.
		 *
		 * @return void
		 */
		public function footer( $form ) {
			$this->enqueue_form_style($form);

			// Filter WP Dark Mode.
			add_filter('wp_dark_mode_is_excluded', '__return_true', 999999);
		}

		/**
		 * Enqueue form style.
		 *
		 * @return void
		 */
		public function enqueue_form_style( $form = 'cf7' ) {
			if ( file_exists(plugin_dir_path(FORMYCHAT_FILE) . "/public/css/forms/{$form}.css") ) {
				wp_enqueue_style("formychat-{$form}", FORMYCHAT_PUBLIC . "/css/forms/{$form}.css", []);
			}
		}

		/**
		 * Form CF7.
		 *
		 * @return void
		 */
		public function form_cf7( $form_id ) {
			echo do_shortcode(wp_sprintf('[contact-form-7 id="%d"]', $form_id));
		}

		/**
		 * Form WPForms.
		 *
		 * @return void
		 */
		public function form_wpforms( $form_id ) {
			echo do_shortcode(wp_sprintf('[wpforms id="%d"]', $form_id));
		}

		/**
		 * Form Gravity.
		 *
		 * @return void
		 */
		public function form_gravity( $form_id ) {
			echo do_shortcode(wp_sprintf('[gravityform id="%d" title=false description=false ajax=true]', $form_id));
		}

		/**
		 * Form FluentForm.
		 *
		 * @return void
		 */
		public function form_fluentform( $form_id ) {
			echo do_shortcode(wp_sprintf('[fluentform id="%d"]', $form_id));
		}

		/**
		 * Show admin bar.
		 *
		 * @return void
		 */
		public function show_admin_bar() {
			return apply_filters('formychat_show_admin_bar', false);
		}

		/**
		 * Form forminator.
		 *
		 * @return void
		 */
		public function form_forminator( $form_id ) {
			echo do_shortcode(wp_sprintf('[forminator_form id="%d"]', $form_id));
		}

		/**
		 * Form Formidable.
		 *
		 * @param int $form_id The ID of the form to display.
		 * @return void
		 */
		public function form_formidable( $form_id ) {
			echo do_shortcode(wp_sprintf('[formidable id="%d"]', $form_id));
		}

		/**
		 * Form Ninja.
		 *
		 * @return void
		 */
		public function form_ninja( $form_id ) {
			echo do_shortcode(wp_sprintf('[ninja_form id="%d"]', $form_id));
		}
	}

	// Run.
	WidgetForm::init();
}
