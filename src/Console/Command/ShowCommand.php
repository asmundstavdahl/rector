<?php

declare (strict_types=1);
namespace Rector\Core\Console\Command;

use Rector\Core\Contract\Console\OutputStyleInterface;
use Rector\Core\Contract\Rector\RectorInterface;
use Rector\PostRector\Contract\Rector\ComplementaryRectorInterface;
use Rector\PostRector\Contract\Rector\PostRectorInterface;
use RectorPrefix20211118\Symfony\Component\Console\Command\Command;
use RectorPrefix20211118\Symfony\Component\Console\Input\InputInterface;
use RectorPrefix20211118\Symfony\Component\Console\Output\OutputInterface;
final class ShowCommand extends \RectorPrefix20211118\Symfony\Component\Console\Command\Command
{
    /**
     * @var \Rector\Core\Contract\Console\OutputStyleInterface
     */
    private $outputStyle;
    /**
     * @var \Rector\Core\Contract\Rector\RectorInterface[]
     */
    private $rectors;
    /**
     * @param RectorInterface[] $rectors
     */
    public function __construct(\Rector\Core\Contract\Console\OutputStyleInterface $outputStyle, array $rectors)
    {
        $this->outputStyle = $outputStyle;
        $this->rectors = $rectors;
        parent::__construct();
    }
    protected function configure() : void
    {
        $this->setDescription('Show loaded Rectors with their configuration');
    }
    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute($input, $output) : int
    {
        $this->outputStyle->title('Loaded Rector rules');
        $rectors = \array_filter($this->rectors, function (\Rector\Core\Contract\Rector\RectorInterface $rector) : bool {
            if ($rector instanceof \Rector\PostRector\Contract\Rector\PostRectorInterface) {
                return \false;
            }
            return !$rector instanceof \Rector\PostRector\Contract\Rector\ComplementaryRectorInterface;
        });
        $rectorCount = \count($rectors);
        if ($rectorCount === 0) {
            $warningMessage = \sprintf('No Rectors were loaded.%sAre sure your "rector.php" config is in the root?%sTry "--config <path>" option to include it.', \PHP_EOL . \PHP_EOL, \PHP_EOL);
            $this->outputStyle->warning($warningMessage);
            return self::SUCCESS;
        }
        $rectorCount = \count($rectors);
        foreach ($rectors as $rector) {
            $this->outputStyle->writeln(' * ' . \get_class($rector));
        }
        $message = \sprintf('%d loaded Rectors', $rectorCount);
        $this->outputStyle->success($message);
        return \RectorPrefix20211118\Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
