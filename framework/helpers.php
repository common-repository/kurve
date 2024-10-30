<?php

if ( ! function_exists( 'strsMakeSlug' ) ) {
	function strsMakeSlug( string $string ) : string
	{
		$slug = str_replace( ' ', '-', $string );
		$slug = preg_replace( '/[^\w\d\-\_]/i', '', $slug );

		return strtolower( $slug );
	}
}

if ( ! function_exists( 'strsMakeMenuSlug' ) ) {
	function strsMakeMenuSlug( string $string, string $separator = '_' ) : string
	{
		return KRV_SLUG . $separator . strsMakeSlug( $string );
	}
}
