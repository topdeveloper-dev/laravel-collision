<?php

/**
 * This file is part of Collision.
 *
 * (c) Nuno Maduro <enunomaduro@gmail.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace NunoMaduro\Collision;

use Facade\IgnitionContracts\ProvidesSolution;
use Facade\IgnitionContracts\Solution;
use NunoMaduro\Collision\Contracts\ArgumentFormatter as ArgumentFormatterContract;
use NunoMaduro\Collision\Contracts\Highlighter as HighlighterContract;
use NunoMaduro\Collision\Contracts\Writer as WriterContract;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Whoops\Exception\Frame;
use Whoops\Exception\Inspector;

/**
 * This is an Collision Writer implementation.
 *
 * @author Nuno Maduro <enunomaduro@gmail.com>
 */
class Writer implements WriterContract
{
    /**
     * The number of frames if no verbosity is specified.
     */
    const VERBOSITY_NORMAL_FRAMES = 1;

    /**
     * Holds an instance of the Output.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * Holds an instance of the Argument Formatter.
     *
     * @var \NunoMaduro\Collision\Contracts\ArgumentFormatter
     */
    protected $argumentFormatter;

    /**
     * Holds an instance of the Highlighter.
     *
     * @var \NunoMaduro\Collision\Contracts\Highlighter
     */
    protected $highlighter;

    /**
     * Ignores traces where the file string matches one
     * of the provided regex expressions.
     *
     * @var string[]
     */
    protected $ignore = [];

    /**
     * Declares whether or not the trace should appear.
     *
     * @var bool
     */
    protected $showTrace = true;

    /**
     * Declares whether or not the editor should appear.
     *
     * @var bool
     */
    protected $showEditor = true;

    /**
     * Creates an instance of the writer.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $output
     * @param  \NunoMaduro\Collision\Contracts\ArgumentFormatter|null  $argumentFormatter
     * @param  \NunoMaduro\Collision\Contracts\Highlighter|null  $highlighter
     */
    public function __construct(
        OutputInterface $output = null,
        ArgumentFormatterContract $argumentFormatter = null,
        HighlighterContract $highlighter = null
    ) {
        $this->output = $output ?: new SymfonyStyle(new ArrayInput([]), new ConsoleOutput);
        $this->argumentFormatter = $argumentFormatter ?: new ArgumentFormatter;
        $this->highlighter = $highlighter ?: new Highlighter;
    }

    /**
     * {@inheritdoc}
     */
    public function write(Inspector $inspector): void
    {
        $this->renderTitle($inspector);

        $this->renderSolution($inspector);

        $frames = $this->getFrames($inspector);

        $editorFrame = array_shift($frames);

        if ($this->showEditor && $editorFrame !== null) {
            $this->renderEditor($editorFrame);
        }

        if ($this->showTrace && ! empty($frames)) {
            $this->renderTrace($frames);
        } else {
            $this->output->writeln('');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function ignoreFilesIn(array $ignore): WriterContract
    {
        $this->ignore = $ignore;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function showTrace(bool $show): WriterContract
    {
        $this->showTrace = $show;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function showEditor(bool $show): WriterContract
    {
        $this->showEditor = $show;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutput(OutputInterface $output): WriterContract
    {
        $this->output = $output;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * Returns pertinent frames.
     *
     * @param  \Whoops\Exception\Inspector  $inspector
     *
     * @return array
     */
    protected function getFrames(Inspector $inspector): array
    {
        return $inspector->getFrames()
            ->filter(
                function ($frame) {
                    foreach ($this->ignore as $ignore) {
                        if (preg_match($ignore, $frame->getFile())) {
                            return false;
                        }
                    }

                    return true;
                }
            )
            ->getArray();
    }

    /**
     * Renders the title of the exception.
     *
     * @param  \Whoops\Exception\Inspector  $inspector
     *
     * @return \NunoMaduro\Collision\Contracts\Writer
     */
    protected function renderTitle(Inspector $inspector): WriterContract
    {
        $exception = $inspector->getException();
        $message = $exception->getMessage();
        $class = $inspector->getExceptionName();

        $this->render("<fg=red;options=bold>$class</>");
        $this->output->writeln('');
        $this->output->writeln("<fg=default;options=bold>  $message</>");

        return $this;
    }

    /**
     * Renders the solution of the exception, if any.
     *
     * @param  \Whoops\Exception\Inspector  $inspector
     *
     * @return \NunoMaduro\Collision\Contracts\Writer
     */
    protected function renderSolution(Inspector $inspector): WriterContract
    {
        $throwable = $inspector->getException();
        $solutions = [];

        if ($throwable instanceof Solution) {
            $solutions[] = $throwable;
        }

        if ($throwable instanceof ProvidesSolution) {
            $solutions[] = $throwable->getSolution();
        }

        foreach ($solutions as $solution) {
            $this->output->newline();
            /** @var \Facade\IgnitionContracts\Solution $solution */
            $title = $solution->getSolutionTitle();
            $description = $solution->getSolutionDescription();
            $links = $solution->getDocumentationLinks();

            $this->output->block("  $title \n  $description", null, 'fg=black;bg=green', ' ', true);

            foreach ($links as $link) {
                $this->render($link);
            }
        }

        return $this;
    }

    /**
     * Renders the editor containing the code that was the
     * origin of the exception.
     *
     * @param  \Whoops\Exception\Frame  $frame
     *
     * @return \NunoMaduro\Collision\Contracts\Writer
     */
    protected function renderEditor(Frame $frame): WriterContract
    {
        $this->render('at <fg=green>'.$frame->getFile().'</>'.':<fg=green>'.$frame->getLine().'</>');

        $content = $this->highlighter->highlight((string) $frame->getFileContents(), (int) $frame->getLine());

        $this->output->writeln($content);

        return $this;
    }

    /**
     * Renders the trace of the exception.
     *
     * @param  array  $frames
     *
     * @return \NunoMaduro\Collision\Contracts\Writer
     */
    protected function renderTrace(array $frames): WriterContract
    {
        $this->render('<comment>Stack trace:</comment>');

        $vendorFrames = 0;
        foreach ($frames as $i => $frame) {
            if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE && strpos($frame->getFile(), '/vendor/') !== false) {
                $vendorFrames++;
                continue;
            }


            if ($vendorFrames > 0) {
                $pos = str_pad((int) $vendorFrames + 1, 4, ' ');
                $description = 'vendor frames...';
                $this->render("<fg=default>$pos$description</>");
                $vendorFrames = 0;
            }

            if ($i > static::VERBOSITY_NORMAL_FRAMES && $this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
                $this->render('<info>Please use the argument <fg=red>-v</> to see more details.</info>');
                break;
            }

            $file = $frame->getFile();
            $line = $frame->getLine();
            $class = empty($frame->getClass()) ? '' : $frame->getClass().'::';
            $function = $frame->getFunction();
            $args = $this->argumentFormatter->format($frame->getArgs());
            $pos = str_pad((int) $i + 1, 4, ' ');

            $this->render("<fg=green>$pos$class$function($args)</>");
            $this->render("    $file:$line", false);
        }

        return $this;
    }

    /**
     * Renders an message into the console.
     *
     * @param  string  $message
     * @param  bool  $break
     *
     * @return $this
     */
    protected function render(string $message, bool $break = true): WriterContract
    {
        if ($break) {
            $this->output->writeln('');
        }

        $this->output->writeln("  $message");

        return $this;
    }

    /**
     * Formats a message as a block of text.
     *
     * @param  string|array  $messages The message to write in the block
     * @param  string|null  $type The block type (added in [] on first line)
     * @param  string|null  $style The style to apply to the whole block
     * @param  string  $prefix The prefix for the block
     * @param  bool  $padding Whether to add vertical padding
     * @param  bool  $escape Whether to escape the message
     */
    protected function block($messages, $type = null, $style = null, $prefix = ' ', $padding = false, $escape = true)
    {
        $messages = \is_array($messages) ? array_values($messages) : [$messages];

        $this->autoPrependBlock();
        $this->writeln($this->createBlock($messages, $type, $style, $prefix, $padding, $escape));
        $this->newLine();
    }
}
