<?php

declare(strict_types=1);

namespace Rector\Console\Output;

use Nette\Utils\Strings;
use Rector\Application\Error;
use Rector\Application\ErrorAndDiffCollector;
use Rector\Contract\Console\Output\OutputFormatterInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Printer\BetterStandardPrinter;
use Rector\Reporting\FileDiff;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

final class ConsoleOutputFormatter implements OutputFormatterInterface
{
    /**
     * @var string
     */
    public const NAME = 'console';

    /**
     * @var SymfonyStyle
     */
    private $symfonyStyle;

    /**
     * @var BetterStandardPrinter
     */
    private $betterStandardPrinter;

    public function __construct(SymfonyStyle $symfonyStyle, BetterStandardPrinter $betterStandardPrinter)
    {
        $this->symfonyStyle = $symfonyStyle;
        $this->betterStandardPrinter = $betterStandardPrinter;
    }

    public function report(ErrorAndDiffCollector $errorAndDiffCollector): void
    {
        $this->reportFileDiffs($errorAndDiffCollector->getFileDiffs());
        $this->reportErrors($errorAndDiffCollector->getErrors());
        $this->reportRemovedFilesAndNodes($errorAndDiffCollector);

        if ($errorAndDiffCollector->getErrors() !== []) {
            return;
        }

        $this->symfonyStyle->success(sprintf(
            'Rector is done! %d changed files',
            count($errorAndDiffCollector->getFileDiffs()) + $errorAndDiffCollector->getRemovedAndAddedFilesCount()
        ));
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @param FileDiff[] $fileDiffs
     */
    private function reportFileDiffs(array $fileDiffs): void
    {
        if (count($fileDiffs) <= 0) {
            return;
        }

        // normalize
        ksort($fileDiffs);

        $this->symfonyStyle->title(
            sprintf('%d file%s with changes', count($fileDiffs), count($fileDiffs) === 1 ? '' : 's')
        );

        $i = 0;
        foreach ($fileDiffs as $fileDiff) {
            $relativeFilePath = $fileDiff->getRelativeFilePath();

            $this->symfonyStyle->writeln(sprintf('<options=bold>%d) %s</>', ++$i, $relativeFilePath));
            $this->symfonyStyle->newLine();
            $this->symfonyStyle->writeln($fileDiff->getDiffConsoleFormatted());
            $this->symfonyStyle->newLine();

            if ($fileDiff->getAppliedRectorClasses() !== []) {
                $this->symfonyStyle->writeln('Applied rules:');
                $this->symfonyStyle->newLine();
                $this->symfonyStyle->listing($fileDiff->getAppliedRectorClasses());
                $this->symfonyStyle->newLine();
            }
        }
    }

    /**
     * @param Error[] $errors
     */
    private function reportErrors(array $errors): void
    {
        foreach ($errors as $error) {
            $message = sprintf(
                'Could not process "%s" file%s, due to: %s"%s".',
                $error->getFileInfo()->getPathname(),
                $error->getRectorClass() ? ' by "' . $error->getRectorClass() . '"' : '',
                PHP_EOL,
                $error->getMessage()
            );

            if ($error->getLine()) {
                $message .= ' On line: ' . $error->getLine();
            }

            $this->symfonyStyle->error($message);
        }
    }

    private function reportRemovedFilesAndNodes(ErrorAndDiffCollector $errorAndDiffCollector): void
    {
        if ($errorAndDiffCollector->getRemovedAndAddedFilesCount() !== 0) {
            $this->symfonyStyle->note(
                sprintf('%d files were added/removed', $errorAndDiffCollector->getRemovedAndAddedFilesCount())
            );
        }

        $this->reportRemovedNodes($errorAndDiffCollector);
    }

    private function reportRemovedNodes(ErrorAndDiffCollector $errorAndDiffCollector): void
    {
        if ($errorAndDiffCollector->getRemovedNodeCount() === 0) {
            return;
        }

        $this->symfonyStyle->warning(sprintf('%d nodes were removed', $errorAndDiffCollector->getRemovedNodeCount()));

        if ($this->symfonyStyle->isVeryVerbose()) {
            $i = 0;
            foreach ($errorAndDiffCollector->getRemovedNodes() as $removedNode) {
                /** @var SmartFileInfo $fileInfo */
                $fileInfo = $removedNode->getAttribute(AttributeKey::FILE_INFO);

                $this->symfonyStyle->writeln(sprintf(
                    '<options=bold>%d) %s:%d</>',
                    ++$i,
                    $fileInfo->getRelativeFilePath(),
                    $removedNode->getStartLine()
                ));

                $printedNode = $this->betterStandardPrinter->print($removedNode);

                // color red + prefix with "-" to visually demonstrate removal
                $printedNode = '-' . Strings::replace($printedNode, '#\n#', "\n-");
                $printedNode = $this->colorTextToRed($printedNode);

                $this->symfonyStyle->writeln($printedNode);
                $this->symfonyStyle->newLine(1);
            }
        }
    }

    private function colorTextToRed(string $text): string
    {
        return '<fg=red>' . $text . '</fg=red>';
    }
}
