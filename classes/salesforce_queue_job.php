<?php

use WP_Queue\Job;

class Salesforce_Queue_Job extends Job {

	/**
	 * @var array
	 */
	public $data;

	/**
	 * @var string
	 */
	public $direction;

	/**
	 * Salesforce_Queue_Job constructor.
	 */
	public function __construct( $data, $direction ) {
		$this->data      = $data;
		$this->direction = $direction;
	}

	/**
	 * Handle job logic.
	 */
	public function handle() {

		error_log( 'data is ' . print_r( $this->data, true ) . ' and direction is ' . $this->direction );

        /*$item = wp_parse_args( $this->data, array(
            'post_id' => 0,
            'width'   => 0,
            'height'  => 0,
            'crop'    => false,
        ) );

        $post_id = $item['post_id'];
        $width   = $item['width'];
        $height  = $item['height'];
        $crop    = $item['crop'];

        if ( ! $width && ! $height ) {
            throw new Exception( "Invalid dimensions '{$width}x{$height}'" );
        }

        if ( Queue::does_size_already_exist_for_image( $post_id, array( $width, $height, $crop ) ) ) {
            return false;
        }

        $image_meta = Queue::get_image_meta( $post_id );

        if ( ! $image_meta ) {
            return false;
        }*/

        add_filter( 'as3cf_get_attached_file_copy_back_to_local', '__return_true' );
        $img_path = Queue::get_image_path( $post_id );

        if ( ! $img_path ) {
            return false;
        }

        $editor = wp_get_image_editor( $img_path );

        if ( is_wp_error( $editor ) ) {
            throw new Exception( 'Unable to get WP_Image_Editor for file "' . $img_path . '": ' . $editor->get_error_message() . ' (is GD or ImageMagick installed?)' );
        }

        $resize = $editor->resize( $width, $height, $crop );

        if ( is_wp_error( $resize ) ) {
            throw new Exception( 'Error resizing image: ' . $resize->get_error_message() );
        }

        $resized_file = $editor->save();

        if ( is_wp_error( $resized_file ) ) {
            throw new Exception( 'Unable to save resized image file: ' . $editor->get_error_message() );
        }

        $size_name = Queue::get_size_name( array( $width, $height, $crop ) );
        $image_meta['sizes'][ $size_name ] = array(
            'file'      => $resized_file['file'],
            'width'     => $resized_file['width'],
            'height'    => $resized_file['height'],
            'mime-type' => $resized_file['mime-type'],
        );

        unset( $image_meta['ipq_locked'] );
        wp_update_attachment_metadata( $post_id, $image_meta );
	}

}
