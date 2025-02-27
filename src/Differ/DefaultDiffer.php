<?php

declare (strict_types=1);
namespace Rector\Core\Differ;

use RectorPrefix20211118\SebastianBergmann\Diff\Differ;
use RectorPrefix20211118\SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
final class DefaultDiffer
{
    /**
     * @var \SebastianBergmann\Diff\Differ
     */
    private $differ;
    public function __construct()
    {
        $strictUnifiedDiffOutputBuilder = new \RectorPrefix20211118\SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder(['fromFile' => 'Original', 'toFile' => 'New']);
        $this->differ = new \RectorPrefix20211118\SebastianBergmann\Diff\Differ($strictUnifiedDiffOutputBuilder);
    }
    public function diff(string $old, string $new) : string
    {
        if ($old === $new) {
            return '';
        }
        return $this->differ->diff($old, $new);
    }
}
