<?php
namespace DWCatalog\Db;

/**
 * PDO connection — MySQL / MariaDB / PostgreSQL. Real prepared statements, typed binding.
 */
class PdoConnection extends AbstractConnection {

	/** @var \PDO */
	private $pdo;

	public function __construct( \PDO $pdo, string $prefix, ?Dialect $dialect = null ) {
		$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		$pdo->setAttribute( \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC );
		$pdo->setAttribute( \PDO::ATTR_EMULATE_PREPARES, false );
		$this->pdo = $pdo;
		if ( null === $dialect ) {
			$dialect = 'pgsql' === $pdo->getAttribute( \PDO::ATTR_DRIVER_NAME ) ? new PostgresDialect() : new MysqlDialect();
		}
		parent::__construct( $prefix, $dialect );
	}

	/**
	 * @param array $cfg driver (mysql|pgsql), host, port, dbname, user, password, prefix, charset
	 */
	public static function fromConfig( array $cfg ): self {
		$driver = isset( $cfg['driver'] ) && 'pgsql' === $cfg['driver'] ? 'pgsql' : 'mysql';
		$host   = isset( $cfg['host'] ) ? $cfg['host'] : 'localhost';
		$dsn    = $driver . ':host=' . $host . ';dbname=' . $cfg['dbname'];
		if ( ! empty( $cfg['port'] ) ) {
			$dsn .= ';port=' . (int) $cfg['port'];
		}
		if ( 'mysql' === $driver ) {
			$dsn .= ';charset=' . ( isset( $cfg['charset'] ) ? $cfg['charset'] : 'utf8mb4' );
		}
		$pdo = new \PDO( $dsn, isset( $cfg['user'] ) ? $cfg['user'] : '', isset( $cfg['password'] ) ? $cfg['password'] : '' );
		if ( 'pgsql' === $driver ) {
			$pdo->exec( "SET client_encoding TO 'UTF8'" );
		}
		return new self( $pdo, isset( $cfg['prefix'] ) ? $cfg['prefix'] : 'dwcat_' );
	}

	public function select( string $sql, array $params = array() ): array {
		return $this->run( $sql, $params )->fetchAll();
	}

	public function execute( string $sql, array $params = array() ): int {
		return $this->run( $sql, $params )->rowCount();
	}

	public function insert( string $table, array $row ): int {
		$cols = array_keys( $row );
		$sql  = 'INSERT INTO ' . $this->table( $table ) . ' (' . implode( ', ', array_map( array( $this->dialect, 'quoteIdent' ), $cols ) ) . ') VALUES (' . implode( ', ', array_fill( 0, count( $cols ), '?' ) ) . ')';
		$auto = Schema::hasAutoId( $table );
		if ( $this->dialect instanceof PostgresDialect ) {
			if ( $auto ) {
				return (int) $this->run( $sql . ' RETURNING id', array_values( $row ) )->fetchColumn();
			}
			$this->run( $sql, array_values( $row ) );
			return 0;
		}
		$this->run( $sql, array_values( $row ) );
		return $auto ? (int) $this->pdo->lastInsertId() : 0;
	}

	public function transaction( callable $fn ) {
		if ( $this->pdo->inTransaction() ) {
			return $fn( $this );
		}
		$this->pdo->beginTransaction();
		try {
			$result = $fn( $this );
			$this->pdo->commit();
			return $result;
		} catch ( \Throwable $e ) {
			$this->pdo->rollBack();
			throw $e;
		}
	}

	private function run( string $sql, array $params ): \PDOStatement {
		list( $sql, $flat ) = $this->expand( $sql, $params );
		$stmt               = $this->pdo->prepare( $sql );
		foreach ( $flat as $i => $v ) {
			if ( null === $v ) {
				$type = \PDO::PARAM_NULL;
			} elseif ( is_int( $v ) || is_bool( $v ) ) {
				$type = \PDO::PARAM_INT;
				$v    = (int) $v;
			} else {
				$type = \PDO::PARAM_STR;
				$v    = (string) $v;
			}
			$stmt->bindValue( $i + 1, $v, $type );
		}
		$stmt->execute();
		return $stmt;
	}
}
