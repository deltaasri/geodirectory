<?php
/**
 * Personal data exporters.
 *
 * @since 1.2.26
 * @package GeoDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * GeoDir_Privacy_Exporters Class.
 */
class GeoDir_Privacy_Exporters {

	/**
	 * Finds and exports data which could be used to identify a person from GeoDirectory data associated with an email address.
	 *
	 * Posts are exported in blocks of 10 to avoid timeouts.
	 *
	 * @since 1.6.26
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public static function post_data_exporter( $email_address, $page ) {
		$post_type 		= GeoDir_Privacy::exporter_post_type();

		$done           = false;
		$page           = (int) $page;
		$data_to_export = array();

		$posts 			= self::posts_by_author( $email_address, $post_type, $page );

		if ( 0 < count( $posts ) ) {
			$obj_post_type = get_post_type_object( $post_type );

			foreach ( $posts as $post_ID ) {
				$gd_post = geodir_get_post_info( $post_ID );
				if ( empty( $gd_post ) ) {
					continue;
				}

				$data_to_export[] = array(
					'group_id'    => 'geodirectory-post-' . $post_type,
					'group_label' => __( $obj_post_type->labels->name, 'geodirectory' ),
					'item_id'     => 'post-' . $post_ID,
					'data'        => self::get_post_personal_data( $gd_post ),
				);
			}
			$done = 10 > count( $posts );
		} else {
			$done = true;
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Get personal data (key/value pairs) for an post object.
	 *
	 * @since 1.6.26
	 * @param WP_Post $gd_post The post object.
	 * @return array
	 */
	protected static function get_post_personal_data( $gd_post ) {
		$post_categories = array();
		$post_tags = array();
		$default_category = '';

		$cat_taxonomy = $gd_post->post_type . 'category';
		$tag_taxonomy = $gd_post->post_type . '_tags';

		$post_terms = wp_get_post_terms( $gd_post->ID, array( $cat_taxonomy, $tag_taxonomy ) );
		if ( ! empty( $post_terms ) && ! is_wp_error( $post_terms ) ) {
			foreach ( $post_terms as $term ) {
				if ( $term->taxonomy == $cat_taxonomy ) {
					$post_categories[] = $term->name;
				} else {
					$post_tags[] = $term->name;
				}

				if ( $gd_post->default_category == $term->term_id ) {
					$default_category = $term->name;
				}
			}
		}

		$personal_data = array();
		$personal_data[] = array(
			'name'  => __( 'Post ID', 'geodirectory' ),
			'value' => $gd_post->ID,
		);
		$personal_data[] = array(
			'name'  => __( 'Post Title', 'geodirectory' ),
			'value' => $gd_post->post_title,
		);
		$personal_data[] = array(
			'name'  => __( 'Post Date', 'geodirectory' ),
			'value' => $gd_post->post_date,
		);
		$personal_data[] = array(
			'name'  => __( 'Post Status', 'geodirectory' ),
			'value' => $gd_post->post_status,
		);
		$personal_data[] = array(
			'name'  => __( 'Post Categories', 'geodirectory' ),
			'value' => ( ! empty( $post_categories ) ? implode( ', ', $post_categories ) : '' ),
		);
		if ( $default_category ) {
			$personal_data[] = array(
				'name'  => __( 'Default Category', 'geodirectory' ),
				'value' => $default_category,
			);
		}
		if ( ! empty( $post_tags ) ) {
			$personal_data[] = array(
				'name'  => __( 'Post Tags', 'geodirectory' ),
				'value' => implode( ', ', $post_tags ),
			);
		}
		$personal_data[] = array(
			'name'  => __( 'Post URL', 'geodirectory' ),
			'value' => get_permalink( $gd_post->ID ),
		);

		// Post Images
		$post_images = geodir_get_images( $gd_post->ID );
		if ( ! empty( $post_images ) ) {
			$images = array();
			foreach ( $post_images as $key => $post_image ) {
				if ( ! empty( $post_image->src ) ) {
					$images[] = $post_image->src;
				}
			}

			if ( ! empty( $images ) ) {
				$personal_data[] = array(
					'name'  => __( 'Post Images', 'geodirectory' ),
					'value' => self::parse_files_value( $images ),
				);
			}
		}

		// Post Rating
		$post_rating = geodir_get_post_rating( $gd_post->ID );
		if ( $post_rating > 0 ) {
			$post_rating = ( is_float( $post_rating) || ( strpos( $post_rating, ".", 1 ) == 1 && strlen( $post_rating ) > 3 ) ) ? number_format( $post_rating, 1, '.', '' ) : $post_rating;
			$personal_data[] = array(
				'name'  => __( 'Post Rating', 'geodirectory' ),
				'value' => $post_rating . ' / 5',
			);
		}

		// Post Reviews
		$post_reviews = geodir_get_review_count_total( $gd_post->ID );
		if ( $post_reviews > 0 ) {
			$personal_data[] = array(
				'name'  => __( 'Post Reviews', 'geodirectory' ),
				'value' => $post_reviews,
			);
		}

		if ( ! empty( $gd_post->is_featured ) ) {
			$personal_data[] = array(
				'name'  => __( 'Post Featured', 'geodirectory' ),
				'value' => __( 'Yes', 'geodirectory' ),
			);
		}

		$custom_fields 	= geodir_post_custom_fields( $gd_post->package_id, 'all', $gd_post->post_type );
		$post_fields 	= array_keys( (array) $gd_post );

		foreach ( $custom_fields as $key => $field ) {
			$field_name 			= ! empty( $field['htmlvar_name'] ) ? $field['htmlvar_name'] : '';
			if ( empty( $field_name ) ) {
				continue;
			}

			$field 					= stripslashes_deep( $field );

			$extra_fields 			= ! empty( $field['extra_fields'] ) ? $field['extra_fields'] : array();
			$data_type              = $field['data_type'];
			$field_type             = $field['field_type'];
			$field_title			= $field['site_title'];
			if ( $field_name == 'post' ) {
				$field_name = 'post_address';
			}

			if ( ! in_array( $field_name, $post_fields ) ) {
				continue;
			}

			$name = $field_title;
			$value = '';
			switch ( $field_type ) {
				case 'address':
					$location_allowed = function_exists( 'geodir_cpt_no_location' ) && geodir_cpt_no_location( $gd_post->post_type ) ? false : true;
					if ( $location_allowed && ! empty( $gd_post->post_country ) && ! empty( $gd_post->post_region ) && ! empty( $gd_post->post_city ) ) {
						$personal_data[] = array(
							'name'  => __( 'Post Address', 'geodirectory' ),
							'value' => $gd_post->post_address,
						);
						$personal_data[] = array(
							'name'  => __( 'Post City', 'geodirectory' ),
							'value' => $gd_post->post_city,
						);
						$personal_data[] = array(
							'name'  => __( 'Post Region', 'geodirectory' ),
							'value' => $gd_post->post_region,
						);
						$personal_data[] = array(
							'name'  => __( 'Post Country', 'geodirectory' ),
							'value' => $gd_post->post_country,
						);
						$personal_data[] = array(
							'name'  => __( 'Post Zip', 'geodirectory' ),
							'value' => $gd_post->post_zip,
						);
						$personal_data[] = array(
							'name'  => __( 'Post Latitude', 'geodirectory' ),
							'value' => $gd_post->post_latitude,
						);
						$personal_data[] = array(
							'name'  => __( 'Post Longitude', 'geodirectory' ),
							'value' => $gd_post->post_longitude,
						);
					}
				break;
				case 'checkbox':
					if ( ! empty( $gd_post->{$field_name} ) ) {
						$value = __( 'Yes', 'geodirectory' );
					} else {
						$value = __( 'No', 'geodirectory' );
					}
					break;
				case 'datepicker':
					$value = $gd_post->{$field_name} != '0000-00-00' ? $gd_post->{$field_name} : '';
					break;
				case 'radio':
					if ( $gd_post->{$field_name} !== '' ) {
						if ( $gd_post->{$field_name} == 'f' || $gd_post->{$field_name} == '0') {
							$value = __( 'No', 'geodirectory' );
						} else if ( $gd_post->{$field_name} == 't' || $gd_post->{$field_name} == '1') {
							$value = __( 'Yes', 'geodirectory' );
						} else {
							if ( !empty( $field['option_values'] ) ) {
								$cf_option_values = geodir_string_values_to_options(stripslashes_deep( $field['option_values'] ), true );
								if ( ! empty( $cf_option_values ) ) {
									foreach ( $cf_option_values as $cf_option_value ) {
										if ( isset( $cf_option_value['value'] ) && $cf_option_value['value'] == $gd_post->{$field_name} ) {
											$value = $cf_option_value['label'];
										}
									}
								}
							}
						}
					}
					break;
				case 'select':
					$value = __( $gd_post->{$field_name}, 'geodirectory');
					if ( !empty( $field['option_values'] ) ) {
						$cf_option_values = geodir_string_values_to_options(stripslashes_deep( $field['option_values'] ), true );
						if ( ! empty( $cf_option_values ) ) {
							foreach ( $cf_option_values as $cf_option_value ) {
								if ( isset( $cf_option_value['value'] ) && $cf_option_value['value'] == $gd_post->{$field_name} ) {
									$value = $cf_option_value['label'];
								}
							}
						}
					}
					break;
				case 'multiselect':
					$field_values = explode( ',', trim( $gd_post->{$field_name}, "," ) );
					if ( is_array( $field_values ) ) {
						$field_values = array_map( 'trim', $field_values );
					}
					$values = array();
					if ( ! empty( $field['option_values'] ) ) {
						$cf_option_values = geodir_string_values_to_options(stripslashes_deep( $field['option_values'] ), true );

						if ( ! empty( $cf_option_values ) ) {
							foreach ( $cf_option_values as $cf_option_value ) {
								if ( isset( $cf_option_value['value'] ) && in_array( $cf_option_value['value'], $field_values ) ) {
									$values[] = $cf_option_value['label'];
								}
							}
						}
					}
					$value = ! empty( $values ) ? implode( ', ', $values ) : '';
					break;
				case 'time':
					$value = $gd_post->{$field_name} != '00:00:00' ? $gd_post->{$field_name} : '';
					break;
				case 'email':
				case 'phone':
				case 'text':
				case 'url':
				case 'html':
				case 'textarea':
					$value = $gd_post->{$field_name} ? $gd_post->{$field_name} : '';
					break;
				case 'file':
					$files = explode( ",", $gd_post->{$field_name} );
					if ( ! empty( $files ) ) {
						$allowed_file_types = !empty( $extra_fields['gd_file_types'] ) && is_array( $extra_fields['gd_file_types'] ) && !in_array( "*", $extra_fields['gd_file_types'] ) ? $extra_fields['gd_file_types'] : '';

						$file_urls = array();
						foreach ( $files as $file ) {
							if ( ! empty( $file ) ) {
								$image_name_arr = explode( '/', $file );
								$curr_img_dir = $image_name_arr[ count( $image_name_arr ) - 2];
								$filename = end($image_name_arr);
								$img_name_arr = explode('.', $filename);

								$arr_file_type = wp_check_filetype( $filename );
								if ( empty( $arr_file_type['ext'] ) || empty( $arr_file_type['type'] ) ) {
									continue;
								}

								$uploaded_file_type = $arr_file_type['type'];
								$uploaded_file_ext = $arr_file_type['ext'];

								if ( ! empty( $allowed_file_types ) && !in_array( $uploaded_file_ext, $allowed_file_types ) ) {
									continue; // Invalid file type.
								}
								$image_file_types = array( 'image/jpg', 'image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'image/x-icon' );
								$audio_file_types = array( 'audio/mpeg', 'audio/ogg', 'audio/mp4', 'audio/vnd.wav', 'audio/basic', 'audio/mid' );

								// If the uploaded file is image
								if ( in_array( $uploaded_file_type, $image_file_types ) ) {
									$file_urls[] = $file;
								}
							}
						}
						$value = ! empty( $file_urls ) ? self::parse_files_value( $file_urls ) : '';
					}
					break;
			}

			if ( ! empty( $name ) && $value !== '' ) {
				$personal_data[] = array(
					'name'  => __( $name, 'geodirectory' ),
					'value' => $value,
				);
			}
		}

		/**
		 * Allow extensions to register their own personal data for this post for the export.
		 *
		 * @since 1.6.26
		 * @param array    $personal_data Array of name value pairs to expose in the export.
		 * @param WP_Post $gd_post The post object.
		 */
		$personal_data = apply_filters( 'geodir_privacy_export_post_personal_data', $personal_data, $gd_post );

		return $personal_data;
	}

	public static function posts_by_author( $email_address, $post_type, $page ) {
		if ( empty( $email_address ) || empty( $post_type ) || empty( $page ) ) {
			return array();
		}

		$user = get_user_by( 'email', $email_address );
		if ( empty( $user ) ) {
			return array();
		}

		$statuses = array_keys( get_post_statuses() );
		$skip_statuses = geodir_imex_export_skip_statuses();
		if ( ! empty( $skip_statuses ) ) {
			$statuses = array_diff( $statuses, $skip_statuses );
		}

		$query_args    = array(
			'post_type'			=> $post_type,
			'post_status'		=> $statuses,
			'fields'			=> 'ids',
			'author'   			=> $user->ID,
			'posts_per_page'	=> 10,
			'paged'     		=> $page,
			'orderby'  			=> 'ID',
			'order'	   			=> 'ASC'
		);

		$query_args = apply_filters( 'geodir_privacy_post_data_exporter_post_query', $query_args, $post_type, $email_address, $page );

		$posts = get_posts( $query_args );

		return apply_filters( 'geodir_privacy_post_data_exporter_posts', $posts, $query_args, $post_type, $email_address, $page );
	}

	public static function review_data_exporter( $response, $exporter_index, $email_address, $page, $request_id, $send_as_email, $exporter_key ) {
		global $wpdb;

		$exporter_key = GeoDir_Privacy::personal_data_exporter_key();

		if ( $exporter_key == 'wordpress-comments' && ! empty( $response['data'] ) ) {
			foreach ( $response['data'] as $key => $data ) {
				$comment_id = str_replace( 'comment-', '', $data['item_id'] );

				$review = geodir_get_review( $comment_id );
				if ( ! empty( $review ) ) {
					if ( ! empty( $review->overall_rating ) ) {
						$response['data'][ $key ]['data'][] = array(
							'name'  => __( 'Rating (Overall)', 'geodirectory' ),
							'value' => (float)$review->overall_rating . ' / 5',
						);
					}
					if ( defined( 'GEODIRREVIEWRATING_VERSION' ) ) {
						if ( get_option( 'geodir_reviewrating_enable_rating' ) ) {
							$ratings = maybe_unserialize( $review->ratings );

							if ( ! empty( $ratings ) && $rating_ids = array_keys( $ratings ) ) {
								$styles = $wpdb->get_results( "SELECT rc.id, rc.title, rs.star_number AS total FROM `" . GEODIR_REVIEWRATING_CATEGORY_TABLE . "` AS rc LEFT JOIN `" . GEODIR_REVIEWRATING_STYLE_TABLE . "` AS rs ON rs.id = rc.category_id WHERE rc.id IN(" . implode( ',', $rating_ids ) . ") AND rs.id IS NOT NULL" );

								foreach ( $styles as $style ) {
									if ( ! empty( $ratings[ $style->id ] ) ) {
										$response['data'][ $key ]['data'][] = array(
											'name'  => wp_sprintf( __( 'Rating (%s)', 'geodirectory' ), __( $style->title, 'geodirectory' ) ),
											'value' => $ratings[ $style->id ] . ' / ' . $style->total,
										);
									}
								}
							}
						}

						if ( get_option( 'geodir_reviewrating_enable_images' ) ) {
							if ( ! empty( $review->comment_images ) ) {
								$comment_images = explode( ',', $review->comment_images );
								$response['data'][ $key ]['data'][] = array(
									'name'  => __( 'Review Images', 'geodirectory' ),
									'value' => self::parse_files_value( $comment_images ),
								);
							}
						}
					}
					if ( ! empty( $review->post_city ) ) {
						$response['data'][ $key ]['data'][] = array(
							'name'  => __( 'Review City', 'geodirectory' ),
							'value' => $review->post_city,
						);
					}
					if ( ! empty( $review->post_region ) ) {
						$response['data'][ $key ]['data'][] = array(
							'name'  => __( 'Review Region', 'geodirectory' ),
							'value' => $review->post_region,
						);
					}
					if ( ! empty( $review->post_country ) ) {
						$response['data'][ $key ]['data'][] = array(
							'name'  => __( 'Review Country', 'geodirectory' ),
							'value' => $review->post_country,
						);
					}
					if ( ! empty( $review->post_latitude ) ) {
						$response['data'][ $key ]['data'][] = array(
							'name'  => __( 'Review Latitude', 'geodirectory' ),
							'value' => $review->post_latitude,
						);
					}
					if ( ! empty( $review->post_longitude ) ) {
						$response['data'][ $key ]['data'][] = array(
							'name'  => __( 'Review Longitude', 'geodirectory' ),
							'value' => $review->post_longitude,
						);
					}
				}
			}
		}
		return $response;
	}

	public static function parse_files_value( $files ) {
		if ( empty( $files ) ) {
			return '';
		}

		if ( ! is_array( $files ) ) {
			return $files;
		}

		if ( count( $files ) == 1 ) {
			return $files[0];
		}

		$links = array();
		foreach ( $files as $file ) {
			if ( false === strpos( $file, ' ' ) && ( 0 === strpos( $file, 'http://' ) || 0 === strpos( $file, 'https://' ) ) ) {
				$file = '<a href="' . esc_url( $file ) . '">' . esc_html( $file ) . '</a>';
			}
			$links[] = $file;
		}
		$links = ! empty( $links ) ? implode( ' <br> ', $links ) : '';

		return $links;
	}
}