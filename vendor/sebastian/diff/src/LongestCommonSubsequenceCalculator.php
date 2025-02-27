<?php

declare (strict_types=1);
/*
 * This file is part of sebastian/diff.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace RectorPrefix20211118\SebastianBergmann\Diff;

interface LongestCommonSubsequenceCalculator
{
    /**
     * Calculates the longest common subsequence of two arrays.
     * @param mixed[] $from
     * @param mixed[] $to
     */
    public function calculate($from, $to) : array;
}
