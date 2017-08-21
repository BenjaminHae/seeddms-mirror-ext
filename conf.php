<?php
$EXT_CONF['export'] = array(
	'title' => 'Export Extension',
	'description' => 'This extension exports all documents to a folder for syncing',
	'disable' => false,
	'version' => '0.0.1',
	'releasedate' => '2017-08-21',
	'author' => array('name'=>'Benjamin Häublein', 'email'=>'benjaminhaeublein@gmail.com', 'company'=>''),
	'config' => array(
		'input_field' => array(
			'title'=>'Export folder',
			'type'=>'input',
			'size'=>20,
		),
//		'checkbox' => array(
//			'title'=>'Example check box',
//			'type'=>'checkbox',
//		),
	),
	'constraints' => array(
		'depends' => array('php' => '5.4.4-', 'seeddms' => '5.0.0-'),
	),
	'icon' => 'icon.png',
	'class' => array(
		'file' => 'class.export.php',
		'name' => 'SeedDMS_ExtExport'
	),
	'language' => array(
		'file' => 'lang.php',
	),
);
?>
