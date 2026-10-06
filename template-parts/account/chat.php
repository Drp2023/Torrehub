<?php
/**
 * Account › Messages: conversation list + the open conversation (or a new one about a listing).
 * Plain links and forms; chat.js sends in place and polls for new messages.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Chat\Module as Chat;
use Torrehub\Modules\Chat\Store;

$user_id = get_current_user_id();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation and notices.
$thread_id  = isset( $_GET['thread'] ) ? absint( $_GET['thread'] ) : 0;
$listing_id = isset( $_GET['listing'] ) ? absint( $_GET['listing'] ) : 0;
$chat_error = isset( $_GET['th_chat_error'] ) ? sanitize_key( wp_unslash( $_GET['th_chat_error'] ) ) : '';
// phpcs:enable

$threads = array_map( static fn( $t ) => Chat::thread_view( $t, $user_id ), Store::threads( $user_id ) );
$thread  = $thread_id ? Store::get( $thread_id ) : null;
$thread  = $thread && Store::side( $thread, $user_id ) ? $thread : null;
if ( ! $thread && $listing_id ) {
	$existing = Store::find( $listing_id, $user_id );
	$thread   = $existing ? $existing : null;
}
$current  = $thread ? Chat::thread_view( $thread, $user_id ) : null;
$messages = array();
if ( $thread ) {
	$messages = array_map( static fn( $msg ) => Chat::message_view( $msg, $user_id ), Store::messages( (int) $thread->id ) );
	Store::mark_read( $thread, $user_id );
}

// New conversation about a listing (no thread yet).
$new_listing = null;
if ( ! $thread && $listing_id ) {
	$listing_post = get_post( $listing_id );
	if ( $listing_post && 'rtcl_listing' === $listing_post->post_type && 'publish' === $listing_post->post_status && (int) $listing_post->post_author !== $user_id ) {
		$new_listing = $listing_post;
	}
}
$open      = $current || $new_listing;
$last_mine = 0;
foreach ( $messages as $msg ) {
	if ( $msg['mine'] ) {
		$last_mine = $msg['id'];
	}
}
?>
<div class="<?php echo esc_attr( th_classes( 'th-chat', array( 'is-open' => $open ) ) ); ?>" data-th-chat<?php echo $current ? ' data-thread="' . esc_attr( (string) $current['id'] ) . '"' : ''; ?>>
	<h1 class="th-account__title th-chat__title"><?php esc_html_e( 'Messages', 'torrehub' ); ?></h1>

	<?php if ( $chat_error ) : ?>
		<?php
		th_component(
			'alert',
			array(
				'variant' => 'error',
				'text'    => Chat::error_text( $chat_error ),
			)
		);
		?>
	<?php endif; ?>

	<div class="th-chat__layout">
		<nav class="th-chat__list" aria-label="<?php esc_attr_e( 'Conversations', 'torrehub' ); ?>">
			<?php if ( $threads ) : ?>
				<ul role="list" data-th-chat-threads>
					<?php foreach ( $threads as $t ) : ?>
						<li>
							<a class="<?php echo esc_attr( th_classes( 'th-chat-item', array( 'is-unread' => $t['unread'] > 0 ) ) ); ?>" href="<?php echo esc_url( $t['url'] ); ?>"<?php echo $current && $current['id'] === $t['id'] ? ' aria-current="page"' : ''; ?> data-thread="<?php echo esc_attr( (string) $t['id'] ); ?>">
								<?php echo th_get_avatar( $t['other']['id'], 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
								<span class="th-chat-item__body">
									<span class="th-chat-item__top">
										<span class="th-chat-item__name"><?php echo esc_html( $t['other']['name'] ); ?></span>
										<span class="th-chat-item__when"><?php echo esc_html( $t['when'] ); ?></span>
									</span>
									<span class="th-chat-item__listing"><?php echo esc_html( $t['listing']['title'] ); ?></span>
									<span class="th-chat-item__last">
										<?php
										echo esc_html( ( $t['last']['mine'] ? __( 'You:', 'torrehub' ) . ' ' : '' ) . $t['last']['body'] );
										?>
									</span>
								</span>
								<?php if ( $t['unread'] ) : ?>
									<span class="th-badge-count">
										<?php echo esc_html( (string) $t['unread'] ); ?>
										<span class="th-sr-only"><?php esc_html_e( 'unread', 'torrehub' ); ?></span>
									</span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<?php
				th_component(
					'empty-state',
					array(
						'icon'          => 'chat',
						'title'         => __( 'No messages yet', 'torrehub' ),
						'text'          => __( 'Use “Chat” on a listing to ask the seller a question. Replies show up here.', 'torrehub' ),
						'heading_level' => 'h2',
						'actions'       => array(
							array(
								'label'   => __( 'Browse listings', 'torrehub' ),
								'variant' => 'primary',
								'href'    => th_url_listings(),
							),
						),
					)
				);
				?>
			<?php endif; ?>
		</nav>

		<?php if ( $open ) : ?>
			<?php
			$head_listing = $current ? $current['listing'] : array(
				'id'    => $new_listing->ID,
				'title' => html_entity_decode( $new_listing->post_title, ENT_QUOTES ),
				'url'   => (string) get_permalink( $new_listing ),
				'image' => (int) get_post_thumbnail_id( $new_listing ),
			);
			$other_name   = $current ? $current['other']['name'] : ( get_userdata( (int) $new_listing->post_author )->display_name ?? '' );
			?>
			<section class="th-chat__thread" aria-labelledby="th-chat-with">
				<header class="th-chat__head">
					<a class="th-chat__back" href="<?php echo esc_url( Chat::url() ); ?>"><?php th_icon( 'arrow-left', array( 'size' => 18 ) ); ?><span class="th-sr-only"><?php esc_html_e( 'All conversations', 'torrehub' ); ?></span></a>
					<?php if ( $head_listing['image'] ) : ?>
						<?php
						echo wp_get_attachment_image(
							$head_listing['image'],
							'th-thumb',
							false,
							array(
								'class' => 'th-chat__thumb',
								'alt'   => '',
							)
						);
						?>
					<?php endif; ?>
					<div class="th-chat__who">
						<h2 class="th-chat__name" id="th-chat-with"><?php echo esc_html( $other_name ); ?></h2>
						<?php if ( $head_listing['url'] ) : ?>
							<a class="th-chat__listing" href="<?php echo esc_url( $head_listing['url'] ); ?>"><?php echo esc_html( $head_listing['title'] ); ?></a>
						<?php else : ?>
							<span class="th-chat__listing"><?php echo esc_html( $head_listing['title'] ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $current ) : ?>
						<form method="post" action="<?php echo esc_url( Chat::url() ); ?>" data-th-confirm="<?php esc_attr_e( 'Remove this conversation from your list? It comes back if a new message arrives.', 'torrehub' ); ?>">
							<input type="hidden" name="th_action" value="th_chat_hide">
							<input type="hidden" name="thread" value="<?php echo esc_attr( (string) $current['id'] ); ?>">
							<?php wp_nonce_field( 'th_chat_hide_' . $current['id'] ); ?>
							<?php
							th_component(
								'button',
								array(
									'variant'    => 'neutral',
									'size'       => 'sm',
									'icon'       => 'trash',
									'icon_only'  => true,
									'type'       => 'submit',
									'aria_label' => __( 'Remove conversation', 'torrehub' ),
								)
							);
							?>
						</form>
					<?php endif; ?>
				</header>

				<ol class="th-chat__messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Messages', 'torrehub' ); ?>" data-th-chat-messages data-last="<?php echo esc_attr( (string) ( $messages ? end( $messages )['id'] : 0 ) ); ?>" data-seen="<?php echo esc_attr( (string) ( $current['read_id'] ?? 0 ) ); ?>" tabindex="0">
					<?php if ( ! $messages ) : ?>
						<li class="th-chat__intro">
							<?php
							/* translators: %s: seller name */
							echo esc_html( sprintf( __( 'Ask %s about this listing. Keep payments and personal details off the chat until you trust the other side.', 'torrehub' ), $other_name ) );
							?>
						</li>
					<?php endif; ?>
					<?php foreach ( $messages as $msg ) : ?>
						<li class="<?php echo esc_attr( th_classes( 'th-msg', array( 'th-msg--mine' => $msg['mine'] ) ) ); ?>" data-id="<?php echo esc_attr( (string) $msg['id'] ); ?>">
							<span class="th-sr-only"><?php echo esc_html( $msg['mine'] ? __( 'You', 'torrehub' ) : $other_name ); ?>:</span>
							<p class="th-msg__body"><?php echo nl2br( esc_html( $msg['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped, then line breaks. ?></p>
							<time class="th-msg__time" datetime="<?php echo esc_attr( $msg['at'] ); ?>"><?php echo esc_html( $msg['time'] ); ?></time>
						</li>
					<?php endforeach; ?>
				</ol>
				<p class="th-chat__seen" data-th-chat-seen<?php echo $last_mine && ( $current['read_id'] ?? 0 ) >= $last_mine ? '' : ' hidden'; ?>><?php esc_html_e( 'Seen', 'torrehub' ); ?></p>

				<form class="th-chat__compose" id="th-chat-compose" method="post" action="<?php echo esc_url( Chat::url() ); ?>" data-th-chat-form>
					<input type="hidden" name="th_action" value="th_chat_send">
					<input type="hidden" name="thread" value="<?php echo esc_attr( (string) ( $current['id'] ?? 0 ) ); ?>">
					<input type="hidden" name="listing" value="<?php echo esc_attr( (string) $head_listing['id'] ); ?>">
					<?php wp_nonce_field( 'th_chat_send' ); ?>
					<label class="th-sr-only" for="th-chat-message"><?php esc_html_e( 'Your message', 'torrehub' ); ?></label>
					<textarea class="th-textarea th-chat__input" id="th-chat-message" name="message" rows="2" maxlength="<?php echo esc_attr( (string) Store::MAX_LENGTH ); ?>" required placeholder="<?php esc_attr_e( 'Write a message…', 'torrehub' ); ?>"></textarea>
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Send', 'torrehub' ),
							'variant' => 'primary',
							'icon'    => 'send',
							'type'    => 'submit',
						)
					);
					?>
					<p class="th-chat__status" data-th-chat-status role="status"></p>
				</form>
			</section>
		<?php elseif ( $threads ) : ?>
			<div class="th-chat__placeholder">
				<?php th_icon( 'chat', array( 'size' => 28 ) ); ?>
				<p><?php esc_html_e( 'Choose a conversation.', 'torrehub' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
