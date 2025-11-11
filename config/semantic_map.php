<?php

return [

    'job_offers' => [
        'languages' => [
            'via' => 'job_offer_language.language_id = languages.id',
            'pivot' => 'job_offer_language',
            'type' => 'many-to-many',
            'transitive' => ['courses', 'careers'],
        ],
        'technologies' => [
            'via' => 'job_offer_technology.technology_id = technologies.id',
            'pivot' => 'job_offer_technology',
            'type' => 'many-to-many',
            'transitive' => ['courses', 'careers'],
        ],
        'cities' => [
            'via' => 'job_offers.city = cities.city',
            'type' => 'one-to-one',
        ],
    ],

    'languages' => [
        'courses' => [
            'via' => 'course_language.language_id = languages.id',
            'type' => 'many-to-many',
        ],
        'careers' => [
            'via' => 'career_course.course_id = course_language.course_id',
            'type' => 'transitive',
        ],
    ],

    'technologies' => [
        'courses' => [
            'via' => 'course_technology.technology_id = technologies.id',
            'type' => 'many-to-many',
        ],
        'careers' => [
            'via' => 'career_course.course_id = course_technology.course_id',
            'type' => 'transitive',
        ],
    ],
];
