<?php
return array(
    'module_config' => array(
        'asset_location' =>  __DIR__ . '/../res',
        'fallback_location' =>  __DIR__ . '/../res/fallback.jpg',
        'tile_url' =>  'http://tiles.myorg.org/',
        'blank_url' =>  'http://tiles.myorg.org/blank.jpg',
    ),
    'controllers' => array(
        'invokables' => array(
            'ImgHl\Controller\ImgHl' => 'ImgHl\Controller\ImgHlController',
        ),
    ),
     'router' => array(
         'routes' => array(
             'imghl' => array(
                 'type'    => 'segment',
                 'options' => array(
                     'route'    => '/imghl[/:action][/:site][/:collection][/:container][/:reel][/:page][/:w][/:h][/:hl]',
                     'constraints' => array(
                         //actions & site are letters only
                         'action' => '[a-zA-Z]*',
                         'site' => '[a-zA-Z]*',
                         //collections & containers need to start with letter
                         'collection' => '[a-zA-Z][a-zA-Z0-9_-]*',
                         'container' => '[a-zA-Z][a-zA-Z0-9_-]*',
                         //reels & pages can be any letter - number combo
                         'reel' => '[a-zA-Z0-9_-]*',
                         'page' => '[a-zA-Z0-9_-]*',
                         // width & height need to be numbers
                         'w' => '[0-9]*',
                         'h' => '[0-9]*',
                         //no constraints on hl query, may often be odd characters
                     ),
                     'defaults' => array(
                         'controller' => 'ImgHl\Controller\ImgHl',
                         'action'     => 'index',
                     ),
                 ),
             ),
         ),
     ),
    'view_manager' => array(
        'template_path_stack' => array(
            'imghl' => __DIR__ . '/../view',
        ),
    ),
);
