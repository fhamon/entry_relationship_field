<?php
	/**
	 * Copyright: Deux Huit Huit 2014
	 * LICENCE: MIT https://deuxhuithuit.mit-license.org
	 */

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(EXTENSIONS . '/entry_relationship_field/lib/class.field.relationship.php');
	require_once(EXTENSIONS . '/entry_relationship_field/lib/class.erfxsltutilities.php');
	require_once(EXTENSIONS . '/entry_relationship_field/lib/class.sectionsinfos.php');
	require_once(EXTENSIONS . '/entry_relationship_field/lib/class.entryqueryentryrelationshipadapter.php');

	/**
	 *
	 * Field class that will represent relationships between entries
	 * @author Deux Huit Huit
	 *
	 */
	class FieldEntry_Relationship extends FieldRelationship
	{
		/**
		 *
		 * Name of the field table
		 *  @var string
		 */
		const FIELD_TBL_NAME = 'tbl_fields_entry_relationship';

		/**
		 *
		 * Current recursive level of output
		 *  @var int
		 */
		protected $recursiveLevel = 1;
		public function getRecursiveLevel()
		{
			return $this->recursiveLevel;
		}
		public function incrementRecursiveLevel($inc = 1)
		{
			return $this->recursiveLevel += $inc;
		}

		/**
		 *
		 * Parent's maximum recursive level of output
		 *  @var int
		 */
		protected $recursiveDeepness = null;
		public function getRecursiveDeepness()
		{
			return $this->recursiveDeepness;
		}
		public function setRecursiveDeepness($deepness)
		{
			return $this->recursiveDeepness = $deepness;
		}

		// cache managers
		private $sectionManager;
		private $entryManager;
		private $sectionInfos;

		public $expandIncludableElements = true;

		/**
		 *
		 * Constructor for the Entry_Relationship Field object
		 */
		public function __construct()
		{
			// call the parent constructor
			parent::__construct();
			// EQFA
			$this->entryQueryFieldAdapter = new EntryQueryEntryrelationshipAdapter($this);
			// set the name of the field
			$this->_name = __('Entry Relationship');
			// permits to make it required
			$this->_required = true;
			// permits the make it show in the table columns
			$this->_showcolumn = true;
			// permits association
			$this->_showassociation = true;
			// current recursive level
			$this->recursiveLevel = 1;
			// parent's maximum recursive level of output
			$this->recursiveDeepness = null;
			// set as orderable
			$this->orderable = true;
			// set as not required by default
			$this->set('required', 'no');
			// show association by default
			$this->set('show_association', 'yes');
			// no sections
			$this->set('sections', null);
			// no max deepness
			$this->set('deepness', null);
			// no included elements
			$this->set('elements', null);
			// no modes
			$this->set('mode', null);
			$this->set('mode_table', null);
			$this->set('mode_header', null);
			$this->set('mode_footer', null);
			// no limit
			$this->set('min_entries', null);
			$this->set('max_entries', null);
			// all permissions
			$this->set('allow_new', 'yes');
			$this->set('allow_edit', 'yes');
			$this->set('allow_link', 'yes');
			$this->set('allow_delete', 'no');
			// display options
			$this->set('allow_collapse', 'yes');
			$this->set('allow_search', 'no');
			$this->set('show_header', 'yes');
			// cache managers
			$this->sectionManager = new SectionManager;
			$this->entryManager = new EntryManager;
			$this->fieldManager = new FieldManager;
			$this->sectionInfos = new SectionsInfos;
		}

		public function isSortable()
		{
			return false;
		}

		public function canFilter()
		{
			return true;
		}

		public function canPublishFilter()
		{
			return true;
		}

		public function canImport()
		{
			return false;
		}

		public function canPrePopulate()
		{
			return true;
		}

		public function mustBeUnique()
		{
			return false;
		}

		public function allowDatasourceOutputGrouping()
		{
			return false;
		}

		public function requiresSQLGrouping()
		{
			return false;
		}

		public function allowDatasourceParamOutput()
		{
			return true;
		}

		/* ********** INPUT AND FIELD *********** */


		/**
		 *
		 * Validates input
		 * Called before <code>processRawFieldData</code>
		 * @param $data
		 * @param $message
		 * @param $entry_id
		 */
		public function checkPostFieldData($data, &$message, $entry_id = null)
		{
			$message = null;
			$required = $this->isRequired();

			if ($required && (!is_array($data) || count($data) == 0 || strlen($data['entries']) < 1)) {
				$message = __("'%s' is a required field.", array($this->get('label')));
				return self::__MISSING_FIELDS__;
			}

			$entries = $data['entries'];

			if (!is_array($entries)) {
				$entries = static::getEntries($data);
			}

			// enforce limits only if required or it contains data
			if ($required || count($entries) > 0) {
				if ($this->getInt('min_entries') > 0 && $this->getInt('min_entries') > count($entries)) {
					$message = __("'%s' requires a minimum of %s entries.", array($this->get('label'), $this->getInt('min_entries')));
					return self::__INVALID_FIELDS__;
				} else if ($this->getInt('max_entries') > 0 && $this->getInt('max_entries') < count($entries)) {
					$message = __("'%s' can not contains more than %s entries.", array($this->get('label'), $this->getInt('max_entries')));
					return self::__INVALID_FIELDS__;
				}
			}

			return self::__OK__;
		}


		/**
		 *
		 * Process data before saving into database.
		 *
		 * @param array $data
		 * @param int $status
		 * @param boolean $simulate
		 * @param int $entry_id
		 *
		 * @return Array - data to be inserted into DB
		 */
		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
		{
			$status = self::__OK__;
			$entries = null;

			if (!is_array($data) && !is_string($data)) {
				return null;
			}

			if (isset($data['entries'])) {
				$entries = $data['entries'];
			}
			else if (is_string($data)) {
				$entries = $data;
			}

			$row = array(
				'entries' => empty($entries) ? null : $entries
			);

			// return row
			return $row;
		}

		/**
		 * This function permits parsing different field settings values
		 *
		 * @param array $settings
		 *	the data array to initialize if necessary.
		 */
		public function setFromPOST(Array $settings = array())
		{
			// call the default behavior
			parent::setFromPOST($settings);

			// declare a new setting array
			$new_settings = array();

			// set new settings
			$new_settings['sections'] = is_array($settings['sections']) ?
				implode(self::SEPARATOR, $settings['sections']) :
				(is_string($settings['sections']) ? $settings['sections'] : null);

			$new_settings['show_association'] = $settings['show_association'] == 'yes' ? 'yes' : 'no';
			$new_settings['deepness'] = General::intval($settings['deepness']);
			$new_settings['deepness'] = $new_settings['deepness'] < 1 ? null : $new_settings['deepness'];
			$new_settings['elements'] = empty($settings['elements']) ? null : $settings['elements'];
			$new_settings['mode'] = empty($settings['mode']) ? null : $settings['mode'];
			$new_settings['mode_table'] = empty($settings['mode_table']) ? null : $settings['mode_table'];
			$new_settings['mode_header'] = empty($settings['mode_header']) ? null : $settings['mode_header'];
			$new_settings['mode_footer'] = empty($settings['mode_footer']) ? null : $settings['mode_footer'];
			$new_settings['allow_new'] = $settings['allow_new'] == 'yes' ? 'yes' : 'no';
			$new_settings['allow_edit'] = $settings['allow_edit'] == 'yes' ? 'yes' : 'no';
			$new_settings['allow_link'] = $settings['allow_link'] == 'yes' ? 'yes' : 'no';
			$new_settings['allow_delete'] = $settings['allow_delete'] == 'yes' ? 'yes' : 'no';
			$new_settings['allow_collapse'] = $settings['allow_collapse'] == 'yes' ? 'yes' : 'no';
			$new_settings['allow_search'] = $settings['allow_search'] == 'yes' ? 'yes' : 'no';
			$new_settings['show_header'] = $settings['show_header'] == 'yes' ? 'yes' : 'no';

			// save it into the array
			$this->setArray($new_settings);
		}


		/**
		 *
		 * Validates the field settings before saving it into the field's table
		 */
		public function checkFields(Array &$errors, $checkForDuplicates = true)
		{
			$parent = parent::checkFields($errors, $checkForDuplicates);
			if ($parent != self::__OK__) {
				return $parent;
			}

			$sections = $this->get('sections');

			if (empty($sections)) {
				$errors['sections'] = __('At least one section must be chosen');
			}

			return (!empty($errors) ? self::__ERROR__ : self::__OK__);
		}

		/**
		 *
		 * Save field settings into the field's table
		 */
		public function commit()
		{
			// if the default implementation works...
			if(!parent::commit()) return false;

			$id = $this->get('id');

			// exit if there is no id
			if($id == false) return false;

			// we are the child, with multiple parents
			$child_field_id = $id;

			// delete associations, only where we are the child
			self::removeSectionAssociation($child_field_id);

			$sections = $this->getSelectedSectionsArray();

			foreach ($sections as $key => $sectionId) {
				if (empty($sectionId)) {
					continue;
				}
				$parent_section_id = General::intval($sectionId);
				if ($parent_section_id < 1) {
					// section not found, bail out
					continue;
				}
				$parent_section = $this->sectionManager
					->select()
					->section($parent_section_id)
					->execute()
					->next();
				if (!$parent_section) {
					// section not found, bail out
					continue;
				}
				$fields = $parent_section->fetchVisibleColumns();
				if (empty($fields)) {
					// no visible field, revert to all
					$fields = $parent_section->fetchFields();
				}
				if (empty($fields)) {
					// no fields found, bail out
					continue;
				}
				$parent_field_id = current($fields)->get('id');
				// create association
				SectionManager::createSectionAssociation(
					$parent_section_id,
					$child_field_id,
					$parent_field_id,
					$this->get('show_association') == 'yes'
				);
			}

			// declare an array contains the field's settings
			$settings = array(
				'sections' => $this->get('sections'),
				'show_association' => $this->get('show_association'),
				'deepness' => $this->get('deepness'),
				'elements' => $this->get('elements'),
				'mode' => $this->get('mode'),
				'mode_table' => $this->get('mode_table'),
				'mode_header' => $this->get('mode_header'),
				'mode_footer' => $this->get('mode_footer'),
				'min_entries' => (int)$this->get('min_entries'),
				'max_entries' => (int)$this->get('max_entries'),
				'allow_new' => $this->get('allow_new'),
				'allow_edit' => $this->get('allow_edit'),
				'allow_link' => $this->get('allow_link'),
				'allow_delete' => $this->get('allow_delete'),
				'allow_collapse' => $this->get('allow_collapse'),
				'allow_search' => $this->get('allow_search'),
				'show_header' => $this->get('show_header'),
			);

			return FieldManager::saveSettings($id, $settings);
		}

		/**
		 *
		 * This function allows Fields to cleanup any additional things before it is removed
		 * from the section.
		 * @return boolean
		 */
		public function tearDown()
		{
			self::removeSectionAssociation($this->get('id'));
			return parent::tearDown();
		}

		/**
		 * Generates the where filter for searching by entry id
		 *
		 * @param string $value
		 * @param @optional string $col
		 */
		public function generateWhereFilter($value, $col = 'd')
		{
			if (!$value) {
				return [
					$junction => [
						[$col . '.entries' => null],
					],
				];
			}

			return [
				'or' => [
					[$col . '.entries' => $value],
					[$col . '.entries' => ['like' => $value . ',%']],
					[$col . '.entries' => ['like' => '%,' . $value]],
					[$col . '.entries' => ['like' => '%,' . $value . ',%']],
				]
			];
		}

		/**
		 * Fetch the number of associated entries for a particular entry id
		 *
		 * @param string $value
		 */
		public function fetchAssociatedEntryCount($value)
		{
			if (!$value) {
				return 0;
			}

			$where = $this->generateWhereFilter($value);

			$entries = Symphony::Database()
				->select(['e.id'])
				->from('tbl_entries', 'e')
				->innerJoin('tbl_entries_data_' . $this->get('id'), 'd')
				->on(['e.id' => '$d.entry_id'])
				->where(['e.section_id' => $this->get('parent_section')])
				->where($where)
				->execute()
				->rows();

			return count($entries);
		}

		public function fetchAssociatedEntrySearchValue($data, $field_id = null, $parent_entry_id = null)
		{
			return $parent_entry_id;
		}

		public function findRelatedEntries($entry_id, $parent_field_id)
		{
			$entries = $this->entryManager
				->select()
				->section($this->get('parent_section'))
				->filter($this->get('id'), [(string)$entry_id])
				->schema(['id'])
				->execute()
				->rows();

			return array_map(function ($e) {
				return $e['id'];
			}, $entries);
		}

		public function findParentRelatedEntries($parent_field_id, $entry_id)
		{
			$entry = $this->entryManager
				->select()
				->entry($entry_id)
				->section($this->entryManager->fetchEntrySectionID($entry_id))
				->includeAllFields()
				->schema(array($this->get('label')))
				->execute()
				->next();

			if (!$entry) {
				return array();
			}

			return self::getEntries($entry->getData($this->get('id')));
		}

		public function fetchFilterableOperators()
		{
			return array(
				array(
					'title' => 'links to',
					'filter' => ' ',
					'help' => __('Find entries that links to the specified filter')
				),
				array(
					'filter' => 'sql: NOT NULL',
					'title' => 'is not empty',
					'help' => __('Find entries with a non-empty value.')
				),
				array(
					'filter' => 'sql: NULL',
					'title' => 'is empty',
					'help' => __('Find entries with an empty value.')
				),
				array(
					'title' => 'contains',
					'filter' => 'regexp: ',
					'help' => __('Find values that match the given <a href="%s">MySQL regular expressions</a>.', array(
						'https://dev.mysql.com/doc/mysql/en/regexp.html'
					))
				),
				array(
					'title' => 'does not contain',
					'filter' => 'not-regexp: ',
					'help' => __('Find values that do not match the given <a href="%s">MySQL regular expressions</a>.', array(
						'https://dev.mysql.com/doc/mysql/en/regexp.html'
					))
				),
			);
		}

		public function fetchSuggestionTypes()
		{
			return array('association');
		}

		public function fetchIDsfromValue($value)
		{
			$ids = array();
			$sections = $this->getArray('sections');

			foreach ($sections as $sectionId) {
				$section = $this->sectionManager
					->select()
					->section($sectionId)
					->execute()
					->next();

				if (!$section) {
					continue;
				}
				$filterableFields = $section->fetchFilterableFields();
				if (empty($filterableFields)) {
					continue;
				}
				foreach ($filterableFields as $fId => $field) {
					if ($field instanceof FieldRelationship) {
						continue;
					}
					$fEntries = (new EntryManager)
						->select(['e.id'])
						->section($sectionId)
						->filter($fId, [$value])
						->execute()
						->rows();

					if (!empty($fEntries)) {
						$ids = array_merge($ids, $fEntries);
						break;
					}
				}
			}

			return array_map(function ($e) {
				return $e['id'];
			}, $ids);
		}

		public function prepareAssociationsDrawerXMLElement(Entry $e, array $parent_association, $prepolutate = '')
		{
			$currentSection = $this->sectionManager
				->select()
				->section($parent_association['child_section_id'])
				->execute()
				->next();
			$visibleCols = $currentSection->fetchVisibleColumns();
			$outputFieldId = current($visibleCols)->get('id');
			$outputField = $this->fieldManager
				->select()
				->field($outputFieldId)
				->execute()
				->next();

			$value = $outputField->prepareReadableValue($e->getData($outputFieldId), $e->get('id'), true, __('None'));

			$li = new XMLElement('li');
			$li->setAttribute('class', 'field-' . $this->get('type'));
			$a = new XMLElement('a', strip_tags($value));
			$a->setAttribute('href', SYMPHONY_URL . '/publish/' . $parent_association['handle'] . '/edit/' . $e->get('id') . '/');
			$li->appendChild($a);

			return $li;
		}

		/* ******* EVENTS ******* */

		public function getExampleFormMarkup()
		{
			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').'][entries]', null, 'hidden'));

			return $label;
		}


		/* ******* DATA SOURCE ******* */

		private function fetchEntryWithData($eId, $sectionId, $elements = array())
		{
			$q = $this->entryManager
				->select()
				->entry($eId)
				->section($sectionId);
			if ($elements === null) {
				$q->includeAllFields();
			} else {
				$q->schema($elements);
			}
			return $q->execute()
				->next();
		}

		private function fetchEntryOnly($eId)
		{
			return $this->entryManager
				->select()
				->entry($eId)
				->execute()
				->next();
		}

		protected function fetchAllIncludableElements()
		{
			$sections = $this->getArray('sections');
			return $allElements = array_reduce($this->sectionInfos->fetch($sections), function ($memo, $item) {
				return array_merge($memo, array_map(function ($field) use ($item) {
					return $item['handle'] . '.' . $field['handle'];
				}, $item['fields']));
			}, array());
		}

		public function fetchIncludableElements()
		{
			$label = $this->get('element_name');
			$elements = $this->getArray('elements');
			if (empty($elements)) {
				$elements = array('*');
			}
			$includedElements = array();
			// Include everything
			if ($this->expandIncludableElements) {
				$includedElements[] = $label . ': *';
			}
			// Include individual elements
			foreach ($elements as $elem) {
				$elem = trim($elem);
				if ($elem !== '*') {
					$includedElements[] = $label . ': ' . $elem;
				} else if ($this->expandIncludableElements) {
					$includedElements = array_unique(array_merge($includedElements, array_map(function ($item) use ($label) {
						return $label . ': ' . $item;
					}, $this->fetchAllIncludableElements())));
				} else {
					$includedElements = array('*');
				}
			}
			return $includedElements;
		}

		/**
		 * Appends data into the XML tree of a Data Source
		 * @param $wrapper
		 * @param $data
		 */
		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
		{
			if (!is_array($data) || empty($data)) {
				return;
			}

			// try to find an existing root
			$root = null;
			$newRoot = false;
			foreach (array_reverse($wrapper->getChildren()) as $xmlField) {
				if ($xmlField->getName() === $this->get('element_name')) {
					$root = $xmlField;
					break;
				}
			}

			// root was not found, create one
			if (!$root) {
				$root = new XMLElement($this->get('element_name'));
				$newRoot = true;
			}

			// devkit will load
			$devkit = isset($_GET['debug']) && (empty($_GET['debug']) || $_GET['debug'] == 'xml');

			// selected items
			$entries = static::getEntries($data);

			// current linked entries
			$root->setAttribute('entries', $data['entries']);

			// available sections
			$root->setAttribute('sections', $this->get('sections'));

			// included elements
			$elements = static::parseElements($this);

			// DS mode
			if (!$mode) {
				$mode = '*';
			}

			$parentDeepness = General::intval($this->recursiveDeepness);
			$deepness = General::intval($this->get('deepness'));

			// both deepnesses are defined and parent restricts more
			if ($parentDeepness > 0 && $deepness > 0 && $parentDeepness < $deepness) {
				$deepness = $parentDeepness;
			}
			// parent is defined, current is not
			else if ($parentDeepness > 0 && $deepness < 1) {
				$deepness = $parentDeepness;
			}

			// cache recursive level because recursion might
			// change its value later on.
			$recursiveLevel = $this->recursiveLevel;

			// build entries
			foreach ($entries as $eId) {
				// try to find and existing item
				// TODO: keep last index found since it should be the next
				$item = null;
				$newItem = false;
				foreach ($root->getChildren() as $xmlItem) {
					if (General::intval($xmlItem->getAttribute('id')) === General::intval($eId)) {
						$item = $xmlItem;
						break;
					}
				}

				// item was not found, create one
				if (!$item) {
					$item = new XMLElement('item');
					$item->setAllowEmptyAttributes(false);
					// output id
					$item->setAttribute('id', $eId);
					// output recursive level
					$item->setAttribute('level', $recursiveLevel);
					$item->setAttribute('max-level', $deepness);
					$newItem = true;
				// item was found, but it is an error, so we can skip it
				} else if ($item->getName() === 'error') {
					continue;
				}

				// max recursion check
				if ($deepness < 1 || $recursiveLevel < $deepness) {
					// current entry, without data
					$entry = $this->fetchEntryOnly($eId);

					// entry not found...
					if (!$entry || empty($entry)) {
						$error = new XMLElement('error');
						$error->setAttribute('id', $eId);
						$error->setValue(__('Error: entry `%s` not found', array($eId)));
						$root->prependChild($error);
						continue;
					}

					// fetch section infos
					$sectionId = $entry->get('section_id');
					$section = $this->sectionManager
						->select()
						->section($sectionId)
						->execute()
						->next();
					$sectionName = $section->get('handle');

					// set section related attributes
					$item->setAttribute('section-id', $sectionId);
					$item->setAttribute('section', $sectionName);

					// Get the valid elements for this section only
					$validElements = $elements[$sectionName];

					// adjust the mode for the current section
					$curMode = $mode;

					// remove section name from current mode so "sectionName.field" becomes simply "field"
					if (preg_match('/^(' . $sectionName . '\.)(.*)$/sU', $curMode)) {
						$curMode = preg_replace('/^' . $sectionName . '\./sU', '', $curMode);
					}
					// remove section name from current mode "sectionName" and
					// treat it like if it is "sectionName: *"
					else if (preg_match('/^(' . $sectionName . ')$/sU', $curMode)) {
						$curMode = '*';
					}
					// section name was not found in mode, check if the mode is "*"
					else if ($curMode != '*') {
						// mode forbids this section
						$validElements = null;
					}

					// this section is not selected, bail out
					if (!is_array($validElements)) {
						if ($newItem) {
							if ($devkit) {
								$item->setAttribute('x-forbidden-by-ds', $curMode);
							}
							$root->appendChild($item);
						}
						continue;
					} else {
						if ($devkit) {
							$item->setAttribute('x-forbidden-by-ds', null);
						}
					}

					// selected fields for fetching
					$sectionElements = array();

					// everything is allowed
					if (in_array('*', $validElements)) {
						if ($curMode !== '*') {
							// get only the mode
							$sectionElements = array($curMode);
						}
						else {
							// setting null = get all
							$sectionElements = null;
						}
					}
					// only use valid elements
					else {
						if ($curMode !== '*') {
							// is this field allowed ?
							if (self::isFieldIncluded($curMode, $validElements)) {
								// get only the mode
								$sectionElements = array($curMode);
							}
							else {
								// $curMode selects something outside of
								// the valid elements: select nothing
								$sectionElements = array();
							}
						}
						else {
							// use field's valid elements
							$sectionElements = $validElements;
						}
					}

					// Filtering is enabled, but nothing is selected
					if (is_array($sectionElements) && empty($sectionElements)) {
						if ($newItem) {
							$root->appendChild($item);
							if ($devkit) {
								$item->setAttribute('x-forbidden-by-selection', $curMode);
							}
						}
						continue;
					} else {
						if ($devkit) {
							$item->setAttribute('x-forbidden-by-selection', null);
						}
					}

					// fetch current entry again, but with data for the allowed schema
					$entry = $this->fetchEntryWithData($eId, $sectionId, $sectionElements);

					// cache the entry data
					$entryData = $entry->getData();

					// for each field returned for this entry...
					foreach ($entryData as $fieldId => $data) {
						$filteredData = array_filter($data, function ($value) {
							return $value != null;
						});

						if (empty($filteredData)) {
							continue;
						}

						$field = $this->fieldManager
							->select()
							->field($fieldId)
							->execute()
							->next();
						$fieldName = $field->get('element_name');
						$fieldCurMode = self::extractMode($fieldName, $curMode);

						$parentIncludableElement = self::getSectionElementName($fieldName, $validElements);
						$parentIncludableElementMode = self::extractMode($fieldName, $parentIncludableElement);

						// Special treatments for ERF
						if ($field instanceof FieldEntry_relationship) {
							// Increment recursive level
							$field->recursiveLevel = $recursiveLevel + 1;
							$field->recursiveDeepness = $deepness;
						}

						$submodes = null;
						// Parent mode is not defined (we are selecting the whole section)
						if ($parentIncludableElementMode === null) {
							if ($fieldCurMode == null) {
								// Field does not defined a mode either: use the field's default
								$submodes = null;
							} else {
								// Use the current field's mode
								$submodes = array($fieldCurMode);
							}
							if ($devkit) {
								$item->setAttribute('x-selection-mode-empty', null);
							}
						} else {
							// Field mode is not defined or it is the same as the parent
							if ($fieldCurMode === null || $fieldCurMode == $parentIncludableElementMode) {
								if ($devkit) {
									$item->setAttribute('x-selection-mode-empty', null);
								}
								// Use parent mode
								$submodes = array($parentIncludableElementMode);
							} else {
								if ($devkit) {
									$item->setAttribute('x-selection-mode-empty', 'yes');
								}
								// Empty selection
								$submodes = array();
							}
						}

						// current selection does not specify a mode
						if ($submodes === null) {
							if ($field instanceof FieldEntry_Relationship) {
								$field->expandIncludableElements = false;
							}
							$submodes = array_map(function ($fieldIncludableElement) use ($fieldName) {
								return FieldEntry_relationship::extractMode($fieldName, $fieldIncludableElement);
							}, $field->fetchIncludableElements());
							if ($field instanceof FieldEntry_Relationship) {
								$field->expandIncludableElements = true;
							}
						}

						// Append the formatted element for each requested mode
						foreach ($submodes as $submode) {
							$field->appendFormattedElement($item, $data, $encode, $submode, $eId);
						}
					}
					// output current mode
					$item->setAttribute('matched-element', $curMode);
					// no field selected
					if (is_array($sectionElements) && empty($sectionElements)) {
						$item->setAttribute('empty-selection', 'yes');
					}
				} // end max recursion check

				if ($newItem) {
					// append item when done
					$root->appendChild($item);
				}
			} // end each entries

			if ($newRoot) {
				// output mode for this field
				if ($devkit) {
					$root->setAttribute('x-data-source-mode', $mode);
					$root->setAttribute('x-field-included-elements', $this->get('elements'));
				}

				// add all our data to the wrapper;
				$wrapper->appendChild($root);
			} else {
				if ($devkit) {
					$root->setAttribute('x-data-source-mode', $root->getAttribute('x-data-source-mode') . ', ' . $mode);
				}
			}

			// clean up
			$this->recursiveLevel = 1;
			$this->recursiveDeepness = null;
		}

		public function getParameterPoolValue(array $data, $entry_id = null)
		{
			if(!is_array($data) || empty($data)) return;
			return static::getEntries($data);
		}

		/* ********* Utils *********** */

		/**
		 * Return true if $fieldName is allowed in $sectionElements
		 * @param string $fieldName
		 * @param string $sectionElements
		 * @return bool
		 */
		public static function isFieldIncluded($fieldName, $sectionElements)
		{
			return self::getSectionElementName($fieldName, $sectionElements) !== null;
		}

		public static function getSectionElementName($fieldName, $sectionElements)
		{
			if (is_array($sectionElements)) {
				foreach ($sectionElements as $element) {
					// everything is allowed, use "fieldName" directly
					if ($element === '*') {
						return $fieldName;
					}
					// make "fieldName: *" the same as "fieldName"
					if (preg_match('/\s*:\s*\*/sU', $fieldName)) {
						$fieldName = trim(current(explode(':', $fieldName)));
					}
					// "fieldName" is included as-is or element starts with "fieldName:"
					if ($fieldName === $element || preg_match('/^' . $fieldName . '\s*:/sU', $element)) {
						return $element;
					}
				}
			}
			return null;
		}

		public static function parseElements($field)
		{
			$elements = array();
			$exElements = $field->getArray('elements');


			if (in_array('*', $exElements)) {
				$sections = $field->getArray('sections');
				$sm = new SectionManager;
				$sections = $sm
					->select()
					->sections($sections)
					->execute()
					->rows();
				return array_reduce($sections, function ($result, $section) {
					$result[$section->get('handle')] = array('*');
					return $result;
				}, array());
			}

			foreach ($exElements as $value) {
				if (!$value) {
					continue;
				}
				// sectionName.fieldName or sectionName.*
				$parts = array_map('trim', explode('.', $value, 2));
				// first time seeing this section
				if (!isset($elements[$parts[0]])) {
					$elements[$parts[0]] = array();
				}
				// we have a value after the dot
				if (isset($parts[1]) && !!$parts[1]) {
					$elements[$parts[0]][] = $parts[1];
				}
				// sectionName only
				else if (!isset($parts[1])) {
					$elements[$parts[0]][] = '*';
				}
			}

			return $elements;
		}

		public static function extractMode($fieldName, $mode)
		{
			$pattern = '/^' . $fieldName . '\s*:\s*/s';
			if (!preg_match($pattern, $mode)) {
				return null;
			}
			$mode = preg_replace($pattern, '', $mode, 1);
			if ($mode === '*') {
				return null;
			}
			return $mode;
		}

		private function buildSectionSelect($name)
		{
			// $sections = SectionManager::fetch();
			$sections = $this->sectionManager
				->select()
				->execute()
				->rows();
			$options = array();
			$selectedSections = $this->getSelectedSectionsArray();

			foreach ($sections as $section) {
				$driver = $section->get('id');
				$selected = in_array($driver, $selectedSections);
				$options[] = array($driver, $selected, General::sanitize($section->get('name')));
			}

			return Widget::Select($name, $options, array('multiple' => 'multiple'));
		}

		private function appendSelectionSelect(&$wrapper)
		{
			$name = $this->createSettingsFieldName('sections', true);

			$input = $this->buildSectionSelect($name);
			$input->setAttribute('class', 'entry_relationship-sections');

			$label = Widget::Label();
			$label->setAttribute('class', 'column');

			$label->setValue(__('Available sections %s', array($input->generate())));

			$wrapper->appendChild($label);
		}

		private function createEntriesHiddenInput($data)
		{
			$hidden = new XMLElement('input', null, array(
				'type' => 'hidden',
				'name' => $this->createPublishFieldName('entries'),
				'value' => $data['entries']
			));

			return $hidden;
		}

		private function createActionBarMenu($sections)
		{
			$wrap = new XMLElement('div', null, array('class' => 'action-bar'));
			$actionBar = '';
			$modeFooter = $this->get('mode_footer');
			if ($modeFooter) {
				$section = $this->sectionManager
					->select()
					->section($this->get('parent_section'))
					->execute()
					->next();
				$actionBar = ERFXSLTUTilities::processXSLT($this, null, $section->get('handle'), null, 'mode_footer', isset($_REQUEST['debug']), 'field');
			}
			if (empty($actionBar)) {
				$inner = new XMLElement('div', null, array('class' => 'inner'));
				$div = new XMLElement('div');

				if ($this->is('allow_search')) {
					$searchCtn = new XMLElement('div', null, array('class' => 'search-ctn'));
					$searchUi = new XMLElement('div', null, array('class' => 'search-ui'));

					$trigger = new XMLElement('button', '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="8" stroke="currentColor" stroke-width="2"/><path d="M15 15L19 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>', array('class' => 'search-trigger'));
					$searchCtn->appendChild($trigger);

					$searchSections = new XMLElement('div', null, array('class' => 'search-sections'));

					foreach ($sections as $section) {
						$searchSections->appendChild(new XMLElement('button', $section->get('icon') . $section->get('name'), array(
							'data-search-btn' => '',
							'data-section' => $section->get('handle'),
							'data-section-name' => $section->get('name'),
						)));
					}

					$searchUi->appendChild($searchSections);


					$searchIndicator = new XMLElement('div', null, array('class' => 'search-indicator', 'data-text' => __('Search in: ')));
					$searchUi->appendChild($searchIndicator);

					$input = new XMLElement('input', null, array('class' => 'search-input', 'type' => 'text', 'data-search' => ''));
					$searchUi->appendChild($input);

					$searchCtn->appendChild($searchUi);

					$inner->appendChild($searchCtn);
				}

				if ($this->is('allow_new')) {
					$sectionButtons = [];

					foreach ($sections as $section) {
						$sectionButtons[] = new XMLElement('button', $section->get('icon') . $section->get('name'), array(
							'data-create' => '',
							'data-section' => $section->get('handle')
						));
					}

					$svg = '<svg width="21" height="20" viewBox="0 0 21 20" fill="none"><path d="M10.3916 1V19M1.3916 10C9.20209 10 11.5811 10 19.3916 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

					if (count($sections) > 1) {
						$inner->appendChild(Widget::Modal($sectionButtons, 'bottom right', array(), $svg));
					} else {
						$inner->appendChild(new XMLElement('button', $svg, array(
							'data-create' => '',
							'data-section' => (current($sections))->get('handle')
						)));
					}

				}
				if ($this->is('allow_link')) {
					$sectionButtons = [];

					foreach ($sections as $section) {
						$sectionButtons[] = new XMLElement('button', $section->get('icon') . $section->get('name'), array(
							'data-link' => '',
							'data-section' => $section->get('handle')
						));
					}

					$svg = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10.8975 15.0021L8.51288 17.3694C7.05134 18.8203 4.5898 18.8203 3.05134 17.3694C1.5898 15.9185 1.5898 13.4748 3.05134 11.9475L6.28211 8.74028C7.74365 7.28937 10.2052 7.28937 11.7437 8.74028" stroke="currentColor" stroke-width="2" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.97442 5.07484L12.436 2.70757C13.8975 1.25666 16.359 1.25666 17.8975 2.70757C19.359 4.15848 19.359 6.60211 17.8975 8.12939L14.6667 11.3367C13.2052 12.7876 10.7437 12.7876 9.20519 11.3367" stroke="currentColor" stroke-width="2" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/></svg>';

					if (count($sections) > 1) {
						$inner->appendChild(Widget::Modal($sectionButtons, 'bottom right', array(), $svg));
					} else {
						$inner->appendChild(new XMLElement('button', $svg, array(
							'data-link' => '',
							'data-section' => (current($sections))->get('handle')
						)));
					}
				}

				$wrap->appendChild($inner);
			} else {
				$wrap->setValue($actionBar);
			}

			return $wrap;
		}

		/* ********* UI *********** */

		/**
		 *
		 * Builds the UI for the field's settings when creating/editing a section
		 * @param XMLElement $wrapper
		 * @param array $errors
		 */
		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
		{
			/* first line, label and such */
			parent::displaySettingsPanel($wrapper, $errors);

			// sections
			$sections = new XMLElement('fieldset');

			$this->appendSelectionSelect($sections);
			if (is_array($errors) && isset($errors['sections'])) {
				$sections = Widget::Error($sections, $errors['sections']);
			}
			$wrapper->appendChild($sections);

			// elements
			$elements = new XMLElement('div');
			$element = Widget::Label();
			$element->setValue(__('Included elements in Data Sources and Backend Templates'));
			$element->setAttribute('class', 'column');
			$element->appendChild(Widget::Input($this->createSettingsFieldName('elements'), $this->get('elements'), 'text', array(
				'class' => 'entry_relationship-elements'
			)));
			$elements->appendChild($element);
			$elements_choices = new XMLElement('ul', null, array('class' => 'tags singular entry_relationship-field-choices'));
			$elements->appendChild($elements_choices);
			$wrapper->appendChild($elements);

			// limit entries
			$limits = new XMLElement('fieldset');
			$limits->appendChild(new XMLElement('legend', __('Limits')));
			$limits_cols = new XMLElement('div');
			$limits_cols->setAttribute('class', 'three columns');
			// min
			$limit_min = Widget::Label();
			$limit_min->setValue(__('Minimum count of entries in this field'));
			$limit_min->setAttribute('class', 'column');
			$limit_min->appendChild(Widget::Input($this->createSettingsFieldName('min_entries'), $this->get('min_entries'), 'number', array(
				'min' => 0,
				'max' => 99999
			)));
			$limits_cols->appendChild($limit_min);
			// max
			$limit_max = Widget::Label();
			$limit_max->setValue(__('Maximum count of entries in this field'));
			$limit_max->setAttribute('class', 'column');
			$limit_max->appendChild(Widget::Input($this->createSettingsFieldName('max_entries'), $this->get('max_entries'), 'number', array(
				'min' => 0,
				'max' => 99999
			)));
			$limits_cols->appendChild($limit_max);

			// deepness
			$deepness = Widget::Label();
			$deepness->setValue(__('Maximum level of recursion in Data Sources'));
			$deepness->setAttribute('class', 'column');
			$deepness->appendChild(Widget::Input($this->createSettingsFieldName('deepness'), $this->get('deepness'), 'number', array(
				'min' => 0,
				'max' => 99
			)));
			$limits_cols->appendChild($deepness);
			$limits->appendChild($limits_cols);
			$wrapper->appendChild($limits);

			// xsl
			$xsl = new XMLElement('fieldset');
			$xsl->appendChild(new XMLElement('legend', __('Backend XSL templates options')));
			$xsl_cols = new XMLElement('div');
			$xsl_cols->setAttribute('class', 'four columns');

			// xsl mode
			$xslmode = Widget::Label();
			$xslmode->setValue(__('XSL mode for entries content template'));
			$xslmode->setAttribute('class', 'column');
			$xslmode->appendChild(Widget::Input($this->createSettingsFieldName('mode'), $this->get('mode'), 'text'));
			$xsl_cols->appendChild($xslmode);
			// xsl header mode
			$xslmodetable = Widget::Label();
			$xslmodetable->setValue(__('XSL mode for entries header template'));
			$xslmodetable->setAttribute('class', 'column');
			$xslmodetable->appendChild(Widget::Input($this->createSettingsFieldName('mode_header'), $this->get('mode_header'), 'text'));
			$xsl_cols->appendChild($xslmodetable);
			// xsl table mode
			$xslmodetable = Widget::Label();
			$xslmodetable->setValue(__('XSL mode for publish table value'));
			$xslmodetable->setAttribute('class', 'column');
			$xslmodetable->appendChild(Widget::Input($this->createSettingsFieldName('mode_table'), $this->get('mode_table'), 'text'));
			$xsl_cols->appendChild($xslmodetable);
			// xsl action bar mode
			$xslmodetable = Widget::Label();
			$xslmodetable->setValue(__('XSL mode for publish action bar'));
			$xslmodetable->setAttribute('class', 'column');
			$xslmodetable->appendChild(Widget::Input($this->createSettingsFieldName('mode_footer'), $this->get('mode_footer'), 'text'));
			$xsl_cols->appendChild($xslmodetable);

			$xsl->appendChild($xsl_cols);
			$wrapper->appendChild($xsl);

			// permissions
			$permissions = new XMLElement('fieldset');
			$permissions->appendChild(new XMLElement('legend', __('Permissions')));
			$permissions_cols = new XMLElement('div');
			$permissions_cols->setAttribute('class', 'four columns');
			$permissions_cols->appendChild($this->createCheckbox('allow_new', 'Show new button'));
			$permissions_cols->appendChild($this->createCheckbox('allow_edit', 'Show edit button'));
			$permissions_cols->appendChild($this->createCheckbox('allow_link', 'Show link button'));
			$permissions_cols->appendChild($this->createCheckbox('allow_delete', 'Show delete button'));
			$permissions->appendChild($permissions_cols);
			$wrapper->appendChild($permissions);

			// display options
			$display = new XMLElement('fieldset');
			$display->appendChild(new XMLElement('legend', __('Display options')));
			$display_cols = new XMLElement('div');
			$display_cols->setAttribute('class', 'four columns');
			$display_cols->appendChild($this->createCheckbox('allow_collapse', 'Allow content collapsing'));
			$display_cols->appendChild($this->createCheckbox('allow_search', 'Allow search linking'));
			$display_cols->appendChild($this->createCheckbox('show_header', 'Show the header box before entries templates'));
			$display->appendChild($display_cols);
			$wrapper->appendChild($display);

			// assoc
			$assoc = new XMLElement('fieldset');
			$assoc->appendChild(new XMLElement('legend', __('Associations')));
			$assoc_cols = new XMLElement('div');
			$assoc_cols->setAttribute('class', 'three columns');
			$this->appendShowAssociationCheckbox($assoc_cols);
			$assoc->appendChild($assoc_cols);
			$wrapper->appendChild($assoc);

			// footer
			$this->appendStatusFooter($wrapper);
		}

		/**
		 *
		 * Builds the UI for the publish page
		 * @param XMLElement $wrapper
		 * @param mixed $data
		 * @param mixed $flagWithError
		 * @param string $fieldnamePrefix
		 * @param string $fieldnamePostfix
		 */
		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
		{
			$entriesId = array();
			$sectionsId = $this->getSelectedSectionsArray();

			if ($data['entries'] != null) {
				$entriesId = static::getEntries($data);
			}

			$sectionsId = array_map(array('General', 'intval'), $sectionsId);
			$sections = $this->sectionManager
				->select()
				->sections($sectionsId)
				->execute()
				->rows();
			usort($sections, function ($a, $b) {
				if ($a->get('name') === $b->get('name')) {
					return 0;
				}
				return $a->get('name') < $b->get('name') ? -1 : 1;
			});

			$label = Widget::Label($this->get('label'));
			$notes = '';

			// min note
			if ($this->getInt('min_entries') > 0) {
				$notes .= __('Minimum number of entries: <b>%s</b>. ', array($this->get('min_entries')));
			}
			// max note
			if ($this->getInt('max_entries') > 0) {
				$notes .= __('Maximum number of entries: <b>%s</b>. ', array($this->get('max_entries')));
			}
			// not required note
			if (!$this->isRequired()) {
				$notes .= __('Optional');
			}
			// append notes
			if ($notes) {
				$label->appendChild(new XMLElement('i', $notes));
			}

			// label error management
			if ($flagWithError != null) {
				$wrapper->appendChild(Widget::Error($label, $flagWithError));
			} else {
				$wrapper->appendChild($label);
			}

			$border = new XMLElement('div', null, array('class' => 'field-border'));
			$border->appendChild($this->createEntriesList($entriesId));
			$border->appendChild($this->createActionBarMenu($sections));

			$wrapper->appendChild($border);
			$wrapper->appendChild($this->createEntriesHiddenInput($data));
			$wrapper->setAttribute('data-value', $data['entries']);
			$wrapper->setAttribute('data-field-id', $this->get('id'));
			$wrapper->setAttribute('data-field-label', $this->get('label'));
			$wrapper->setAttribute('data-min', $this->get('min_entries'));
			$wrapper->setAttribute('data-max', $this->get('max_entries'));
			$wrapper->setAttribute('data-required', $this->get('required'));
			if (isset($_REQUEST['debug'])) {
				$wrapper->setAttribute('data-debug', true);
			}
		}

		/**
		 *
		 * Return a plain text representation of the field's data
		 * @param array $data
		 * @param int $entry_id
		 */
		public function prepareTextValue($data, $entry_id = null)
		{
			if ($entry_id == null || !is_array($data) || empty($data)) {
				return '';
			}
			return $data['entries'];
		}

		/**
		 * Format this field value for display as readable text value.
		 *
		 * @param array $data
		 *  an associative array of data for this string. At minimum this requires a
		 *  key of 'value'.
		 * @param integer $entry_id (optional)
		 *  An option entry ID for more intelligent processing. Defaults to null.
		 * @param string $defaultValue (optional)
		 *  The value to use when no plain text representation of the field's data
		 *  can be made. Defaults to null.
		 * @return string
		 *  the readable text summary of the values of this field instance.
		 */
		public function prepareReadableValue($data, $entry_id = null, $truncate = false, $defaultValue = 'None')
		{
			if ($entry_id == null || !is_array($data) || empty($data)) {
				return __($defaultValue);
			}
			$entries = static::getEntries($data);
			$realEntries = array();
			foreach ($entries as $entryId) {
				$e = $this->entryManager
					->select()
					->entry($entryId)
					->execute()
					->rows();
				if (!empty($e)) {
					$realEntries = array_merge($realEntries, $e);
				}
			}
			$count = count($entries);
			$realCount = count($realEntries);
			if ($count === $realCount) {
				return self::formatCount($count);
			}
			return self::formatCount($realCount) . ' (' . self::formatCount($count - $realCount) . ' not found)';
		}

		/**
		 * Format this field value for display in the publish index tables.
		 *
		 * @param array $data
		 *  an associative array of data for this string. At minimum this requires a
		 *  key of 'value'.
		 * @param XMLElement $link (optional)
		 *  an XML link structure to append the content of this to provided it is not
		 *  null. it defaults to null.
		 * @param integer $entry_id (optional)
		 *  An option entry ID for more intelligent processing. defaults to null
		 * @return string
		 *  the formatted string summary of the values of this field instance.
		 */
		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null)
		{
			$value = $this->prepareReadableValue($data, $entry_id, false, __('None'));

			if ($entry_id && $this->get('mode_table')) {
				$entries = static::getEntries($data);
				$cellcontent = '';
				foreach ($entries as $position => $child_entry_id) {
					$entry = $this->entryManager
						->select()
						->entry($child_entry_id)
						->execute()
						->next();
					if (!$entry || empty($entry)) {
						continue;
					}
					$section = $this->sectionManager
						->select()
						->section($entry->get('section_id'))
						->execute()
						->next();
					$entry = $this->entryManager
						->select()
						->entry($child_entry_id)
						->section($entry->get('section_id'))
						->includeAllFields()
						->execute()
						->next();
					$content = ERFXSLTUTilities::processXSLT(
						$this,
						$entry,
						$section->get('handle'),
						$section->fetchFields(),
						'mode_table',
						isset($_REQUEST['debug']),
						'entry',
						$position + 1
					);
					if ($content) {
						$cellcontent .= $content;
					}
				}

				$cellcontent = trim($cellcontent);

				if (General::strlen($cellcontent)) {
					if ($link) {
						$link->setValue($cellcontent);
						return $link->generate();
					}
					return $cellcontent;
				}
			} else if ($link) {
				$link->setValue($value);
				return $link->generate();
			}

			return $value;
		}

		/* ********* SQL Data Definition ************* */

		/**
		 *
		 * Creates table needed for entries of individual fields
		 */
		public function createTable()
		{
			return Symphony::Database()
				->create('tbl_entries_data_' . $this->get('id'))
				->ifNotExists()
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'entry_id' => 'int(11)',
					'entries' => [
						'type' => 'text',
						'null' => true,
					],
				])
				->keys([
					'id' => 'primary',
					'entry_id' => 'unique',
				])
				->execute()
				->success();
		}

		/**
		 * Creates the table needed for the settings of the field
		 */
		public static function createFieldTable()
		{
			return Symphony::Database()
				->create(self::FIELD_TBL_NAME)
				->ifNotExists()
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'field_id' => 'int(11)',
					'sections' => [
						'type' => 'varchar(2048)',
						'null' => true,
					],
					'show_association' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
					'deepness' => [
						'type' => 'int(2)',
						'null' => true,
					],
					'elements' => [
						'type' => 'text',
						'null' => true,
					],
					'mode' => [
						'type' => 'varchar(50)',
						'null' => true,
					],
					'mode_table' => [
						'type' => 'varchar(50)',
						'null' => true,
					],
					'mode_header' => [
						'type' => 'varchar(50)',
						'null' => true,
					],
					'mode_footer' => [
						'type' => 'varchar(50)',
						'null' => true,
					],
					'min_entries' => [
						'type' => 'int(5)',
						'null' => true,
					],
					'max_entries' => [
						'type' => 'int(5)',
						'null' => true,
					],
					'allow_edit' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
					'allow_new' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
					'allow_link' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
					'allow_delete' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'no',
					],
					'allow_collapse' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
					'allow_search' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'no',
					],
					'show_header' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
				])
				->keys([
					'id' => 'primary',
					'field_id' => 'unique',
				])
				->execute()
				->success();
		}

		public static function update_102()
		{
			$addColumns = Symphony::Database()
				->alter(self::FIELD_TBL_NAME)
				->add([
					'allow_edit' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
					'allow_new' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
					'allow_link' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
				])
				->after('max_entries')
				->execute()
				->success();

			if (!$addColumns) {
				return false;
			}

			$fields = $this->fieldManager
				->select()
				->sort('id', 'asc')
				->type('entry_relationship')
				->execute()
				->rows();

			if (!empty($fields) && is_array($fields)) {
				foreach ($fields as $fieldId => $field) {
					if (!Symphony::Database()
						->alter('tbl_entries_data_' . $fieldId)
						->modify([
							'entries' => [
								'type' => 'text',
								'null' => true,
							],
						])
						->execute()
						->success()
					) {
						throw new Exception(__('Could not update table `tbl_entries_data_%s`.', array($fieldId)));
					}
				}
			}
			return true;
		}

		public static function update_103()
		{
			return Symphony::Database()
				->alter(self::FIELD_TBL_NAME)
				->add([
					'allow_delete' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'no',
					],
				])
				->after('allow_link')
				->execute()
				->success();
		}

		public static function update_200()
		{
			return Symphony::Database()
				->alter(self::FIELD_TBL_NAME)
				->add([
					'allow_collapse' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
				])
				->after('allow_delete')
				->add([
					'mode_table' => [
						'type' => 'varchar(50)',
						'null' => true,
					],
					'mode_header' => [
						'type' => 'varchar(50)',
						'null' => true,
					],
					'mode_footer' => [
						'type' => 'varchar(50)',
						'null'
					],
				])
				->after('mode')
				->add([
					'show_header' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
				])
				->after('allow_collapse')
				->change([
					'sections' => [
						'type' => 'varchar(2048)',
						'null' => true,
					],
					'elements' => [
						'type' => 'text',
						'null' => true,
					],
				])
				->execute()
				->success();
		}

		public static function update_2008()
		{
			return Symphony::Database()
				->alter(self::FIELD_TBL_NAME)
				->add([
					'allow_search' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'no',
					],
				])
				->after('allow_collapse')
				->execute()
				->success();
		}

		/**
		 *
		 * Drops the table needed for the settings of the field
		 */
		public static function deleteFieldTable()
		{
			return Symphony::Database()
				->drop(self::FIELD_TBL_NAME)
				->ifExists()
				->execute()
				->success();
		}

		private static function removeSectionAssociation($child_field_id)
		{
			return Symphony::Database()
				->delete('tbl_sections_association')
				->where(['child_section_field_id' => $child_field_id])
				->execute()
				->success();
		}
	}
