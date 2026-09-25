<?php
declare(strict_types=1);
// Local visual fixtures only; never inserted into the database.
return [
    'categories' => [
        ['id'=>'camera','name'=>'Cameras','image_path'=>'media/rentals-sony-alpha.jpg','description'=>'Camera equipment for production.'],
        ['id'=>'lighting','name'=>'Lighting','image_path'=>'media/rentals-lighting-modifiers.jpg','description'=>'Lighting equipment for controlled setups.'],
    ],
    'items' => [
        ['id'=>'preview-camera','name'=>'Sony camera','category'=>'camera','image_path'=>'media/rentals-sony-camera.jpg','description'=>'Camera imagery supplied for layout testing. Confirm the exact model with 3AM.','availability_status'=>'Preview only','is_sample'=>true],
        ['id'=>'preview-alpha','name'=>'Sony Alpha camera','category'=>'camera','image_path'=>'media/rentals-sony-alpha-7.jpg','description'=>'Camera imagery supplied for layout testing.','availability_status'=>'Preview only','is_sample'=>true],
        ['id'=>'preview-vlogging','name'=>'Compact camera','category'=>'camera','image_path'=>'media/rentals-vlogging-camera.webp','description'=>'Compact camera imagery supplied for layout testing.','availability_status'=>'Preview only','is_sample'=>true],
        ['id'=>'preview-lighting','name'=>'LED lighting','category'=>'lighting','image_path'=>'media/rentals-led-panels.jpg','description'=>'LED panel imagery supplied for layout testing.','availability_status'=>'Preview only','is_sample'=>true],
    ],
    'services' => [],
];
