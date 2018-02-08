<?php

/**
 * This is an extremely crude tool.
 * This is not elegantly coded and requires your PHP execution time to be set very high.
 *
 * It scrapes all of the photos from a meetup.com group and spits them into relevant folders along with
 * a data dump file with information about the particular event the photo was for.
 * 
 * This was made as a tool for a one off purpose. I'm simply releasing it here in case it's of
 * use to someone else out there. It is not supported and I probably won't help with answering 
 * questions about it if you ask ;) Feel free to ask any way though, perhaps you'll catch me
 * in a good mood :P
 */

$api_key = 'ADD_YOUR_API_KEY_HERE';
$group_slug; = 'the-slug-for-the-group';

$time_start = microtime( true ); 

$photo_args['type'] = 'photos';
$photo_args['key'] = $api_key;
$photo_args['page'] = 100;
$photo_args['group_urlname'] = $group_slug;

$event_args['type'] = 'events';
$event_args['key'] = $api_key;
$event_args['page'] = 2;
$event_args['offset'] = 0;


// Test to see how many pages of photos there are
$photo_args['offset'] = 0;
$response = meetup_request( $photo_args );

// Iterate through every page of photos
$iterations = ceil( $response->meta->total_count / $photo_args['page'] );

$photo_args['offset'] = 0;
$photos_response = meetup_request( $photo_args );

if ( $iterations > 1 ) {
	$offset = 0;
	while ( $offset <= $iterations ) {

		$photo_args['offset'] = $offset;
		$photos_response = meetup_request( $photo_args );

		// Loop through each photo
		foreach ( $photos_response->results as $photo ) {

			if ( isset( $photo->photo_album->event_id ) && ! in_array( $photo->photo_album->event_id, $event_ids_completed ) ) {
				$event_ids_completed[] = $photo->photo_album->event_id;

				$event_args['event_id'] = $photo->photo_album->event_id;

				$event_response = meetup_request( $event_args );

				$event = $event_response->results[0];
				$event_dump = json_encode( $event );
			}

			if ( isset( $event->time ) ) {
				$date = date( 'Y-m-d', ( $event->time / 1000 ) );
				$folder = $date;
			} else {
				$folder = 'unattached';
			}

			if ( isset( $event->name ) ) {
				$title = sanitize_title_with_dashes( $event->name );
				$folder .= '-' . $title;
			}

			$folder_path = dirname( __FILE__ ) . '/events/' . $folder . '/';

			// Make folder if it doesn't exist
			if ( ! is_dir( $folder_path ) ) {
				mkdir( $folder_path );
			}

			// Create image file
			$image_data = file_get_contents( $photo->highres_link );
			$image_path = $folder_path . $photo->photo_id . '.jpg';
			if ( ! file_exists( $image_path ) ) {
				file_put_contents ( $image_path, $image_data );
			}

			// Create data dump
			if ( isset( $photo->photo_album->event_id ) ) {

				$data_file_path = $folder_path . $event_args['event_id'] . '.txt';
				if ( ! file_exists( $data_file_path ) ) {
					file_put_contents ( $data_file_path, $event_dump );
				}

			}

		}

		$offset++;
	} 
}

$time_end = microtime(true);

$execution_time = ($time_end - $time_start)/60;
echo "\n\n\nExecution time: $execution_time s";


function meetup_request( $photo_args ) {

	if ( ! isset( $photo_args['offset'] ) ) {
		$photo_args['offset'] = 0;
	}

	$url = 'https://api.meetup.com/2/' . $photo_args['type'] . '?key=' . $photo_args['key'] . '&offset=' . $photo_args['offset'] . '&page=' . $photo_args['page'] . '&sign=true&photo-host=public&sign=true';

	if ( isset( $photo_args['event_id'] ) ) {
		$url .= '&event_id=' . $photo_args['event_id'];
	} else {
		$url .= '&group_urlname=' . $photo_args['group_urlname'];
	}

	$json_response = file_get_contents( $url );
	$response = json_decode( $json_response );
	return $response;
}

/**
 * Stolen from WordPress core.
 * Used for making folder names more sensible.
 */
function sanitize_title_with_dashes( $title, $raw_title = '', $context = 'display' ) {
	$title = strip_tags($title);
	// Preserve escaped octets.
	$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
	// Remove percent signs that are not part of an octet.
	$title = str_replace('%', '', $title);
	// Restore octets.
	$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

	$title = mb_strtolower($title, 'UTF-8');

	$title = strtolower($title);

	if ( 'save' == $context ) {
		// Convert nbsp, ndash and mdash to hyphens
		$title = str_replace( array( '%c2%a0', '%e2%80%93', '%e2%80%94' ), '-', $title );
		// Convert nbsp, ndash and mdash HTML entities to hyphens
		$title = str_replace( array( '&nbsp;', '&#160;', '&ndash;', '&#8211;', '&mdash;', '&#8212;' ), '-', $title );
		// Convert forward slash to hyphen
		$title = str_replace( '/', '-', $title );

		// Strip these characters entirely
		$title = str_replace( array(
			// iexcl and iquest
			'%c2%a1', '%c2%bf',
			// angle quotes
			'%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba',
			// curly quotes
			'%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d',
			'%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f',
			// copy, reg, deg, hellip and trade
			'%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2',
			// acute accents
			'%c2%b4', '%cb%8a', '%cc%81', '%cd%81',
			// grave accent, macron, caron
			'%cc%80', '%cc%84', '%cc%8c',
		), '', $title );

		// Convert times to x
		$title = str_replace( '%c3%97', 'x', $title );
	}

	$title = preg_replace('/&.+?;/', '', $title); // kill entities
	$title = str_replace('.', '-', $title);

	$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
	$title = preg_replace('/\s+/', '-', $title);
	$title = preg_replace('|-+|', '-', $title);
	$title = trim($title, '-');

	return $title;
}
