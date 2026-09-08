<?php

declare(strict_types=1);

return [
    'corpus' => [
        'name' => 'Vigilance Behavioral Analysis - Corpus legal RDC',
        'updated_at' => '2026-07-03',
        'scope' => 'Corpus cible pour la conformité du module d analyse comportementale et pour les points de droit a verifier dans un entretien interne. Ce corpus n est pas une base exhaustive de tout le droit congolais.',
        'usage_note' => 'La lecture legale RDC est une aide interne de conformite et de qualification preliminaire. Elle ne remplace ni un avocat, ni le parquet, ni une decision judiciaire.',
        'sources' => [
            [
                'id' => 'constitution_2006',
                'type' => 'official',
                'title' => 'Constitution de la Republique Democratique du Congo du 18 fevrier 2006, telle que revisee en 2011',
                'source_url' => 'https://www.presidence.cd/uploads/files/Constitution%20de%20la%203me%20Republique.%2018%20Fev%202006.pdf',
                'articles' => [
                    [
                        'article' => 'Art. 16',
                        'summary' => 'La dignite, la vie et l integrite physique doivent etre respectees; les traitements cruels, inhumains ou degradants sont prohibes.',
                    ],
                    [
                        'article' => 'Art. 19',
                        'summary' => 'Le droit de la defense est garanti a tous les niveaux de la procedure, y compris l enquete policiere et l instruction pre juridictionnelle.',
                    ],
                    [
                        'article' => 'Art. 31',
                        'summary' => 'La vie privee et le secret des correspondances, telecommunications et autres communications sont proteges; toute atteinte doit etre prevue par la loi.',
                    ],
                ],
            ],
            [
                'id' => 'telecom_2020',
                'type' => 'secondary-citation',
                'title' => 'Loi n°20/017 du 25 novembre 2020 relative aux telecommunications et aux technologies de l information et de la communication',
                'source_url' => 'https://www.leganet.cd/Doctrine.textes/Decon/Numerique/LaprotectiondesdonneespersonnellesenRDC.pdf',
                'note' => 'Le lien pointe vers une analyse juridique Leganet qui cite les articles pertinents de la loi 20/017.',
                'articles' => [
                    [
                        'article' => 'Art. 126 (cite dans la doctrine)',
                        'summary' => 'Le secret des correspondances emises par voie de telecommunications est protege et ne peut etre leve que dans les cas prevus par la loi.',
                    ],
                    [
                        'article' => 'Art. 153 (cite dans la doctrine)',
                        'summary' => 'Les atteintes a la confidentialite des systemes et aux donnees a caractere personnel sont rattachees au regime de cybercriminalite.',
                    ],
                ],
            ],
            [
                'id' => 'code_numerique_2023',
                'type' => 'secondary-citation',
                'title' => 'Ordonnance-Loi n°23/010 du 13 mars 2023 portant Code du Numerique',
                'source_url' => 'https://www.leganet.cd/Doctrine.textes/Decon/Numerique/LaprotectiondesdonneespersonnellesenRDC.pdf',
                'note' => 'Le lien pointe vers une analyse juridique Leganet citant le Code du Numerique et ses articles sur les donnees personnelles et le consentement.',
                'articles' => [
                    [
                        'article' => 'Art. 183 (cite dans la doctrine)',
                        'summary' => 'Les photographies, enregistrements sonores, images et autres donnees biometriques sont traitees comme donnees personnelles.',
                    ],
                    [
                        'article' => 'Art. 192 (cite dans la doctrine)',
                        'summary' => 'Le traitement des donnees personnelles doit reposer sur le consentement ou une base legale et se faire dans le respect de la dignite humaine, de la vie privee et des libertes publiques.',
                    ],
                ],
            ],
        ],
    ],
];
