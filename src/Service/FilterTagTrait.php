<?php

namespace SyncEngine\WordPress\Service;

trait FilterTagTrait
{
	protected function normalizeFilterTag( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9_]+/', '_', $value );
		return trim( (string) $value, '_' );
	}
}
