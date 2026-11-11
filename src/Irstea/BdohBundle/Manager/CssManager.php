<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohBundle\Manager;

use Irstea\BdohDataBundle\Entity\Observatoire;

/**
 * This class allows to :
 *   => load the 'observatoire' context ;
 *   => manage the current 'observatoire' ;
 *   => manage the css file of an 'observatoire' ;.
 */
class CssManager
{
    /**
     * @var array
     */
    protected $files;

    /**
     * @var string
     */
    protected $cssPath;

    /**
     * @var string
     */
    protected $webRoot;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $variables;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var bool
     */
    protected $failsafe;

    /**
     * @param array  $files
     * @param string $cssPath
     * @param string $webRoot
     * @param array  $options
     * @param array  $variables
     * @param mixed  $debug
     */
    public function __construct(array $files, $cssPath, $webRoot, array $options, array $variables = [], $debug = false)
    {
        $this->files = $files;
        $this->cssPath = $cssPath;
        $this->webRoot = $webRoot;
        $this->options = $options;
        $this->variables = $variables;
        $this->debug = $debug;
    }

    /**
     * Gets CSS file path, relative to the web directory.
     * If this file doesn't exist, creates it !
     *
     * @param Observatoire|null $obs
     *
     * @return string
     */
    public function getObservatoireCssFile(?Observatoire $obs = null)
    {
        list($webPath, $localPath) = $this->getPaths($obs);
        if ($this->debug || !file_exists($localPath)) {
            $this->updateObservatoireCssFile($obs);
        }

        return $webPath;
    }

    /**
     * Gets CSS file path, relative to the web directory.
     * If this file doesn't exist, creates it !
     *
     * @param Observatoire|null $obs
     *
     * @throws \Less_Exception_Parser
     */
    public function updateObservatoireCssFile(?Observatoire $obs = null)
    {
        list(, $localPath) = $this->getPaths($obs);

        $opts = $this->options;
        $opts['output'] = $localPath;
        $opts['compress'] = !$this->debug;

        try {
            \Less_Cache::Get($this->files, $opts, $this->getVariables($obs));
        } catch (\Less_Exception_Parser $ex) {
            $ex->genMessage();
            file_put_contents($localPath, sprintf("/* LESS compilation error:\n\n%s\n*/", $ex->getMessage()));
            if (!$this->failsafe) {
                $this->failsafe = true;
                throw $ex;
            }
        }
    }

    /**
     * @param Observatoire|null $obs
     */
    public function removeObservatoireCssFile(?Observatoire $obs = null)
    {
        list(, $localPath) = $this->getPaths($obs);
        if (file_exists($localPath)) {
            unlink($localPath);
        }
    }

    /**
     * @param Observatoire|null $obs
     *
     * @return array
     */
    private function getVariables(?Observatoire $obs = null)
    {
        $vars = $this->variables;
        if ($obs !== null) {
            $vars['primaryColor'] = $obs->getCouleurPrimaire() ?: $vars['primaryColor'];
            $vars['secondaryColor'] = $obs->getCouleurSecondaire() ?: $vars['secondaryColor'];
        }

        return array_merge($vars, self::palette($vars['primaryColor']));
    }

    /** L'ancienne couleur par défaut des observatoires, que la plupart ont gardée sans la choisir */
    private const OLD_DEFAULT = '#003a80';
    /** Le bleu pétrole de la charte d'INRAE (septembre 2020, p. 14), qui la remplace */
    private const INRAE_PETROL = '#275662';

    /**
     * Les couleurs d'un thème, toutes tirées de la couleur primaire de l'observatoire (dev/decisions.md, « L'apparence
     * se modernise, le visage reste ») : la couleur elle-même (primaryColor) ; inkColor, la même assombrie jusqu'au
     * contraste de 4,5 sur blanc (WCAG 2, niveau AA), pour le texte, les liens, les barres et les boutons ; tintColor,
     * très pâle, pour les fonds ; lineColor, pour les filets. L'ancienne couleur par défaut devient le bleu pétrole.
     *
     * @return array{primaryColor: string, inkColor: string, tintColor: string, lineColor: string}
     */
    public static function palette(string $color): array
    {
        $rgb = self::rgb($color) ?? self::rgb(self::INRAE_PETROL);
        if (self::hex($rgb) === self::OLD_DEFAULT) {
            $rgb = self::rgb(self::INRAE_PETROL);
        }
        $ink = $rgb;
        while (self::contrast($ink, [255, 255, 255]) < 4.5) {
            $ink = self::mix($ink, [0, 0, 0], 0.06);
        }

        return [
            'primaryColor' => self::hex($rgb),
            'inkColor'     => self::hex($ink),
            'tintColor'    => self::hex(self::mix($rgb, [255, 255, 255], 0.9)),
            'lineColor'    => self::hex(self::mix($rgb, [255, 255, 255], 0.6)),
        ];
    }

    /** @return int[]|null [r, g, b], de « #rrggbb » ou « #rgb » */
    private static function rgb(string $color): ?array
    {
        $hex = ltrim(trim($color), '#');
        if (preg_match('/^[0-9a-f]{3}$/i', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return null;
        }

        return array_map('hexdec', str_split($hex, 2));
    }

    private static function hex(array $rgb): string
    {
        return '#' . implode('', array_map(fn ($v) => sprintf('%02x', (int) round($v)), $rgb));
    }

    /** $a mêlé de $b dans la proportion $t (0 : $a, 1 : $b) */
    private static function mix(array $a, array $b, float $t): array
    {
        return array_map(fn ($x, $y) => $x * (1 - $t) + $y * $t, $a, $b);
    }

    /** Contraste de deux couleurs, selon WCAG 2 (de 1 à 21) */
    private static function contrast(array $a, array $b): float
    {
        $luminance = function (array $rgb): float {
            [$r, $g, $b] = array_map(function ($v) {
                $v /= 255;

                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, $rgb);

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        };
        [$light, $dark] = [max($luminance($a), $luminance($b)), min($luminance($a), $luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }

    /**
     * @param Observatoire|null $obs
     *
     * @return array [string, string]
     */
    private function getPaths(?Observatoire $obs = null)
    {
        $slug = $obs !== null ? $obs->getSlug() : 'home';
        $webPath = $this->cssPath . $slug . ($this->debug ? '' : '.min') . '.css';
        $localPath = $this->webRoot . $webPath;

        return [$webPath, $localPath];
    }
}
