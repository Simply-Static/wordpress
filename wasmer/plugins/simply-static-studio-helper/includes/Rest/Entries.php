<?php

namespace Simply_Static_Studio\Rest;

use Simply_Static_Studio\Models\FormEntry;
use Simply_Static_Studio\Options;
use WP_REST_Request;

class Entries extends Rest {

	protected $route = 'entries';

	public function deleteItem( \WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		$entry = FormEntry::query()->find_by( 'id', $id );

		if ( $entry ) {
			$delete = FormEntry::query()->delete_by( 'id', $id );
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	public function getItems( WP_REST_Request $request ) {
		$page    = $request->get_param( 'page' ) ?? 1;
		$perPage = $request->get_param( 'per_page' ) ?? 25;

		$entries = FormEntry::query()->limit( $perPage )->offset( ( $page - 1 ) * $perPage )->find();
		$total   = FormEntry::query()->count();

		$data = [];

		foreach ( $entries as $entry ) {
			$posted    = json_decode( $entry->posted, true );
			$formatted = apply_filters( 'simply_static_studio_formatted_entry', $posted );

			$data[] = [
				'id'         => $entry->id,
				'created_at' => $entry->created_at,
				'form_id'    => $entry->form_id,
				'form'       => $entry->form_plugin,
				'posted'     => $posted,
				'title'      => $entry->title,
				'formatted'  => $formatted,
			];
		}

		return rest_ensure_response( [
			'data'  => $data,
			'total' => $total
		] );
	}

	public function verifyDeleteItemPermission( \WP_REST_Request $request ) {
		return $this->verifyAdminRequest( $request );
	}

	public function verifyGetItemsPermission( \WP_REST_Request $request ) {
		return $this->verifyAdminRequest( $request );
	}

	public function verifyAdminRequest( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	public function verifyRequest( \WP_REST_Request $request ) {
		$secret = SSS_SECRET_KEY;

		if ( empty( $secret ) ) {
			return true;
		}

		// Get secret from $request as a header parameter
		$secret_from_request = $request->get_header( 'X-Simply-Static-Studio-Secret' );

		if ( empty( $secret_from_request ) ) {
			return false;
		}

		if ( $secret !== $secret_from_request ) {
			return false;
		}

		return true;
	}

	public function createItem( \WP_REST_Request $request ) {
		$posted = $request->get_body_params();
		$entry  = new FormEntry();

		// Setup data.
		$entry->posted = wp_json_encode( $posted );
		$this->set_data( $entry );
		$entry->save();

		$this->handle_emails( $entry, $posted );

		return rest_ensure_response( [
			'success' => true,
			'message' => __( 'Submitted successfully.', 'simply-static-studio' ),
		] );
	}


	public function set_data( FormEntry $entry ) {
		do_action( 'simply_static_studio_form_submitted_set_data', $entry );
	}

	public function handle_emails( $entry, $posted ) {
		$sending_email = SSS_EMAIL;
		$formatted     = apply_filters( 'simply_static_studio_formatted_entry', $posted );
		$site_name     = get_bloginfo( 'name' );

		// Setup mail content.
		$subject = 'New submission on ' . $site_name;
		$message = '<h4>New submission on <a href="' . get_bloginfo( 'url' ) . '">' . $site_name . '</a><h4>';
		$message .= '<p>';
		$message .= '<b>Form ID:</b> ' . $entry->form_id . '<br>';
		$message .= '<b>Form Plugin:</b> ' . $entry->form_plugin . '<br>';
		$message .= '</p>';
		$message .= '<p><b>Message:</b> <br>' . $formatted . '</p>';

		// Get ID prefix based on form plugin.
		switch ( $entry->form_plugin ) {
			case 'cf7':
				$form_id = $entry->form_id; // TODO: find a way to store _wpcf7_unit_tag to get the form configuration ID.
				break;
			case 'wpforms':
				$form_id = 'wpforms-form-' . $entry->form_id;
				break;
			case 'wsform':
				$form_id = 'ws-form-' . $entry->form_id;
				break;
			case 'fluentform':
				$form_id = 'fluentform_' . $entry->form_id;
				break;
			case 'gravityforms':
				$form_id = 'gform_' . $entry->form_id;
				break;
			default:
				$form_id = '';
		}

		// Different sending e-mail address?
		$args = array(
			'meta_query'     => array(
				array(
					'key'   => 'form_id',
					'value' => $form_id,
				)
			),
			'post_type'      => 'ssp-form',
			'posts_per_page' => - 1
		);

		$form_configs = get_posts( $args );

		if ( ! empty( $form_configs ) ) {
			// Get e-mail from ssp-form.
			$ssp_form_email = get_post_meta( $form_configs[0]->ID, 'form_email_recipient', true );

			if ( ! empty( $ssp_form_email ) ) {
				$sending_email = $ssp_form_email;
			}
		}

		// Using SMTP?
		$use_smtp = apply_filters( 'sss_use_smtp', false );

		if ( $use_smtp ) {
			if ( ! empty( $ssp_form_email ) ) {
				$this->send_notification_email( $sending_email, $subject, $message );
			}

			$confirmation_email = apply_filters( 'sss_confirmation_email', [
					'subject' => 'Thank you for your submission on ' . get_bloginfo( 'name' ),
					'message' => '<h4>Thank you for contacting us!<h4><p>We have received your submission and will get back to you as soon as possible.</p>'
				]
			);

			wp_mail( $entry->email, $confirmation_email['subject'], $confirmation_email['message'] );

		} else {
			$this->send_notification_email( $sending_email, $subject, $message );
		}
	}

	public function send_notification_email( $email, $subject, $message ) {
		$json = json_encode( array(
			'email'   => $email,
			'subject' => $subject,
			'content' => $message,
		) );

		$response = wp_remote_post( 'https://api.static.studio/functions/v1/send-mail', array(
			'headers' => array(
				'Content-Type' => 'application/json; charset=utf-8',
			),
			'body'    => $json,
		) );

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}
	}

}