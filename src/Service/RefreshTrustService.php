<?php

namespace SyncEngine\WordPress\Service;

class RefreshTrustService extends Singleton
{
	const OPTION_CONNECTION_REFS = 'syncengine_known_connection_refs';

	/**
	 * @param array<int, string> $refs
	 */
	public function rememberConnectionRefs( array $refs ): void
	{
		$existing = $this->getConnectionRefs();
		$normalized = $this->normalizeRefs( $refs );

		if ( empty( $normalized ) ) {
			return;
		}

		$merged = array_values( array_unique( array_merge( $existing, $normalized ) ) );
		sort( $merged );

		if ( $merged !== $existing ) {
			update_option( self::OPTION_CONNECTION_REFS, $merged );
		}
	}

	public function isTrustedConnectionRef( string $ref ): bool
	{
		$ref = $this->normalizeRef( $ref );
		if ( '' === $ref ) {
			return false;
		}

		return in_array( $ref, $this->getConnectionRefs(), true );
	}

	/**
	 * @return array<int, string>
	 */
	private function getConnectionRefs(): array
	{
		$refs = get_option( self::OPTION_CONNECTION_REFS, [] );
		if ( ! is_array( $refs ) ) {
			return [];
		}

		$refs = $this->normalizeRefs( $refs );
		sort( $refs );

		return $refs;
	}

	/**
	 * @param array<int, mixed> $refs
	 * @return array<int, string>
	 */
	private function normalizeRefs( array $refs ): array
	{
		$refs = array_map( fn ( $ref ) => $this->normalizeRef( (string) $ref ), $refs );
		$refs = array_values( array_unique( array_filter( $refs ) ) );

		return $refs;
	}

	private function normalizeRef( string $ref ): string
	{
		return strtolower( trim( $ref ) );
	}
}