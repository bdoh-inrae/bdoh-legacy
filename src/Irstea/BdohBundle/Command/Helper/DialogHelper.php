<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Command\Helper;

use Symfony\Component\Console\Helper\DialogHelper as BaseDialogHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dialog helper for Bdoh project.
 */
class DialogHelper extends BaseDialogHelper
{
    /**
     * @var Symfony\Component\Translation\TranslatorInterface
     */
    protected $translator;

    /**
     * @param Symfony\Component\Translation\TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Shortcut to translate a message.
     *
     * @param mixed $message
     * @param mixed $catalog
     */
    protected function trans($message, array $params = [], $catalog = 'commands')
    {
        return $this->translator->trans($message, $params, $catalog);
    }

    /**
     * Let a list of elements, each identified by a unique key.
     * Asks user to select one of these keys.
     * Then, returns the pair [key, corresponding element].
     *
     * If $emptySelection parameter is true, the user is allowed to select nothing.
     * Then, the pair [-1, NULL] is returned.
     *
     * If $freeSelection parameter is true, the user can input any string.
     * Then, the pair [-1, free string] is returned.
     *
     * If $stopSelection parameter is true, the user can stop the process without any selection.
     * Then, the pair [-1, -1] is returned.
     *
     * @param OutputInterface $output
     * @param string          $title
     * @param array           $elements
     * @param bool            $emptySelection
     * @param bool            $freeSelection
     * @param bool            $stopSelection
     *
     * @return array A pair : 1st element = key selected ; 2nd = the corresponding element
     */
    public function getSelection(
        OutputInterface $output,
        $title,
        array $elements,
        $emptySelection = false,
        $freeSelection = false,
        $stopSelection = false
    ) {
        $translator = $this->translator;
        $emptyKey = $this->trans('selection.empty.key');
        $emptyMsg = $this->trans('selection.empty.msg');
        $freeKey = $this->trans('selection.free.key');
        $freeMsg = $this->trans('selection.free.msg');
        $stopKey = $this->trans('selection.stop.key');
        $stopMsg = $this->trans('selection.stop.msg');

        if ($emptySelection) {
            $elements[$emptyKey] = $emptyMsg;
        }
        if ($freeSelection) {
            $elements[$freeKey] = $freeMsg;
        }
        if ($stopSelection) {
            $elements[$stopKey] = $stopMsg;
        }

        // Computes sprintf pattern for a good display of selection list
        $align = max(array_map('strlen', array_keys($elements))) + 2;
        $pattern = '  <comment>%-' . $align . 's  %s</comment>';

        // Displays the selection list
        $text = ['<info>' . $title . ' :</info>'];
        foreach ($elements as $key => $value) {
            $text[] = sprintf($pattern, "[$key]", $value);
        }
        $output->writeln($text);

        // Asks a key to the user until its validation
        $keySelected = $this->askAndValidate(
            $output,
            '<question>' . $this->trans('selection.ask.key') . '</question> ',
            function ($keySelected) use ($elements, $translator) {
                if ('' === $keySelected) {
                    throw new \Exception(
                        $translator->trans('selection.error.empty', [], 'commands')
                    );
                } elseif (false === array_key_exists($keySelected, $elements)) {
                    throw new \Exception(
                        $translator->trans('selection.error.invalid(%key%)', ['%key%' => $keySelected], 'commands')
                    );
                }

                return $keySelected;
            }
        );

        // Treatment of user selection
        switch ($keySelected) {
            case $stopKey:
                return [-1, -1];
            case $emptyKey:
                return [-1, null];
            case $freeKey:
                return [-1, $this->ask($output, '<question>' . $this->trans('selection.ask.element') . '</question> ')];
            default:
                return [$keySelected, $elements[$keySelected]];
        }
    }

    public function getRunner(OutputInterface $output, &$errors)
    {
        $runner = function ($err) use ($output, &$errors) {
            if ($err) {
                $output->writeln('<fg=red>FAILED</>');
                $errors = array_merge($errors, $err);
            } else {
                $output->writeln('<info>OK</info>');
            }
        };

        return $runner;
    }

    public function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(
            [
                '',
                $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true),
                '',
            ]
        );
    }

    public function getQuestion($question, $default, $sep = ':')
    {
        return $default ? sprintf('<info>%s</info> [<comment>%s</comment>]%s ', $question, $default, $sep) :
            sprintf('<info>%s</info>%s ', $question, $sep);
    }
}
