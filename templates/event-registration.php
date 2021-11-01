<?php if ($register = get_event_registration_method()) :
	wp_enqueue_script('wp-event-manager-event-registration');

	if ($register->type) :
?>
		<div class="event_registration registration">
			<?php do_action('event_registration_start', $register); ?>
			<div class="wpem-event-sidebar-button wpem-registration-event-button">
				<?php if ($register->type == 'online') : ?>
					<?php
					$isUserRegeisterd = false;
					$userId = get_current_user_id();
					$eventId = get_the_ID();
					$url;
					$linkText = 'Wezmę udział';
					$iconRegister = true;

					function isUserRegisteredOnEvent($userId, $eventId) {
						global $wpdb;
						return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}event_members WHERE EventMember = {$userId} AND EventId = {$eventId}");
					}
					
					if ($userId && isUserRegisteredOnEvent($userId, $eventId)) {
						$isUserRegeisterd = true;
					};
					
					
					if ($isUserRegeisterd) {
						$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/wp-content/plugins/wp-event-manager-online-registration/online-deregistration.php?eventId=" . $eventId;
						$linkText = 'Nie wezmę udziału';
						$iconRegister = false;
					} else {
						$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/wp-content/plugins/wp-event-manager-online-registration/online-registration.php?eventId=" . $eventId;
					}
					?>

					<a class="eventRegistration__button" href="<?php echo wp_make_link_relative($url); ?>">
						<?php if ($iconRegister) : ?>
							<i class="eventRegistration__icon fa fa-check-circle"></i>
						<?php else : ?>
							<i class="eventRegistration__icon fa fa-times-circle"></i>
						<?php endif; ?>
						<span class="eventRegistration__text"><?php echo $linkText; ?></span>
					</a>
				<?php endif; ?>
			</div>
			<?php do_action('event_registration_end', $register); ?>
		</div>
	<?php endif; ?>
<?php endif; ?>