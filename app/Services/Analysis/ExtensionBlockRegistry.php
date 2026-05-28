<?php

namespace App\Services\Analysis;

/**
 * Registro dos blocos de extensão reconhecidos no resultado da análise.
 *
 * Modularidade: para suportar um novo bloco opcional numa futura versão do
 * prompt, basta adicioná-lo aqui. Blocos não registrados são descartados pelo
 * validador (degradação graciosa), nunca persistidos às cegas.
 */
class ExtensionBlockRegistry
{
    /** Bloco de alertas socioemocionais/pedagógicos (extraído para linhas). */
    public const BLOCK_ALERTAS = 'alertas';

    /** Bloco de evidências textuais por indicador. */
    public const BLOCK_EVIDENCIAS = 'evidencias';

    /** Bloco de confiança por indicador (0-100). */
    public const BLOCK_CONFIANCA = 'confianca';

    /**
     * Chaves de bloco de extensão reconhecidas.
     *
     * @return list<string>
     */
    public function knownBlocks(): array
    {
        return [
            self::BLOCK_ALERTAS,
            self::BLOCK_EVIDENCIAS,
            self::BLOCK_CONFIANCA,
        ];
    }

    /**
     * Indica se uma chave de bloco é reconhecida.
     */
    public function isKnown(string $key): bool
    {
        return in_array($key, $this->knownBlocks(), true);
    }
}
