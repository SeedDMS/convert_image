<?php
$EXT_CONF['convert_image'] = array(
	'title' => 'Converter from image to pdf',
	'description' => 'Converts image files into pdf using fpdf. This extension adds conversion services from image/png and image/jpg to application/pdf.',
	'disable' => false,
	'version' => '1.0.0',
	'releasedate' => '2025-01-22',
	'author' => array('name'=>'Uwe Steinmann', 'email'=>'uwe@steinmann.cx', 'company'=>'MMK GmbH'),
	'config' => array(
		'papersize' => array(
			'title'=>'Paper size',
			'type'=>'select',
			'options' => array('A4'=>'A4', 'letter'=>'letter'),
			'multiple' => false,
			'size' => 1,
		),
	),
	'constraints' => array(
		'depends' => array('php' => '5.6.40-', 'seeddms' => ['5.1.24-5.1.99', '6.0.17-6.0.99', '6.1.0-']),
	),
	'icon' => 'icon.svg',
	'changelog' => 'changelog.md',
	'class' => array(
		'file' => 'class.convert_image.php',
		'name' => 'SeedDMS_ExtConvertImage'
	),
);
?>
