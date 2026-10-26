<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Irstea\BdohAdminBundle\Form\Type\FileType;
use Irstea\BdohAdminBundle\Form\Type\LinkType;
use Irstea\BdohDataBundle\Entity\Partenaire as PartenaireEntity;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Validator\ErrorElement;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Class Partenaire.
 */
class Partenaire extends Admin
{
    /**
     * Configure the ordered field.
     */
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        parent::configureDefaultSortValues($sortValues);
        $sortValues['_sort_by'] = 'nom';  // name of the ordered field
        // (default = the model's id field, if any)
    }

    /**
     * Les chroniques qui utilisent ce partenaire.
     */
    private $timeSeries = null;

    /**
     * @return array|null
     */
    public function getTimeSeries()
    {
        if ($this->timeSeries === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->timeSeries = $this->getBdohRepo('Chronique')->findByProducteur($this->getSubject());
        }

        return $this->timeSeries;
    }

    /**
     * Les observatoires qui utilisent ce partenaire.
     */
    private $observatories = null;

    /**
     * @return array|null
     */
    public function getObservatories()
    {
        if ($this->observatories === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->observatories = $this->getBdohRepo('Observatoire')->findByPartenaire($this->getSubject());
        }

        return $this->observatories;
    }

    protected function configureBatchActions(array $actions): array
    {
        if ($this->hasRequest() && $this->getRequest()->get('no_batch')) {
            return [];
        }

        return parent::configureBatchActions($actions);
    }

    public function getExportFormats(): array
    {
        if ($this->hasRequest() && $this->getRequest()->get('no_batch')) {
            return [];
        }

        return parent::getExportFormats();
    }

    public function getFundings()
    {
        $subject = $this->getSubject();
        $financeur = (bool) $subject->getEstFinanceur();

        return $financeur;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-partenaire.html.twig');
        $this->setTemplate('delete', '@IrsteaBdohAdmin/CRUD/delete-partenaire.html.twig');
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationPartenaire(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.PartenaireCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationPartenaire(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.PartenaireUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationPartenaire(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.PartenaireRemove',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function alterNewInstance(object $object): void
    {
        $object->setObservatoire(
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('nom', null, ['route' => ['name' => 'edit']])
            ->add('lien')
            ->add('iso')
            ->add('estFinanceur')
            ->add('observatoire');
    }

    public function validate(ErrorElement $errorElement, $object)
    {
        $errorElement
                ->with('typeFundings')
                ->assertNotNull([
                    'message'            => 'Compléter le champs',
                ])
                ->end();
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        /* @var $subject PartenaireEntity */
        $translator = $this->getContainer()->get('translator');

        $formMapper
            ->add('nom')
            // Sans protocole par défaut, le champ reste de type url, comme sous Symfony 2
            ->add('lien', LinkType::class, ['required' => false])
            // image widget
            ->add(
                'pathLogo',
                FileType::class,
                [
                    'required' => false,
                    'accept'   => 'image/*,.png,.jpg,.gif',
                    'label'    => 'fileLogo',
                ]
            )
            ->end()
            ->with('Pays', ['class' => 'col-md-6'])
            // Les pays, code => nom, retournés en nom => code (choix de Symfony 3 et plus)
            ->add('iso', ChoiceType::class, [
                'choices' => array_flip([
                    'FR' => 'France',
                    'AF' => 'Afghanistan',
                    'AL' => 'Albanie',
                    'DZ' => 'Algérie',
                    'AS' => 'Samoa orientales',
                    'AD' => 'Andorre',
                    'AO' => 'Angola',
                    'AI' => 'Anguilla',
                    'AQ' => 'Antarctique',
                    'AG' => 'Antigua-et-Barbuda',
                    'AR' => 'Argentine',
                    'AM' => 'Arménie',
                    'AW' => 'Aruba',
                    'AU' => 'Australie',
                    'AT' => 'Autriche',
                    'AZ' => 'Azerbaïdjan',
                    'BS' => 'Bahamas',
                    'BH' => 'Bahreïn',
                    'BD' => 'Bangladesh',
                    'BB' => 'Barbade',
                    'BY' => 'Biélorussie',
                    'BE' => 'Belgique',
                    'BZ' => 'Bélize',
                    'BJ' => 'Bénin',
                    'BM' => 'Bermudes',
                    'BT' => 'Bhoutan',
                    'BO' => 'Bolivie',
                    'BA' => 'Bosnie-Herzégovine',
                    'BW' => 'Botswana',
                    'BV' => 'Bouvet (Île)',
                    'BR' => 'Brésil',
                    'BN' => 'Bruneï',
                    'BG' => 'Bulgarie',
                    'BF' => 'Burkina Faso',
                    'BI' => 'Burundi',
                    'KH' => 'Cambodge',
                    'CM' => 'Cameroun',
                    'CA' => 'Canada',
                    'CV' => 'Cap Vert',
                    'KY' => 'Caïmans (Îles)',
                    'CF' => 'République Centrafricaine',
                    'TD' => 'Tchad',
                    'CL' => 'Chili',
                    'CN' => 'Chine',
                    'CX' => 'Christmas (Île)',
                    'CC' => 'Cocos / Keeling (Îles)',
                    'CO' => 'Colombie',
                    'KM' => 'Comores',
                    'CG' => 'Congo',
                    'CD' => 'République Démocratique du Congo',
                    'CK' => 'Cook (Îles)',
                    'CR' => 'Costa Rica',
                    'CI' => 'Côte D Ivoire',
                    'HR' => 'Croatie',
                    'CU' => 'Cuba',
                    'CY' => 'Chypre',
                    'CZ' => 'République Tchèque',
                    'DK' => 'Danemark',
                    'DJ' => 'Djibouti',
                    'DM' => 'Dominique',
                    'DO' => 'République Dominicaine',
                    'TP' => 'Timor-Oriental',
                    'EC' => 'Equateur',
                    'EG' => 'Egypte',
                    'SV' => 'Salvador',
                    'GQ' => 'Guinée Equatoriale',
                    'ER' => 'Erythrée',
                    'EE' => 'Estonie',
                    'ET' => 'Ethiopie',
                    'FK' => 'Falkland / Malouines (Îles)',
                    'FO' => 'Féroé (Îles)',
                    'FJ' => 'Fiji',
                    'FI' => 'Finlande',
                    'FX' => 'France métropolitaine',
                    'GF' => 'Guyane française',
                    'PF' => 'Polynésie française',
                    'TF' => 'Territoires Antarctiques français',
                    'GA' => 'Gabon',
                    'GM' => 'Gambie',
                    'GE' => 'Géorgie',
                    'DE' => 'Allemagne',
                    'GH' => 'Ghana',
                    'GI' => 'Gibraltar',
                    'GR' => 'Grèce',
                    'GL' => 'Groënland',
                    'GD' => 'Grenade',
                    'GP' => 'Guadeloupe',
                    'GU' => 'Guam',
                    'GT' => 'Guatemala',
                    'GN' => 'Guinée',
                    'GW' => 'Guinée-Bissau',
                    'GY' => 'Guyana',
                    'HT' => 'Haïti',
                    'HM' => 'Territoire des Îles Heard et McDonald',
                    'VA' => 'Vatican',
                    'HN' => 'Honduras',
                    'HK' => 'Hong Kong',
                    'HU' => 'Hongrie',
                    'IS' => 'Islande',
                    'IN' => 'Inde',
                    'ID' => 'Indonésie',
                    'IR' => 'Iran',
                    'IQ' => 'Irak',
                    'IE' => 'Irlande',
                    'IL' => 'Israël',
                    'IT' => 'Italie',
                    'JM' => 'Jamaïque',
                    'JP' => 'Japon',
                    'JO' => 'Jordanie',
                    'KZ' => 'Kazakstan',
                    'KE' => 'Kenya',
                    'KI' => 'Kiribati',
                    'KP' => 'Corée (République populaire démocratique du)',
                    'KR' => 'Corée (République démocratique du)',
                    'KW' => 'Koweït',
                    'KG' => 'Kyrgyzstan',
                    'LA' => 'Laos (République populaire démocratique du)',
                    'LV' => 'Lettonie',
                    'LB' => 'Liban',
                    'LS' => 'Lesotho',
                    'LR' => 'Libéria',
                    'LY' => 'Libye (Jamahiriya Arabe Libyenne)',
                    'LI' => 'Liechtenstein',
                    'LT' => 'Lithuanie',
                    'LU' => 'Luxembourg',
                    'MO' => 'Macau',
                    'MK' => 'Macédoine (ancienne République yougoslave de)',
                    'MG' => 'Madagascar',
                    'MW' => 'Malawi',
                    'MY' => 'Malaysie',
                    'MV' => 'Maldives',
                    'ML' => 'Mali',
                    'MT' => 'Malte',
                    'MH' => 'Marshall (Îles)',
                    'MQ' => 'Martinique',
                    'MR' => 'Mauritanie',
                    'MU' => 'Maurice',
                    'YT' => 'Mayotte',
                    'MX' => 'Mexique',
                    'FM' => 'Micronésie (Etats fédérés de)',
                    'MD' => 'Moldavie',
                    'MC' => 'Monaco',
                    'MN' => 'Mongolie',
                    'MS' => 'Montserrat',
                    'MA' => 'Maroc',
                    'MZ' => 'Mozambique',
                    'MM' => 'Myanmar',
                    'NA' => 'Namibie',
                    'NR' => 'Nauru',
                    'NP' => 'Népal',
                    'NL' => 'Pays-Bas',
                    'AN' => 'Antilles néerlandaises',
                    'NC' => 'Nouvelle-Calédonie',
                    'NZ' => 'Nouvelle-Zélande',
                    'NI' => 'Nicaragua',
                    'NE' => 'Niger',
                    'NG' => 'Nigéria',
                    'NU' => 'Niue',
                    'NF' => 'Norfolk (Île)',
                    'MP' => 'Mariannes (Îles)',
                    'NO' => 'Norvège',
                    'OM' => 'Oman',
                    'PK' => 'Pakistan',
                    'PW' => 'Palau',
                    'PA' => 'Panama',
                    'PG' => 'Papouasie Nouvelle-Guinée',
                    'PY' => 'Paraguay',
                    'PE' => 'Pérou',
                    'PH' => 'Philippines',
                    'PN' => 'Pitcaïrn',
                    'PL' => 'Pologne',
                    'PT' => 'Portugal',
                    'PR' => 'Porto Rico',
                    'QA' => 'Quatar',
                    'RE' => 'Réunion',
                    'RO' => 'Romania',
                    'RU' => 'Russie (Fédération de)',
                    'RW' => 'Rwanda',
                    'KN' => 'Saint Kitts et Nevis',
                    'LC' => 'Sainte Lucie',
                    'VC' => 'Saint Vincent et Grenadines',
                    'WS' => 'Samoa',
                    'SM' => 'San Marin',
                    'ST' => 'Sao Tomé et Principe',
                    'SA' => 'Arabie Séoudite',
                    'SN' => 'Sénégal',
                    'SC' => 'Seychelles',
                    'SL' => 'Sierra Léone',
                    'SG' => 'Singapour',
                    'SK' => 'Slovaquie',
                    'SI' => 'Slovénie',
                    'SB' => 'Salomon (Îles)',
                    'SO' => 'Somalie',
                    'ZA' => 'Afrique du Sud',
                    'GS' => 'Géorgie du Sud et îles Sandwich du Sud',
                    'ES' => 'Espagne',
                    'LK' => 'Sri Lanka',
                    'SH' => 'Ste Hélène',
                    'PM' => 'St. Pierre et Miquelon',
                    'SD' => 'Soudan',
                    'SR' => 'Surinam',
                    'SJ' => 'Svalbard et Jan Mayen (Îles)',
                    'SZ' => 'Swaziland',
                    'SE' => 'Suède',
                    'CH' => 'Suisse',
                    'SY' => 'Syrie (République arabe syrienne)',
                    'TW' => 'Taïwan',
                    'TJ' => 'Tadjikistan',
                    'TZ' => 'Tanzanie',
                    'TH' => 'Thaïlande',
                    'TG' => 'Togo',
                    'TK' => 'Tokelau',
                    'TO' => 'Tonga',
                    'TT' => 'Trinidad et Tobago',
                    'TN' => 'Tunisie',
                    'TR' => 'Turquie',
                    'TM' => 'Turkménistan',
                    'TC' => 'Turks et Caicos (Îles)',
                    'TV' => 'Tuvalu',
                    'UG' => 'Ouganda',
                    'UA' => 'Ukraine',
                    'AE' => 'Emirats Arabes Unis',
                    'GB' => 'Royaume-Uni',
                    'US' => 'Etats-Unis',
                    'UM' => 'Territoires non incorporés des États-Unis ',
                    'UY' => 'Uruguay',
                    'UZ' => 'Ouzbékistan',
                    'VU' => 'Vanuatu',
                    'VE' => 'Vénézuela',
                    'VN' => 'Vietnam',
                    'VG' => 'Vierges (Îles) - RU',
                    'VI' => 'Vierges (Îles) - EU',
                    'WF' => 'Wallis et Futuna (Îles)',
                    'EH' => 'Sahara Occidental',
                    'YE' => 'Yemen',
                    'YU' => 'Yougoslavie',
                    'ZM' => 'Zambie',
                    'ZW' => 'Zimbabwe',
                ]),
            ])
            ->add(
                'scanR',
                TextType::class,
                [
                    'required'  => false,
                    'help'      => $translator->trans('admin.help.partenaire.help-scanR'),
                    'help_html' => true,
                ]
            )
            ->end()
            ->with('Financeur/Organisation', ['class' => 'col-md-6'])
            ->add('estFinanceur', ChoiceType::class, [
                'expanded' => true,

                'choices' => [
                    'Non' => false,
                    'Oui' => true,
                ],
                'help'      => $translator->trans('admin.help.partenaire.help-boolean'),
                'help_html' => true,
            ])
            ->add('typeFundings', null, [
                'required'    => true,
            ])
            ->end();
    }
}
