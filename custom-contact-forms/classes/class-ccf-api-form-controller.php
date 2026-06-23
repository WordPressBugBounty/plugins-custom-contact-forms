<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_API_Form_Controller extends WP_REST_Controller {

	/**
	 * Array of field attributes with sanitization/escaping callbacks
	 *
	 * @var array
	 * @since 7.0
	 */
	protected $field_attribute_keys;

	/**
	 * Array of field choice attributes with sanitization/escaping callbacks
	 *
	 * @var array
	 * @since 7.0
	 */
	protected $choice_attribute_keys;

	/**
	 * Setup instance variables
	 *
	 * @since 7.0
	 */
	public function __construct() {
		$this->field_attribute_keys = apply_filters( 'ccf_field_attributes', array(
			'type' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'slug' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'placeholder' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'className' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'label' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
			'description' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
			'value' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
			'required' => array(
				'sanitize' => array( $this, 'boolval' ),
				'escape' => array( $this, 'boolval' ),
			),
			'showDate' => array(
				'sanitize' => array( $this, 'boolval' ),
				'escape' => array( $this, 'boolval' ),
			),
			'addressType' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'defaultCountry' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'defaultState' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'siteKey' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'secretKey' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'phoneFormat' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'emailConfirmation' => array(
				'sanitize' => array( $this, 'boolval' ),
				'escape' => array( $this, 'boolval' ),
			),
			'useValues' => array(
				'sanitize' => array( $this, 'boolval' ),
				'escape' => array( $this, 'boolval' ),
			),
			'showTime' => array(
				'sanitize' => array( $this, 'boolval' ),
				'escape' => array( $this, 'boolval' ),
			),
			'dateFormat' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
			'heading' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
			'subheading' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
			'html' => array(
				'sanitize' => 'wp_kses_post',
				'escape' => 'wp_kses_post',
			),
			'maxFileSize' => array(
				'sanitize' => 'intval',
				'escape' => 'intval',
			),
			'fileExtensions' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
			'conditionalsEnabled' => array(
				'sanitize' => array( $this, 'boolval' ),
				'escape' => array( $this, 'boolval' ),
			),
			'conditionalType' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
			'conditionalFieldsRequired' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
		) );

		$this->choice_attribute_keys = apply_filters( 'ccf_choice_attributes', array(
			'label' => array(
				'sanitize' => 'sanitize_text_field',
				'escape' => 'esc_html',
			),
			'value' => array(
				'sanitize' => 'esc_attr',
				'escape' => 'esc_attr',
			),
			'selected' => array(
				'sanitize' => array( $this, 'boolval' ),
				'escape' => array( $this, 'boolval' ),
			),
		) );
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 7.0
	 */
	public function register_routes() {
		$version = '1';
		$namespace = 'ccf/v' . $version;

		register_rest_route( $namespace, '/forms', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'            => array(),
			),
			array(
				'methods'         => WP_REST_Server::CREATABLE,
				'callback'        => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( true ),
			),
		) );

		register_rest_route( $namespace, '/forms/(?P<id>[\d]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => array(
				    	'default'      => 'view',
					),
				),
			),
			array(
				'methods'         => WP_REST_Server::EDITABLE,
				'callback'        => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( false ),
			),
			array(
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'     => array(
					'force'    => array(
				    	'default'      => false,
					),
				),
			),
		) );

		register_rest_route( $namespace, '/forms/(?P<id>[\d]+)/submissions', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_submissions' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => array(
				    	'default'      => 'view',
					),
				),
			),
		) );

		register_rest_route( $namespace, '/submissions/(?P<id>[\d]+)', array(
			array(
				'methods'         => WP_REST_Server::DELETABLE,
				'callback'        => array( $this, 'delete_submission' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'     => array(
					'force'    => array(
				    	'default'     => true,
					),
				),
			),
		) );

		register_rest_route( $namespace, '/forms/(?P<id>[\d]+)/fields', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_fields' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => array(
				    	'default'      => 'view',
					),
				),
			),
		) );
	}

	/**
	 * Create field choices and attach them to fields. Not an API route.
	 *
	 * @param array $choices
	 * @param int   $field_id
	 * @since 7.0
	 */
	public function _create_and_map_choices( $choices, $field_id ) {
		$new_choices = array();

		foreach ( $choices as $choice ) {
			if ( ! empty( $choice['label'] ) || ( isset( $choice['label'] ) && $choice['label'] === '0' ) ) {
				if ( empty( $choice['ID'] ) ) {
					$args = array(
						'post_title' => sanitize_text_field( $choice['label'] ) . '-' . (int) $field_id,
						'post_status' => 'publish',
						'post_parent' => (int) $field_id,
						'post_type' => 'ccf_choice',
					);

					$choice_id = wp_insert_post( $args );
				} else {
					$choice_id = (int) $choice['ID'];
				}

				if ( ! is_wp_error( $choice_id ) ) {
					foreach ( $this->choice_attribute_keys as $key => $functions ) {
						if ( isset( $choice[ $key ] ) ) {
							update_post_meta( $choice_id, 'ccf_choice_' . $key, call_user_func( $functions['sanitize'], $choice[ $key ] ) );
						}
					}

					$new_choices[] = $choice_id;
				}
			} else {
				if ( ! empty( $choice['ID'] ) ) {
					wp_delete_post( (int) $choice['ID'], true );
				}
			}
		}

		$current_choices = get_post_meta( $field_id, 'ccf_attached_choices', true );
		$new_choices = array_map( 'absint', $new_choices );

		if ( ! empty( $current_choices ) && is_array( $current_choices ) ) {
			$deleted_choices = array_diff( $current_choices, $new_choices );
			foreach ( $deleted_choices as $choice_id ) {
				wp_delete_post( (int) $choice_id, true );
			}
		}

		update_post_meta( $field_id, 'ccf_attached_choices', array_map( 'absint', $new_choices ) );
	}

	/**
	 * Create fields and map them to forms. Not an API route.
	 *
	 * @param array $fields
	 * @param int   $form_id
	 * @since 7.0
	 */
	public function _create_and_map_fields( $fields, $form_id ) {
		$new_fields = array();

		foreach ( $fields as $field ) {
			if ( empty( $field['ID'] ) ) {
				$args = array(
					'post_title' => sanitize_text_field( isset( $field['slug'] ) ? $field['slug'] : '' ) . '-' . (int) $form_id,
					'post_status' => 'publish',
					'post_parent' => (int) $form_id,
					'post_type' => 'ccf_field',
				);

				$field_id = wp_insert_post( $args );
			} else {
				$field_id = (int) $field['ID'];
			}

			if ( ! is_wp_error( $field_id ) ) {
				foreach ( $this->field_attribute_keys as $key => $functions ) {
					if ( isset( $field[ $key ] ) ) {
						update_post_meta( $field_id, 'ccf_field_' . $key, call_user_func( $functions['sanitize'], $field[ $key ] ) );
					}
				}

				if ( isset( $field['choices'] ) ) {
					$choices = ( empty( $field['choices'] ) ) ? array() : $field['choices'];
					$this->_create_and_map_choices( $choices, $field_id );
				}

				if ( isset( $field['conditionals'] ) ) {
					$conditionals = ( empty( $field['conditionals'] ) ) ? array() : $field['conditionals'];
					$this->_create_and_map_conditionals( $conditionals, $field_id );
				}

				$new_fields[] = $field_id;
			}
		}

		$current_fields = get_post_meta( $form_id, 'ccf_attached_fields', true );
		$new_fields = array_map( 'absint', $new_fields );

		if ( ! empty( $current_fields ) && is_array( $current_fields ) ) {
			$deleted_fields = array_diff( $current_fields, $new_fields );
			foreach ( $deleted_fields as $field_id ) {
				wp_delete_post( (int) $field_id, true );
			}
		}

		update_post_meta( $form_id, 'ccf_attached_fields', $new_fields );
	}

	/**
	 * Create/update notifications
	 *
	 * @param array $notifications
	 * @param int   $form_id
	 * @since 7.2
	 */
	public function _create_and_map_notifications( $notifications, $form_id ) {
		if ( ! is_array( $notifications ) ) {
			$notifications = array();
		}

		$clean_notifications = array();
		$count = count( $notifications );
		for ( $index = 0; $index < $count; $index++ ) {
			if ( ! is_array( $notifications[ $index ] ) ) {
				continue;
			}
			foreach ( $notifications[ $index ] as $notification_key => $notification_value ) {
				if ( 'addresses' === $notification_key && is_array( $notification_value ) ) {
					foreach ( $notification_value as $address_key => $address_value ) {
						if ( ! is_array( $address_value ) ) {
							continue;
						}
						$addr_type = isset( $address_value['type'] ) ? $address_value['type'] : '';
						if ( ( 'field' === $addr_type && ! empty( $address_value['field'] ) ) || ( 'custom' === $addr_type && ! empty( $address_value['email'] ) ) ) {
							$clean_notifications[ $index ][ $notification_key ][ $address_key ] = array_map( 'sanitize_text_field', $address_value );
						}
					}
				} elseif ( 'content' === $notification_key ) {
					$clean_notifications[ $index ][ $notification_key ] = wp_kses_post( $notification_value );
				} else {
					$clean_notifications[ $index ][ $notification_key ] = sanitize_text_field( $notification_value );
				}
			}
		}

		update_post_meta( $form_id, 'ccf_form_notifications', $clean_notifications );
	}

	/**
	 * Create/update field conditionals
	 *
	 * @param array $conditionals
	 * @param int   $field_id
	 * @since 7.5
	 */
	public function _create_and_map_conditionals( $conditionals, $field_id ) {
		if ( ! is_array( $conditionals ) ) {
			$conditionals = array();
		}

		$clean_conditionals = array();
		$count = count( $conditionals );

		for ( $index = 0; $index < $count; $index++ ) {
			if ( ! is_array( $conditionals[ $index ] ) ) {
				continue;
			}
			if ( ! empty( $conditionals[ $index ]['field'] ) && ! empty( $conditionals[ $index ]['compare'] ) ) {
				foreach ( $conditionals[ $index ] as $conditional_key => $conditional_value ) {
					$clean_conditionals[ $index ][ $conditional_key ] = sanitize_text_field( $conditional_value );
				}
			}
		}

		update_post_meta( $field_id, 'ccf_attached_conditionals', $clean_conditionals );
	}

	/**
	 * Create/update post field mappings
	 *
	 * @param array $post_field_mappings
	 * @param int   $form_id
	 * @since 7.3
	 */
	public function _create_and_map_post_field_mappings( $post_field_mappings, $form_id ) {
		if ( ! is_array( $post_field_mappings ) ) {
			$post_field_mappings = array();
		}

		$clean_post_field_mappings = array();

		foreach ( $post_field_mappings as $mapping ) {
			if ( is_array( $mapping ) && ! empty( $mapping['formField'] ) && ! empty( $mapping['postField'] ) ) {
				$clean_post_field_mappings[] = array_map( 'sanitize_text_field', $mapping );
			}
		}

		update_post_meta( $form_id, 'ccf_form_post_field_mappings', $clean_post_field_mappings );
	}

	/**
	 * Create/update a form
	 *
	 * @param array $data
	 * @since 7.0
	 * @return int|WP_Error
	 */
	public function _create_item( $data ) {

		$args = array(
			'post_title' => sanitize_text_field( isset( $data['title'] ) ? $data['title'] : '' ),
			'post_type' => 'ccf_form',
			'post_status' => 'publish',
		);

		if ( ! empty( $data['id'] ) ) {
			$args['ID'] = (int) $data['id'];
		}

		$result = wp_insert_post( $args );

		if ( ! is_wp_error( $result ) ) {
			$fields = isset( $data['fields'] ) ? $data['fields'] : array();
			if ( ! is_array( $fields ) ) {
				$fields = array();
			}

			$this->_create_and_map_fields( $fields, $result );

			$notifications = isset( $data['notifications'] ) ? $data['notifications'] : array();
			$this->_create_and_map_notifications( $notifications, $result );

			$post_field_mappings = isset( $data['postFieldMappings'] ) ? $data['postFieldMappings'] : array();
			$this->_create_and_map_post_field_mappings( $post_field_mappings, $result );

			if ( isset( $data['buttonText'] ) ) {
				update_post_meta( $result, 'ccf_form_buttonText', sanitize_text_field( $data['buttonText'] ) );
			}

			if ( isset( $data['buttonClass'] ) ) {
				update_post_meta( $result, 'ccf_form_buttonClass', sanitize_text_field( $data['buttonClass'] ) );
			}

			if ( isset( $data['description'] ) ) {
				update_post_meta( $result, 'ccf_form_description', sanitize_text_field( $data['description'] ) );
			}

			if ( isset( $data['completionActionType'] ) ) {
				update_post_meta( $result, 'ccf_form_completion_action_type', sanitize_text_field( $data['completionActionType'] ) );
			}

			if ( isset( $data['completionMessage'] ) ) {
				update_post_meta( $result, 'ccf_form_completion_message', sanitize_text_field( $data['completionMessage'] ) );
			}

			if ( isset( $data['completionRedirectUrl'] ) ) {
				update_post_meta( $result, 'ccf_form_completion_redirect_url', esc_url_raw( $data['completionRedirectUrl'] ) );
			}

			if ( isset( $data['sendEmailNotifications'] ) ) {
				update_post_meta( $result, 'ccf_form_send_email_notifications', (bool) $data['sendEmailNotifications'] );
			}

			if ( isset( $data['pause'] ) ) {
				update_post_meta( $result, 'ccf_form_pause', (bool) $data['pause'] );
			}

			if ( isset( $data['hideTitle'] ) ) {
				update_post_meta( $result, 'ccf_form_hide_title', (bool) $data['hideTitle'] );
			}

			if ( isset( $data['requireLoggedIn'] ) ) {
				update_post_meta( $result, 'ccf_form_require_logged_in', (bool) $data['requireLoggedIn'] );
			}

			if ( isset( $data['theme'] ) ) {
				update_post_meta( $result, 'ccf_form_theme', sanitize_text_field( $data['theme'] ) );
			}

			if ( isset( $data['postCreation'] ) ) {
				update_post_meta( $result, 'ccf_form_post_creation', (bool) $data['postCreation'] );
			}

			if ( isset( $data['postCreationType'] ) ) {
				update_post_meta( $result, 'ccf_form_post_creation_type', sanitize_text_field( $data['postCreationType'] ) );
			}

			if ( isset( $data['postCreationStatus'] ) ) {
				update_post_meta( $result, 'ccf_form_post_creation_status', sanitize_text_field( $data['postCreationStatus'] ) );
			}

			if ( isset( $data['pauseMessage'] ) ) {
				update_post_meta( $result, 'ccf_form_pause_message', sanitize_text_field( $data['pauseMessage'] ) );
			}

			return $result;
		} else {
			return new WP_Error( 'create-form-error' );
		}
	}

	/**
	 * Ensure value is boolean
	 *
	 * @param mixed $value
	 * @since 7.0
	 * @return bool
	 */
	protected function boolval( $value ) {
		return ! ! $value;
	}

	/**
	 * Get a collection of submissions
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_submissions( $request ) {
		$params = $request->get_params();

		$args                   = array( 'post_type' => 'ccf_submission' );
		$args['post_parent']    = (int) $params['id'];
		$args['paged']          = isset( $request['page'] ) ? absint( $request['page'] ) : 1;
		$args['posts_per_page'] = ( ! empty( $request['per_page'] ) ) ? absint( $request['per_page'] ) : get_option( 'posts_per_page' );

		// SECURITY FIX: removed unsafe $request['filter'] merge

		$query = new WP_Query( $args );

		$posts = array();
		foreach ( $query->posts as $item ) {
			$posts[] = $this->prepare_submission_for_response( $item );
		}

		$response = rest_ensure_response( $posts );

		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$max_pages = ceil( (int) $query->found_posts / $args['posts_per_page'] );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		return $response;
	}

	/**
	 * Get a collection of fields
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_fields( $request ) {
		$params = $request->get_params();

		$fields = array();

		if ( ! empty( $params['id'] ) ) {
			$fields = $this->_get_fields( (int) $params['id'] );
		}

		return new WP_REST_Response( $fields, 200 );
	}

	/**
	 * Prepare submission for the REST response
	 *
	 * @param  WP_Post $item
	 * @since  7.0
	 * @return array
	 */
	public function prepare_submission_for_response( $item ) {
		$data = array(
			'id'           => $item->ID,
			'date'         => $this->prepare_date_response( $item->post_date_gmt, $item->post_date ),
			'date_gmt'     => $this->prepare_date_response( $item->post_date_gmt ),
			'guid'         => array(
				'raw'      => $item->guid,
			),
			'modified'     => $this->prepare_date_response( $item->post_modified_gmt, $item->post_modified ),
			'modified_gmt' => $this->prepare_date_response( $item->post_modified_gmt ),
			'slug'         => $item->post_name,
			'status'       => $item->post_status,
			'type'         => $item->post_type,
			'link'         => get_permalink( $item->ID ),
			'title'        => array(
				'raw'      => $item->post_title,
				'rendered' => get_the_title( $item->ID ),
			),
		);

		$data['data'] = get_post_meta( $item->ID, 'ccf_submission_data', true );
		$data['fields'] = get_post_meta( $item->ID, 'ccf_submission_form_fields', true );
		$data['ip_address'] = esc_html( get_post_meta( $item->ID, 'ccf_submission_ip', true ) );
		$data['form_page_url'] = esc_url( get_post_meta( $item->ID, 'ccf_submission_form_page', true ) );

		return $data;
	}

	/**
	 * Get a collection of items
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$args                   = array( 'post_type' => 'ccf_form' );
		$args['paged']          = isset( $request['page'] ) ? absint( $request['page'] ) : 1;
		$args['posts_per_page'] = ( ! empty( $request['per_page'] ) ) ? absint( $request['per_page'] ) : get_option( 'posts_per_page' );

		// SECURITY FIX: removed unsafe $request['filter'] merge that allowed arbitrary WP_Query injection
		// Only allow safe, whitelisted filter params
		if ( isset( $request['filter'] ) && is_array( $request['filter'] ) ) {
			$allowed_filter_keys = array( 'orderby', 'order' );
			foreach ( $request['filter'] as $key => $value ) {
				if ( in_array( $key, $allowed_filter_keys, true ) ) {
					$args[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		$query = new WP_Query( $args );

		$posts = array();
		foreach ( $query->posts as $item ) {
			$posts[] = $this->prepare_item_for_response( $item, $request );
		}

		$response = rest_ensure_response( $posts );

		$response->header( 'X-WP-Total', (int) $query->found_posts );
		$max_pages = ceil( (int) $query->found_posts / $args['posts_per_page'] );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		return $response;
	}

	/**
	 * Get one item from the collection
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$params = $request->get_params();

		$item = get_post( (int) $params['id'] );

		if ( empty( $item ) || 'ccf_form' !== $item->post_type ) {
			return new WP_Error( 'cant-find', __( 'Form not found', 'custom-contact-forms' ), array( 'status' => 404 ) );
		}

		$data = $this->prepare_item_for_response( $item, $request );

		if ( is_array( $data ) ) {
			return new WP_REST_Response( $data, 200 );
		} else {
			return new WP_Error( 'cant-find', __( 'Form not found', 'custom-contact-forms' ), array( 'status' => 404 ) );
		}
	}

	/**
	 * Create one item from the collection
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {

		$item = $this->prepare_item_for_database( $request );

		if ( false === $item ) {
			return new WP_Error( 'cant-create', __( 'Could not create form', 'custom-contact-forms' ), array( 'status' => 400 ) );
		}

		$item_id = $this->_create_item( $item );

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		$item = get_post( $item_id );

		if ( empty( $item ) ) {
			return new WP_Error( 'cant-create', __( 'Could not create form', 'custom-contact-forms' ), array( 'status' => 500 ) );
		}

		$data = $this->prepare_item_for_response( $item, $request );

		if ( is_array( $data ) ) {
			return new WP_REST_Response( $data, 200 );
		}

		return new WP_Error( 'cant-create', __( 'Could not create form', 'custom-contact-forms' ), array( 'status' => 500 ) );
	}

	/**
	 * Update one item from the collection
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$params = $request->get_params();

		if ( ! empty( $params['id'] ) ) {
			// Verify the post exists and is a ccf_form
			$existing = get_post( (int) $params['id'] );
			if ( empty( $existing ) || 'ccf_form' !== $existing->post_type ) {
				return new WP_Error( 'cant-update', __( 'Form not found', 'custom-contact-forms' ), array( 'status' => 404 ) );
			}

			$item = $this->prepare_item_for_database( $request );

			if ( false === $item ) {
				return new WP_Error( 'cant-update', __( 'Could not update form', 'custom-contact-forms' ), array( 'status' => 400 ) );
			}

			$item['id'] = (int) $params['id'];

			$item_id = $this->_create_item( $item );

			if ( is_wp_error( $item_id ) ) {
				return $item_id;
			}

			$item = get_post( $item_id );

			if ( empty( $item ) ) {
				return new WP_Error( 'cant-update', __( 'Could not update form', 'custom-contact-forms' ), array( 'status' => 500 ) );
			}

			$data = $this->prepare_item_for_response( $item, $request );

			if ( is_array( $data ) ) {
				return new WP_REST_Response( $data, 200 );
			}
		}

		return new WP_Error( 'cant-update', __( 'Could not update form', 'custom-contact-forms' ), array( 'status' => 500 ) );
	}

	/**
	 * Delete one item from the collection
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		$params = $request->get_params();
		$post_id = (int) $params['id'];

		// SECURITY FIX: verify this is actually a ccf_form
		if ( 'ccf_form' !== get_post_type( $post_id ) ) {
			return new WP_Error( 'cant-delete', __( 'Form not found', 'custom-contact-forms' ), array( 'status' => 404 ) );
		}

		$force = false;
		if ( ! empty( $params['force'] ) ) {
			$force = (bool) $params['force'];
		}

		if ( $force ) {
			$deleted = wp_delete_post( $post_id, true );
		} else {
			$deleted = wp_trash_post( $post_id );
		}

		if ( $deleted ) {
			return new WP_REST_Response( true, 200 );
		}

		return new WP_Error( 'cant-delete', __( 'Could not delete form', 'custom-contact-forms' ), array( 'status' => 500 ) );
	}


	/**
	 * Delete one submission from the collection
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_submission( $request ) {
		$params = $request->get_params();
		$post_id = (int) $params['id'];

		// SECURITY FIX: verify this is actually a ccf_submission
		if ( 'ccf_submission' !== get_post_type( $post_id ) ) {
			return new WP_Error( 'cant-delete', __( 'Submission not found', 'custom-contact-forms' ), array( 'status' => 404 ) );
		}

		$force = false;
		if ( ! empty( $params['force'] ) ) {
			$force = (bool) $params['force'];
		}

		if ( $force ) {
			$deleted = wp_delete_post( $post_id, true );
		} else {
			$deleted = wp_trash_post( $post_id );
		}

		if ( $deleted ) {
			return new WP_REST_Response( true, 200 );
		}

		return new WP_Error( 'cant-delete', __( 'Could not delete submission', 'custom-contact-forms' ), array( 'status' => 500 ) );
	}

	/**
	 * Handle field deletion. We need to delete choices attached to the field too. Not an API route.
	 *
	 * @param int $form_id
	 * @since 7.0
	 */
	public function delete_fields( $form_id ) {
		$attached_fields = get_post_meta( $form_id, 'ccf_attached_fields', true );
		if ( ! empty( $attached_fields ) && is_array( $attached_fields ) ) {
			foreach ( $attached_fields as $field_id ) {
				$this->delete_choices( (int) $field_id );
				wp_delete_post( (int) $field_id, true );
			}
		}
	}

	/**
	 * Delete all submissions associated with a post.
	 *
	 * @param int $form_id
	 * @since 7.0
	 */
	public function delete_submissions( $form_id ) {
		$submissions = get_children( array( 'post_parent' => (int) $form_id, 'post_type' => 'ccf_submission', 'numberposts' => apply_filters( 'ccf_max_submissions', 5000, get_post( $form_id ) ) ) );
		if ( ! empty( $submissions ) ) {
			foreach ( $submissions as $submission ) {
				wp_delete_post( $submission->ID, true );
			}
		}
	}

	/**
	 * Delete field choices. Not an API route.
	 *
	 * @param int $field_id
	 * @since 7.0
	 */
	public function delete_choices( $field_id ) {
		$attached_choices = get_post_meta( $field_id, 'ccf_attached_choices', true );
		if ( ! empty( $attached_choices ) && is_array( $attached_choices ) ) {
			foreach ( $attached_choices as $choice_id ) {
				wp_delete_post( (int) $choice_id, true );
			}
		}
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in.', 'custom-contact-forms' ), array( 'status' => 401 ) );
		}

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission.', 'custom-contact-forms' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to get a specific item
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @since  7.9.0 — Per-post capability check.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in.', 'custom-contact-forms' ), array( 'status' => 401 ) );
		}

		$post_id = (int) $request['id'];
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission.', 'custom-contact-forms' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @since  7.9.0 — Uses publish_posts capability.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission.', 'custom-contact-forms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Check if a given request has access to update a specific item
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @since  7.9.0 — Per-post capability check.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		$post_id = (int) $request['id'];
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission.', 'custom-contact-forms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Check if a given request has access to delete a specific item
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @since  7.0
	 * @since  7.9.0 — Per-post capability check.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		$post_id = (int) $request['id'];
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission.', 'custom-contact-forms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Prepare the item for create or update operation
	 *
	 * @param  WP_REST_Request $request Request object
	 * @since  7.0
	 * @return array|false $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {
		$body = $request->get_body();

		if ( ! empty( $body ) ) {
			$body = json_decode( $body, true );

			if ( ! is_array( $body ) ) {
				return false;
			}

			$raw_title = ( ! empty( $body['title'] ) && ! empty( $body['title']['raw'] ) ) ? $body['title']['raw'] : '';
			$body['title'] = sanitize_text_field( $raw_title );

			return $body;
		}

		return false;
	}

	/**
	 * Get fields given a form ID. Not an API route.
	 *
	 * @param int $form_id
	 * @since  7.0
	 * @return array
	 */
	public function _get_fields( $form_id ) {
		$fields = array();

		$attached_fields = get_post_meta( $form_id, 'ccf_attached_fields', true );

		if ( ! empty( $attached_fields ) && is_array( $attached_fields ) ) {
			foreach ( $attached_fields as $field_id ) {
				$field_id = (int) $field_id;
				$field = array( 'id' => $field_id );

				foreach ( $this->field_attribute_keys as $key => $functions ) {
					$value = get_post_meta( $field_id, 'ccf_field_' . $key );

					if ( isset( $value[0] ) ) {
						$field[ $key ] = call_user_func( $functions['escape'], $value[0] );
					}
				}

				$choices = get_post_meta( $field_id, 'ccf_attached_choices' );

				if ( ! empty( $choices ) ) {
					$field['choices'] = array();

					if ( ! empty( $choices[0] ) && is_array( $choices[0] ) ) {
						foreach ( $choices[0] as $choice_id ) {
							$choice_id = (int) $choice_id;
							$choice = array( 'id' => $choice_id );

							foreach ( $this->choice_attribute_keys as $key => $functions ) {
								$value = get_post_meta( $choice_id, 'ccf_choice_' . $key );

								if ( isset( $value[0] ) ) {
									$choice[ $key ] = call_user_func( $functions['escape'], $value[0] );
								}
							}

							$field['choices'][] = $choice;
						}
					}
				}

				$conditionals = get_post_meta( $field_id, 'ccf_attached_conditionals' );

				if ( ! empty( $conditionals ) ) {
					$field['conditionals'] = array();

					if ( ! empty( $conditionals[0] ) && is_array( $conditionals[0] ) ) {
						foreach ( $conditionals[0] as $conditional ) {
							if ( is_array( $conditional ) ) {
								$field['conditionals'][] = array(
									'field' => esc_attr( isset( $conditional['field'] ) ? $conditional['field'] : '' ),
									'compare' => esc_attr( isset( $conditional['compare'] ) ? $conditional['compare'] : '' ),
									'value' => esc_html( isset( $conditional['value'] ) ? $conditional['value'] : '' ),
								);
							}
						}
					}
				}

				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Prepare the item for the REST response
	 *
	 * @param  WP_Post         $item
	 * @param  WP_REST_Request $request
	 * @since  7.0
	 * @return array
	 */
	public function prepare_item_for_response( $item, $request ) {
		$user = get_user_by( 'id', (int) $item->post_author );

		if ( ! empty( $user ) ) {
			$user = (array) $user->data;

			unset( $user['user_pass'] );
			unset( $user['user_activation_key'] );
		} else {
			$user = 0;
		}

		$data = array(
			'id'           => $item->ID,
			'date'         => $this->prepare_date_response( $item->post_date_gmt, $item->post_date ),
			'date_gmt'     => $this->prepare_date_response( $item->post_date_gmt ),
			'guid'         => array(
				'raw'      => $item->guid,
			),
			'modified'     => $this->prepare_date_response( $item->post_modified_gmt, $item->post_modified ),
			'modified_gmt' => $this->prepare_date_response( $item->post_modified_gmt ),
			'slug'         => $item->post_name,
			'status'       => $item->post_status,
			'type'         => $item->post_type,
			'link'         => get_permalink( $item->ID ),
			'title'        => array(
				'raw'      => $item->post_title,
				'rendered' => get_the_title( $item->ID ),
			),
			'author'       => $user,
		);

		$data['fields'] = $this->_get_fields( $data['id'] );

		$data['buttonText'] = esc_attr( get_post_meta( $data['id'], 'ccf_form_buttonText', true ) );
		$data['buttonClass'] = esc_attr( get_post_meta( $data['id'], 'ccf_form_buttonClass', true ) );
		$data['description'] = esc_html( get_post_meta( $data['id'], 'ccf_form_description', true ) );
		$data['completionActionType'] = esc_attr( get_post_meta( $data['id'], 'ccf_form_completion_action_type', true ) );
		$data['completionRedirectUrl'] = esc_url( get_post_meta( $data['id'], 'ccf_form_completion_redirect_url', true ) );
		$data['completionMessage'] = esc_html( get_post_meta( $data['id'], 'ccf_form_completion_message', true ) );
		$data['pause'] = (bool) get_post_meta( $data['id'], 'ccf_form_pause', true );
		$data['hideTitle'] = (bool) get_post_meta( $data['id'], 'ccf_form_hide_title', true );
		$data['requireLoggedIn'] = (bool) get_post_meta( $data['id'], 'ccf_form_require_logged_in', true );
		$data['postCreation'] = (bool) get_post_meta( $data['id'], 'ccf_form_post_creation', true );
		$data['postCreationType'] = esc_html( get_post_meta( $data['id'], 'ccf_form_post_creation_type', true ) );
		$data['postCreationStatus'] = esc_html( get_post_meta( $data['id'], 'ccf_form_post_creation_status', true ) );
		$data['pauseMessage'] = esc_html( get_post_meta( $data['id'], 'ccf_form_pause_message', true ) );
		$data['theme'] = esc_html( get_post_meta( $data['id'], 'ccf_form_theme', true ) );

		$notifications = get_post_meta( $data['id'], 'ccf_form_notifications', true );
		if ( empty( $notifications ) || ! is_array( $notifications ) ) {
			$notifications = array();
		}

		$data['notifications'] = $notifications;

		$post_field_mappings = get_post_meta( $data['id'], 'ccf_form_post_field_mappings', true );
		if ( empty( $post_field_mappings ) || ! is_array( $post_field_mappings ) ) {
			$post_field_mappings = array();
		}

		$data['postFieldMappings'] = $post_field_mappings;

		$submissions = get_children( array( 'post_type' => 'ccf_submission', 'post_parent' => $data['id'], 'numberposts' => apply_filters( 'ccf_max_submissions', 5000, $data ) ) );

		$data['submissions'] = count( $submissions );

		return $data;
	}

	/**
	 * Format date for response
	 *
	 * @param  string $date_gmt
	 * @param  string $date
	 * @since  7.0
	 * @return string|null
	 */
	protected function prepare_date_response( $date_gmt, $date = null ) {
		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		return mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Get the query params for collections
	 *
	 * @since  7.0
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'page'                   => array(
				'description'        => 'Current page of the collection.',
				'type'               => 'integer',
				'default'            => 1,
				'sanitize_callback'  => 'absint',
			),
			'per_page'               => array(
				'description'        => 'Maximum number of items to be returned in result set.',
				'type'               => 'integer',
				'default'            => 10,
				'sanitize_callback'  => 'absint',
			),
		);
	}
}
