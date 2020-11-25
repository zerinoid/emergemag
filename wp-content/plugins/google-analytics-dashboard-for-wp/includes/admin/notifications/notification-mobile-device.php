<?php
/**
 * Add notification when percentage of visitors from mobile device is less than 10%
 * Recurrence: 15 Days
 *
 * @since 7.12.3
 */
final class ExactMetrics_Notification_Mobile_Device extends ExactMetrics_Notification_Event {

	public $notification_id             = 'exactmetrics_notification_mobile_device';
	public $notification_interval       = 15; // in days
	public $notification_type           = array( 'basic', 'lite', 'master', 'plus', 'pro' );

	/**
	 * Prepare Notification
	 *
	 * @return array $notification notification is ready to add
	 *
	 * @since 7.12.3
	 */
	public function prepare_notification_data( $notification ) {
		if ( ! exactmetrics_is_pro_version() ) {
			// Improve performance for lite users by disabling external API calls they can’t access.
			// Since lite users can’t access this feature return early.
			return false;
		}
		$data                                  = array();
		$report                                = $this->get_report( 'overview', $this->report_start_from, $this->report_end_to );
		$data['percentage_of_mobile_visitors'] = isset( $report['data']['devices']['mobile'] ) ? $report['data']['devices']['mobile'] : 0;

		if ( ! empty( $data ) && $data['percentage_of_mobile_visitors'] < 10 ) {
			// Translators: Mobile device notification title
			$notification['title']   = sprintf( __( 'Traffic from Mobile Devices is %s%%', 'google-analytics-dashboard-for-wp' ), $data['percentage_of_mobile_visitors'] );
			// Translators: Mobile device notification content
			$notification['content'] = sprintf( __( 'Traffic from mobile devices is considerably lower on your site compared to desktop devices. This could be an indicator that your site is not optimised for mobile devices.<br><br>Take a look now at %show your site looks%s on mobile and make sure all your content can be accessed correctly.', 'google-analytics-dashboard-for-wp' ), '<a href="'. $this->build_external_link( 'https://www.wpbeginner.com/beginners-guide/how-to-preview-the-mobile-layout-of-your-site/' ) .'">', '</a>' );
			$notification['btns']    = array(
				"view_report" => array(
					'url'  => $this->get_view_url(),
					'text' => __( 'View Report', 'google-analytics-dashboard-for-wp' )
				),
				"learn_more"  => array(
					'url'  => $this->build_external_link('https://www.wpbeginner.com/beginners-guide/how-to-preview-the-mobile-layout-of-your-site/' ),
					'text' => __( 'Learn More', 'google-analytics-dashboard-for-wp' )
				),
			);

			return $notification;
		}

		return false;
	}

}

// initialize the class
new ExactMetrics_Notification_Mobile_Device();
