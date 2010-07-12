<?php

	class Extension_Field_XMLSelectBox implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'XML Select Box',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-07-09',
				'author'		=> (object)array(
					'name'			=> 'Nick Dunn, Brendan Abbott',
					'website'		=> 'http://nick-dunn.co.uk',
					'email'			=> 'nick@nick-dunn.co.uk'
				),
				'description' => 'A select box field that takes it\'s values from XML documents',
				'type'			=> array(
					'Field'
				),
			);
		}

		public function __construct() {
			Field::load(EXTENSIONS . '/field_selectbox/fields/field.select.php');
		}
	}

	return 'Extension_Field_XMLSelectBox';