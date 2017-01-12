<?php
/**
 * This file is part of the Mesour DataGrid (http://grid.mesour.com)
 *
 * Copyright (c) 2015-2016 Matouš Němec (http://mesour.com)
 *
 * For full licence and copyright please view the file licence.md in root of this project
 */

namespace Mesour\DataGrid;

use Mesour;
use Mesour\Sources\Structures\Columns\BaseTableColumnStructure;

/**
 * @author Matouš Němec <matous.nemec@mesour.com>
 *
 * @method null onRenderColumnHeader($column, $i, $columnCount)
 * @method null onAfterRenderRow($body, $key, $rawData, $data)
 */
abstract class BaseGrid extends Mesour\UI\Table
{

	const WRAPPER = 'wrapper';

	static public $defaults = [
		self::WRAPPER => [
			'el' => 'div',
			'attributes' => [
				'class' => 'mesour-datagrid',
			],
		],
	];

	/** @var Mesour\Components\Utils\Html */
	protected $wrapper;

	/** @var Mesour\Components\Session\ISessionSection */
	private $privateSession;

	/** @var ExtensionStorage */
	private $extensionStorage;

	/** @var Sources\IGridSource */
	private $source;

	/** @var Renderer\IGridRenderer */
	private $gridRenderer;

	protected $isCreateCalled = false;

	protected $isColumnsFixed = false;

	private $count = 0;

	protected $option = [];

	private $realColumnNames;

	protected $emptyText = 'Nothing to display.';

	protected $emptyFilterText = 'Nothing found. Please change the filter criteria.';

	public $onAfterRenderRow = [];

	public $onRenderColumnHeader = [];

	public function __construct($name = null, Mesour\Components\ComponentModel\IContainer $parent = null)
	{
		if (is_null($name)) {
			throw new Mesour\InvalidStateException('Component name is required.');
		}
		parent::__construct($name, $parent);
		$this->option = self::$defaults;

		$this->extensionStorage = new ExtensionStorage($this);

		$this->getExtension('IOrdering');

		$this->setAttribute('class', 'table table-striped table-hover');
	}

	public function attached(Mesour\Components\ComponentModel\IContainer $parent)
	{
		parent::attached($parent);
		$this->privateSession = $this->getSession()->getSection($this->createLinkName());
		return $this;
	}

	public function getExtensionStorage()
	{
		return $this->extensionStorage;
	}

	public function setExtension(Extensions\IExtension $extension, $extensionName)
	{
		$this->extensionStorage->set($extension, $extensionName);
		return $this;
	}

	public function reset($hard = false)
	{
		foreach ($this->extensionStorage->getActiveExtensions() as $extension) {
			$extension->reset($hard);
		}
	}

	/**
	 * @param string $extension
	 * @param bool|TRUE $need
	 * @return Mesour\Components\ComponentModel\IComponent|object|null
	 * @throws Mesour\InvalidArgumentException
	 */
	public function getExtension($extension, $need = true)
	{
		return $this->extensionStorage->get($extension, $need);
	}

	/**
	 * @param Mesour\Sources\ISource $source
	 * @return $this
	 * @throws Mesour\InvalidStateException
	 * @throws Mesour\InvalidArgumentException
	 */
	public function setSource(Mesour\Sources\ISource $source)
	{
		if ($this->isSourceUsed) {
			throw new Mesour\InvalidStateException('Cannot change source after using them.');
		}
		if (!$source instanceof Sources\IGridSource) {
			throw new Mesour\InvalidArgumentException(
				sprintf('Source must be instance of %s.', Sources\IGridSource::class)
			);
		}
		$this->source = $source;
		return $this;
	}

	/**
	 * @param string $name
	 * @param null $header
	 * @return Column\Text
	 */
	public function addColumn($name, $header = null)
	{
		return $this->setColumn(new Column\Text, $name, $header);
	}

	/**
	 * @param bool $need
	 * @return Sources\IGridSource
	 * @throws NoDataSourceException
	 */
	public function getSource($need = true)
	{
		if ($need && !$this->source) {
			throw new NoDataSourceException('Data source is not set.');
		}
		if ($need) {
			$this->isSourceUsed = true;
		}
		return $this->source;
	}

	public function setEmptyText($text)
	{
		$this->emptyText = $text;
		return $this;
	}

	public function setEmptyFilterText($text)
	{
		$this->emptyFilterText = $text;
		return $this;
	}

	public function setDefaultOrder($key, $sorting = 'ASC')
	{
		$this->getExtension('IOrdering')
			->setDefaultOrder($key, $sorting);
		return $this;
	}

	public function disableOrdering($disabled = true)
	{
		$this->getExtension('IOrdering')
			->setDisabled($disabled);
		return $this;
	}

	public function enableMultiOrdering()
	{
		$this->getExtension('IOrdering')
			->enableMulti();
		return $this;
	}

	public function beforeRender()
	{
		parent::beforeRender();
		$this->checkEmptyColumns();
	}

	public function getRealColumnNames($fullData = [])
	{
		if (is_null($this->realColumnNames)) {
			if (!empty($fullData)) {
				$x = (array) reset($fullData);
				$this->realColumnNames = array_keys($x);
			} else {
				$this->realColumnNames = $this->getSource()->getColumnNames();
			}
		}
		return $this->realColumnNames;
	}

	/**
	 * @return Renderer\IGridRenderer
	 */
	public function getGridRenderer()
	{
		if (!$this->gridRenderer) {
			$this->gridRenderer = new Renderer\GridRenderer();
		}
		return $this->gridRenderer;
	}

	public function setGridRenderer(Renderer\IGridRenderer $gridRenderer)
	{
		$this->gridRenderer = $gridRenderer;
		return $this;
	}

	private function getWrapperAttributes()
	{
		$application = $this->getApplication(false);
		if (!$application) {
			throw new Mesour\InvalidStateException('DataGrid must be attached to application before call getWrapperAttributes.');
		}
		return ['data-mesour-grid' => $this->createLinkName()];
	}

	public function getWrapperPrototype()
	{
		if (!$this->wrapper) {
			$this->wrapper = Mesour\Components\Utils\Html::el($this->option[self::WRAPPER]['el'], $this->option[self::WRAPPER]['attributes'])
				->addAttributes($this->getWrapperAttributes());
			$this->wrapper->insert(100, '<hr class="mesour-clear">');
		}
		return $this->wrapper;
	}

	public function create($data = [])
	{
		/** @var Extensions\IExtension $extension */
		$this->isCreateCalled = true;

		$renderer = $this->getGridRenderer();

		$activeExtensions = $this->extensionStorage->getActiveExtensions();
		foreach ($activeExtensions as $extension) {
			$extension->gridCreate($data);
		}

		$this->count = $this->getSource()->count();

		foreach ($activeExtensions as $extension) {
			$extension->afterGetCount($this->count);
		}

		$this->beforeRender();

		foreach ($activeExtensions as $extension) {
			$extension->beforeFetchData($data);
		}

		$currentData = $this->getSource()->fetchAll();
		$currentRawData = $this->getSource()->fetchLastRawRows();

		foreach ($activeExtensions as $extension) {
			$extension->afterFetchData($currentData, $data, $currentRawData);
		}

		foreach ($this->getColumns() as $column) {
			$column->validate($currentData, $data);
			$column->setListRenderer(new Mesour\DataGrid\Renderer\GridListRenderer($column));
		}

		$script = Mesour\Components\Utils\Html::el('script');
		$script->setHtml($this->createCoreScript());

		$table = $this->createTable($currentData, $currentRawData);

		$snippet = $this->createSnippet();

		$tableWrapper = Mesour\Components\Utils\Html::el('div');
		$createdTable = $table->create();

		foreach ($activeExtensions as $extension) {
			$extension->attachToRenderer($renderer, $data, $currentRawData);
		}

		$tableWrapper->add($script);
		$tableWrapper->add($createdTable);

		$renderer->setComponent('grid', $tableWrapper);
		$renderer->setComponent('snippet', $snippet->attrs['id']);

		$wrapper = $this->getWrapperPrototype();
		$wrapper->class(
			isset($this->option[self::WRAPPER]['attributes']['class'])
				? $this->option[self::WRAPPER]['attributes']['class']
				: 'mesour-datagrid',
			true
		);
		$renderer->setComponent('wrapper', $wrapper);

		return $renderer;
	}

	protected function createCoreScript()
	{
		$outScript = 'var mesour = !mesour ? {} : mesour;';
		$outScript .= 'mesour.grid = !mesour.grid ? [] : mesour.grid;';

		$dataStructure = $this->getSource()->getDataStructure();
		foreach ($this->getColumns() as $column) {
			if (
				$column instanceof Column\IInlineEdit
				&& $dataStructure->hasColumn($column->getName())
				&& $dataStructure->getColumn($column->getName()) instanceof BaseTableColumnStructure
			) {
				/** @var BaseTableColumnStructure $structureColumn */
				$structureColumn = $dataStructure->getColumn($column->getName());
				$column->setReference($structureColumn->getTableStructure()->getName());
				$referencedTables[$structureColumn->getReferencedColumn()]
					= $structureColumn->getTableStructure()->getName();
			} else {
				continue;
			}
		}

		return $outScript;
	}

	private function createTable($data, $rawData)
	{
		$renderer = $this->getRendererFactory();

		$this->onRender($this, $rawData, $data);

		$table = $renderer->createTable();

		$table->setAttributes($this->attributes);

		$header = $renderer->createHeader();

		$i = 0;
		$columns = $this->getColumns();
		$columnCount = count($columns);
		foreach ($columns as $column) {
			$this->onRenderColumnHeader($column, $i, $columnCount);
			$headerCell = $renderer->createHeaderCell($column);
			$header->addCell($headerCell);

			$i++;
		}
		$this->onRenderHeader($header, $rawData, $data);

		$table->setHeader($header);

		$body = $renderer->createBody();

		if ($this->getSource()->getTotalCount() === 0) {
			$this->addRow($body, count($columns), [], true, $this->getTranslator()->translate($this->emptyText));
		} elseif ($this->count === 0) {
			$this->addRow($body, count($columns), [], true, $this->getTranslator()->translate($this->emptyFilterText));
		} else {
			foreach ($data as $key => $rowData) {
				$this->addRow($body, $rowData, $rawData[$key]);
				$this->onAfterRenderRow($body, $key, $rawData[$key], $rowData);
			}
		}

		$this->onRenderBody($body, $renderer, $rawData, $data);

		$table->setBody($body);

		return $table;
	}

	protected function addRow(Mesour\Table\Render\Body &$body, $data, $rawData, $empty = false, $text = null)
	{
		$row = $this->getRendererFactory()->createRow($data, $rawData);
		if ($this->count > 0) {
			$row->setAttributes(
				[
					'id' => $this->getLineId($data),
				]
			);
		}
		if ($empty !== false) {
			$columnsCount = $data;

			$column = new Column\EmptyData;
			$column->setText($text);

			$cell = $this->getRendererFactory()->createCell($columnsCount, $column, $rawData);
			$row->setAttribute('class', 'no-sort ' . count($this->getColumns()));
			$row->addCell($cell);
		} else {
			foreach ($this->getColumns() as $column) {
				$row->addCell($this->getRendererFactory()->createCell($data, $column, $rawData));
			}
		}
		$this->onRenderRow($row, $rawData, $data);
		$body->addRow($row);
		return $row;
	}

	protected function getLineId($data)
	{
		if (!isset($data[$this->getPrimaryKey()])) {
			throw new Mesour\OutOfRangeException(
				'Primary key "' . $this->getPrimaryKey() . '" does not exists in data.'
			);
		}
		return $this->createLinkName() . '-' . $data[$this->getPrimaryKey()];
	}

	protected function setColumn(Mesour\Table\Render\IColumn $column, $name, $header = null)
	{
		if (!$column instanceof Column\IColumn) {
			throw new Mesour\InvalidArgumentException('Column must be instanceof \Mesour\DataGrid\Column\IColumn.');
		}
		$column->setHeader($header);
		return $this['col'][$name] = $column;
	}

	/**
	 * @return Column\IColumn[]
	 * @throws Mesour\InvalidStateException
	 * @throws Mesour\InvalidArgumentException
	 */
	public function getColumns()
	{
		if (!$this->isCreateCalled) {
			throw new Mesour\InvalidStateException('First call create() method.');
		}

		if (count(parent::getColumns()) === 0) {
			$columnNames = $this->getSource()->getColumnNames();
			foreach ($columnNames as $columnName) {
				$this->addColumn($columnName);
			}
		}

		if (!$this->isColumnsFixed) {
			$fixContainer = false;

			foreach ($this->extensionStorage->getActiveExtensions() as $extension) {
				if ($extension instanceof Extensions\IHasColumn && !$extension->isGetSpecialColumnUsed()) {
					$fixContainer = true;
					$this->setColumn($extension->getSpecialColumn(), $extension->getSpecialColumnName());
				}
			}

			/** @var Column\IColumn[]|Mesour\Components\Control\IControl $columns */
			$columns = parent::getColumns();

			if ($fixContainer) {
				$prepend = [];
				$names = [];
				$output = [];
				foreach ($columns as $name => $column) {
					$names[] = $name;
					if ($column->isDisabled() || !$column->isAllowed()) {
						continue;
					}
					if ($column instanceof Column\IPrependedColumn) {
						$prepend[] = $column;
					} else {
						$output[] = $column;
					}
				}

				foreach ($names as $name) {
					unset($columns[$name]);
				}

				$all = array_merge($prepend, $output);
				foreach ($all as $name => $column) {
					$columns->addComponent($column);
				}
			}
			$this->isColumnsFixed = true;
			return $columns;
		}
		return parent::getColumns();
	}

	public function setPrimaryKey($primaryKey)
	{
		trigger_error('Method set primary key is deprecated. PrimaryKey is in DataStructure.', E_USER_DEPRECATED);
	}

	public function getPrimaryKey()
	{
		return $this->getSource()->getPrimaryKey();
	}

	public function render()
	{
		if ($this->getSession()) {
			$this->getSession()->saveState();
		}
		echo $this->create();
	}

	protected function checkEmptyColumns()
	{
		if ($this->count === 0 && count($this->getColumns()) === 0) {
			foreach ($this->getRealColumnNames() as $key) {
				$this->setColumn(new Column\Text, $key);
			}
		}
	}

	public function __clone()
	{
		$this->isCreateCalled = false;
		$this->isRendererUsed = false;
		$this->setGridRenderer(clone $this->getGridRenderer());
		try {
			$source = clone $this->getSource();
			$this->isSourceUsed = false;
			$this->setSource($source);
		} catch (NoDataSourceException $e) {
			// no action
		}

		$this->extensionStorage = clone $this->extensionStorage;
		$this->extensionStorage->setParent($this);
		parent::__clone();
	}

	protected function getPrivateSession()
	{
		return $this->privateSession;
	}

}
