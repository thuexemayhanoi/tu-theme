<?php
/**
 * Demo content for theme QA — LOCAL TEST ENVIRONMENT ONLY, never production.
 *
 * Usage: wp eval-file tests/e2e/theme/fixtures/seed-demo.php
 * Idempotent: removes previous demo posts (meta _mr_tu_demo) before inserting.
 * Copy follows the content markup contract (docs/architecture-contract.md §3) and stays honest:
 * no invented prices, ratings, years or addresses.
 *
 * @package MrTu\Theme\Tests
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable

$old = get_posts(
	array(
		'post_type'      => array( 'page', 'post' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_key'       => '_mr_tu_demo',
		'fields'         => 'ids',
	)
);
foreach ( $old as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( 1, 2, 3 ) as $id ) {
	if ( get_post( $id ) ) {
		wp_delete_post( $id, true );
	}
}

function mrtu_demo_p( $text, $class = '' ) {
	if ( $class ) {
		return "<!-- wp:paragraph {\"className\":\"$class\"} -->\n<p class=\"$class\">$text</p>\n<!-- /wp:paragraph -->\n\n";
	}
	return "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->\n\n";
}
function mrtu_demo_h( $text, $level = 2 ) {
	$attr = 2 === $level ? '' : ' {"level":' . $level . '}';
	return "<!-- wp:heading$attr -->\n<h$level class=\"wp-block-heading\">$text</h$level>\n<!-- /wp:heading -->\n\n";
}
function mrtu_demo_list( $items, $ordered = false ) {
	$tag   = $ordered ? 'ol' : 'ul';
	$attr  = $ordered ? ' {"ordered":true}' : '';
	$inner = '';
	foreach ( $items as $item ) {
		$inner .= "<!-- wp:list-item -->\n<li>$item</li>\n<!-- /wp:list-item -->\n\n";
	}
	return "<!-- wp:list$attr -->\n<$tag class=\"wp-block-list\">$inner</$tag>\n<!-- /wp:list -->\n\n";
}
function mrtu_demo_details( $q, $a ) {
	return "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>$q</summary>" . mrtu_demo_p( $a ) . "</details>\n<!-- /wp:details -->\n\n";
}
function mrtu_demo_section( $variant, $inner ) {
	return "<!-- wp:group {\"tagName\":\"section\",\"className\":\"mrtu-section mrtu-section--$variant\"} -->\n<section class=\"wp-block-group mrtu-section mrtu-section--$variant\">$inner</section>\n<!-- /wp:group -->\n\n";
}
function mrtu_demo_columns( $cols ) {
	$inner = '';
	foreach ( $cols as $col ) {
		$inner .= "<!-- wp:column -->\n<div class=\"wp-block-column\">" . mrtu_demo_h( $col[0], 3 ) . mrtu_demo_p( $col[1] ) . "</div>\n<!-- /wp:column -->\n\n";
	}
	return "<!-- wp:columns -->\n<div class=\"wp-block-columns\">$inner</div>\n<!-- /wp:columns -->\n\n";
}
function mrtu_demo_buttons( $buttons ) {
	$inner = '';
	foreach ( $buttons as $b ) {
		$cls    = isset( $b[2] ) ? ' {"className":"' . $b[2] . '"}' : '';
		$clsatt = isset( $b[2] ) ? ' ' . $b[2] : '';
		$inner .= "<!-- wp:button$cls -->\n<div class=\"wp-block-button$clsatt\"><a class=\"wp-block-button__link wp-element-button\" href=\"{$b[1]}\">{$b[0]}</a></div>\n<!-- /wp:button -->\n\n";
	}
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">$inner</div>\n<!-- /wp:buttons -->\n\n";
}
function mrtu_demo_callout( $title, $text, $items = array() ) {
	return "<!-- wp:group {\"className\":\"mrtu-callout\"} -->\n<div class=\"wp-block-group mrtu-callout\">" . mrtu_demo_h( $title, 3 ) . mrtu_demo_p( $text ) . ( $items ? mrtu_demo_list( $items ) : '' ) . "</div>\n<!-- /wp:group -->\n\n";
}
function mrtu_demo_insert( $args, $role = '', $nav = '' ) {
	$args = wp_parse_args( $args, array( 'post_status' => 'publish', 'post_type' => 'page', 'post_author' => 1 ) );
	$id   = wp_insert_post( wp_slash( $args ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( $id, '_mr_tu_demo', '1' );
	if ( $role ) {
		update_post_meta( $id, '_mrtu_role', $role );
	}
	if ( $nav ) {
		update_post_meta( $id, '_mrtu_nav_label', $nav );
	}
	return $id;
}

/* ---------------------------------------------------------------- Home */
$home = '';
$home .= mrtu_demo_section(
	'promise',
	mrtu_demo_h( 'Why travellers ride with Mr Tu' )
	. mrtu_demo_p( 'Everything you need to know before you ride, said plainly — so the only surprise in Hanoi is how good the coffee is.' )
	. mrtu_demo_columns(
		array(
			array( 'Close to the Old Quarter', 'Hand-over is arranged by appointment around Phố Cổ, so you are on your way within minutes of arriving.' ),
			array( 'Clear conditions', 'Documents, deposit and return times are explained before you book — in Vietnamese or English.' ),
			array( 'Checked together', 'Brakes, lights, tyres and fuel are checked with you at hand-over. Helmets are part of every rental.' ),
			array( 'Help on the road', 'A problem with the bike? Call and we will tell you exactly what to do next.' ),
		)
	)
);
$home .= mrtu_demo_section(
	'fleet',
	mrtu_demo_h( 'Pick the bike that fits your trip' )
	. mrtu_demo_p( 'Automatic scooters for city days, semi-automatics for longer rides. Availability is confirmed when you call.' )
	. "<!-- wp:mrtu/fleet {\"category\":\"all\",\"showPrices\":true} /-->\n\n"
);
$home .= mrtu_demo_section(
	'pricing',
	mrtu_demo_h( 'Prices by day, week or month' )
	. mrtu_demo_p( 'Rental and deposit are always shown separately. Prices that are not confirmed yet are clearly marked.' )
	. "<!-- wp:mrtu/pricing-table {\"period\":\"day\",\"showDeposit\":true} /-->\n\n"
);
$home .= mrtu_demo_section(
	'story',
	mrtu_demo_h( 'Hà Nội starts at Km 0' )
	. mrtu_demo_p( 'Every road in Vietnam is measured from a kilometre stone, and Hanoi’s own Km 0 sits a short walk from the Old Quarter. That is where most journeys here begin — and where Mr Tu hands you the keys.' )
	. mrtu_demo_p( 'We keep it simple: a well-maintained bike, a helmet that fits, honest conditions and a phone number that answers. The rest of Hanoi is yours to discover.' )
);
$home .= mrtu_demo_section(
	'steps',
	mrtu_demo_h( 'How renting works' )
	. mrtu_demo_list(
		array(
			'<strong>Call or message</strong> Tell us your dates and the kind of bike you need.',
			'<strong>Confirm the details</strong> Price, deposit and hand-over time are agreed before you come.',
			'<strong>Check and ride</strong> Inspect the bike together, take your helmet and go.',
			'<strong>Return the bike</strong> Bring it back at the agreed time and get your deposit back.',
		),
		true
	)
);
$home .= mrtu_demo_section(
	'trust',
	mrtu_demo_h( 'Straight answers, no surprises' )
	. mrtu_demo_list(
		array(
			'<strong>Rental and deposit kept separate</strong> You always know what you pay and what comes back to you.',
			'<strong>Unconfirmed prices are marked</strong> Reference prices are labelled until they are confirmed for this location.',
			'<strong>One phone number</strong> The number on this site is the one that answers your call.',
		)
	)
);
$home .= mrtu_demo_section(
	'faq',
	mrtu_demo_h( 'Questions before you ride' )
	. mrtu_demo_p( 'Quick answers to what travellers ask most. The full guide has more.' )
	. mrtu_demo_details( 'Which documents do I need?', 'Bring your passport or ID. We explain the current licence requirements for your situation before you book.' )
	. mrtu_demo_details( 'How does the deposit work?', 'The deposit is agreed in advance and returned when the bike comes back in the same condition.' )
	. mrtu_demo_details( 'Can I rent for a month?', 'Yes — monthly rentals are available for people living or working in Hanoi. Call to check which bikes are free.' )
	. mrtu_demo_details( 'Do you include helmets?', 'Yes, a helmet is part of every rental. Ask if you need a second one for a passenger.' )
);
$home .= mrtu_demo_section(
	'contact',
	mrtu_demo_h( 'Ready when you are' )
	. mrtu_demo_p( 'Call to check availability and arrange the hand-over near the Old Quarter.' )
	. "<!-- wp:mrtu/cta {\"variant\":\"call\"} /-->\n\n"
	. "<!-- wp:mrtu/contact-card {\"showHours\":true,\"showAddress\":true} /-->\n\n"
);

$home_id = mrtu_demo_insert(
	array(
		'post_title'   => 'Motorbike rental near Hanoi’s Old Quarter',
		'post_name'    => 'home',
		'post_excerpt' => 'Scooters and semi-automatic bikes by the day, week or month — clear conditions, a bike checked together at hand-over, and help in Vietnamese or English.',
		'post_content' => $home,
		'menu_order'   => 0,
	),
	'home'
);

/* ---------------------------------------------------------- Pillar */
$pillar  = mrtu_demo_p( 'Renting a motorbike is the easiest way to see Hanoi on your own schedule. This page explains how renting with Mr Tu works, what to bring and how to choose the right bike.', 'mrtu-lead' );
$pillar .= mrtu_demo_h( 'Who rents with Mr Tu' );
$pillar .= mrtu_demo_p( 'Travellers staying a few days, people visiting for a few weeks and residents who need a bike for work all rent from us. The process is the same: a short call, a clear agreement and a bike that is checked together.' );
$pillar .= mrtu_demo_h( 'Choosing a bike', 3 );
$pillar .= mrtu_demo_p( 'Automatic scooters are the easiest choice in city traffic. Semi-automatic bikes have four gears and no clutch lever — they are economical and comfortable on longer rides.' );
$pillar .= mrtu_demo_list( array( 'City only, first time on a scooter: a light automatic', 'Two riders or day trips: a larger scooter', 'Longer journeys outside Hanoi: ask us first' ) );
$pillar .= mrtu_demo_h( 'How long can I rent?', 3 );
$pillar .= mrtu_demo_p( 'By the day, by the week or by the month. Longer rentals are agreed individually, including maintenance.' );
$pillar .= mrtu_demo_callout( 'Good to know', 'Prices and availability are confirmed when you call. Reference prices on this site are marked until they are confirmed for the Mr Tu location.', array( 'Rental and deposit are separate', 'Hand-over by appointment' ) );
$pillar .= mrtu_demo_h( 'What to bring' );
$pillar .= mrtu_demo_p( 'Bring your passport or ID and your driving licence if you have one. We explain the current requirements before you book, so there are no surprises at hand-over.' );
$pillar .= mrtu_demo_h( 'At the hand-over', 3 );
$pillar .= mrtu_demo_list( array( 'Check brakes, lights, horn and tyres together', 'Take photos of the bike', 'Agree the return time and place' ), true );
$pillar .= mrtu_demo_h( 'Riding safely in Hanoi' );
$pillar .= mrtu_demo_p( 'Traffic flows rather than stops. Ride predictably, keep a steady speed, use your horn lightly to signal and always wear your helmet.' );
$pillar .= mrtu_demo_details( 'Is it hard to ride in the Old Quarter?', 'The streets are busy but slow. Start with a short loop at a calm hour and you will be comfortable quickly.' );
$pillar .= mrtu_demo_details( 'What if the bike breaks down?', 'Call the number on this site. We tell you what to do and arrange help.' );
$pillar .= mrtu_demo_buttons( array( array( 'See bikes & prices', '/prices/' ), array( 'Read the full guide', '/guide-faq/', 'is-style-outline' ) ) );

$pillar_id  = mrtu_demo_insert( array( 'post_title' => 'Motorbike rental in Hanoi', 'post_name' => 'motorbike-rental-hanoi', 'post_excerpt' => 'Scooters and semi-automatic bikes by the day, week or month near the Old Quarter — what to bring, how to choose and how the hand-over works.', 'post_content' => $pillar, 'menu_order' => 1 ), 'pillar', 'Rent a bike' );

$fleet  = mrtu_demo_p( 'Every bike is checked before hand-over. Availability changes, so call to confirm the model you want.', 'mrtu-lead' );
$fleet .= "<!-- wp:mrtu/fleet {\"category\":\"all\",\"showPrices\":true} /-->\n\n";
$fleet .= mrtu_demo_h( 'Automatic scooters' ) . mrtu_demo_p( 'Light, easy and perfect for the city. No gears to think about.' );
$fleet .= mrtu_demo_h( 'Semi-automatic bikes' ) . mrtu_demo_p( 'Four gears, no clutch lever. Economical and sturdy for longer rides.' );
$fleet .= mrtu_demo_h( 'Which one should I pick?' ) . mrtu_demo_p( 'Tell us where you plan to ride and we will suggest a bike.' );
$fleet_id = mrtu_demo_insert( array( 'post_title' => 'Bikes for rent', 'post_name' => 'bikes', 'post_excerpt' => 'Automatic scooters and semi-automatic bikes, checked before every hand-over.', 'post_content' => $fleet, 'menu_order' => 2 ), 'fleet', 'Bikes' );

$prices  = mrtu_demo_p( 'All prices are managed in one place and shown the same in every language. Unconfirmed prices are labelled.', 'mrtu-lead' );
$prices .= "<!-- wp:mrtu/pricing-table {\"period\":\"day\",\"showDeposit\":true} /-->\n\n";
$prices .= mrtu_demo_h( 'Deposit' ) . mrtu_demo_p( 'The deposit is separate from the rental price and returned when the bike comes back in the same condition.' );
$prices .= mrtu_demo_h( 'Longer rentals' ) . mrtu_demo_p( 'Weekly and monthly prices are lower per day. Maintenance during a monthly rental is agreed in advance.' );
$prices .= mrtu_demo_h( 'Extras' ) . mrtu_demo_p( 'Delivery, out-of-hours hand-over and trips outside Hanoi are agreed case by case.' );
$prices .= mrtu_demo_callout( 'Before you book', 'Call to confirm the price and the bike. Nothing is final until it is confirmed with you.' );
$prices_id = mrtu_demo_insert( array( 'post_title' => 'Motorbike rental prices', 'post_name' => 'prices', 'post_excerpt' => 'Daily, weekly and monthly prices with deposits shown separately.', 'post_content' => $prices, 'menu_order' => 3 ), 'pricing', 'Prices' );

$oq  = mrtu_demo_p( 'The Old Quarter — Phố Cổ — is the heart of Hoàn Kiếm district and the easiest place to start your Hanoi ride.', 'mrtu-lead' );
$oq .= mrtu_demo_h( 'Hand-over near Phố Cổ' ) . mrtu_demo_p( 'Hand-over is arranged by appointment. Call first and we agree a time and an easy meeting point.' );
$oq .= mrtu_demo_h( 'Riding in the Old Quarter' ) . mrtu_demo_p( 'Streets are narrow and many are one-way. Ride slowly and watch for pedestrians.' );
$oq .= mrtu_demo_h( 'Parking', 3 ) . mrtu_demo_p( 'Use guarded parking where you see attendants and keep your ticket.' );
$oq .= mrtu_demo_h( 'Around Hoàn Kiếm Lake', 3 ) . mrtu_demo_p( 'Some streets around the lake become walking streets at weekends. Plan your route around them.' );
$oq_id = mrtu_demo_insert( array( 'post_title' => 'Motorbike rental in the Old Quarter & Hoàn Kiếm', 'post_name' => 'old-quarter', 'post_excerpt' => 'Where the hand-over happens, how to ride and park in Phố Cổ and what changes at weekends.', 'post_content' => $oq, 'menu_order' => 4 ), 'old-quarter', 'Old Quarter' );

$guide  = mrtu_demo_p( 'Everything you need from the first call to the return of the bike.', 'mrtu-lead' );
$guide .= mrtu_demo_h( 'Before you rent' ) . mrtu_demo_list( array( 'Decide on dates and the type of bike', 'Prepare your passport or ID', 'Call to confirm price and deposit' ), true );
$guide .= mrtu_demo_h( 'Documents and licence' ) . mrtu_demo_p( 'Requirements depend on your nationality and licence. We explain the current rules for your case before you book.' );
$guide .= mrtu_demo_h( 'Frequently asked questions' );
$guide .= mrtu_demo_details( 'Which documents do I need?', 'Your passport or ID. We explain licence requirements when you call.' );
$guide .= mrtu_demo_details( 'How does the deposit work?', 'It is agreed in advance and returned when the bike comes back in the same condition.' );
$guide .= mrtu_demo_details( 'Can I extend my rental?', 'Yes, call before the agreed return time and we will confirm.' );
$guide .= mrtu_demo_details( 'What if I get a flat tyre?', 'Most repair shops fix it in minutes. Call us if you are unsure.' );
$guide_id = mrtu_demo_insert( array( 'post_title' => 'How to rent a motorbike: guide & FAQ', 'post_name' => 'guide-faq', 'post_excerpt' => 'Documents, deposit, hand-over and return — with answers to the questions travellers ask most.', 'post_content' => $guide, 'menu_order' => 5 ), 'guide-faq', 'Guide & FAQ' );

$st_id = mrtu_demo_insert( array( 'post_title' => 'Daily & weekly motorbike rental', 'post_name' => 'daily-weekly', 'post_excerpt' => 'Short rentals for travellers: how days are counted, hand-over times and extensions.', 'post_content' => mrtu_demo_h( 'How days are counted' ) . mrtu_demo_p( 'A rental day runs from hand-over to the same time the next day.' ) . mrtu_demo_h( 'Extending' ) . mrtu_demo_p( 'Call before the return time to extend.' ), 'menu_order' => 6 ), 'short-term', 'Daily & weekly' );
$mo_id = mrtu_demo_insert( array( 'post_title' => 'Monthly motorbike rental in Hanoi', 'post_name' => 'monthly', 'post_excerpt' => 'For people living or working in Hanoi: suitable bikes, deposit and maintenance.', 'post_content' => mrtu_demo_h( 'Who it is for' ) . mrtu_demo_p( 'Residents, students and long-stay visitors.' ) . mrtu_demo_h( 'Maintenance' ) . mrtu_demo_p( 'Maintenance is agreed in advance.' ), 'menu_order' => 7 ), 'monthly', 'Monthly' );
$ab_id = mrtu_demo_insert( array( 'post_title' => 'About Mr Tu', 'post_name' => 'about', 'post_excerpt' => 'A small motorbike rental near Hanoi’s Old Quarter.', 'post_content' => mrtu_demo_p( 'Mr Tu rents scooters and semi-automatic bikes near the Old Quarter.' ), 'menu_order' => 8 ), 'about', 'About' );
$co_id = mrtu_demo_insert( array( 'post_title' => 'Contact Mr Tu', 'post_name' => 'contact', 'post_excerpt' => 'Call to check availability and arrange a hand-over near the Old Quarter.', 'post_content' => "<!-- wp:mrtu/contact-card {\"showHours\":true,\"showAddress\":true} /-->\n\n" . mrtu_demo_h( 'Before you call' ) . mrtu_demo_p( 'Have your dates and the type of bike in mind.' ), 'menu_order' => 9 ), 'contact', 'Contact' );
$blog_id = mrtu_demo_insert( array( 'post_title' => 'Ride notes', 'post_name' => 'blog', 'post_excerpt' => 'Practical notes for riding, parking and exploring Hanoi by motorbike.', 'post_content' => '', 'menu_order' => 10 ), 'blog', 'Blog' );
$pr_id = mrtu_demo_insert( array( 'post_title' => 'Privacy policy', 'post_name' => 'privacy', 'post_content' => mrtu_demo_h( 'What we collect' ) . mrtu_demo_p( 'Only what you send us when you call or write.' ), 'menu_order' => 11 ), 'privacy' );
$te_id = mrtu_demo_insert( array( 'post_title' => 'Terms of use', 'post_name' => 'terms', 'post_content' => mrtu_demo_p( 'Terms of use of this website.' ), 'menu_order' => 12 ), 'terms' );
$rp_id = mrtu_demo_insert( array( 'post_title' => 'Rental rules', 'post_name' => 'rental-rules', 'post_content' => mrtu_demo_p( 'The rules that apply to every rental.' ), 'menu_order' => 13 ), 'rental-policy' );

/* Vietnamese diacritics stress test (stacked marks in headings). */
$vi  = mrtu_demo_p( 'Trang thử nghiệm dấu tiếng Việt: Ấ Ầ Ẩ Ẫ Ậ · Ể Ễ Ệ · Ổ Ỗ Ộ · Ữ Ự · Đ đ.', 'mrtu-lead' );
$vi .= mrtu_demo_h( 'Ổn định, tiết kiệm và dễ đi' ) . mrtu_demo_p( 'Xe số và xe tay ga được kiểm tra trước mỗi lần giao xe. Giá và tiền cọc được xác nhận trước khi đặt.' );
$vi .= mrtu_demo_h( 'Những điều cần biết khi thuê dài hạn', 3 ) . mrtu_demo_p( 'Bảo dưỡng định kỳ được thỏa thuận trước.' );
$vi .= mrtu_demo_h( 'Hỏi đáp nhanh' ) . mrtu_demo_details( 'Thuê xe máy cần giấy tờ gì?', 'Hộ chiếu hoặc căn cước; điều kiện bằng lái được giải thích trước khi đặt xe.' );
$vi_id = mrtu_demo_insert( array( 'post_title' => 'Thuê xe máy theo tháng ở Hà Nội — Ổn định, tiết kiệm', 'post_name' => 'thue-xe-may-theo-thang', 'post_excerpt' => 'Kiểm tra hiển thị dấu tiếng Việt chồng tầng trên tiêu đề lớn: Ấn tượng, Ổn định, Ở, Ữ.', 'post_content' => $vi, 'menu_order' => 20 ) );

/* Plugin markup preview (static HTML that mirrors docs/theme-markup-reference.md). */
$dir     = __DIR__ . '/markup/';
$preview = mrtu_demo_p( 'Static copies of the plugin block markup, used to design and test the theme skins.', 'mrtu-lead' );
foreach ( array( 'Fleet' => 'fleet.html', 'Pricing table' => 'pricing.html', 'Contact card' => 'contact-card.html', 'Call to action' => 'cta.html' ) as $label => $file ) {
	$preview .= mrtu_demo_h( $label ) . "<!-- wp:html -->\n" . file_get_contents( $dir . $file ) . "\n<!-- /wp:html -->\n\n";
}
$preview_id = mrtu_demo_insert( array( 'post_title' => 'Plugin markup preview', 'post_name' => 'plugin-markup-preview', 'post_excerpt' => 'Reference markup for .mrtu-fleet, .mrtu-pricing, .mrtu-contact-card and .mrtu-cta.', 'post_content' => $preview, 'menu_order' => 30 ) );

/* --------------------------------------------------------------- Posts */
$cats = array();
foreach ( array( 'riding-in-hanoi' => 'Riding in Hanoi', 'old-quarter' => 'Old Quarter', 'rental-guide' => 'Rental guide' ) as $slug => $name ) {
	$term = get_term_by( 'slug', $slug, 'category' );
	$cats[ $slug ] = $term ? (int) $term->term_id : (int) wp_insert_term( $name, 'category', array( 'slug' => $slug ) )['term_id'];
}
$posts = array(
	array( 'First ride in the Old Quarter: a calm 20-minute loop', 'old-quarter', 'A gentle first loop through Phố Cổ at a quiet hour, with the turns that keep you out of the busiest streets.' ),
	array( 'Scooter or semi-automatic: which bike suits your trip?', 'rental-guide', 'The honest differences between an automatic scooter and a semi-automatic bike, and how to choose for your plans.' ),
	array( 'What to check before you ride away', 'rental-guide', 'Brakes, lights, horn, tyres, mirrors and fuel: a two-minute check that makes every ride safer.' ),
	array( 'Parking in the Old Quarter without stress', 'old-quarter', 'How guarded parking works, what the ticket means and where not to leave your bike.' ),
	array( 'Riding in Hanoi rain: a short survival guide', 'riding-in-hanoi', 'Raincoats, braking distance and the puddles to avoid when the afternoon storm arrives.' ),
	array( 'Helmets, lights and the rules that matter', 'riding-in-hanoi', 'The everyday rules every rider should know, and why a good helmet is not optional.' ),
);
$tag_pool = array( 'beginners', 'safety', 'old-quarter', 'weather' );
foreach ( $posts as $i => $p ) {
	$body  = mrtu_demo_p( 'Hanoi looks chaotic from the pavement and feels surprisingly calm from the saddle once you know a few habits. This note collects the ones that matter most for this topic.', 'mrtu-lead' );
	$body .= mrtu_demo_h( 'Before you start' ) . mrtu_demo_p( 'Pick a calm time of day, check the bike and plan a simple route. Hanoi rewards riders who are predictable rather than fast.' );
	$body .= mrtu_demo_h( 'On the road' ) . mrtu_demo_p( 'Keep a steady speed and leave space in front of you. Traffic flows around you when you ride smoothly.' );
	$body .= mrtu_demo_h( 'Small habits that help', 3 ) . mrtu_demo_list( array( 'Use your horn lightly to signal, never in anger', 'Look over your shoulder before turning', 'Keep your phone in your pocket' ) );
	$body .= mrtu_demo_h( 'When something feels wrong', 3 ) . mrtu_demo_p( 'Pull over somewhere safe and call. It is always better to ask.' );
	$body .= mrtu_demo_callout( 'Tip', 'Save the Mr Tu number in your phone before your first ride.' );
	$body .= mrtu_demo_h( 'Wrapping up' ) . mrtu_demo_p( 'Every ride gets easier. Start small, enjoy the city and come back with stories.' );
	$pid = wp_insert_post(
		wp_slash(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $p[0],
				'post_excerpt'  => $p[2],
				'post_content'  => $body,
				'post_category' => array( $cats[ $p[1] ] ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '2026-10-20 09:00:00' ) - $i * 3 * DAY_IN_SECONDS ),
				'tags_input'    => array( $tag_pool[ $i % 4 ], $tag_pool[ ( $i + 1 ) % 4 ] ),
				'comment_status' => 0 === $i ? 'open' : 'closed',
			)
		)
	);
	update_post_meta( $pid, '_mr_tu_demo', '1' );
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_id );
update_option( 'page_for_posts', $blog_id );
update_option( 'wp_page_for_privacy_policy', $pr_id );
update_option( 'blogdescription', '' );
update_option( 'posts_per_page', 7 );
update_option( 'default_comment_status', 'closed' );

WP_CLI::success( 'Demo content ready. Home #' . $home_id . ', pillar #' . $pillar_id . ', preview #' . $preview_id . ', VI test #' . $vi_id . '.' );
