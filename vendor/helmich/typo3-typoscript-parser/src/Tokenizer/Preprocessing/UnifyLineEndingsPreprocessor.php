<?php

declare (strict_types=1);
namespace RectorPrefix20211118\Helmich\TypoScriptParser\Tokenizer\Preprocessing;

/**
 * Preprocessor that unifies line endings for a file
 *
 * @package Helmich\TypoScriptParser\Tokenizer\Preprocessing
 */
class UnifyLineEndingsPreprocessor implements \RectorPrefix20211118\Helmich\TypoScriptParser\Tokenizer\Preprocessing\Preprocessor
{
    /** @var string */
    private $eolCharacter;
    public function __construct(string $eolCharacter = "\n")
    {
        $this->eolCharacter = $eolCharacter;
    }
    /**
     * @param string $contents Un-processed Typoscript contents
     * @return string Processed TypoScript contents
     */
    public function preprocess($contents) : string
    {
        return \preg_replace(",(\r\n|\r|\n),", $this->eolCharacter, $contents);
    }
}
