<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace RectorPrefix20211118\Symfony\Component\Console\Helper;

use RectorPrefix20211118\Symfony\Component\Console\Exception\InvalidArgumentException;
/**
 * @author Yewhen Khoptynskyi <khoptynskyi@gmail.com>
 */
class TableCellStyle
{
    public const DEFAULT_ALIGN = 'left';
    private $options = ['fg' => 'default', 'bg' => 'default', 'options' => null, 'align' => self::DEFAULT_ALIGN, 'cellFormat' => null];
    private $tagOptions = ['fg', 'bg', 'options'];
    private $alignMap = ['left' => \STR_PAD_RIGHT, 'center' => \STR_PAD_BOTH, 'right' => \STR_PAD_LEFT];
    public function __construct(array $options = [])
    {
        if ($diff = \array_diff(\array_keys($options), \array_keys($this->options))) {
            throw new \RectorPrefix20211118\Symfony\Component\Console\Exception\InvalidArgumentException(\sprintf('The TableCellStyle does not support the following options: \'%s\'.', \implode('\', \'', $diff)));
        }
        if (isset($options['align']) && !\array_key_exists($options['align'], $this->alignMap)) {
            throw new \RectorPrefix20211118\Symfony\Component\Console\Exception\InvalidArgumentException(\sprintf('Wrong align value. Value must be following: \'%s\'.', \implode('\', \'', \array_keys($this->alignMap))));
        }
        $this->options = \array_merge($this->options, $options);
    }
    public function getOptions() : array
    {
        return $this->options;
    }
    /**
     * Gets options we need for tag for example fg, bg.
     *
     * @return string[]
     */
    public function getTagOptions()
    {
        return \array_filter($this->getOptions(), function ($key) {
            return \in_array($key, $this->tagOptions) && isset($this->options[$key]);
        }, \ARRAY_FILTER_USE_KEY);
    }
    public function getPadByAlign()
    {
        return $this->alignMap[$this->getOptions()['align']];
    }
    public function getCellFormat() : ?string
    {
        return $this->getOptions()['cellFormat'];
    }
}
