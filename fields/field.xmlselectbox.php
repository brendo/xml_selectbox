<?php

	require_once(LIB . '/class.gateway.php');
	require_once(LIB . '/class.cache.php');

	Class FieldXMLSelectbox extends FieldSelect {
		public function __construct(){
			parent::__construct();
			$this->_name = __('XML Select Box');
		}

		/*-------------------------------------------------------------------------
			Utilities:
		-------------------------------------------------------------------------*/

		public function run(DOMXPath $xpath, $node, $query) {
			if($query == "") return null;

			$result = $xpath->evaluate($query, $node);

			if($result instanceof DOMNodeList) {
				foreach($result as $n) return General::sanitize($n->nodeValue);
			}
			else {
			 	return General::sanitize($result);
			}
		}

		public function getToggleStates() {
			return $this->getValuesFromXML();
		}

		public function getValuesFromXML() {

			$xml_location = $this->{'xml-location'};
			$cache_life = (int) $this->{'cache'};

			require(LIB . '/include.validators.php');

			// allow use of choice params in URL
			$xml_location = preg_replace('/{\$root}/', URL, $xml_location);
			$xml_location = preg_replace('/{\$workspace}/', WORKSPACE, $xml_location);

			$doc = new DOMDocument;

			if (preg_match($validators['URI'], $xml_location)) {
				// is a URL, check cache
				$cache_id = md5('xmlselectbox_' . $xml_location);
				$cache = new Cache();
				$cachedData = $cache->check($cache_id);

				if(!$cachedData) {
					$ch = new Gateway;
					$ch->init();
					$ch->setopt('URL', $xml_location);
					$ch->setopt('TIMEOUT', 6);
					$xml = $ch->exec();
					$writeToCache = true;

					$cache->write($cache_id, $xml, $cache_life); // Cache life is in minutes not seconds e.g. 2 = 2 minutes

					$xml = trim($xml);
					if (empty($xml) && $cachedData) $xml = $cachedData['data'];
				} else {
					$xml = $cachedData['data'];
				}

				$doc->loadXML($xml);

			} elseif (substr($xml_location, 0, 1) == '/') {
				// relative to DOCROOT
				$doc->load(DOCROOT . $this->{'xml-location'});
			} else {
				// in extension's /xml folder
				$doc->load(EXTENSIONS . '/field_xmlselectbox/xml/' . $this->{'xml-location'});
			}

			$xpath = new DOMXPath($doc);

			$options = array();

			foreach($xpath->query($this->{'item-xpath'}) as $item) {
				$text = null;
				$handle = null;

				$text = $this->run($xpath, $item, $this->{'text-xpath'});
				$handle = $this->run($xpath, $item, $this->{'value-xpath'});

				if (is_null($handle)) $handle = $text;

				if($item->hasChildNodes()) {
					$option = array();
					$option['label'] = $text;

					foreach($xpath->query('*', $item) as $child) {
						$ctext = $this->run($xpath, $child, $this->{'text-xpath'});
						$chandle = $this->run($xpath, $child, $this->{'value-xpath'});

						if (is_null($chandle)) $chandle = $ctext;

						$option['options'][$handle . "-" . $chandle] = $ctext;
					}

					$options[$handle] = $option;
				}

				else {
					$options[$handle] = $text;
				}
			}
			//var_dump($options);

			return $options;
		}

		/*-------------------------------------------------------------------------
			Settings:
		-------------------------------------------------------------------------*/

			public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $messages) {
				Field::displaySettingsPanel($wrapper, $messages);

				$document = $wrapper->ownerDocument;

				$group = $document->createElement('div');
				$group->setAttribute('class', 'group');

				$label = Widget::Label(__('XML Location'));
				$input = Widget::Input('xml-location', General::sanitize($this->{'xml-location'}));
				$label->appendChild($input);
				$group->appendChild($label);

				$label = Widget::Label(__('Item (XPath)'));
				$input = Widget::Input('item-xpath', General::sanitize($this->{'item-xpath'}));
				$label->appendChild($input);
				$group->appendChild($label);

				$wrapper->appendChild($group);

				$group = $document->createElement('div');
				$group->setAttribute('class', 'group');

				$label = Widget::Label(__('Value (XPath)'));
				$input = Widget::Input('value-xpath', General::sanitize($this->{'value-xpath'}));
				$label->appendChild($input);
				$group->appendChild($label);

				$label = Widget::Label(__('Label (XPath)'));
				$input = Widget::Input('text-xpath', General::sanitize($this->{'text-xpath'}));
				$label->appendChild($input);
				$group->appendChild($label);

				$wrapper->appendChild($group);

				## Cached time input
				$group = $document->createElement('div');
				$group->setAttribute('class', 'group');

				$label = Widget::Label(__('Cache'));
				$label->appendChild($document->createElement('em', __('How often to refresh the cache in minutes')));
				$input = Widget::Input('cache', max(1, intval($this->{'cache'})), 'text', array('size' => '6'));
				$label->appendChild($input);
				$group->appendChild($label);

				$wrapper->appendChild($group);

				$options_list = $document->createElement('ul');
				$options_list->setAttribute('class', 'options-list');

				$this->appendShowColumnCheckbox($options_list);
				$this->appendRequiredCheckbox($options_list);

				## Allow selection of multiple items
				$label = Widget::Label(__('Allow selection of multiple options'));

				$input = Widget::Input('allow-multiple-selection', 'yes', 'checkbox');
				if($this->{'allow-multiple-selection'} == 'yes') $input->setAttribute('checked', 'checked');

				$label->prependChild($input);
				$options_list->appendChild($label);

				$wrapper->appendChild($options_list);
			}


		/*-------------------------------------------------------------------------
			Publish:
		-------------------------------------------------------------------------*/

			public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = null, $data = null) {
				if(!is_array($data)){
					$data = array($data);
				}

				$selected = array();
				foreach($data as $d){
					if(!($d instanceof StdClass) || !isset($d->value)) continue;
					$selected[] = $d->value;
				}

				$states = $this->getToggleStates();
				$options = array();

				if($this->{'required'} == 'yes') {
					$options[] = array(null, false);
				}

				foreach($states as $handle => $v){
					if(isset($v['label'])) {
						$opts = array();
						foreach($v['options'] as $handle => $opt) {
							$opts[] = array($handle, in_array($handle, $selected), $opt);
						}

						$options[] = array('label' => $v['label'], 'options' => $opts);
					}
					else {
						$options[] = array($handle, in_array($v, $selected), $v);
					}
				}

				$fieldname = 'fields['.$this->{'element-name'}.']';
				if($this->{'allow-multiple-selection'} == 'yes') $fieldname .= '[]';

				$label = Widget::Label(
					(isset($this->{'publish-label'}) && strlen(trim($this->{'publish-label'})) > 0
						? $this->{'publish-label'}
						: $this->name)
				);
				$label->appendChild(Widget::Select($fieldname, $options,
					($this->{'allow-multiple-selection'} == 'yes') ? array('multiple' => 'multiple') : array()
				));

				if ($errors->valid()) {
					$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
				}

				$wrapper->appendChild($label);
			}

	}

	return 'FieldXMLSelectbox';