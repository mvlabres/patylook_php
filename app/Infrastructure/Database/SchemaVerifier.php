<?php

declare(strict_types=1);

namespace Patylook\Infrastructure\Database;

use Patylook\Exceptions\SchemaException;

/** Read-only sanity check of the existing database. It never changes it. */
final class SchemaVerifier
{
    /** The width bcrypt hashes need (60 characters). */
    private const MIN_PASSWORD_COLUMN = 60;

    public function __construct(private readonly Database $db)
    {
    }

    /** @throws SchemaException saying what is missing */
    public function verify(): void
    {
        $row = $this->db->selectOne(
            'SELECT CHARACTER_MAXIMUM_LENGTH AS width FROM information_schema.COLUMNS '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'password'",
        );
        if ($row === null) {
            throw new SchemaException('tabela `user` não encontrada no banco configurado (DB_NAME): confira o nome do banco');
        }
        $width = $row['width'];
        if ($width !== null && (int) $width < self::MIN_PASSWORD_COLUMN) {
            throw new SchemaException(sprintf(
                'a coluna user.password tem %d caracteres e não comporta hashes bcrypt (%d); aplique migrations/001_widen_user_password.sql',
                (int) $width,
                self::MIN_PASSWORD_COLUMN,
            ));
        }
        if (!$this->tableExists('product_images')) {
            throw new SchemaException('tabela product_images não encontrada; execute o bloco dela no schema.sql (somente esse bloco)');
        }
        if (!$this->tableExists('brand')) {
            throw new SchemaException('tabela brand não encontrada; execute o bloco dela no schema.sql (somente esse bloco)');
        }
        $n = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND COLUMN_NAME = 'brand_id'",
        );
        if ($n === 0) {
            throw new SchemaException('a coluna product.brand_id não existe; aplique migrations/002_add_product_brand.sql');
        }
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        ) > 0;
    }
}
