<?php
/*
  Plugin Name: Pull Images to WP Media Library
  Description: This plugin will pull all inline images from post content to WP Media Library on post save.
  Version: 1.0
  Author: Rhythm Shahriar <rhythm@newscred.com>
  License: MIT
 */

/***
 * This will pull all in-line images to media library
 * and replace cdn urls with media library url
 *
 * @param $post_ID
 * @param $post
 */

if ( ! function_exists( 'nc_pull_images_wp_ml' ) ) {
	function nc_pull_images_wp_ml( $post_ID, $post ) {

		//allow pull for following domains
		//e.g., CDN URL
		$domain_whitelist = [
			'newscred.com',
		];

		//skip pull for following domains
		//definitely we will not sync our own images
		$domain_blacklist = [
			preg_replace( "(^https?://)", "", get_home_url() ),
		];

		//check if this article is coming from cmp
		if ( get_post_meta( $post_ID, 'nc-image', true )
		     || get_post_meta( $post_ID, 'nc-author', true )
		     || get_post_meta( $post_ID, 'nc-source', true ) ) {

			//get a list of images
			$doc = new DOMDocument();
			$doc->loadHTML( $post->post_content );
			$images = $doc->getElementsByTagName( 'img' );

			//cdn images
			$cdn_image_list = [];

			foreach ( $images as $image ) {
				if ( preg_match( '/(\b' . implode( '\b|\b', $domain_whitelist ) . '\b)/i', $image->getAttribute( 'src' ) ) ) {
					if ( ! preg_match( '/(\b' . implode( '\b|\b', $domain_blacklist ) . '\b)/i', $image->getAttribute( 'src' ) ) ) {
						$cdn_image_list[] = $image->getAttribute( 'src' );
					}
				}
			}

			if ( $cdn_image_list ) {
				//check if this post was previously synced
				if ( $cmp_ml_images = get_post_meta( $post_ID, 'nc-cmp-ml-images', true ) ) {
					$cmp_ml_images = json_decode( $cmp_ml_images, true );

					//check if that image is already pulled
					foreach ( $cdn_image_list as $cdn_image_url ) {
						if ( ! array_search( $cdn_image_url, array_column( $cmp_ml_images, 'cmp_img_url', 'cmp_img_url' ) ) ) {
							array_push( $cmp_ml_images, [
								'cmp_img_url' => $cdn_image_url,
								'wp_img_url'  => nc_pull_images( $cdn_image_url ),
							] );
						}
					}

					//add this in a custom field
					update_post_meta( $post_ID, 'nc-cmp-ml-images', json_encode( $cmp_ml_images ) );

				} else {
					$cmp_ml_images = [];

					//upload each of the images and map with cmp url
					foreach ( $cdn_image_list as $cdn_image_url ) {
						$cmp_ml_images[] = [
							'cmp_img_url' => $cdn_image_url,
							'wp_img_url'  => nc_pull_images( $cdn_image_url ),
						];
					}

					//add this in a custom field
					add_post_meta( $post_ID, 'nc-cmp-ml-images', json_encode( $cmp_ml_images ), true );
				}

				//update post content
				$post->post_content = nc_update_inline_images( $cmp_ml_images, $post->post_content );
				wp_update_post( $post );

			}
		}
	}

	add_action( 'save_post', 'nc_pull_images_wp_ml', 10, 2 );
}

/**
 * Pull image from URL and upload it on WP ML
 *
 * @param $image_url
 *
 * @return mixed
 */
if ( ! function_exists( 'nc_pull_images' ) ) {
	function nc_pull_images( $image_url ) {
		$upload_dir = wp_upload_dir();
		$image_data = file_get_contents( $image_url );
		$file_type  = getimagesize( $image_url )['mime'];
		$file_name  = basename( $image_url );

		//define file name and extension
		switch ( $file_type ) {
			case "image/gif":
				$file_name = time() . '.gif';
				break;
			case "image/jpeg":
				$file_name = time() . '.jpeg';
				break;
			case "image/png":
				$file_name = time() . '.png';
				break;
			case "image/bmp":
				$file_name = time() . '.bmp';
				break;
		}

		if ( wp_mkdir_p( $upload_dir['path'] ) ) {
			$file = $upload_dir['path'] . '/' . $file_name;
		} else {
			$file = $upload_dir['basedir'] . '/' . $file_name;
		}

		//place the file
		file_put_contents( $file, $image_data );

		$attach_id = wp_insert_attachment( [
			'post_title'     => $file_name,
			'post_mime_type' => $file_type,
			'post_status'    => 'inherit',
		], $file );

		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return wp_get_attachment_url( $attach_id );
	}
}

/**
 * Replace image src from post content
 *
 * @param $images
 * @param $content
 *
 * @return string|string[]
 */

if ( ! function_exists( 'nc_update_inline_images' ) ) {
	function nc_update_inline_images( $images, $content ) {
		foreach ( $images as $image ) {
			$content = str_replace( $image['cmp_img_url'], $image['wp_img_url'], $content );
		}

		return $content;
	}
}