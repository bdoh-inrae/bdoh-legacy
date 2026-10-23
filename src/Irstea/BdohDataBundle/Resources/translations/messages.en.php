<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

$entities = require __DIR__ . '/entitiesSingulars.en.php';
$fields = require __DIR__ . '/fields.en.php';

/*************************************************
 * 'CHRONIQUE' TYPES
 ************************************************/

$chroniqueTypes = [
    'chronique.type.continue'    => 'Continuous time series',
    'chronique.type.discontinue' => 'Discontinuous time series',
    'chronique.type.calculee'    => 'Calculated time series',
    'chronique.type.convertie'   => 'Converted time series',
    'type.continue'              => 'continuous',
    'type.discontinue'           => 'discontinuous',
    'type.calculee'              => 'calculated',
    'type.convertie'             => 'converted',
];

/*************************************************
 * FILE FORMATS
 ************************************************/

$fileFormats = [
    'export' => [
        'Bdoh'      => 'BDOH',
        'Qtvar'     => 'Hydro2 - QTVAR',
        'Vigilance' => 'Vigilance',
    ],
];

/*************************************************
 * EXPORTS
 ************************************************/

$export = [
    'measure.export'  => [
        'title'        => '[0,1] Download this time series | ]1,Inf] Download the time series',
        'help'         => 'Help',
        'help-title'   => 'Help - Download time series',
        'help-content' => <<<HELP
<ul>
    <li>The time zone for the selection of start/end dates and hours is UTC.</li>
    <li>The default option for download is "identical time step":
        <ul>
            <li>the original time step of the time series is conserved (it is potentially variable)</li>
            <li>several time series belonging to a same station can be downloaded at the same time</li>
            <li>the file is directly downloaded from BDOH without any size restriction.</li>
        </ul>
    </li>
    <li>Some time series can be downloaded with an option for fixed time step calculation (using linear interpolation, mean calculation or sum calculation):</li>
    <ul>
        <li>only one time series can be downloaded at a time</li>
        <li>in order not to block the server, each download is limited to a maximum span of 2 years per minute of time step, i.e. 1051200 values (~ 10<sup>6</sup> lines)</li>
        <li>if a calculation is needed on a longer span, the download must be performed onto a series of smaller spans</li>
        <li>data are sent by e-mail as attached file</li>
        <li>the options for fiexed time step calculation are: (i) for linear interpolation, a time step between 1 and 1440 minutes; (ii) for mean and sum calculations, one of the time steps available in the drop-down list. The interpolated values start at a round time. Option “\u{a0}event\u{a0}” calculates the mean or sum of the values in the time range of the event (1 output value).</li>
    </ul>
    <li>One separate file is downloaded for each time series.</li>
    <li>For downloads of continuous time series with identical time step or linear interpolation, several file formats are available (BDOH standard or Hydro2 QTVAR).</li>
    <li>For fix time steps with mean/sum calculation options, the only possible file format is "BDOH discontinu".</li>
</ul>
HELP
        ,
        'underway'     => 'The download of the time series is underway. You will shortly receive the result by e-mail.',
        'error'        => [
            'abort'                               => "For technical reasons the download couldn't be achieved.",
            'badExportType'                       => 'Download of the time series: incorrect time step for extraction.',
            'badTimeStep'                         => 'Download of the time series: incorrect time step.',
            'badTimeStep(%min%, %max%)'           => 'Download of the time series: the time step must be between %min% and %max%.',
            'badTimeStepForExportType'            => 'Download of the time series: incorrect time step for this extraction type.',
            'badTimezone'                         => 'Download of the time series: incorrect time zone.',
            'badBeginDate'                        => 'Download of the time series: incorrect start date.',
            'badCumulStartTime'                   => 'Download of the time series: bad start time of accumulations.',
            'badEndDate'                          => 'Download of the time series: incorrect end date.',
            'badFormat'                           => 'Download of the time series: incorrect format.',
            'noChronique'                         => 'Download of the time series: no selected time series.',
            'badChronique(%chroniqueId%)'         => "The time series %chroniqueId% does not exist or is not available.\n",
            'isNotSupported(%chronique%, Plage)'  => "The continuous time series “\u{a0}%chronique%\u{a0}” can not be downloaded with this format.\n",
            'isNotSupported(%chronique%, Mesure)' => "The discontinuous time series “\u{a0}%chronique%\u{a0}” can not be downloaded with this format.\n",
            'measureDirectionNotSpecified'        => 'Choice of dates for extraction (at the beginning or end of time intervals) not specified.',
            'timeStepNotSpecified'                => 'Time step not specified.',
            'noStartingTime'                      => 'Please specify at which type of instant the extracted data must start.',
        ],
        'job_queued(%job_id%)' => 'The download of the time series is underway. You will shortly receive the result by e-mail. (Job #%job_id%)',
        'report'               => [
            'headline' => [
                '1(%format%, %date%)' => "Download from %date% UTC in the format “\u{a0}%format%\u{a0}”",
                '2(%begin%, %end%)'   => 'Requested period: from %begin% UTC to %end% UTC',
                '3(%timezone%)'       => 'Timezone: %timezone% ',
            ],

            'chronique(%chronique%)' => "Time series “\u{a0}%chronique%\u{a0}”:",

            'chroniqueCalculee(%chronique%)' => "Calculated time series “\u{a0}%chronique%\u{a0}”:",

            'measures(%number%,%first%,%last%)' => '[0,0]No data points found|' .
                '[1,1]One data point found|' .
                ']1,Inf]%number% data points found, from %first% to %last%',

            'file(%file%)' => 'File: %file%',

            'doi' => [
                'header(%observatoire%)'    => "DOI related to the observatory “\u{a0}%observatoire%\u{a0}”:",
                'item(%doi%,%description%}' => 'dx.doi.org/%doi% [ %description% ]',
            ],

            'resampled' => [
                'header'                   => 'Download made with fixed time steps',
                'type(%type%)'             => 'Sampling type: %type%',
                'timestep'                 => 'Time step:',
                'timestep-var(%timestep%)' => '[0,1]%timestep% minute|]1,Inf]%timestep% minutes',
                'count(%count%)'           => '[0,0]No data points extracted|' .
                    '[1,1]One data point extracted|' .
                    ']1,Inf]%count% data points extracted',
                'types'                    => [
                    'instantaneous' => 'linear interpolation',
                    'mean'          => 'mean',
                    'cumulative'    => 'accumulation',
                ],
            ],

            'chronique.lq_ld'               => [
                'options'                      => [
                    'true_value'  => 'use of the original values',
                    'half_value'  => 'values divided by 2',
                    'placeholder' => 'values replaced by the placeholder value ',
                    'gap'         => 'values processed as invalid data',
                ],
            ],

            'calculated' => [
                'input'                       => [
                    'unique(%chronique%)'     => "Single parent time series: “\u{a0}%chronique%\u{a0}”",
                    'first(%chronique%)'      => "First parent time series: “\u{a0}%chronique%\u{a0}”",
                    'second(%chronique%)'     => "Second parent time series: “\u{a0}%chronique%\u{a0}”",
                ],
                'limit_policy(%limitPolicy%)' => 'Transformation of quantification/detection limits: %limitPolicy%',

                'bareme' => [
                    'header(%bareme%)'             => "Scale “\u{a0}%bareme%\u{a0}”",
                    'created(%dateCreation%)'      => ', as of %dateCreation% UTC',
                    'applied'                      => ', applied ',
                    'from(%dateDebut%)'            => 'from %dateDebut% UTC onward',
                    'range(%dateDebut%,%dateFin%)' => 'from %dateDebut% UTC to %dateFin% UTC',
                ],
            ],

            'error'                  => 'An error happened during the extraction:',
            'chronique.noHydro2Code' => "    -> WARNING: no “\u{a0}Hydro2 code\u{a0}” found; “\u{a0}station code\u{a0}” used instead.\n\n",
        ],
    ],
    'controle.export' => [
        'title' => 'Exporting checkpoints',
    ],
    'bareme.export'   => [
        'report' => [
            'main(%format%, %date%, %chronicle%)' => "Download from %date% UTC for the calculated time series “\u{a0}%chronicle%\u{a0}”\n" .
                "Qualities are in the format “\u{a0}%format%\u{a0}”",
            'bareme'                              => [
                'base(%bareme%, %dateCreation%, %inputUnit%, %outputUnit%, %number%, %min%, %max%)' => "\n\nScale “\u{a0}%bareme%\u{a0}” created the %dateCreation% UTC\n" .
                    "    -> Input unit: %inputUnit%\n" .
                    "    -> Output unit: %outputUnit%\n" .
                    "    -> %number% downloaded lines for values in abscissa ranging from %min% to %max%\n",
                'parentChronicle(%parentChronicle%)'                                                => "    -> Parent time series: “\u{a0}%parentChronicle%\u{a0}”\n",
                'applyFromTo(%dateBegin%, %dateEnd%)'                                               => "    -> Apply from %dateBegin% UTC to %dateEnd% UTC\n",
                'applyFrom(%dateBegin%)'                                                            => "    -> Apply from %dateBegin% UTC\n",
                'notApplied'                                                                        => "    -> Not applied for this calculated time series\n",
                'outputFile(%file%)'                                                                => '    -> File: %file%',
            ],
        ],
        'error'  => [
            'badBareme(%id%)' => 'Scale #%id% unknown',
        ],
    ],
];

$jobs = [
    'job'             => [
        'title'         => [
            'list'       => 'Jobs',
            'show(%id%)' => 'Job #%id%',
        ],
        'state'         => [
            'new'        => 'New',
            'pending'    => 'Pending',
            'ready'      => 'Ready',
            'canceled'   => 'Canceled',
            'running'    => 'Running',
            'finished'   => 'Finished',
            'failed'     => 'Failed',
            'terminated' => 'Timed out',
            'incomplete' => 'Error',
        ],
        'state.tooltip' => [
            'new'        => 'The job has just been created.',
            'pending'    => 'The job is pending its programmed date or another job.',
            'ready'      => 'The job will be run as soon as possible.',
            'canceled'   => 'The job has been canceled by the user or an administrator.',
            'running'    => 'The job is running.',
            'finished'   => 'The job has succesfully finished.',
            'failed'     => 'The job failed.',
            'terminated' => 'The job ran longer than its allowed duration.',
            'incomplete' => 'An indenpendent error happened during the job processing.',
        ],
        'id'            => 'Number',
        'owner'         => 'Requester',
        'observatoire'  => 'Observatory',
        'chronicles'    => 'Time series',
        'created_at'    => 'Created at',
        'started_at'    => 'Started at',
        'closed_at'     => 'Closed at',
        'queue'         => 'Queue',
        'priority'      => 'Priority',
        'command'       => 'Command',
        'arguments'     => 'Arguments',
        'runtime'       => 'Run time (s)',
        'output'        => 'Message(s)',
        'error_output'  => 'Error message(s)',
        'exit_code'     => 'Exit code',
        'actions'       => 'Actions',
        'button.retry'  => 'Retry',
        'description'   => [
            'bdoh:compute:filling_rates' => 'Data completenes calculation',
            'bdoh:compute:chronique'     => 'Time series calculation',
            'bdoh:convert:chronique'     => 'Time series conversion',
            'bdoh:export:identical'      => 'Data download as is',
            'bdoh:export:instantaneous'  => 'Data download with linear interpolation',
            'bdoh:export:mean'           => 'Data download with mean calculation',
            'bdoh:export:cumulative'     => 'Data download with sum calculation',
        ],
        'refreshInfo'   => 'Please remember to refresh the page to view the jobs progression.',
        'refreshPage'   => 'Refresh the page',
        'jobList'       => 'Job list',
    ],
    'job.description' => 'Description',
    'job.state'       => 'State',
];

/*************************************************
 * RETURNED MESSAGES
 ************************************************/

return array_merge($entities, $fields, $chroniqueTypes, $fileFormats, $export, $jobs);
