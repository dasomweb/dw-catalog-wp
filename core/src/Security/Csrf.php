<?php
namespace DWCatalog\Security;

use DWCatalog\Host\Host;

/**
 * CSRF tokens for admin forms: HMAC(secret, user · action · 12h window). Valid for the
 * current and previous window (up to 24h), bound to the user and the action.
 */
final class Csrf {

	const FIELD = '_dwcat_token';

	/** @var Host */
	private $host;

	public function __construct( Host $host ) {
		$this->host = $host;
	}

	public function token( string $action ): string {
		return $this->make( $action, $this->tick() );
	}

	public function verify( string $action, $token ): bool {
		if ( ! is_string( $token ) || '' === $token || 0 === $this->host->userId() ) {
			return false;
		}
		$tick = $this->tick();
		return hash_equals( $this->make( $action, $tick ), $token ) || hash_equals( $this->make( $action, $tick - 1 ), $token );
	}

	public function field( string $action ): string {
		return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars( $this->token( $action ), ENT_QUOTES, 'UTF-8' ) . '">';
	}

	private function tick(): int {
		return (int) floor( time() / 43200 );
	}

	private function make( string $action, int $tick ): string {
		return substr( hash_hmac( 'sha256', $this->host->userId() . '|' . $action . '|' . $tick, $this->host->secret() ), 0, 32 );
	}
}
